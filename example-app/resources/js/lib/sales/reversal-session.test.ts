import { expect, it, vi } from 'vite-plus/test';
import { createReversalSession } from './reversal-session';
import {
    normalizeReversal,
    decodeCurrent,
    decodeAttempt,
} from './reversal-requests';
import type {
    ReversalStorage,
    ReversalScope,
    ReversalRecord,
} from './reversal-storage';
import type { ReversalInput, SaleReversal } from '@/types/sales';
import { clearPosMemory } from '../pos/auth-scope';
const scope: ReversalScope = {
    userId: 1,
    storeId: 1,
    storeSlug: 'store',
    saleId: 5,
};
const request: ReversalInput = {
    idempotencyKey: 'aaaaaaaa-1111-4111-8111-111111111111',
    reason: 'Returned',
    refundConfirmed: true,
    refundMethod: 'maya',
    refundReference: null,
    restock: [],
};
const reversal: SaleReversal = {
    ...request,
    id: 1,
    saleId: 5,
    totalMinor: 41000,
    actorId: 1,
    actorName: 'Owner',
    occurredAt: '2026-11-09T00:00:00Z',
};
function setup(failBegin = false, failClear = false) {
    let record: ReversalRecord | null = null;
    const events: string[] = [];
    const storage: ReversalStorage = {
        read: async () => structuredClone(record),
        begin: async (s, r) => {
            events.push('persist');
            if (failBegin) throw new Error('Storage unavailable');
            record ??= {
                schemaVersion: 1,
                scope: s,
                request: structuredClone(r),
            };
            return record;
        },
        clear: async () => {
            if (failClear) throw new Error('Cleanup failed');
            record = null;
        },
    };
    const transport = {
        current: vi.fn(async () => ({ status: 'not_recorded' as const })),
        lookup: vi.fn(async (_key: string) => ({
            saleId: 5,
            idempotencyKey: request.idempotencyKey,
            status: 'pending' as const,
        })),
        post: vi.fn(async (_input: ReversalInput) => {
            events.push('post');
            throw new Error('Response lost');
        }),
    };
    const session = createReversalSession(scope, storage, transport);
    return { session, storage, transport, events };
}
it('normalizes exact return quantities and rejects fractions, duplicates and unsafe amounts', () => {
    expect(
        normalizeReversal({
            ...request,
            reason: ' Returned ',
            refundReference: ' ',
            restock: [{ inventoryItemId: 2, quantity: '2' }],
        }),
    ).toEqual({
        ...request,
        restock: [{ inventoryItemId: 2, quantity: '2.000' }],
    });
    for (const quantity of ['0', '-1', '1.5', '9007199254741', '1e3'])
        expect(() =>
            normalizeReversal({
                ...request,
                restock: [{ inventoryItemId: 2, quantity }],
            }),
        ).toThrow();
    expect(() =>
        normalizeReversal({
            ...request,
            restock: [
                { inventoryItemId: 2, quantity: '1' },
                { inventoryItemId: 2, quantity: '1' },
            ],
        }),
    ).toThrow();
});
it('persists before POST and uncertain retry preserves original request without automatic posts', async () => {
    const f = setup();
    await f.session.restore();
    await f.session.submit(request);
    expect(f.events).toEqual(['persist', 'post']);
    expect(f.session.snapshot().status).toBe('unknown');
    await f.session.check();
    expect(f.session.snapshot().canRetry).toBe(true);
    expect(f.transport.post).toHaveBeenCalledTimes(1);
    await f.session.retryOriginal();
    expect(f.transport.post).toHaveBeenCalledTimes(2);
    expect(f.transport.post.mock.calls[1][0]).toEqual(request);
    await f.session.submit({ ...request, reason: 'Changed' });
    expect(f.transport.post).toHaveBeenCalledTimes(2);
    f.session.dispose();
});
it('storage failure prevents all POSTs', async () => {
    const f = setup(true);
    await f.session.restore();
    await f.session.submit(request);
    expect(f.transport.post).not.toHaveBeenCalled();
    f.session.dispose();
});
it('recorded current outcome remains read-only when local cleanup fails', async () => {
    const f = setup(false, true);
    await f.session.restore();
    await f.session.submit(request);
    f.transport.current.mockResolvedValue({
        status: 'recorded',
        reversal,
    } as never);
    await f.session.check();
    expect(f.session.snapshot().status).toBe('recorded');
    expect(f.session.snapshot().reversal).toEqual(reversal);
    expect(f.session.snapshot().canRetry).toBe(false);
    await f.session.submit(request);
    expect(f.transport.post).toHaveBeenCalledTimes(1);
    f.session.dispose();
});
it('rejects malformed results and mismatched sale or key identities', () => {
    expect(() =>
        decodeCurrent(
            { status: 'recorded', reversal: { ...reversal, saleId: 6 } },
            scope,
        ),
    ).toThrow();
    expect(() =>
        decodeAttempt(
            {
                status: 'recorded',
                saleId: 5,
                idempotencyKey: 'wrong',
                reversal,
            },
            scope,
            request.idempotencyKey,
        ),
    ).toThrow();
    expect(() =>
        decodeCurrent(
            { status: 'recorded', reversal: { ...reversal, totalMinor: NaN } },
            scope,
        ),
    ).toThrow();
});
it('a late response cannot restore sensitive state after account invalidation', async () => {
    const f = setup();
    let resolve!: (value: { status: 'not_recorded' }) => void;
    f.transport.current.mockImplementationOnce(
        () =>
            new Promise((r) => {
                resolve = r;
            }),
    );
    const pending = f.session.restore();
    clearPosMemory(1);
    resolve({ status: 'not_recorded' });
    await pending;
    expect(f.session.snapshot().sessionValid).toBe(false);
    await f.session.submit(request);
    expect(f.transport.post).not.toHaveBeenCalled();
    f.session.dispose();
});
it('only a durable rejected outcome permits a new request after rereading the current sale', async () => {
    const f = setup();
    await f.session.restore();
    await f.session.submit(request);
    f.transport.lookup.mockResolvedValue({
        status: 'rejected',
        saleId: 5,
        idempotencyKey: request.idempotencyKey,
        error: {
            code: 'validation_failed',
            message: 'Too many',
            fieldErrors: {},
            issues: [],
        },
    } as never);
    await f.session.check();
    expect(f.session.snapshot().status).toBe('rejected');
    expect(await f.storage.read(scope)).toBeNull();
    await f.session.submit({
        ...request,
        idempotencyKey: 'bbbbbbbb-1111-4111-8111-111111111111',
    });
    expect(f.transport.post).toHaveBeenCalledTimes(2);
    f.session.dispose();
});
