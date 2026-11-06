import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { openPos, fixture } from './support/app';
test('desktop tablet and phone notice retain layout and keyboard review', async ({
    page,
}) => {
    fixture('reset');
    await openPos(page);
    await page.getByRole('button', { name: /Water PHP/ }).click();
    await page.getByLabel('Cash tender (PHP)').fill('50');
    await expect(
        page.getByRole('button', { name: 'Review payment', exact: true }),
    ).toBeEnabled();
    await page
        .getByRole('button', { name: 'Review payment', exact: true })
        .focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).not.toBeVisible();
    const directory = 'docs/verification/screenshots/2026-11-06';
    mkdirSync(directory, { recursive: true });
    for (const [width, height] of [
        [1440, 900],
        [1024, 768],
        [768, 1024],
        [1024, 600],
        [390, 844],
    ]) {
        await page.setViewportSize({ width, height });
        await page.emulateMedia({ reducedMotion: 'reduce' });
        if (width < 768)
            await expect(
                page.getByRole('heading', {
                    name: 'Checkout requires a larger screen',
                }),
            ).toBeVisible();
        else {
            await expect(
                page.getByRole('heading', { name: 'Products', exact: true }),
            ).toBeVisible();
            if (width < 1024) {
                await page.getByRole('button', { name: /Cart \(/ }).click();
                await expect(page.getByRole('dialog')).toBeVisible();
                await page.keyboard.press('Escape');
                await expect(page.getByRole('dialog')).not.toBeVisible();
            }
        }
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: directory + '/task5-' + width + 'x' + height + '.png',
            fullPage: true,
        });
    }
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await page.screenshot({
        path: directory + '/task5-dark.png',
        fullPage: true,
    });
    await page.evaluate(() => {
        document.documentElement.classList.remove('dark');
        document.documentElement.style.zoom = '2';
    });
    await page.screenshot({
        path: directory + '/task5-css-zoom-200.png',
        fullPage: true,
    });
});
