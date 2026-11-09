import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { fixture, completedMixedSale, login } from './support/app';
const directory = 'docs/verification/screenshots/2026-11-09';
test.beforeEach(() => {
    fixture('reset');
    mkdirSync(directory, { recursive: true });
});
test('original and refunded receipt prints read-only light A5 without audit reasons', async ({
    page,
}) => {
    await completedMixedSale(page);
    const receipt = page.getByRole('region', { name: 'Sale receipt' });
    await expect(receipt).toContainText('Cash tender: PHP 500.00');
    await expect(receipt).toContainText('Change: PHP 90.00');
    await expect(receipt).not.toContainText('Recorded refund');
    await page
        .getByRole('button', { name: 'Refund full sale', exact: true })
        .click();
    await page.getByLabel('Reason', { exact: true }).fill('INTERNAL REASON');
    await page.getByLabel('Actual refund method').selectOption('maya');
    await page.getByLabel('Refund reference (optional)').fill('X'.repeat(255));
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
    await page.keyboard.press('Escape');
    await expect(receipt).toContainText('Recorded refund');
    await expect(receipt).toContainText('maya');
    await expect(receipt).not.toContainText('INTERNAL REASON');
    const before = fixture();
    await page.evaluate(() => {
        window.print = () => {
            window.dispatchEvent(new Event('beforeprint'));
            window.dispatchEvent(new Event('afterprint'));
        };
    });
    await page
        .getByRole('button', { name: 'Print / Save as PDF', exact: true })
        .click();
    expect(fixture()).toEqual(before);
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await page.emulateMedia({ media: 'print' });
    await expect(
        page.getByRole('button', { name: 'Print / Save as PDF', exact: true }),
    ).not.toBeVisible();
    await expect(page.getByRole('banner')).not.toBeVisible();
    await expect(receipt).toBeVisible();
    expect(
        await receipt.evaluate(
            (element) => getComputedStyle(element).backgroundColor,
        ),
    ).toBe('rgb(255, 255, 255)');
    await page.screenshot({
        path: directory + '/task6-refunded-receipt-print.png',
        fullPage: true,
    });
    const pdf = await page.pdf({
        path: directory + '/task6-refunded-receipt-a5.pdf',
        preferCSSPageSize: true,
        printBackground: true,
    });
    const box = pdf
        .toString('latin1')
        .match(/\/MediaBox\s*\[0 0 ([\d.]+) ([\d.]+)\]/);
    expect(box).not.toBeNull();
    expect(Number(box![1])).toBeCloseTo(420, 0);
    expect(Number(box![2])).toBeCloseTo(595, 0);
    await page.emulateMedia({ media: 'screen' });
    expect(fixture()).toEqual(before);
});

test('long historical receipt wraps modifiers across multiple A5 pages', async ({
    page,
}) => {
    const sale = fixture('long-sale');
    await login(page);
    await page.goto(`/${sale.slug}/sales/${sale.saleId}`);
    const receipt = page.getByRole('region', { name: 'Sale receipt' });
    await expect(receipt).toBeVisible();
    await expect(receipt.getByText(/long historical modifier/)).toHaveCount(80);
    const before = fixture();
    await page.emulateMedia({ media: 'print' });
    const pdf = await page.pdf({
        path: directory + '/task6-long-receipt-a5.pdf',
        preferCSSPageSize: true,
        printBackground: true,
    });
    expect(
        (pdf.toString('latin1').match(/\/Type\s*\/Page\b/g) ?? []).length,
    ).toBeGreaterThan(1);
    expect(
        await receipt.evaluate(
            (element) => element.scrollWidth <= element.clientWidth,
        ),
    ).toBe(true);
    expect(fixture()).toEqual(before);
});
