import type { ReversalInput } from '@/types/sales';
import { normalizeReversal } from './reversal-requests';
export type ReversalScope = {
    userId: number;
    storeId: number;
    storeSlug: string;
    saleId: number;
};
export type ReversalRecord = {
    schemaVersion: 1;
    scope: ReversalScope;
    request: ReversalInput;
};
export type ReversalStorage = {
    read: (scope: ReversalScope) => Promise<ReversalRecord | null>;
    begin: (
        scope: ReversalScope,
        request: ReversalInput,
    ) => Promise<ReversalRecord>;
    clear: (scope: ReversalScope, key: string) => Promise<void>;
};
const key = (scope: ReversalScope) =>
    `${scope.userId}:${scope.storeId}:${scope.saleId}`;
function validate(raw: unknown, scope: ReversalScope): ReversalRecord | null {
    if (raw === undefined) return null;
    const value = raw as ReversalRecord;
    try {
        if (
            value.schemaVersion !== 1 ||
            value.scope.userId !== scope.userId ||
            value.scope.storeId !== scope.storeId ||
            value.scope.saleId !== scope.saleId
        )
            throw new Error();
        normalizeReversal(value.request);
        return structuredClone(value);
    } catch {
        throw new Error(
            'Stored refund uncertainty is damaged. Keep this record and check the sale; a replacement is blocked.',
        );
    }
}
export function openReversalStorage(): Promise<ReversalStorage> {
    return new Promise((resolve, reject) => {
        const open = indexedDB.open('stockrage-sales', 1);
        open.onupgradeneeded = () => open.result.createObjectStore('reversals');
        open.onerror = () => reject(open.error);
        open.onblocked = () =>
            reject(
                new Error(
                    'Refund storage is blocked by another browser session.',
                ),
            );
        open.onsuccess = () => {
            const db = open.result;
            function transaction<T>(
                scope: ReversalScope,
                mode: IDBTransactionMode,
                change: (
                    store: IDBObjectStore,
                    current: ReversalRecord | null,
                ) => T,
            ): Promise<T> {
                return new Promise((done, fail) => {
                    const tx = db.transaction('reversals', mode),
                        store = tx.objectStore('reversals'),
                        get = store.get(key(scope));
                    let result: T, error: unknown;
                    get.onsuccess = () => {
                        try {
                            result = change(store, validate(get.result, scope));
                        } catch (e) {
                            error = e;
                            tx.abort();
                        }
                    };
                    tx.oncomplete = () => {
                        if (
                            mode === 'readwrite' &&
                            typeof window !== 'undefined' &&
                            typeof BroadcastChannel !== 'undefined'
                        ) {
                            const channel = new BroadcastChannel(
                                'stockrage-sales',
                            );
                            channel.postMessage({
                                userId: scope.userId,
                                storeId: scope.storeId,
                                saleId: scope.saleId,
                            });
                            channel.close();
                        }
                        done(result);
                    };
                    tx.onabort = tx.onerror = () =>
                        fail(
                            error ||
                                tx.error ||
                                new Error('Refund storage failed.'),
                        );
                });
            }
            resolve({
                read: (scope) =>
                    transaction(
                        scope,
                        'readonly',
                        (_store, current) => current,
                    ),
                begin: (scope, input) =>
                    transaction(scope, 'readwrite', (store, current) => {
                        const request = normalizeReversal(input);
                        if (current) {
                            if (
                                JSON.stringify(
                                    normalizeReversal(current.request),
                                ) !== JSON.stringify(request)
                            )
                                throw new Error(
                                    'Another refund request is unresolved for this sale. Check its original operation.',
                                );
                            return current;
                        }
                        const record: ReversalRecord = {
                            schemaVersion: 1,
                            scope: { ...scope },
                            request,
                        };
                        store.add(record, key(scope));
                        return structuredClone(record);
                    }),
                clear: (scope, expected) =>
                    transaction(scope, 'readwrite', (store, current) => {
                        if (
                            current &&
                            current.request.idempotencyKey !== expected
                        )
                            throw new Error(
                                'A different refund operation is stored.',
                            );
                        store.delete(key(scope));
                    }),
            });
        };
    });
}
