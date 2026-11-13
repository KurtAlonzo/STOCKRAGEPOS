import { test, expect } from '@playwright/test';
import { fixture, mixedCart, openPos } from './support/app';

test.beforeEach(() => fixture('reset'));

test('sales history links confirmed checkout, retains filters and renders current detail on phones', async ({
    page,
}) => {
    await openPos(page);
    await mixedCart(page);
    await page
        .getByRole('button', { name: 'Review payment', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Confirm payment recording' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Sale recorded' }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'View sale', exact: true }).click();
    await expect(
        page.getByRole('heading', { name: /SR-00000001/ }),
    ).toBeVisible();
    await expect(
        page.getByText('Original total PHP 410.00', { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText('Cash tender PHP 500.00', { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText('Change PHP 90.00', { exact: true }),
    ).toBeVisible();
    await page
        .getByRole('link', { name: 'Back to sales', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Sales', exact: true }),
    ).toBeVisible();
    await page.getByLabel('Reference', { exact: true }).fill('SR-00000001');
    await page.getByLabel('Original payment').selectOption('cash');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page).toHaveURL(/payment=cash/);
    await page.getByRole('link', { name: 'SR-00000001', exact: true }).click();
    await page
        .getByRole('link', { name: 'Back to sales', exact: true })
        .click();
    await expect(page.getByLabel('Reference', { exact: true })).toHaveValue(
        'SR-00000001',
    );
    await expect(page.getByLabel('Original payment')).toHaveValue('cash');
    for (const width of [390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page
            .getByRole('link', { name: 'SR-00000001', exact: true })
            .click();
        await expect(
            page.getByRole('heading', { name: /SR-00000001/ }),
        ).toBeVisible();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page
            .getByRole('link', { name: 'Back to sales', exact: true })
            .click();
    }
    await page.getByLabel('Reference', { exact: true }).fill('no-match');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.getByText('No sales match these filters.')).toBeVisible();
});
