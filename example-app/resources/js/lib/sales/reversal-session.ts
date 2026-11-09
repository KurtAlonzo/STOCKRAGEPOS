import type { ContractError } from '@/types/business';
import type {
    CurrentReversalResult,
    ReversalAttemptResult,
    ReversalInput,
    SaleReversal,
} from '@/types/sales';
import type {
    ReversalRecord,
    ReversalScope,
    ReversalStorage,
} from './reversal-storage';
import {
    normalizeReversal,
    decodeCurrent,
    decodeAttempt,
} from './reversal-requests';
import { requestJson, PosRequestError, contractError } from '../pos/requests';
import { subscribePosMemory } from '../pos/auth-scope';
import { store, show } from '@/routes/sales/reversals';
import { show as attemptRoute } from '@/routes/sales/reversal-attempts';
export type ReversalTransport = {
    current: () => Promise<CurrentReversalResult>;
    lookup: (key: string) => Promise<ReversalAttemptResult>;
    post: (input: ReversalInput) => Promise<ReversalAttemptResult>;
};
export type ReversalState = {
    status: 'editing' | 'submitting' | 'unknown' | 'recorded' | 'rejected';
    sessionValid: boolean;
    busy: boolean;
    canRetry: boolean;
    reversal: SaleReversal | null;
    error: ContractError | null;
    message: string;
};
export function createReversalSession(
    scope: ReversalScope,
    storage: ReversalStorage,
    transport?: ReversalTransport,
) {
    const args = { current_team: scope.storeSlug, sale: scope.saleId };
    const network: ReversalTransport = transport ?? {
        current: async () =>
            decodeCurrent(
                await requestJson(
                    show(args).url,
                    { signal: AbortSignal.timeout(10000) },
                    scope,
                ),
                scope,
            ),
        lookup: async (key) =>
            decodeAttempt(
                await requestJson(
                    attemptRoute({ ...args, key }).url,
                    { signal: AbortSignal.timeout(10000) },
                    scope,
                ),
                scope,
                key,
            ),
        post: async (input) =>
            decodeAttempt(
                await requestJson(
                    store(args).url,
                    {
                        method: 'POST',
                        body: JSON.stringify(input),
                        signal: AbortSignal.timeout(10000),
                    },
                    scope,
                ),
                scope,
                input.idempotencyKey,
            ),
    };
    let state: ReversalState = {
        status: 'editing',
        sessionValid: false,
        busy: false,
        canRetry: false,
        reversal: null,
        error: null,
        message: '',
    };
    let disposed = false,
        invalidated = false,
        generation = 0,
        active = false,
        record: ReversalRecord | null = null;
    const listeners = new Set<() => void>();
    const emit = (next: Partial<ReversalState>) => {
        if (disposed) return;
        state = { ...state, ...next };
        listeners.forEach((fn) => fn());
    };
    const unsubscribe = subscribePosMemory(scope.userId, () => {
        invalidated = true;
        generation++;
        emit({
            sessionValid: false,
            busy: false,
            canRetry: false,
            reversal: null,
            message:
                'Session changed. Sign in as the original owner to check this refund.',
        });
    });
    const valid = (token: number) =>
        !disposed && !invalidated && token === generation;
    const online = () =>
        typeof navigator === 'undefined' || navigator.onLine !== false;
    async function recorded(reversal: SaleReversal, token: number) {
        if (!valid(token)) return;
        emit({
            status: 'recorded',
            reversal,
            sessionValid: true,
            canRetry: false,
            error: null,
            message: '',
        });
        if (record)
            try {
                await storage.clear(scope, record.request.idempotencyKey);
                record = null;
            } catch {
                if (valid(token))
                    emit({
                        message:
                            'Refund recorded. Local cleanup failed; keep this reference. Do not return money again.',
                    });
            }
    }
    async function reconcile(token: number, envelopeError?: ContractError) {
        const current = decodeCurrent(await network.current(), scope);
        if (!valid(token)) return;
        emit({ sessionValid: true });
        // Read storage even when another operation recorded this sale so cleanup
        // cannot permit an old unresolved operation to become editable.
        try {
            record = await storage.read(scope);
        } catch (error) {
            if (current.status === 'recorded') {
                await recorded(current.reversal, token);
                return;
            }
            throw error;
        }
        if (!valid(token)) return;
        if (current.status === 'recorded') {
            await recorded(current.reversal, token);
            return;
        }
        if (!record) {
            emit({
                status: 'editing',
                canRetry: false,
                error: null,
                message: '',
            });
            return;
        }
        const outcome = decodeAttempt(
            await network.lookup(record.request.idempotencyKey),
            scope,
            record.request.idempotencyKey,
        );
        if (!valid(token)) return;
        if (outcome.status === 'recorded') {
            await recorded(outcome.reversal, token);
            return;
        }
        if (
            outcome.status === 'rejected' ||
            (outcome.status === 'not_found' &&
                envelopeError?.code === 'validation_failed')
        ) {
            // Reread current sale after the terminal attempt lookup before edits.
            const latest = decodeCurrent(await network.current(), scope);
            if (!valid(token)) return;
            if (latest.status === 'recorded') {
                await recorded(latest.reversal, token);
                return;
            }
            await storage.clear(scope, record.request.idempotencyKey);
            if (!valid(token)) return;
            record = null;
            emit({
                status: 'rejected',
                error:
                    outcome.status === 'rejected'
                        ? outcome.error
                        : envelopeError!,
                canRetry: false,
                message:
                    'Refund was not recorded. Review the sale and correct the request.',
            });
            return;
        }
        emit({ status: 'unknown', canRetry: true, message: '' });
    }
    function failed(error: unknown, token: number) {
        if (!valid(token)) return;
        if (
            error instanceof PosRequestError &&
            (['session_changed'].includes(error.code) ||
                [401, 403, 419].includes(error.httpStatus))
        ) {
            invalidated = true;
            generation++;
            emit({
                sessionValid: false,
                reversal: null,
                canRetry: false,
                message:
                    'Session changed. Sign in as the original owner to check this refund.',
            });
            return;
        }
        emit({
            status: 'unknown',
            canRetry: false,
            message:
                error instanceof Error
                    ? error.message
                    : 'Unable to verify refund status.',
        });
    }
    async function check(restore = false) {
        if (active || disposed || invalidated) return;
        active = true;
        const token = ++generation;
        emit({
            busy: true,
            canRetry: false,
            ...(restore ? { sessionValid: false, reversal: null } : {}),
        });
        try {
            await reconcile(token);
        } catch (error) {
            failed(error, token);
        } finally {
            active = false;
            if (valid(token)) emit({ busy: false });
        }
    }
    async function postOriginal(token: number) {
        if (!record || !valid(token)) return;
        emit({
            status: 'submitting',
            busy: true,
            canRetry: false,
            error: null,
            message: '',
        });
        try {
            const outcome = decodeAttempt(
                await network.post(structuredClone(record.request)),
                scope,
                record.request.idempotencyKey,
            );
            if (!valid(token)) return;
            if (outcome.status === 'recorded')
                await recorded(outcome.reversal, token);
            else await reconcile(token);
        } catch (error) {
            failed(error, token);
            if (valid(token) && error instanceof PosRequestError)
                try {
                    await reconcile(token, contractError(error));
                } catch (readError) {
                    failed(readError, token);
                }
        }
    }
    return {
        snapshot: () => state,
        subscribe: (fn: () => void) => {
            listeners.add(fn);
            return () => {
                listeners.delete(fn);
            };
        },
        restore: () => check(true),
        check: () => check(),
        submit: async (input: ReversalInput) => {
            if (
                active ||
                !state.sessionValid ||
                invalidated ||
                disposed ||
                !online() ||
                !['editing', 'rejected'].includes(state.status)
            )
                return;
            active = true;
            const token = ++generation;
            emit({ busy: true, canRetry: false });
            try {
                const canonical = normalizeReversal(input);
                const current = decodeCurrent(await network.current(), scope);
                if (!valid(token)) return;
                if (current.status === 'recorded') {
                    await recorded(current.reversal, token);
                    return;
                }
                record = await storage.begin(scope, canonical);
                if (!valid(token)) return;
                await postOriginal(token);
            } catch (error) {
                failed(error, token);
            } finally {
                active = false;
                if (valid(token)) emit({ busy: false });
            }
        },
        retryOriginal: async () => {
            if (
                active ||
                !state.sessionValid ||
                !state.canRetry ||
                invalidated ||
                disposed ||
                !online()
            )
                return;
            active = true;
            const token = ++generation;
            emit({ busy: true, canRetry: false });
            try {
                await reconcile(token);
                if (
                    valid(token) &&
                    state.status === 'unknown' &&
                    state.canRetry
                )
                    await postOriginal(token);
            } catch (error) {
                failed(error, token);
            } finally {
                active = false;
                if (valid(token)) emit({ busy: false });
            }
        },
        dispose: () => {
            disposed = true;
            generation++;
            unsubscribe();
            listeners.clear();
        },
    };
}
