import { test, expect, type Page } from '@playwright/test';
import {
    fixture,
    completedMixedSale,
    abandonRegisteredReversal,
    login,
    logout,
} from './support/app';
const warning =
    'Refund recording status is unknown. Do not return money again while checking this sale.';
test.beforeEach(() => fixture('reset'));
async function prepare(page: Page, stock = true) {
    await completedMixedSale(page);
    await page
        .getByRole('button', { name: 'Refund full sale', exact: true })
        .click();
    await page.getByLabel('Reason', { exact: true }).fill('Customer return');
    await page.getByLabel('Actual refund method').selectOption('maya');
    if (stock) {
        await page.getByLabel(/Return Water/).fill('1');
        await page.getByLabel(/Return Cheese/).fill('1');
    }
    await page.getByLabel(/I confirm the full amount was returned/).check();
}

test('committed refund response loss refresh recovers one full refund and two returns', async ({
    page,
}) => {
    await prepare(page);
    let posts = 0;
    await page.route('**/sales/*/reversal', (route) => route.abort());
    await page.route('**/sales/*/reversal-attempts/*', (route) =>
        route.abort(),
    );
    await page.route('**/sales/*/reversals', async (route) => {
        posts++;
        const response = await route.fetch();
        expect(response.status()).toBe(200);
        await route.abort('connectionreset');
    });
    // The final current-state check must complete before simulating response loss.
    await page.unroute('**/sales/*/reversal');
    await page
        .getByRole('button', { name: 'Record full refund', exact: true })
        .click();
    await expect(page.getByText(warning, { exact: true })).toBeVisible();
    expect(fixture()).toMatchObject({ sales: 1, payments: 1, movements: 8 });
    expect(fixture().reversals).toHaveLength(1);
    await page.route('**/sales/*/reversal', (route) => route.abort());
    await page.reload();
    await expect(page.getByText(warning, { exact: true })).toBeVisible();
    expect(posts).toBe(1);
    await page.unroute('**/sales/*/reversal');
    await page.unroute('**/sales/*/reversal-attempts/*');
    await page
        .getByRole('button', { name: 'Check again', exact: true })
        .click();
    await expect(page.getByText('Net PHP 0.00', { exact: true })).toBeVisible();
    await expect(
        page.getByRole('heading', {
            name: 'Recorded full refund',
            exact: true,
        }),
    ).toBeVisible();
    expect(posts).toBe(1);
    const counts = fixture();
    expect(counts.reversals[0].total_minor).toBe(41000);
    expect(
        counts.stock.find((s: { name: string }) => s.name === 'Water')
            .balance_thousandths,
    ).toBe(2000);
    expect(
        counts.stock.find((s: { name: string }) => s.name === 'Cheese')
            .balance_thousandths,
    ).toBe(2000);
});

test('pending refund uses explicit identical request retry after refresh', async ({
    page,
}) => {
    await prepare(page);
    let request: unknown;
    let posts = 0;
    await page.route('**/sales/*/reversals', async (route) => {
        posts++;
        request = route.request().postDataJSON();
        await abandonRegisteredReversal(request, 1);
        await route.abort('connectionreset');
    });
    await page
        .getByRole('button', { name: 'Record full refund', exact: true })
        .click();
    await expect(page.getByText(warning, { exact: true })).toBeVisible();
    expect(fixture().reversals).toHaveLength(0);
    expect(fixture().reversalAttempts[0].status).toBe('pending');
    await page.reload();
    await page.getByRole('button', { name: 'Check refund recording' }).click();
    await expect(
        page.getByRole('button', { name: 'Retry original refund recording' }),
    ).toBeEnabled();
    expect(posts).toBe(1);
    await page.unroute('**/sales/*/reversals');
    await page.route('**/sales/*/reversals', async (route) => {
        posts++;
        expect(route.request().postDataJSON()).toEqual(request);
        await route.continue();
    });
    await page
        .getByRole('button', { name: 'Retry original refund recording' })
        .click();
    await expect(
        page.getByRole('heading', {
            name: 'Full refund recorded',
            exact: true,
        }),
    ).toBeVisible();
    expect(posts).toBe(2);
    expect(fixture().reversals).toHaveLength(1);
    expect(fixture().movements).toBe(8);
});

test('storage failure blocks all refund POSTs', async ({ page }) => {
    await page.addInitScript(() => {
        const add: IDBObjectStore['add'] = Reflect.get(
            IDBObjectStore.prototype,
            'add',
        );
        IDBObjectStore.prototype.add = function (value, key) {
            if (this.transaction.db.name === 'stockrage-sales')
                throw new DOMException(
                    'Injected failure',
                    'QuotaExceededError',
                );
            return add.call(this, value, key);
        };
    });
    await prepare(page, false);
    let posts = 0;
    page.on('request', (request) => {
        if (request.method() === 'POST' && request.url().endsWith('/reversals'))
            posts++;
    });
    await page
        .getByRole('button', { name: 'Record full refund', exact: true })
        .click();
    await expect(page.getByText(warning, { exact: true })).toBeVisible();
    expect(posts).toBe(0);
    expect(fixture().reversals).toHaveLength(0);
});

test('durable rejection permits corrected new request and stock selection resets confirmation', async ({
    page,
}) => {
    await prepare(page);
    await page.getByLabel(/Return Water/).fill('');
    await page.getByLabel(/Return Water/).fill('1');
    await expect(
        page.getByRole('button', { name: 'Record full refund', exact: true }),
    ).toBeDisabled();
    await page.getByLabel(/I confirm the full amount was returned/).check();
    fixture('overflow-water');
    await page
        .getByRole('button', { name: 'Record full refund', exact: true })
        .click();
    await expect(
        page.getByText('Returned stock exceeds the supported balance.', {
            exact: true,
        }),
    ).toBeVisible();
    expect(fixture().reversals).toHaveLength(0);
    fixture('normalize-water');
    await page.getByLabel(/I confirm the full amount was returned/).check();
    await page
        .getByRole('button', { name: 'Record full refund', exact: true })
        .click();
    await expect(
        page.getByRole('heading', {
            name: 'Full refund recorded',
            exact: true,
        }),
    ).toBeVisible();
    expect(fixture().reversalAttempts).toHaveLength(2);
    expect(fixture().reversals).toHaveLength(1);
});

test('recorded refund remains read-only when cleanup fails, including refresh', async ({
    page,
}) => {
    await page.addInitScript(() => {
        const remove: IDBObjectStore['delete'] = Reflect.get(
            IDBObjectStore.prototype,
            'delete',
        );
        IDBObjectStore.prototype.delete = function (key) {
            if (this.transaction.db.name === 'stockrage-sales')
                throw new DOMException(
                    'Injected cleanup failure',
                    'QuotaExceededError',
                );
            return remove.call(this, key);
        };
    });
    await prepare(page, false);
    await page
        .getByRole('button', { name: 'Record full refund', exact: true })
        .click();
    await expect(
        page.getByRole('heading', {
            name: 'Full refund recorded',
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByText(/Refund recorded. Local cleanup failed/),
    ).toBeVisible();
    await page.reload();
    await expect(
        page.getByRole('button', { name: 'View recorded refund' }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Refund full sale', exact: true }),
    ).toHaveCount(0);
    expect(fixture().reversals).toHaveLength(1);
    expect(fixture().movements).toBe(6);
});

test('missed account broadcast hides unresolved refund details and original owner can recover', async ({
    page,
    context,
}) => {
    await page.addInitScript(() =>
        Object.defineProperty(window, 'BroadcastChannel', { value: undefined }),
    );
    await prepare(page);
    const originalUrl = page.url();
    await page.route('**/sales/*/reversals', async (route) => {
        await route.fetch();
        await route.abort('connectionreset');
    });
    await page
        .getByRole('button', { name: 'Record full refund', exact: true })
        .click();
    await expect(page.getByText(warning, { exact: true })).toBeVisible();
    const other = await context.newPage();
    await logout(page);
    await login(other, 'other@example.test');
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await expect(
        page.getByText(/Session changed. Sign in as the original owner/),
    ).toBeVisible();
    await expect(
        page.getByText('Customer return', { exact: true }),
    ).toHaveCount(0);
    await logout(other);
    await login(other);
    await page.goto(originalUrl);
    await expect(
        page.getByRole('heading', {
            name: 'Recorded full refund',
            exact: true,
        }),
    ).toBeVisible();
    expect(fixture().reversals).toHaveLength(1);
    expect(fixture().movements).toBe(8);
});
