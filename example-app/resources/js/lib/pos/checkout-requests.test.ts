import { subscribePosMemory } from './auth-scope';
import { expect, it, vi } from 'vite-plus/test';
import {
    requestPosJson,
    decodeLookup,
    decodeCompletion,
} from './checkout-requests';
const scope = { userId: 1, storeId: 2, storeSlug: 'shop' };
it('rejects another actor response before applying its business rejection', async () => {
    let invalidations = 0;
    const stop = subscribePosMemory(scope.userId, () => invalidations++);
    vi.stubGlobal(
        'fetch',
        vi.fn(
            async () =>
                new Response(
                    JSON.stringify({
                        error: {
                            code: 'validation_failed',
                            message: 'No',
                            fieldErrors: {},
                            issues: [],
                        },
                    }),
                    {
                        status: 409,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-POS-Actor-Id': '3',
                            'X-POS-Store-Id': '2',
                        },
                    },
                ),
        ),
    );
    await expect(
        requestPosJson('/pos', {}, scope, decodeLookup),
    ).rejects.toThrow('session');
    expect(invalidations).toBe(1);
    stop();
    vi.unstubAllGlobals();
});
it('rejects malformed confirmed and pending bodies', () => {
    expect(() =>
        decodeCompletion({
            status: 'confirmed',
            idempotencyKey: 'x',
            sale: { id: 1 },
        }),
    ).toThrow();
    expect(() => decodeLookup({ status: 'pending' })).toThrow();
    expect(decodeLookup({ status: 'not_found' })).toEqual({
        status: 'not_found',
    });
});
