import type {
    AttemptLookupResult,
    CheckoutCompletionInput,
    CheckoutCompletionResult,
    DraftOwner,
    PosScope,
} from '@/types/pos';
import type { ContractError } from '@/types/business';
import type { Sale } from '@/types/sales';
import type { DraftStorage } from './draft-storage';
import { PosRequestError, contractError } from './requests';
import { subscribePosMemory } from './auth-scope';
export type CheckoutTransport = {
    complete: (
        input: CheckoutCompletionInput,
        signal?: AbortSignal,
    ) => Promise<CheckoutCompletionResult>;
    lookup: (key: string, signal?: AbortSignal) => Promise<AttemptLookupResult>;
};
export type CheckoutClock = {
    now: () => number;
    setTimeout: (
        callback: () => void,
        delay: number,
    ) => ReturnType<typeof setTimeout>;
    clearTimeout: (id: ReturnType<typeof setTimeout>) => void;
    visible: () => boolean;
    online: () => boolean;
};
const defaultClock: CheckoutClock = {
    now: () => Date.now(),
    setTimeout: (callback, delay) => setTimeout(callback, delay),
    clearTimeout: (id) => clearTimeout(id),
    visible: () =>
        typeof document === 'undefined' ||
        document.visibilityState === 'visible',
    online: () =>
        typeof navigator === 'undefined' || navigator.onLine !== false,
};
export type CheckoutState = {
    state: 'draft' | 'submitting' | 'unknown' | 'confirmed' | 'rejected';
    sale: Sale | null;
    error: ContractError | null;
    message: string;
    lookup: AttemptLookupResult['status'] | null;
    busy: boolean;
    durable: boolean;
    sessionValid: boolean;
};
export function createCheckoutSession(
    scope: PosScope,
    storage: DraftStorage,
    transport: CheckoutTransport,
    clock: CheckoutClock = defaultClock,
) {
    const listeners = new Set<() => void>();
    let disposed = false,
        checking = false,
        posting = false,
        generation = 0;
    let timer: ReturnType<typeof setTimeout> | undefined,
        controller: AbortController | null = null;
    let state: CheckoutState = {
        state: 'draft',
        sale: null,
        error: null,
        message: '',
        lookup: null,
        busy: false,
        durable: false,
        sessionValid: true,
    };
    const emit = (next: Partial<CheckoutState>) => {
        if (disposed) return;
        state = { ...state, ...next };
        listeners.forEach((l) => l());
    };
    const stop = () => {
        generation++;
        if (timer) clock.clearTimeout(timer);
        timer = undefined;
        controller?.abort();
    };
    const invalidate = () => {
        stop();
        emit({
            sessionValid: false,
            sale: null,
            message:
                'Session changed. Sign in as the original owner to recover this sale.',
            busy: false,
        });
    };
    const unsubscribe = subscribePosMemory(scope.userId, invalidate);
    async function timeout<T>(
        fn: (signal: AbortSignal) => Promise<T>,
    ): Promise<T> {
        controller = new AbortController();
        let timeoutId: ReturnType<typeof setTimeout> | undefined;
        try {
            return await Promise.race([
                fn(controller.signal),
                new Promise<T>((_, reject) => {
                    timeoutId = clock.setTimeout(() => {
                        controller?.abort();
                        reject(new Error('Status request timed out.'));
                    }, 10000);
                }),
            ]);
        } finally {
            if (timeoutId) clock.clearTimeout(timeoutId);
        }
    }
    async function apply(key: string, result: AttemptLookupResult) {
        if ('idempotencyKey' in result && result.idempotencyKey !== key)
            throw new Error('Operation identity mismatch.');
        if (result.status === 'confirmed') {
            if (
                result.sale.actorId !== scope.userId ||
                result.sale.storeId !== scope.storeId
            )
                throw new Error('Sale scope mismatch.');
            stop();
            emit({
                state: 'confirmed',
                sale: result.sale,
                lookup: 'confirmed',
                busy: false,
            });
            try {
                await storage.reconcile(scope, key, result);
                emit({ durable: true, message: '' });
            } catch (e) {
                emit({
                    durable: false,
                    message: `Sale confirmed, but local confirmation could not be saved. Keep this reference and check again. ${String(e)}`,
                });
            }
        } else if (result.status === 'rejected') {
            await storage.reconcile(scope, key, result);
            stop();
            emit({
                state: 'rejected',
                error: result.error,
                lookup: 'rejected',
                durable: true,
                busy: false,
            });
        } else emit({ state: 'unknown', lookup: result.status, busy: false });
    }
    async function checkAgain() {
        if (
            disposed ||
            !state.sessionValid ||
            checking ||
            posting ||
            !clock.online() ||
            !clock.visible()
        )
            return;
        checking = true;
        emit({ busy: true });
        const captured = generation;
        try {
            const r = await storage.read(scope);
            const key =
                r?.attempt?.idempotencyKey ?? r?.terminal?.idempotencyKey;
            if (!key) return;
            const result = await timeout((signal) =>
                transport.lookup(key, signal),
            );
            if (captured !== generation || disposed) return;
            await apply(key, result);
        } catch (e) {
            if (captured !== generation || disposed) return;
            if (
                e instanceof PosRequestError &&
                (['session_changed'].includes(e.code) ||
                    [401, 403, 419].includes(e.httpStatus))
            )
                invalidate();
            else
                emit({
                    message:
                        e instanceof Error
                            ? e.message
                            : 'Unable to check status.',
                    busy: false,
                });
        } finally {
            checking = false;
            emit({ busy: false });
        }
    }
    async function postOriginal() {
        if (
            posting ||
            checking ||
            disposed ||
            !state.sessionValid ||
            !clock.online()
        )
            return;
        const r = await storage.read(scope),
            attempt = r?.attempt;
        if (
            !attempt ||
            (attempt.state !== 'submitting' && attempt.state !== 'unknown')
        )
            return;
        posting = true;
        emit({ state: 'submitting', busy: true, error: null, message: '' });
        const captured = generation;
        try {
            const result = await timeout((signal) =>
                transport.complete(attempt.originalRequest, signal),
            );
            if (captured !== generation || disposed) return;
            await apply(attempt.idempotencyKey, result);
        } catch (e) {
            if (captured !== generation || disposed) return;
            if (
                e instanceof PosRequestError &&
                (['session_changed'].includes(e.code) ||
                    [401, 403, 419].includes(e.httpStatus))
            ) {
                emit({ state: 'unknown' });
                invalidate();
            } else if (
                e instanceof PosRequestError &&
                [
                    'quote_changed',
                    'quote_expired',
                    'stock_conflict',
                    'validation_failed',
                ].includes(e.code) &&
                [409, 422].includes(e.httpStatus)
            ) {
                await apply(attempt.idempotencyKey, {
                    status: 'rejected',
                    idempotencyKey: attempt.idempotencyKey,
                    error: contractError(e),
                });
            } else
                emit({
                    state: 'unknown',
                    message:
                        e instanceof Error
                            ? e.message
                            : 'Response lost. Check status.',
                    busy: false,
                });
        } finally {
            posting = false;
            emit({ busy: false });
        }
    }
    function recover() {
        if (
            disposed ||
            !state.sessionValid ||
            state.state === 'rejected' ||
            (state.state === 'confirmed' && state.durable)
        )
            return;
        stop();
        const started = clock.now(),
            captured = generation;
        const step = async () => {
            if (
                captured !== generation ||
                disposed ||
                !state.sessionValid ||
                !clock.online() ||
                !clock.visible()
            )
                return;
            await checkAgain();
            if (
                captured !== generation ||
                state.state === 'confirmed' ||
                state.state === 'rejected'
            )
                return;
            const next = [2000, 5000, 10000, 20000].find(
                (slot) => slot > clock.now() - started,
            );
            if (next !== undefined)
                timer = clock.setTimeout(
                    () => {
                        void step();
                    },
                    next - (clock.now() - started),
                );
        };
        void step();
    }
    return {
        subscribe: (l: () => void) => {
            listeners.add(l);
            return () => {
                listeners.delete(l);
            };
        },
        snapshot: () => state,
        submit: async (
            request: CheckoutCompletionInput,
            owner: DraftOwner,
            revision: number,
        ) => {
            if (!clock.online() || !state.sessionValid || posting) return;
            try {
                await storage.begin(scope, owner, revision, request);
            } catch (e) {
                emit({ message: String(e), state: 'draft' });
                return;
            }
            await postOriginal();
        },
        restore: async () => {
            const r = await storage.read(scope);
            if (r?.terminal) {
                emit({ state: 'unknown' });
                recover();
            } else if (
                r?.attempt &&
                (r.attempt.state === 'submitting' ||
                    r.attempt.state === 'unknown')
            ) {
                emit({ state: 'unknown' });
                recover();
            } else if (r?.attempt?.state === 'rejected')
                emit({ state: 'rejected', error: r.attempt.error });
        },
        recover,
        checkAgain,
        retryOriginal: postOriginal,
        dispose: () => {
            stop();
            disposed = true;
            unsubscribe();
            listeners.clear();
        },
    };
}
