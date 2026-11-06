import { describe, expect, it } from 'vite-plus/test';
import { decodeDraft, selectedSnapshots } from './draft-session';
describe('draft persistence boundaries', () => {
    it('quarantines unsupported records rather than replacing uncertain work', () => {
        expect(() =>
            decodeDraft({ schemaVersion: 2, attempt: { state: 'unknown' } }),
        ).toThrow();
    });
    it('rejects malformed version-one records', () => {
        expect(() => decodeDraft({ schemaVersion: 1, lines: [] })).toThrow();
    });
    it('stores only selected display snapshots', () => {
        const snapshots = {
            products: [
                { id: 1, kind: 'retail', stockItemId: 1 },
                { id: 2, kind: 'retail', stockItemId: 2 },
            ],
            addons: [
                { id: 3, kind: 'addon', stockItemId: 3 },
                { id: 4, kind: 'addon', stockItemId: 4 },
            ],
            inventory: [],
            issues: [],
        };
        expect(
            selectedSnapshots(
                [{ id: 'line', productId: 1, quantity: 1, addonIds: [3] }],
                snapshots as never,
            ).products.map((x) => x.id),
        ).toEqual([1]);
    });
});

it('quarantines partially readable submissions whose original request is corrupt', () => {
    const record = {
        schemaVersion: 1,
        scope: { userId: 1, storeId: 1, storeSlug: 'store' },
        draftId: 'draft',
        revision: 1,
        owner: { tabId: 'tab', epoch: 1 },
        lines: [],
        quantityBuffers: {},
        snapshots: { products: [], addons: [], inventory: [], issues: [] },
        attempt: {
            state: 'unknown',
            userId: 1,
            storeId: 1,
            draftId: 'draft',
            idempotencyKey: 'key',
            originalRequest: { idempotencyKey: 'key', lines: [] },
        },
        terminal: null,
    };
    expect(() => decodeDraft(record)).toThrow();
});

it('rejects false operation markers instead of treating them as editable', () => {
    const base = {
        schemaVersion: 1,
        scope: { userId: 1, storeId: 1, storeSlug: 's' },
        draftId: 'd',
        revision: 1,
        owner: { tabId: 'a', epoch: 1 },
        lines: [],
        quantityBuffers: {},
        snapshots: { products: [], addons: [], inventory: [], issues: [] },
        attempt: null,
        terminal: null,
    };
    for (const extra of [
        { attempt: false },
        { terminal: false },
        {
            attempt: {
                state: 'confirmed',
                userId: 1,
                storeId: 1,
                draftId: 'd',
                idempotencyKey: 'key',
            },
        },
    ])
        expect(() => decodeDraft({ ...base, ...extra })).toThrow();
});

import { createDraftSession } from './draft-session';
import type { DraftStorage } from './draft-storage';
import { vi } from 'vite-plus/test';
it('a former editor cannot save using a newly restored rival owner token', async () => {
    const scope = { userId: 1, storeId: 1, storeSlug: 's' },
        cart = {
            lines: [],
            quantityBuffers: {},
            snapshots: { products: [], addons: [], inventory: [], issues: [] },
        };
    const save = vi.fn();
    const storage = {
        read: async () => ({
            schemaVersion: 1,
            scope,
            draftId: 'd',
            revision: 2,
            owner: { tabId: 'new-editor', epoch: 2 },
            ...cart,
            attempt: null,
            terminal: null,
        }),
        save,
    } as unknown as DraftStorage;
    const session = createDraftSession(scope, 'former-editor', storage);
    await session.restore();
    await expect(session.saveCart(cart)).rejects.toThrow();
    expect(save).toHaveBeenCalledTimes(0);
    session.dispose();
});

it('restoring a disposed session reconnects broadcasts before saving', async () => {
    const scope = { userId: 1, storeId: 1, storeSlug: 's' },
        cart = {
            lines: [],
            quantityBuffers: {},
            snapshots: { products: [], addons: [], inventory: [], issues: [] },
        },
        record = {
            schemaVersion: 1,
            scope,
            draftId: 'd',
            revision: 1,
            owner: { tabId: 'editor', epoch: 1 },
            ...cart,
            attempt: null,
            terminal: null,
        };
    const storage = {
        read: async () => record,
        save: async () => ({ ...record, revision: 2 }),
    } as unknown as DraftStorage;
    const session = createDraftSession(scope, 'editor', storage);
    await session.restore();
    session.dispose();
    await session.restore();
    await expect(session.saveCart(cart)).resolves.toBeUndefined();
    expect(session.snapshot().record?.revision).toBe(2);
    session.dispose();
});
