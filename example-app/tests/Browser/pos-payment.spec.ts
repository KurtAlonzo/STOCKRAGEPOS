import { test, expect } from '@playwright/test';
import { fixture, mixedCart, openPos } from './support/app';
test.beforeEach(() => fixture('reset'));
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
