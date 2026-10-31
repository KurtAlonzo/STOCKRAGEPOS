import { useEffect, useState, useSyncExternalStore } from 'react';
import { cartInputKey, createQuoteSession } from '@/lib/pos/requests';
import type { QuoteInputs, QuoteState } from '@/lib/pos/requests';
export type { QuoteState } from '@/lib/pos/requests';
export function usePosQuote(
    input: QuoteInputs,
): QuoteState & { retry: () => void } {
    const [session] = useState(() => createQuoteSession(() => {}));
    useEffect(() => {
        session.update(input);
    }, [session, input]);
    useEffect(() => {
        const recover = () => {
            if (document.visibilityState === 'visible') session.recover();
        };
        document.addEventListener('visibilitychange', recover);
        window.addEventListener('focus', recover);
        window.addEventListener('online', recover);
        return () => {
            session.dispose();
            document.removeEventListener('visibilitychange', recover);
            window.removeEventListener('focus', recover);
            window.removeEventListener('online', recover);
        };
    }, [session]);
    const state = useSyncExternalStore(
        session.subscribe,
        session.snapshot,
        session.snapshot,
    );
    const matches =
        state.quote &&
        state.quote.business.storeId === Number(input.scopeKey.split(':')[0]) &&
        cartInputKey(input.lines) ===
            cartInputKey(
                state.quote.lines.map((x) => ({
                    id: x.lineId,
                    productId: x.productId,
                    quantity: x.quantity,
                    addonIds: x.addons.map((a) => a.id),
                })),
            );
    const status = !input.lines.length
        ? 'empty'
        : !input.valid
          ? 'invalid'
          : state.status === 'current' &&
              (!matches || Date.parse(state.quote!.expiresAt) <= Date.now())
            ? 'stale'
            : state.status === 'empty'
              ? 'pending'
              : state.status;
    useEffect(() => {
        if (state.status === 'stale' && document.visibilityState === 'visible')
            session.recover();
    }, [session, state.status]);
    return { ...state, status, retry: session.retry };
}
