import { test, expect, type Page } from '@playwright/test';
import type {
    ReversalStorage,
    ReversalScope,
} from '../../resources/js/lib/sales/reversal-storage';
import type { ReversalInput } from '../../resources/js/types/sales';
type Harness = Window & { salesStorage: ReversalStorage };
const scope: ReversalScope = {
    userId: 1,
    storeId: 1,
    storeSlug: 'store',
    saleId: 99,
};
const request: ReversalInput = {
    idempotencyKey: 'aaaaaaaa-1111-4111-8111-111111111111',
    reason: 'Returned',
    refundConfirmed: true,
    refundMethod: 'cash',
    refundReference: null,
    restock: [],
};
async function adapter(page: Page) {
    await page.goto('/login');
    await page.evaluate(async () => {
        const url =
            'http://127.0.0.1:5179/resources/js/lib/sales/reversal-storage.ts';
        const module = await import(url);
        (window as unknown as Harness).salesStorage =
            await module.openReversalStorage();
    });
}
test('real IndexedDB atomically claims one refund and blocks corrupt uncertain replacement', async ({
    page,
    context,
}) => {
    await adapter(page);
    const other = await context.newPage();
    await adapter(other);
    const attempts = await Promise.allSettled([
        page.evaluate(
            ({ scope, request }) =>
                (window as unknown as Harness).salesStorage.begin(
                    scope,
                    request,
                ),
            { scope, request },
        ),
        other.evaluate(
            ({ scope, request }) =>
                (window as unknown as Harness).salesStorage.begin(scope, {
                    ...request,
                    idempotencyKey: 'bbbbbbbb-1111-4111-8111-111111111111',
                }),
            { scope, request },
        ),
    ]);
    expect(
        attempts.filter((value) => value.status === 'fulfilled'),
    ).toHaveLength(1);
    const original = await page.evaluate(
        (scope) => (window as unknown as Harness).salesStorage.read(scope),
        scope,
    );
    expect(original?.request.idempotencyKey).toMatch(/^[ab]/);
    const foreign = await other.evaluate(
        (scope) =>
            (window as unknown as Harness).salesStorage.read({
                ...scope,
                userId: 2,
            }),
        scope,
    );
    expect(foreign).toBeNull();
    await page.evaluate(async () => {
        await new Promise<void>((resolve, reject) => {
            const open = indexedDB.open('stockrage-sales', 1);
            open.onsuccess = () => {
                const tx = open.result.transaction('reversals', 'readwrite');
                tx.objectStore('reversals').put(
                    {
                        schemaVersion: 2,
                        request: { idempotencyKey: 'damaged' },
                    },
                    '1:1:100',
                );
                tx.oncomplete = () => resolve();
                tx.onerror = () => reject(tx.error);
            };
        });
    });
    await expect(
        page.evaluate(
            ({ scope, request }) =>
                (window as unknown as Harness).salesStorage.begin(
                    { ...scope, saleId: 100 },
                    request,
                ),
            { scope, request },
        ),
    ).rejects.toThrow(/damaged/);
    const saved = await page.evaluate(
        (scope) => (window as unknown as Harness).salesStorage.read(scope),
        scope,
    );
    expect(saved).toEqual(original);
});
