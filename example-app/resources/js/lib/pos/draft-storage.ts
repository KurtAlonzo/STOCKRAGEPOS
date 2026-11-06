import type {
    AttemptLookupResult,
    CheckoutCompletionInput,
    DraftOwner,
    PosDraftRecord,
    PosScope,
} from '@/types/pos';
import {
    decodeDraft,
    selectedSnapshots,
    unresolved,
    CorruptDraftError,
} from './draft-session';
export class DraftConflict extends Error {}
export type DraftCart = Pick<
    PosDraftRecord,
    'lines' | 'quantityBuffers' | 'snapshots'
>;
export interface DraftStorage {
    read(scope: PosScope): Promise<PosDraftRecord | null>;
    create(scope: PosScope, tabId: string): Promise<PosDraftRecord>;
    takeover(
        scope: PosScope,
        tabId: string,
        expectedRevision: number,
    ): Promise<PosDraftRecord>;
    save(
        scope: PosScope,
        owner: DraftOwner,
        expectedRevision: number,
        cart: DraftCart,
    ): Promise<PosDraftRecord>;
    begin(
        scope: PosScope,
        owner: DraftOwner,
        expectedRevision: number,
        request: CheckoutCompletionInput,
    ): Promise<PosDraftRecord>;
    reconcile(
        scope: PosScope,
        key: string,
        result: AttemptLookupResult,
    ): Promise<PosDraftRecord>;
    newSale(
        scope: PosScope,
        expectedRevision: number,
        tabId: string,
    ): Promise<PosDraftRecord>;
    discard(
        scope: PosScope,
        owner: DraftOwner,
        expectedRevision: number,
    ): Promise<void>;
}
const key = (scope: PosScope) => `${scope.userId}:${scope.storeId}`;
const fresh = (scope: PosScope, tabId: string): PosDraftRecord => ({
    schemaVersion: 1,
    scope,
    draftId: crypto.randomUUID(),
    revision: 1,
    owner: { tabId, epoch: 1 },
    lines: [],
    quantityBuffers: {},
    snapshots: { products: [], addons: [], inventory: [], issues: [] },
    attempt: null,
    terminal: null,
});
function editable(r: PosDraftRecord) {
    if (unresolved(r) || r.terminal)
        throw new DraftConflict('This sale must be reconciled before editing.');
}
function owns(r: PosDraftRecord, owner: DraftOwner, revision: number) {
    if (
        r.revision !== revision ||
        r.owner.tabId !== owner.tabId ||
        r.owner.epoch !== owner.epoch
    )
        throw new DraftConflict(
            'Draft changed in another tab. Reload or take over explicitly.',
        );
}
export function openDraftStorage(): Promise<DraftStorage> {
    return new Promise((resolve, reject) => {
        const open = indexedDB.open('stockrage-pos', 1);
        open.onupgradeneeded = () => open.result.createObjectStore('drafts');
        open.onerror = () =>
            reject(
                new Error(
                    'Unable to open local draft storage. Checkout is disabled.',
                ),
            );
        open.onblocked = () =>
            reject(new Error('Close older POS tabs to open draft storage.'));
        open.onsuccess = () => {
            const db = open.result;
            db.onversionchange = () => db.close();
            const transact = <T>(
                scope: PosScope,
                write: boolean,
                fn: (record: PosDraftRecord | null) => {
                    result: T;
                    record?: PosDraftRecord;
                    remove?: boolean;
                },
                allowCorruptEditable = false,
            ): Promise<T> =>
                new Promise((done, fail) => {
                    const tx = db.transaction(
                            'drafts',
                            write ? 'readwrite' : 'readonly',
                        ),
                        store = tx.objectStore('drafts');
                    let result: T;
                    const get = store.get(key(scope));
                    let failure: unknown;
                    get.onsuccess = () => {
                        try {
                            let record: PosDraftRecord | null = null;
                            if (get.result !== undefined) {
                                try {
                                    record = decodeDraft(get.result);
                                } catch (error) {
                                    const raw = get.result;
                                    const safe =
                                        raw &&
                                        typeof raw === 'object' &&
                                        !Array.isArray(raw) &&
                                        raw.schemaVersion === 1 &&
                                        raw.attempt === null &&
                                        raw.terminal === null &&
                                        raw.scope?.userId === scope.userId &&
                                        raw.scope?.storeId === scope.storeId &&
                                        typeof raw.draftId === 'string' &&
                                        Number.isSafeInteger(raw.revision) &&
                                        raw.revision > 0 &&
                                        typeof raw.owner?.tabId === 'string' &&
                                        Number.isSafeInteger(
                                            raw.owner?.epoch,
                                        ) &&
                                        raw.owner.epoch > 0;
                                    const editable = safe
                                        ? {
                                              owner: {
                                                  tabId: raw.owner.tabId,
                                                  epoch: raw.owner.epoch,
                                              },
                                              revision: raw.revision,
                                          }
                                        : null;
                                    if (!allowCorruptEditable || !editable)
                                        throw new CorruptDraftError(
                                            error instanceof Error
                                                ? error.message
                                                : 'Saved draft is corrupt.',
                                            editable,
                                        );
                                    record = {
                                        ...fresh(scope, editable.owner.tabId),
                                        owner: editable.owner,
                                        revision: editable.revision,
                                        draftId: raw.draftId,
                                    };
                                }
                            }
                            if (
                                record &&
                                (record.scope.userId !== scope.userId ||
                                    record.scope.storeId !== scope.storeId)
                            )
                                throw new DraftConflict(
                                    'Stored scope mismatch.',
                                );
                            const outcome = fn(record);
                            result = outcome.result;
                            if (outcome.remove) store.delete(key(scope));
                            else if (outcome.record)
                                store.put(outcome.record, key(scope));
                        } catch (e) {
                            failure = e;
                            tx.abort();
                        }
                    };
                    tx.oncomplete = () => done(result);
                    tx.onabort = tx.onerror = () =>
                        fail(
                            failure ??
                                new Error(
                                    'Draft storage failed. The previous recovery record was retained.',
                                ),
                        );
                });
            const requireRecord = (r: PosDraftRecord | null) => {
                if (!r) throw new DraftConflict('Draft was removed. Reload.');
                return r;
            };
            const update = (
                scope: PosScope,
                fn: (r: PosDraftRecord) => PosDraftRecord,
            ) =>
                transact(scope, true, (r) => {
                    const original = requireRecord(r);
                    const record = fn(original);
                    if (record === original) return { result: record };
                    record.revision++;
                    return { result: record, record };
                });
            resolve({
                read: (scope) => transact(scope, false, (r) => ({ result: r })),
                create: (scope, tabId) =>
                    transact(scope, true, (r) => {
                        const record = r ?? fresh(scope, tabId);
                        return { result: record, record };
                    }),
                takeover: (scope, tabId, revision) =>
                    update(scope, (r) => {
                        editable(r);
                        if (r.revision !== revision)
                            throw new DraftConflict(
                                'Draft changed. Reload before taking over.',
                            );
                        return {
                            ...r,
                            owner: { tabId, epoch: r.owner.epoch + 1 },
                        };
                    }),
                save: (scope, owner, revision, cart) =>
                    update(scope, (r) => {
                        editable(r);
                        owns(r, owner, revision);
                        return {
                            ...r,
                            lines: cart.lines,
                            quantityBuffers: cart.quantityBuffers,
                            snapshots: selectedSnapshots(
                                cart.lines,
                                cart.snapshots,
                            ),
                            attempt: null,
                        };
                    }),
                begin: (scope, owner, revision, request) =>
                    update(scope, (r) => {
                        editable(r);
                        owns(r, owner, revision);
                        if (
                            JSON.stringify(r.lines) !==
                            JSON.stringify(request.lines)
                        )
                            throw new DraftConflict(
                                'Save this exact cart before submitting.',
                            );
                        return {
                            ...r,
                            attempt: {
                                state: 'submitting',
                                idempotencyKey: request.idempotencyKey,
                                draftId: r.draftId,
                                userId: scope.userId,
                                storeId: scope.storeId,
                                originalRequest: structuredClone(request),
                            },
                        };
                    }),
                reconcile: (scope, operationKey, result) =>
                    update(scope, (r) => {
                        const savedKey =
                            r.attempt?.idempotencyKey ??
                            r.terminal?.idempotencyKey;
                        if (
                            savedKey !== operationKey ||
                            ('idempotencyKey' in result &&
                                result.idempotencyKey !== operationKey)
                        )
                            throw new DraftConflict(
                                'Recovery operation mismatch.',
                            );
                        if (result.status === 'confirmed') {
                            if (
                                result.sale.storeId !== scope.storeId ||
                                result.sale.actorId !== scope.userId
                            )
                                throw new DraftConflict('Sale scope mismatch.');
                            if (r.terminal?.saleId === result.sale.id) return r;
                            return {
                                ...r,
                                lines: [],
                                quantityBuffers: {},
                                snapshots: {
                                    products: [],
                                    addons: [],
                                    inventory: [],
                                    issues: [],
                                },
                                attempt: null,
                                terminal: {
                                    idempotencyKey: operationKey,
                                    saleId: result.sale.id,
                                },
                            };
                        }
                        if (result.status === 'rejected' && !r.terminal)
                            return {
                                ...r,
                                attempt: {
                                    state: 'rejected',
                                    idempotencyKey: operationKey,
                                    draftId: r.draftId,
                                    userId: scope.userId,
                                    storeId: scope.storeId,
                                    error: result.error,
                                },
                            };
                        return r;
                    }),
                newSale: (scope, revision, tabId) =>
                    update(scope, (r) => {
                        if (!r.terminal || r.revision !== revision)
                            throw new DraftConflict(
                                'Save verified confirmation before starting a new sale.',
                            );
                        return { ...fresh(scope, tabId), revision: r.revision };
                    }),
                discard: (scope, owner, revision) =>
                    transact(
                        scope,
                        true,
                        (r) => {
                            const record = requireRecord(r);
                            editable(record);
                            owns(record, owner, revision);
                            return { result: undefined, remove: true };
                        },
                        true,
                    ),
            });
        };
    });
}
