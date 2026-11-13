import { test, expect } from '@playwright/test';
import { fixture, mixedCart, openPos } from './support/app';
test.beforeEach(() => fixture('reset'));

test('duplicate_reference_requires_review', async ({ page }) => {
    await openPos(page);
    for (let sale = 0; sale < 2; sale++) {
        await page.getByRole('button', { name: /Water PHP/ }).click();
        await page.getByLabel('Method', { exact: true }).selectOption('gcash');
        await page.getByLabel('Reference (optional)').fill('RECEIPT-123');
        await page
            .getByLabel(
                'I checked receipt of the current total outside the app',
            )
            .check();
        await page
            .getByRole('button', { name: 'Review payment', exact: true })
            .click();
        if (sale) {
            await expect(page.getByRole('dialog')).toContainText(
                'reference has already been recorded',
            );
            expect(fixture().sales).toBe(1);
        }
        await expect(page.getByRole('dialog')).toContainText(
            'Recording confirms that you reviewed all warnings above.',
        );
        await page
            .getByRole('button', { name: 'Confirm payment recording' })
            .click();
        await expect(
            page.getByRole('heading', { name: 'Sale recorded' }),
        ).toBeVisible();
        if (!sale) await page.getByRole('button', { name: 'New Sale' }).click();
    }
    const attempts = fixture().attempts.map((a: { request_json: string }) =>
        JSON.parse(a.request_json),
    );
    expect(attempts[0].acknowledgedWarningIds).not.toContain(
        'duplicate-reference-gcash',
    );
    expect(attempts[1].acknowledgedWarningIds).toContain(
        'duplicate-reference-gcash',
    );
    expect(fixture()).toMatchObject({ sales: 2, payments: 2, movements: 2 });
});

test('recipe_and_shortage_changes_require_renewed_review', async ({ page }) => {
    await openPos(page);
    await mixedCart(page);
    await page
        .getByRole('button', { name: 'Review payment', exact: true })
        .click();
    fixture('recipe');
    await page
        .getByRole('button', { name: 'Confirm payment recording' })
        .click();
    await expect(page.getByText(/Store data changed/)).toBeVisible();
    expect(fixture()).toMatchObject({ sales: 0, payments: 0, movements: 0 });
    await expect(page.getByRole('dialog')).not.toBeVisible();
    await page
        .getByRole('button', { name: 'Review payment', exact: true })
        .click();
    await expect(page.getByRole('dialog')).toContainText('Beef');
    await expect(page.getByRole('dialog')).toContainText('Warning:');
    await page
        .getByRole('button', { name: 'Confirm payment recording' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Sale recorded' }),
    ).toBeVisible();
    const request = JSON.parse(fixture().attempts.at(-1).request_json);
    expect(request.acknowledgedWarningIds.length).toBeGreaterThan(0);
    expect(fixture()).toMatchObject({ sales: 1, payments: 1, movements: 6 });
});
test('cash mixed sale records once and starts a new draft explicitly', async ({
    page,
}) => {
    await openPos(page);
    await mixedCart(page);
    await page
        .getByRole('button', { name: 'Review payment', exact: true })
        .click();
    await expect(page.getByRole('dialog')).toContainText('90.00');
    await page
        .getByRole('button', { name: 'Confirm payment recording' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Sale recorded' }),
    ).toBeVisible();
    await expect(
        page.getByText('Change PHP 90.00', { exact: true }),
    ).toBeVisible();
    expect(fixture()).toMatchObject({ sales: 1, payments: 1, movements: 6 });
    await page.getByRole('button', { name: 'New Sale' }).click();
    await expect(page.getByText('Empty cart', { exact: true })).toBeVisible();
});
for (const provider of ['gcash', 'maya'])
    test(
        provider + ' records external receipt with no reference',
        async ({ page }) => {
            await openPos(page);
            await page.getByRole('button', { name: /Water PHP/ }).click();
            await page
                .getByLabel('Method', { exact: true })
                .selectOption(provider);
            await page
                .getByLabel(
                    'I checked receipt of the current total outside the app',
                )
                .check();
            await page
                .getByRole('button', { name: 'Review payment', exact: true })
                .click();
            await page
                .getByRole('button', { name: 'Confirm payment recording' })
                .click();
            await expect(
                page.getByRole('heading', { name: 'Sale recorded' }),
            ).toBeVisible();
            expect(fixture()).toMatchObject({
                sales: 1,
                payments: 1,
                movements: 1,
            });
        },
    );

test('canceling a saved cart requires confirmation and durably clears it', async ({
    page,
}) => {
    await openPos(page);
    await page.getByRole('button', { name: /Water PHP/ }).click();
    await expect(page.getByText('Cart saved on this browser')).toBeVisible();
    await page
        .getByRole('button', { name: 'Cancel cart', exact: true })
        .click();
    await expect(page.getByRole('dialog')).toContainText('Cancel this cart?');
    await page.getByRole('button', { name: 'Keep cart', exact: true }).click();
    await expect(page.getByLabel('Quantity', { exact: true })).toHaveValue('1');
    await page
        .getByRole('button', { name: 'Cancel cart', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Discard cart', exact: true })
        .click();
    await expect(page.getByText('Empty cart', { exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByText('Empty cart', { exact: true })).toBeVisible();
    expect(fixture()).toMatchObject({ sales: 0 });
});

test('known offline blocks POST and changed price requires another explicit review', async ({
    page,
    context,
}) => {
    await openPos(page);
    await page.getByRole('button', { name: /Water PHP/ }).click();
    await page.getByLabel('Cash tender (PHP)').fill('100');
    await expect(
        page.getByRole('button', { name: 'Review payment', exact: true }),
    ).toBeEnabled();
    await context.setOffline(true);
    await expect(
        page.getByRole('button', { name: 'Review payment', exact: true }),
    ).toBeDisabled();
    expect(fixture()).toMatchObject({ sales: 0 });
    await context.setOffline(false);
    await page
        .getByRole('button', { name: 'Review payment', exact: true })
        .click();
    fixture('price');
    await page
        .getByRole('button', { name: 'Confirm payment recording' })
        .click();
    await expect(page.getByText(/Store data changed/)).toBeVisible();
    expect(fixture()).toMatchObject({ sales: 0 });
    await page
        .getByRole('button', { name: 'Review payment', exact: true })
        .click();
    await expect(page.getByRole('dialog')).toContainText('55.00');
    await page
        .getByRole('button', { name: 'Confirm payment recording' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Sale recorded' }),
    ).toBeVisible();
    expect(fixture()).toMatchObject({ sales: 1 });
});
