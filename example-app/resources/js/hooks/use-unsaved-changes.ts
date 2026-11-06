import { createElement, useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { historyIndex, registerHistoryGuard } from '@/lib/navigation-history';
import { UnsavedChangesPrompt } from '@/components/unsaved-changes-prompt';

export function useUnsavedChanges(dirty: boolean, submitting = false) {
    const current = useRef({ dirty, submitting });
    const ownSubmission = useRef(false);
    const [pending, setPending] = useState<{ proceed: () => void } | null>(
        null,
    );
    useEffect(() => {
        current.current = { dirty, submitting };
    }, [dirty, submitting]);
    useEffect(() => {
        let index = historyIndex(window.history.state),
            restoring = false,
            allowPop = false;
        let afterRestore: (() => void) | null = null;
        const removeNavigate = router.on('navigate', () => {
            index = historyIndex(window.history.state);
        });
        const pop = (event: PopStateEvent) => {
            if (restoring) {
                restoring = false;
                event.stopImmediatePropagation();
                afterRestore?.();
                afterRestore = null;
                return;
            }
            if (allowPop) {
                allowPop = false;
                return;
            }
            const next = historyIndex(event.state);
            if (
                current.current.dirty &&
                !current.current.submitting &&
                index !== null &&
                next !== null &&
                next !== index
            ) {
                event.stopImmediatePropagation();
                const delta = next - index;
                restoring = true;
                window.history.go(-delta);
                setPending({
                    proceed: () => {
                        const go = () => {
                            allowPop = true;
                            window.history.go(delta);
                        };
                        if (restoring) afterRestore = go;
                        else go();
                    },
                });
            }
        };
        const removeHistoryGuard = registerHistoryGuard(pop);
        const remove = router.on('before', (event) => {
            if (ownSubmission.current) {
                ownSubmission.current = false;
                return;
            }
            if (current.current.dirty && !current.current.submitting) {
                const visit = event.detail.visit;
                setPending({
                    proceed: () => {
                        ownSubmission.current = true;
                        router.visit(visit.url, visit);
                    },
                });
                return false;
            }
        });
        const unload = (event: BeforeUnloadEvent) => {
            if (current.current.dirty && !current.current.submitting)
                event.preventDefault();
        };
        window.addEventListener('beforeunload', unload);
        return () => {
            remove();
            removeNavigate();
            removeHistoryGuard();
            window.removeEventListener('beforeunload', unload);
        };
    }, []);
    const mayClose = (proceed: () => void) => {
        if (!dirty) return true;
        setPending({ proceed });
        return false;
    };
    mayClose.beforeSubmit = () => {
        ownSubmission.current = true;
    };
    mayClose.dialog = createElement(UnsavedChangesPrompt, {
        open: pending !== null,
        keep: () => setPending(null),
        discard: () => {
            const proceed = pending?.proceed;
            setPending(null);
            proceed?.();
        },
    });
    return mayClose;
}
