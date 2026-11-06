import { useEffect, useState, useSyncExternalStore } from 'react';
import type { PosScope } from '@/types/pos';
import type { DraftStorage } from '@/lib/pos/draft-storage';
import { createCheckoutSession } from '@/lib/pos/checkout-session';
import {
    decodeCompletion,
    decodeLookup,
    requestPosJson,
} from '@/lib/pos/checkout-requests';
export function useCheckoutAttempt(
    scope: PosScope,
    storage: DraftStorage | null,
) {
    const [session, setSession] = useState<ReturnType<
        typeof createCheckoutSession
    > | null>(null);
    useEffect(() => {
        if (!storage) return;
        const base = `/${scope.storeSlug}/pos`;
        const next = createCheckoutSession(scope, storage, {
            complete: (input, signal) =>
                requestPosJson(
                    base + '/checkouts',
                    { method: 'POST', signal, body: JSON.stringify(input) },
                    scope,
                    decodeCompletion,
                ),
            lookup: (key, signal) =>
                requestPosJson(
                    base + '/attempts/' + encodeURIComponent(key),
                    { signal },
                    scope,
                    decodeLookup,
                ),
        });
        setSession(next);
        void next.restore().catch(() => {});
        const check = () => {
            void next.checkAgain();
        };
        window.addEventListener('online', check);
        document.addEventListener('visibilitychange', check);
        return () => {
            next.dispose();
            window.removeEventListener('online', check);
            document.removeEventListener('visibilitychange', check);
        };
    }, [scope, storage]);
    const fallback = null;
    const state = useSyncExternalStore(
        session?.subscribe ?? (() => () => {}),
        session?.snapshot ?? (() => fallback),
        () => fallback,
    );
    return { state, session };
}
