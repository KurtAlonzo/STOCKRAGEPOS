import { useEffect, useMemo, useState, useSyncExternalStore } from 'react';
import type { PosScope } from '@/types/pos';
import { openDraftStorage } from '@/lib/pos/draft-storage';
import type { DraftStorage } from '@/lib/pos/draft-storage';
import { createDraftSession } from '@/lib/pos/draft-session';
export function usePosDraft(scope: PosScope) {
    const [tabId] = useState(() => crypto.randomUUID());
    const [storage, setStorage] = useState<DraftStorage | null>(null),
        [error, setError] = useState('');
    useEffect(() => {
        let active = true;
        void openDraftStorage()
            .then((s) => {
                if (active) setStorage(s);
            })
            .catch((e) => {
                if (active) setError(String(e));
            });
        return () => {
            active = false;
        };
    }, []);
    const session = useMemo(
        () => (storage ? createDraftSession(scope, tabId, storage) : null),
        [scope, tabId, storage],
    );
    useEffect(() => {
        if (!session) return;
        void session.restore().catch(() => {});
        const reload = () => {
            void session.restore().catch(() => {});
        };
        window.addEventListener('focus', reload);
        return () => {
            window.removeEventListener('focus', reload);
            session.dispose();
        };
    }, [session]);
    const fallback = useMemo(
        () => ({
            record: null,
            status: 'loading' as const,
            error,
            discardable: null,
        }),
        [error],
    );
    const state = useSyncExternalStore(
        session?.subscribe ?? (() => () => {}),
        session?.snapshot ?? (() => fallback),
        () => fallback,
    );
    return { ...state, error: state.error || error, storage, session, tabId };
}
