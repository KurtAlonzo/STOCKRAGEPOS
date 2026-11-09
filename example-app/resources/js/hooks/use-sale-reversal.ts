import { useEffect, useState, useSyncExternalStore } from 'react';
import { openReversalStorage } from '@/lib/sales/reversal-storage';
import type {
    ReversalScope,
    ReversalStorage,
} from '@/lib/sales/reversal-storage';
import { createReversalSession } from '@/lib/sales/reversal-session';
export function useSaleReversal(scope: ReversalScope) {
    const [storage, setStorage] = useState<ReversalStorage | null>(null),
        [error, setError] = useState('');
    useEffect(() => {
        let active = true;
        void openReversalStorage()
            .then((value) => {
                if (active) setStorage(value);
            })
            .catch((e) => {
                if (active) setError(String(e));
            });
        return () => {
            active = false;
        };
    }, []);
    const [session, setSession] = useState<ReturnType<
        typeof createReversalSession
    > | null>(null);
    useEffect(() => {
        if (!storage) return;
        const session = createReversalSession(scope, storage);
        setSession(session);
        void session.restore();
        const check = () => {
            if (document.visibilityState === 'visible') void session.restore();
        };
        window.addEventListener('focus', check);
        window.addEventListener('online', check);
        document.addEventListener('visibilitychange', check);
        const channel =
            typeof BroadcastChannel !== 'undefined'
                ? new BroadcastChannel('stockrage-sales')
                : null;
        if (channel)
            channel.onmessage = () => {
                void session.check();
            };
        return () => {
            session.dispose();
            window.removeEventListener('focus', check);
            window.removeEventListener('online', check);
            document.removeEventListener('visibilitychange', check);
            channel?.close();
        };
    }, [scope, storage]);
    const state = useSyncExternalStore(
        session?.subscribe ?? (() => () => {}),
        session?.snapshot ?? (() => null),
        () => null,
    );
    return { state, session, error };
}
