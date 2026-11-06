import { afterEach, expect, it, vi } from 'vite-plus/test';
import { createCheckoutSession } from './checkout-session';
import type { DraftStorage } from './draft-storage';
import { mixedBasketFixture } from '../../fixtures/pos/scenarios';
const scope = { userId: 1, storeId: 1, storeSlug: 'store' };
const request = { ...mixedBasketFixture.completionInput };
const owner = { tabId: 'tab', epoch: 1 };
function setup(failSave = false) {
    let record: any = {
        schemaVersion: 1,
        scope,
        draftId: 'draft',
        revision: 1,
        owner,
        lines: request.lines,
        quantityBuffers: {},
        snapshots: { products: [], addons: [], inventory: [], issues: [] },
        attempt: null,
        terminal: null,
    };
    const events: string[] = [];
    const storage = {
        read: async () => structuredClone(record),
        begin: async () => {
            events.push('persist');
            if (failSave) throw new Error('disk');
            record = {
                ...record,
                revision: 2,
                attempt: {
                    state: 'submitting',
                    userId: 1,
                    storeId: 1,
                    draftId: 'draft',
                    idempotencyKey: request.idempotencyKey,
                    originalRequest: structuredClone(request),
                },
            };
            return record;
        },
        reconcile: async () => record,
    } as unknown as DraftStorage;
    const transport = {
        complete: vi.fn(async (_input: typeof request) => {
            events.push('post');
            throw new Error('lost');
        }),
        lookup: vi.fn(async () => ({ status: 'not_found' as const })),
    };
    return { storage, transport, events };
}
afterEach(() => vi.useRealTimers());
it('persists before POST, keeps unknown original and retries only explicitly', async () => {
    const f = setup();
    const s = createCheckoutSession(scope, f.storage, f.transport);
    await s.submit(request, owner, 1);
    expect(f.events).toEqual(['persist', 'post']);
    expect(s.snapshot().state).toBe('unknown');
    await s.checkAgain();
    expect(s.snapshot().lookup).toBe('not_found');
    expect(f.transport.complete).toHaveBeenCalledTimes(1);
    await s.retryOriginal();
    expect(f.transport.complete).toHaveBeenCalledTimes(2);
    expect(f.transport.complete.mock.calls[1][0]).toEqual(request);
    s.dispose();
});
it('storage failure sends zero requests', async () => {
    const f = setup(true),
        s = createCheckoutSession(scope, f.storage, f.transport);
    await s.submit(request, owner, 1);
    expect(f.transport.complete).toHaveBeenCalledTimes(0);
    expect(s.snapshot().state).toBe('draft');
    s.dispose();
});
it('bounded recovery checks at 0,2,5,10,20 seconds and never POSTs', async () => {
    vi.useFakeTimers();
    const f = setup(),
        s = createCheckoutSession(scope, f.storage, f.transport);
    await s.submit(request, owner, 1);
    s.recover();
    await vi.advanceTimersByTimeAsync(21000);
    expect(f.transport.lookup).toHaveBeenCalledTimes(5);
    expect(f.transport.complete).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(90000);
    expect(f.transport.lookup).toHaveBeenCalledTimes(5);
    s.dispose();
});
import { PosRequestError } from './requests';
import type { CheckoutTransport } from './checkout-session';
it('pending is unresolved and a replacement rejection never automatically resubmits', async () => {
    const f = setup();
    const complete = vi.fn<CheckoutTransport['complete']>(async () => ({
        status: 'pending',
        idempotencyKey: request.idempotencyKey,
    }));
    const s = createCheckoutSession(scope, f.storage, {
        ...f.transport,
        complete,
    });
    await s.submit(request, owner, 1);
    expect(s.snapshot().state).toBe('unknown');
    expect(s.snapshot().lookup).toBe('pending');
    expect(complete).toHaveBeenCalledTimes(1);
    s.dispose();
    const rejected = vi.fn<CheckoutTransport['complete']>(async () => {
        throw new PosRequestError(
            {
                code: 'quote_changed',
                message: 'Review again',
                fieldErrors: {},
                issues: [],
            },
            409,
            mixedBasketFixture.quote,
        );
    });
    const other = createCheckoutSession(scope, setup().storage, {
        ...f.transport,
        complete: rejected,
    });
    await other.submit(request, owner, 1);
    expect(other.snapshot().state).toBe('rejected');
    expect(rejected).toHaveBeenCalledTimes(1);
    other.dispose();
});
it('a verified confirmation remains visible when marker storage fails and blocks New Sale', async () => {
    const f = setup();
    f.storage.reconcile = async () => {
        throw new Error('quota');
    };
    const s = createCheckoutSession(scope, f.storage, {
        ...f.transport,
        complete: async () => ({
            status: 'confirmed',
            idempotencyKey: request.idempotencyKey,
            sale: mixedBasketFixture.sale,
        }),
    });
    await s.submit(request, owner, 1);
    expect(s.snapshot().state).toBe('confirmed');
    expect(s.snapshot().sale?.id).toBe(1);
    expect(s.snapshot().durable).toBe(false);
    expect((await f.storage.read(scope))?.attempt).toMatchObject({
        originalRequest: request,
    });
    s.dispose();
});
it('actor precondition failure preserves unresolved draft and stops recovery', async () => {
    vi.useFakeTimers();
    const f = setup();
    const lookup = vi.fn<CheckoutTransport['lookup']>(async () => {
        throw new PosRequestError(
            {
                code: 'session_changed',
                message: 'session changed',
                fieldErrors: {},
                issues: [],
            },
            409,
        );
    });
    const s = createCheckoutSession(scope, f.storage, {
        ...f.transport,
        lookup,
    });
    await s.submit(request, owner, 1);
    s.recover();
    await vi.advanceTimersByTimeAsync(30000);
    expect(lookup).toHaveBeenCalledTimes(1);
    expect(s.snapshot().sessionValid).toBe(false);
    expect((await f.storage.read(scope))?.attempt).toMatchObject({
        originalRequest: request,
    });
    await s.retryOriginal();
    expect(f.transport.complete).toHaveBeenCalledTimes(1);
    s.dispose();
});
it('slow lookups skip elapsed slots, timeout and never overlap or POST', async () => {
    vi.useFakeTimers();
    const f = setup();
    const lookup = vi.fn<CheckoutTransport['lookup']>(
        () => new Promise(() => {}),
    );
    const s = createCheckoutSession(scope, f.storage, {
        ...f.transport,
        lookup,
    });
    await s.submit(request, owner, 1);
    s.recover();
    await vi.advanceTimersByTimeAsync(9999);
    expect(lookup).toHaveBeenCalledTimes(1);
    await s.checkAgain();
    expect(lookup).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(10001);
    expect(lookup).toHaveBeenCalledTimes(2);
    await vi.advanceTimersByTimeAsync(60000);
    expect(lookup).toHaveBeenCalledTimes(2);
    expect(f.transport.complete).toHaveBeenCalledTimes(1);
    s.dispose();
});
