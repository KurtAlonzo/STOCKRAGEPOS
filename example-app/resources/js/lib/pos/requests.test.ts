import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    cartInputKey,
    createRequestGate,
    requestJson,
    validateQuote,
    createQuoteSession,
} from './requests';
import { mixedBasketFixture } from '../../fixtures/pos/scenarios';
const lines = mixedBasketFixture.quoteInput.lines;
const quote = () => ({
    ...mixedBasketFixture.quote,
    payment: null,
    changeMinor: null,
    expiresAt: new Date(Date.now() + 300000).toISOString(),
});
afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
});
describe('POS requests', () => {
    it('publishes immutable snapshots to React external-store subscribers', () => {
        const session = createQuoteSession(() => {}),
            listener = vi.fn();
        const unsubscribe = session.subscribe(listener);
        session.update({
            scopeKey: '1:1',
            lines,
            valid: false,
            quoteUrl: '/quote',
        });
        expect(listener).toHaveBeenCalledTimes(1);
        expect(session.snapshot().status).toBe('invalid');
        const before = session.snapshot();
        unsubscribe();
        session.update({
            scopeKey: '1:1',
            lines: [],
            valid: true,
            quoteUrl: '/quote',
        });
        expect(before.status).toBe('invalid');
        expect(listener).toHaveBeenCalledTimes(1);
        session.dispose();
    });
    it('canonicalizes property order for exact cart correspondence', () => {
        expect(cartInputKey(lines)).toBe(
            cartInputKey(
                lines.map((x) => ({
                    addonIds: x.addonIds,
                    quantity: x.quantity,
                    productId: x.productId,
                    id: x.id,
                })),
            ),
        );
    });
    it('rejects old generations, changed scope and cleared carts', () => {
        const gate = createRequestGate();
        const old = gate.next('A', 'one');
        const current = gate.next('B', 'two');
        expect(gate.accepts(old, 'A', 'one')).toBe(false);
        expect(gate.accepts(current, 'B', 'two')).toBe(true);
        gate.invalidate();
        expect(gate.accepts(current, 'B', 'two')).toBe(false);
    });
    it('preserves field errors and rejects HTML and authentication failures', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response(
                    JSON.stringify({
                        error: {
                            code: 'validation_failed',
                            message: 'Review quantity',
                            fieldErrors: {
                                'lines.0.quantity': ['Invalid'],
                            },
                            issues: [],
                        },
                    }),
                    {
                        status: 422,
                        headers: { 'Content-Type': 'application/json' },
                    },
                ),
            ),
        );
        await expect(
            requestJson('/quote', { method: 'POST' }),
        ).rejects.toMatchObject({
            fieldErrors: { 'lines.0.quantity': ['Invalid'] },
        });
        for (const status of [200, 401, 403, 419]) {
            vi.stubGlobal(
                'fetch',
                vi.fn().mockResolvedValue(
                    new Response('<html>Login</html>', {
                        status,
                        headers: { 'Content-Type': 'text/html' },
                    }),
                ),
            );
            await expect(requestJson('/quote', {})).rejects.toThrow();
        }
    });
    it('sends the decoded Laravel CSRF cookie and same-origin credentials', async () => {
        vi.stubGlobal('document', { cookie: 'XSRF-TOKEN=hello%3D' });
        const fetch = vi.fn().mockResolvedValue(
            new Response('{}', {
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetch);
        await requestJson('/quote', { method: 'POST', body: '{}' });
        expect(fetch.mock.calls[0][1].credentials).toBe('same-origin');
        expect(fetch.mock.calls[0][1].headers.get('X-XSRF-TOKEN')).toBe(
            'hello=',
        );
    });
    it('validates exact line correspondence and malformed quote totals', () => {
        expect(validateQuote(quote(), lines, 1)).toBe(true);
        expect(
            validateQuote(
                { ...quote(), lines: quote().lines.slice(1) },
                lines,
                1,
            ),
        ).toBe(false);
        expect(validateQuote({ ...quote(), totalMinor: NaN }, lines, 1)).toBe(
            false,
        );
        expect(
            validateQuote(
                {
                    ...quote(),
                    lines: quote().lines.map((x) => ({ ...x, quantity: 99 })),
                },
                lines,
                1,
            ),
        ).toBe(false);
    });
    it('debounces, ignores reversed results, expires once, and retries current inputs after failure', async () => {
        vi.useFakeTimers();
        const pending: ((value: unknown) => void)[] = [];
        const fetch = vi
            .fn()
            .mockImplementation(
                () => new Promise((resolve) => pending.push(resolve)),
            );
        vi.stubGlobal('fetch', fetch);
        const session = createQuoteSession(() => {});
        const input = {
            scopeKey: '1:1',
            lines,
            valid: true,
            quoteUrl: '/quote',
        };
        session.update(input);
        await vi.advanceTimersByTimeAsync(300);
        expect(fetch).toHaveBeenCalledTimes(1);
        session.update({ ...input, lines: [lines[0]] });
        await vi.advanceTimersByTimeAsync(300);
        const respond = (value: unknown) =>
            new Response(JSON.stringify(value), {
                headers: {
                    'Content-Type': 'application/json',
                    'X-POS-Actor-Id': '1',
                    'X-POS-Store-Id': '1',
                },
            });
        pending[1](
            respond({
                ...quote(),
                lines: [quote().lines[0]],
                subtotalMinor: 5000,
                totalMinor: 5000,
            }),
        );
        await vi.advanceTimersByTimeAsync(0);
        pending[0](respond(quote()));
        await vi.advanceTimersByTimeAsync(0);
        expect(session.snapshot().quote?.totalMinor).toBe(5000);
        await vi.advanceTimersByTimeAsync(300000);
        expect(session.snapshot().status).toBe('stale');
        session.recover();
        session.recover();
        await vi.advanceTimersByTimeAsync(300);
        expect(fetch).toHaveBeenCalledTimes(3);
        session.recover();
        await vi.advanceTimersByTimeAsync(300);
        expect(fetch).toHaveBeenCalledTimes(3);
        pending[2](new Response('offline', { status: 500 }));
        await vi.advanceTimersByTimeAsync(0);
        expect(session.snapshot().status).toBe('error');
        await vi.advanceTimersByTimeAsync(600000);
        expect(fetch).toHaveBeenCalledTimes(3);
        session.retry();
        await vi.advanceTimersByTimeAsync(300);
        expect(JSON.parse(fetch.mock.calls[3][1].body).lines).toEqual([
            lines[0],
        ]);
        session.update({ ...input, valid: false });
        expect(session.snapshot().status).toBe('invalid');
        session.update({ ...input, lines: [] });
        expect(session.snapshot().status).toBe('empty');
        session.dispose();
    });
});
