import { test, expect } from '@playwright/test';
import { fixture, mixedCart, openPos } from './support/app';
test.beforeEach(() => fixture('reset'));
test('committed response loss and refresh recover one original sale without another POST', async ({
    page,
}) => {
    await openPos(page);
    await mixedCart(page);
    let posts = 0;
    await page.route('**/pos/attempts/**', (route) => route.abort());
    await page.route('**/pos/checkouts', async (route) => {
        posts++;
        const response = await route.fetch();
        expect(response.status()).toBe(200);
        expect(fixture()).toMatchObject({
            sales: 1,
            payments: 1,
            movements: 6,
        });
        await route.abort('connectionreset');
    });
    await page
        .getByRole('button', { name: 'Review payment', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Confirm payment recording' })
        .click();
    await expect(
        page.getByText(
            'Payment recording status is unknown. Do not collect payment again while checking this sale.',
        ),
    ).toBeVisible();
    expect(posts).toBe(1);
    const original = fixture().attempts[0].idempotency_key;
    await page.reload();
    await expect(
        page.getByText(
            'Payment recording status is unknown. Do not collect payment again while checking this sale.',
        ),
    ).toBeVisible();
    await page.unroute('**/pos/attempts/**');
    await page
        .getByRole('button', { name: 'Check again', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Sale recorded' }),
    ).toBeVisible();
    expect(posts).toBe(1);
    expect(fixture()).toMatchObject({ sales: 1, payments: 1, movements: 6 });
    expect(fixture().attempts[0].idempotency_key).toBe(original);
});
test('refresh restores cart and unfinished quantity but clears unsubmitted payment', async ({
    page,
}) => {
    await openPos(page);
    await page.getByRole('button', { name: /Water PHP/ }).click();
    await page.getByLabel('Cash tender (PHP)').fill('500');
    const quantity = page.getByLabel(/Quantity/i).first();
    await quantity.fill('');
    await expect(page.getByText('Cart saved on this browser')).toBeVisible();
    await page.reload();
    await expect(page.getByLabel('Cash tender (PHP)')).toHaveValue('');
    await expect(page.getByLabel(/Quantity/i).first()).toHaveValue('');
    await expect(
        page.getByRole('button', { name: 'Review payment', exact: true }),
    ).toBeDisabled();
});

test('verified sale stays visible when the IndexedDB confirmation marker write fails', async ({
    page,
}) => {
    await page.addInitScript(() => {
        const put: IDBObjectStore['put'] = Reflect.get(
            IDBObjectStore.prototype,
            'put',
        );
        IDBObjectStore.prototype.put = function (value, key) {
            if (value?.terminal)
                throw new DOMException(
                    'Injected quota failure',
                    'QuotaExceededError',
                );
            return put.call(this, value, key);
        };
    });
    await openPos(page);
    await page.getByRole('button', { name: /Water PHP/ }).click();
    await page.getByLabel('Cash tender (PHP)').fill('50.00');
    await page
        .getByRole('button', { name: 'Review payment', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Confirm payment recording' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Sale recorded' }),
    ).toBeVisible();
    await expect(page.getByRole('button', { name: 'New Sale' })).toBeDisabled();
    expect(fixture()).toMatchObject({ sales: 1, payments: 1, movements: 1 });
    await page.reload();
    await expect(
        page.getByRole('heading', { name: 'Sale recorded' }),
    ).toBeVisible();
    await expect(page.getByRole('button', { name: 'New Sale' })).toBeDisabled();
});
test('old actor cannot submit under a new account after response loss and missed broadcasts', async ({
    page,
    context,
}) => {
    await page.addInitScript(() => {
        Object.defineProperty(window, 'BroadcastChannel', { value: undefined });
    });
    await openPos(page);
    await page.getByRole('button', { name: /Water PHP/ }).click();
    await page.getByLabel('Cash tender (PHP)').fill('50');
    await page.route('**/pos/attempts/**', (r) => r.abort());
    await page.route('**/pos/checkouts', async (route) => {
        await route.fetch();
        await route.abort('connectionreset');
    });
    await page
        .getByRole('button', { name: 'Review payment', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Confirm payment recording' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Payment status unknown' }),
    ).toBeVisible();
    const oldUrl = page.url(),
        operation = fixture().attempts[0],
        original = JSON.parse(operation.request_json);
    const other = await context.newPage();
    const logout = async () => {
        const csrf = (await context.cookies()).find(
            (x) => x.name === 'XSRF-TOKEN',
        );
        await other.request.post('/logout', {
            headers: { 'X-XSRF-TOKEN': decodeURIComponent(csrf!.value) },
        });
    };
    await logout();
    const { login } = await import('./support/app');
    await login(other, 'other@example.test');
    const status = await other.evaluate(
        async ({ url, actor, body }) => {
            const token = document.cookie
                .split('; ')
                .find((x) => x.startsWith('XSRF-TOKEN='));
            return (
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-POS-Actor-Id': String(actor),
                        'X-XSRF-TOKEN': decodeURIComponent(token!.slice(11)),
                    },
                    body: JSON.stringify(body),
                })
            ).status;
        },
        {
            url: oldUrl + '/checkouts',
            actor: operation.actor_id,
            body: original,
        },
    );
    expect([403, 409]).toContain(status);
    expect(fixture().attempts).toHaveLength(1);
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await expect(
        page.getByText(/Verify your session or sign in as the original owner/),
    ).toBeVisible();
    await logout();
    await login(other);
    await page.unroute('**/pos/attempts/**');
    await page.goto(oldUrl);
    await expect(
        page.getByRole('heading', { name: 'Sale recorded' }),
    ).toBeVisible();
    expect(fixture()).toMatchObject({ sales: 1, payments: 1, movements: 1 });
});

test('damaged editable cart offers confirmed discard in the real POS', async ({
    page,
}) => {
    await openPos(page);
    await page.getByRole('button', { name: /Water PHP/ }).click();
    await page.getByLabel('Cash tender (PHP)').fill('50');
    await expect(
        page.getByRole('button', { name: 'Review payment', exact: true }),
    ).toBeEnabled();
    await page.evaluate(async () => {
        await new Promise<void>((resolve, reject) => {
            const open = indexedDB.open('stockrage-pos', 1);
            open.onsuccess = () => {
                const tx = open.result.transaction('drafts', 'readwrite'),
                    store = tx.objectStore('drafts'),
                    get = store.get('1:1');
                get.onsuccess = () =>
                    store.put({ ...get.result, lines: 'damaged' }, '1:1');
                tx.oncomplete = () => resolve();
                tx.onerror = () => reject(tx.error);
            };
        });
    });
    await page.reload();
    await page
        .getByRole('button', { name: 'Discard damaged editable cart' })
        .click();
    await page
        .getByRole('button', { name: 'Discard cart', exact: true })
        .click();
    await expect(page.getByText('Empty cart', { exact: true })).toBeVisible();
    expect(fixture()).toMatchObject({ sales: 0 });
});
test('inactive saved selection stays repairable and removing it clears restored issues', async ({
    page,
}) => {
    await openPos(page);
    await page.getByRole('button', { name: /Water PHP/ }).click();
    await page.getByLabel('Cash tender (PHP)').fill('50');
    await expect(
        page.getByRole('button', { name: 'Review payment', exact: true }),
    ).toBeEnabled();
    fixture('disable');
    await page.reload();
    await page.getByRole('button', { name: 'Take over', exact: true }).click();
    await expect(
        page.getByText(
            'A saved selection is unavailable. Correct or remove this line.',
        ),
    ).toBeVisible();
    await page
        .getByRole('button', { name: 'Remove Water', exact: true })
        .click();
    await expect(page.getByText('Empty cart', { exact: true })).toBeVisible();
    expect(fixture()).toMatchObject({ sales: 0 });
});
