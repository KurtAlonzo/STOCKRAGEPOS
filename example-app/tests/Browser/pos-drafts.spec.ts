import { test, expect } from '@playwright/test';
import type { DraftStorage } from '../../resources/js/lib/pos/draft-storage';
type Harness = Window & { storage: DraftStorage };
const scope = { userId: 99, storeId: 99, storeSlug: 'test' };
async function adapter(page: import('@playwright/test').Page) {
    await page.goto('/login');
    await page.evaluate(async () => {
        const url =
            'http://127.0.0.1:5179/resources/js/lib/pos/draft-storage.ts';
        const module = await import(url);
        (window as unknown as Harness).storage =
            await module.openDraftStorage();
    });
}
test('actual IndexedDB serializes simultaneous claims, takeover and stale saves', async ({
    page,
    context,
}) => {
    await adapter(page);
    const other = await context.newPage();
    await adapter(other);
    const [a, b] = await Promise.all([
        page.evaluate(
            (s) => (window as unknown as Harness).storage.create(s, 'a'),
            scope,
        ),
        other.evaluate(
            (s) => (window as unknown as Harness).storage.create(s, 'b'),
            scope,
        ),
    ]);
    expect(a.owner).toEqual(b.owner);
    const results = await Promise.allSettled([
        page.evaluate(
            ({ s, r }) =>
                (window as unknown as Harness).storage.takeover(s, 'a', r),
            { s: scope, r: a.revision },
        ),
        other.evaluate(
            ({ s, r }) =>
                (window as unknown as Harness).storage.takeover(s, 'b', r),
            { s: scope, r: a.revision },
        ),
    ]);
    expect(results.filter((x) => x.status === 'fulfilled')).toHaveLength(1);
    await expect(
        page.evaluate(
            ({ s, r }) =>
                (window as unknown as Harness).storage.save(
                    s,
                    r.owner,
                    r.revision,
                    {
                        lines: [],
                        quantityBuffers: {},
                        snapshots: {
                            products: [],
                            addons: [],
                            inventory: [],
                            issues: [],
                        },
                    },
                ),
            { s: scope, r: a },
        ),
    ).rejects.toThrow();
});
test('begin freezes exact request and prevents takeover, discard and new sale across refresh', async ({
    page,
}) => {
    await adapter(page);
    const s = { ...scope, storeId: 100 };
    const r = await page.evaluate(
        (s) => (window as unknown as Harness).storage.create(s, 'a'),
        s,
    );
    const request = {
        idempotencyKey: 'operation',
        quoteId: 'quote',
        quoteRevision: '1',
        lines: [],
        payment: { method: 'cash' as const, tenderedMinor: 50000 },
        acknowledgedWarningIds: [],
    };
    const begun = await page.evaluate(
        ({ s, r, request }) =>
            (window as unknown as Harness).storage.begin(
                s,
                r.owner,
                r.revision,
                request,
            ),
        { s, r, request },
    );
    await expect(
        page.evaluate(
            ({ s, r }) =>
                (window as unknown as Harness).storage.takeover(
                    s,
                    'b',
                    r.revision,
                ),
            { s, r: begun },
        ),
    ).rejects.toThrow();
    await expect(
        page.evaluate(
            ({ s, r }) =>
                (window as unknown as Harness).storage.discard(
                    s,
                    r.owner,
                    r.revision,
                ),
            { s, r: begun },
        ),
    ).rejects.toThrow();
    await adapter(page);
    const restored = await page.evaluate(
        (s) => (window as unknown as Harness).storage.read(s),
        s,
    );
    expect(restored?.attempt).toMatchObject({
        state: 'submitting',
        originalRequest: request,
    });
    const foreign = await page.evaluate(
        (s) =>
            (window as unknown as Harness).storage.read({ ...s, userId: 1000 }),
        s,
    );
    expect(foreign).toBeNull();
});
test('corrupt records are quarantined without erasing an uncertain operation', async ({
    page,
}) => {
    await adapter(page);
    await page.evaluate(async () => {
        await new Promise<void>((resolve, reject) => {
            const open = indexedDB.open('stockrage-pos', 1);
            open.onsuccess = () => {
                const tx = open.result.transaction('drafts', 'readwrite');
                tx.objectStore('drafts').put(
                    { schemaVersion: 2, attempt: { state: 'unknown' } },
                    '98:98',
                );
                tx.oncomplete = () => resolve();
                tx.onerror = () => reject(tx.error);
            };
        });
    });
    await expect(
        page.evaluate(() =>
            (window as unknown as Harness).storage.read({
                userId: 98,
                storeId: 98,
                storeSlug: 'bad',
            }),
        ),
    ).rejects.toThrow(/corrupt/i);
});

test('takeover and begin race grants exactly one durable authority', async ({
    page,
    context,
}) => {
    await adapter(page);
    const other = await context.newPage();
    await adapter(other);
    const s = { ...scope, storeId: 101 };
    const r = await page.evaluate(
        (s) => (window as unknown as Harness).storage.create(s, 'a'),
        s,
    );
    const request = {
        idempotencyKey: '11111111-1111-4111-8111-111111111111',
        quoteId: '22222222-2222-4222-8222-222222222222',
        quoteRevision: '1',
        lines: [],
        payment: { method: 'cash' as const, tenderedMinor: 50000 },
        acknowledgedWarningIds: [],
    };
    const outcomes = await Promise.allSettled([
        page.evaluate(
            ({ s, r, request }) =>
                (window as unknown as Harness).storage.begin(
                    s,
                    r.owner,
                    r.revision,
                    request,
                ),
            { s, r, request },
        ),
        other.evaluate(
            ({ s, r }) =>
                (window as unknown as Harness).storage.takeover(
                    s,
                    'b',
                    r.revision,
                ),
            { s, r },
        ),
    ]);
    expect(outcomes.filter((x) => x.status === 'fulfilled')).toHaveLength(1);
    const result = await page.evaluate(
        (s) => (window as unknown as Harness).storage.read(s),
        s,
    );
    expect(
        result!.attempt?.state === 'submitting' || result!.owner.tabId === 'b',
    ).toBe(true);
});
test('open failure refuses checkout storage instead of silently using memory', async ({
    page,
}) => {
    await page.goto('/login');
    await page.evaluate(() => {
        indexedDB.open = () => {
            throw new DOMException(
                'Injected unavailable storage',
                'SecurityError',
            );
        };
    });
    await expect(
        page.evaluate(async () => {
            const url =
                'http://127.0.0.1:5179/resources/js/lib/pos/draft-storage.ts';
            const module = await import(url);
            return module.openDraftStorage();
        }),
    ).rejects.toThrow('Injected unavailable storage');
});

test('only provably editable corrupt records can be discarded explicitly', async ({
    page,
}) => {
    await adapter(page);
    const s = { userId: 97, storeId: 97, storeSlug: 'broken' };
    const r = await page.evaluate(
        (s) => (window as unknown as Harness).storage.create(s, 'owner'),
        s,
    );
    await page.evaluate(async () => {
        await new Promise<void>((resolve, reject) => {
            const open = indexedDB.open('stockrage-pos', 1);
            open.onsuccess = () => {
                const tx = open.result.transaction('drafts', 'readwrite'),
                    store = tx.objectStore('drafts'),
                    get = store.get('97:97');
                get.onsuccess = () =>
                    store.put({ ...get.result, lines: 'damaged' }, '97:97');
                tx.oncomplete = () => resolve();
                tx.onerror = () => reject(tx.error);
            };
        });
    });
    await expect(
        page.evaluate((s) => (window as unknown as Harness).storage.read(s), s),
    ).rejects.toThrow();
    await page.evaluate(
        ({ s, r }) =>
            (window as unknown as Harness).storage.discard(
                s,
                r.owner,
                r.revision,
            ),
        { s, r },
    );
    expect(
        await page.evaluate(
            (s) => (window as unknown as Harness).storage.read(s),
            s,
        ),
    ).toBeNull();
});
