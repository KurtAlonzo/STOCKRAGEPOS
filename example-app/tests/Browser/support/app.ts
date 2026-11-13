import { expect, type Page } from '@playwright/test';
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
export function fixture(command = 'counts') {
    const dir = resolve('storage/framework/testing/task5-browser');
    const result = spawnSync(
        'php',
        ['tests/Support/pos-browser-fixture.php', command],
        {
            encoding: 'utf8',
            env: {
                ...process.env,
                APP_ENV: 'testing',
                TASK5_STORAGE: dir,
                DB_CONNECTION: 'sqlite',
                DB_DATABASE: resolve(dir, 'test.sqlite'),
                DB_URL: '',
                CACHE_STORE: 'file',
                SESSION_DRIVER: 'file',
                BCRYPT_ROUNDS: '4',
                APP_CONFIG_CACHE: resolve(dir, 'config.php'),
            },
        },
    );
    if (result.status !== 0) throw new Error(result.stderr + result.stdout);
    return result.stdout ? JSON.parse(result.stdout) : null;
}
export async function login(page: Page, email = 'task5@example.test') {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(/dashboard/);
}
export async function openPos(page: Page) {
    await login(page);
    await page.getByRole('link', { name: 'POS', exact: true }).first().click();
    await expect(
        page.getByRole('heading', { name: 'Products', exact: true }),
    ).toBeVisible();
}
export async function mixedCart(page: Page) {
    await page.getByRole('button', { name: /Water PHP/ }).click();
    await page.getByRole('button', { name: /Burger PHP/ }).click();
    await page.getByLabel('Cheese').check();
    await page.getByRole('button', { name: /Add to cart|Apply/ }).click();
    const qty = page.getByLabel('Quantity', { exact: true }).nth(1);
    await qty.fill('2');
    await page.getByRole('button', { name: /Burger PHP/ }).click();
    await page.getByLabel('Extra beef').check();
    await page.getByRole('button', { name: /Add to cart|Apply/ }).click();
    await page.getByLabel('Cash tender (PHP)').fill('500.00');
    await expect(
        page.getByRole('button', { name: 'Review payment', exact: true }),
    ).toBeEnabled();
}

// Register the intercepted real request in a separate process, then interrupt
// processing only after its durable registration barrier is visible.
export async function abandonRegisteredCheckout(request: unknown) {
    const dir = resolve('storage/framework/testing/task5-browser');
    const prefix = resolve(dir, `abandoned-${Date.now()}`);
    writeFileSync(prefix + '.json', JSON.stringify(request));
    const worker = spawn(
        'php',
        [
            'tests/Support/checkout-worker.php',
            resolve(dir, 'test.sqlite'),
            resolve(dir, 'framework/cache/data'),
            '1',
            prefix + '.json',
            prefix,
            prefix + '.gate',
            'register-only',
        ],
        {
            env: {
                ...process.env,
                APP_ENV: 'testing',
                APP_CONFIG_CACHE: resolve(dir, 'config.php'),
            },
        },
    );
    let error = '';
    worker.stderr.on('data', (data) => {
        error += String(data);
    });
    try {
        await expect
            .poll(
                () => {
                    if (worker.exitCode !== null)
                        throw new Error(error || 'Registration worker exited');
                    return existsSync(prefix + '.registered');
                },
                { timeout: 20000 },
            )
            .toBe(true);
    } finally {
        worker.kill();
    }
}

export async function logout(page: Page) {
    const csrf = (await page.context().cookies()).find(
        (x) => x.name === 'XSRF-TOKEN',
    );
    const response = await page.request.post('/logout', {
        headers: { 'X-XSRF-TOKEN': decodeURIComponent(csrf!.value) },
    });
    expect(response.ok()).toBe(true);
}

export async function completedMixedSale(page: Page) {
    await openPos(page);
    await mixedCart(page);
    await page
        .getByRole('button', { name: 'Review payment', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Confirm payment recording' })
        .click();
    await page.getByRole('link', { name: 'View sale', exact: true }).click();
    await expect(
        page.getByRole('heading', { name: 'SR-00000001', exact: true }),
    ).toBeVisible();
}
export async function abandonRegisteredReversal(
    request: unknown,
    saleId: number,
) {
    const dir = resolve('storage/framework/testing/task5-browser');
    const prefix = resolve(dir, `refund-abandoned-${Date.now()}`);
    writeFileSync(prefix + '.json', JSON.stringify(request));
    const worker = spawn(
        'php',
        [
            'tests/Support/reversal-worker.php',
            resolve(dir, 'test.sqlite'),
            resolve(dir, 'framework/cache/data'),
            '1',
            String(saleId),
            prefix + '.json',
            prefix,
            prefix + '.gate',
        ],
        {
            env: {
                ...process.env,
                APP_ENV: 'testing',
                APP_CONFIG_CACHE: resolve(dir, 'config.php'),
            },
        },
    );
    let error = '';
    worker.stderr.on('data', (data) => {
        error += String(data);
    });
    try {
        await expect
            .poll(
                () => {
                    if (worker.exitCode !== null)
                        throw new Error(error || 'Worker exited');
                    return existsSync(prefix + '.registered');
                },
                { timeout: 20000 },
            )
            .toBe(true);
    } finally {
        worker.kill();
    }
}
