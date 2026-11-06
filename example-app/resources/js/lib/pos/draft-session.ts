import type {
    CartLine,
    PosDraftCatalog,
    PosDraftRecord,
    PosScope,
} from '@/types/pos';
import type { DraftStorage } from './draft-storage';
export function selectedSnapshots(
    lines: CartLine[],
    snapshots: PosDraftCatalog,
): PosDraftCatalog {
    const products = new Set(lines.map((x) => x.productId)),
        addons = new Set(lines.flatMap((x) => x.addonIds));
    const selected = [
        ...snapshots.products.filter((x) => products.has(x.id)),
        ...snapshots.addons.filter((x) => addons.has(x.id)),
    ];
    const inventory = new Set(
        selected.flatMap((x) =>
            'stockItemId' in x
                ? [x.stockItemId]
                : x.components.map((p) => p.inventoryItemId),
        ),
    );
    return {
        products: selected.filter((x) => x.kind !== 'addon'),
        addons: selected.filter((x) => x.kind === 'addon'),
        inventory: snapshots.inventory.filter((x) => inventory.has(x.id)),
        issues: snapshots.issues.filter((x) =>
            lines.some((l) => l.id === x.field),
        ),
    };
}
const plain = (v: unknown): v is Record<string, unknown> =>
    !!v && typeof v === 'object' && !Array.isArray(v);
const positive = (v: unknown) => Number.isSafeInteger(v) && Number(v) > 0;
function validLine(v: unknown): boolean {
    return (
        plain(v) &&
        typeof v.id === 'string' &&
        !!v.id &&
        positive(v.productId) &&
        positive(v.quantity) &&
        Number(v.quantity) <= 9007199254740 &&
        Array.isArray(v.addonIds) &&
        v.addonIds.every(positive) &&
        new Set(v.addonIds).size === v.addonIds.length
    );
}
function validRequest(v: unknown): boolean {
    if (
        !plain(v) ||
        typeof v.idempotencyKey !== 'string' ||
        !v.idempotencyKey ||
        typeof v.quoteId !== 'string' ||
        !v.quoteId ||
        typeof v.quoteRevision !== 'string' ||
        !v.quoteRevision ||
        !Array.isArray(v.lines) ||
        !v.lines.every(validLine) ||
        !Array.isArray(v.acknowledgedWarningIds) ||
        !v.acknowledgedWarningIds.every((x) => typeof x === 'string') ||
        !plain(v.payment)
    )
        return false;
    const p = v.payment;
    return p.method === 'cash'
        ? Number.isSafeInteger(p.tenderedMinor) && Number(p.tenderedMinor) >= 0
        : ['gcash', 'maya'].includes(String(p.method)) &&
              p.receivedConfirmed === true &&
              (p.reference === null || typeof p.reference === 'string');
}
function validSnapshot(v: unknown): boolean {
    return (
        plain(v) &&
        positive(v.id) &&
        typeof v.name === 'string' &&
        typeof v.sku === 'string' &&
        ['retail', 'food', 'addon'].includes(String(v.kind)) &&
        typeof v.active === 'boolean' &&
        Number.isSafeInteger(v.priceMinor) &&
        Number(v.priceMinor) >= 0 &&
        Array.isArray(v.allowedAddonIds) &&
        (positive(v.stockItemId) ||
            (Array.isArray(v.components) &&
                v.components.every(
                    (p) =>
                        plain(p) &&
                        positive(p.inventoryItemId) &&
                        typeof p.quantity === 'string' &&
                        /^\d+\.\d{3}$/.test(p.quantity),
                ))) &&
        plain(v.availability) &&
        Array.isArray(v.availability.issues)
    );
}
export function decodeDraft(value: unknown): PosDraftRecord {
    if (!value || typeof value !== 'object')
        throw new Error(
            'Saved draft is unreadable. Recovery data has been retained.',
        );
    const r = value as PosDraftRecord;
    if (
        (!plain(r.attempt) && r.attempt !== null) ||
        (!plain(r.terminal) && r.terminal !== null) ||
        (r.attempt !== null && r.terminal !== null)
    )
        throw new Error(
            'Corrupt operation marker. Recovery data has been retained.',
        );
    if (
        r.schemaVersion !== 1 ||
        !r.scope ||
        !Number.isSafeInteger(r.scope.userId) ||
        !Number.isSafeInteger(r.scope.storeId) ||
        typeof r.scope.storeSlug !== 'string' ||
        typeof r.draftId !== 'string' ||
        !Number.isSafeInteger(r.revision) ||
        r.revision < 1 ||
        !r.owner ||
        typeof r.owner.tabId !== 'string' ||
        !Number.isSafeInteger(r.owner.epoch) ||
        !Array.isArray(r.lines) ||
        !r.quantityBuffers ||
        !r.snapshots ||
        !Array.isArray(r.snapshots.products) ||
        !Array.isArray(r.snapshots.addons) ||
        !Array.isArray(r.snapshots.inventory) ||
        !Array.isArray(r.snapshots.issues) ||
        r.attempt === undefined ||
        r.terminal === undefined
    )
        throw new Error(
            'Unsupported or corrupt draft. Recovery data has been retained; do not collect again.',
        );
    if (
        r.lines.some(
            (l) =>
                !l ||
                typeof l.id !== 'string' ||
                !Number.isSafeInteger(l.productId) ||
                !Number.isSafeInteger(l.quantity) ||
                l.quantity < 1 ||
                !Array.isArray(l.addonIds) ||
                l.addonIds.some((a) => !Number.isSafeInteger(a)),
        ) ||
        Object.values(r.quantityBuffers).some((x) => typeof x !== 'string')
    )
        throw new Error(
            'Saved cart is corrupt. Recovery data has been retained.',
        );
    if (
        r.attempt &&
        (r.attempt.userId !== r.scope.userId ||
            r.attempt.storeId !== r.scope.storeId ||
            r.attempt.draftId !== r.draftId ||
            !['submitting', 'unknown', 'rejected'].includes(r.attempt.state) ||
            !r.attempt.idempotencyKey)
    )
        throw new Error(
            'Saved operation is corrupt. Do not discard or collect again.',
        );
    if (
        r.attempt &&
        (r.attempt.state === 'submitting' || r.attempt.state === 'unknown') &&
        (!validRequest(r.attempt.originalRequest) ||
            r.attempt.originalRequest.idempotencyKey !==
                r.attempt.idempotencyKey ||
            !Array.isArray(r.attempt.originalRequest.lines))
    )
        throw new Error(
            'Saved submission is corrupt. Recovery data has been retained.',
        );
    if (
        r.terminal &&
        (!Number.isSafeInteger(r.terminal.saleId) || !r.terminal.idempotencyKey)
    )
        throw new Error(
            'Saved confirmation is corrupt. Recovery data has been retained.',
        );
    if (
        !r.snapshots.products.every(validSnapshot) ||
        !r.snapshots.addons.every(validSnapshot) ||
        !r.snapshots.inventory.every(
            (x) =>
                plain(x) &&
                positive(x.id) &&
                typeof x.name === 'string' &&
                typeof x.balance === 'string',
        ) ||
        !r.snapshots.issues.every(
            (x) =>
                plain(x) &&
                typeof x.id === 'string' &&
                typeof x.code === 'string' &&
                typeof x.message === 'string',
        ) ||
        !plain(r.quantityBuffers) ||
        new Set(r.lines.map((l) => l.id)).size !== r.lines.length
    )
        throw new Error(
            'Saved display data is corrupt. Recovery data has been retained.',
        );
    if (
        r.attempt?.state === 'rejected' &&
        (!plain(r.attempt.error) ||
            typeof r.attempt.error.code !== 'string' ||
            typeof r.attempt.error.message !== 'string' ||
            !plain(r.attempt.error.fieldErrors) ||
            !Array.isArray(r.attempt.error.issues))
    )
        throw new Error(
            'Saved rejection is corrupt. Recovery data has been retained.',
        );
    return r;
}
export const unresolved = (r: PosDraftRecord) =>
    r.attempt?.state === 'submitting' || r.attempt?.state === 'unknown';
export class CorruptDraftError extends Error {
    constructor(
        message: string,
        readonly editable: Pick<PosDraftRecord, 'owner' | 'revision'> | null,
    ) {
        super(message);
    }
}
export function createDraftSession(
    scope: PosScope,
    tabId: string,
    storage: DraftStorage,
) {
    const listeners = new Set<() => void>();
    let disposed = false;
    let state: {
        record: PosDraftRecord | null;
        status:
            | 'loading'
            | 'saved'
            | 'saving'
            | 'storage-error'
            | 'read-only'
            | 'recovering';
        error: string;
        discardable: Pick<PosDraftRecord, 'owner' | 'revision'> | null;
    } = { record: null, status: 'loading', error: '', discardable: null };
    const emit = (next: typeof state) => {
        if (disposed) return;
        state = next;
        listeners.forEach((l) => l());
    };
    const accept = (record: PosDraftRecord) =>
        emit({
            record,
            status: unresolved(record)
                ? 'recovering'
                : record.owner.tabId === tabId
                  ? 'saved'
                  : 'read-only',
            error: '',
            discardable: null,
        });
    const run = async (operation: () => Promise<PosDraftRecord>) => {
        try {
            accept(await operation());
        } catch (e) {
            emit({
                ...state,
                status: 'storage-error',
                error: e instanceof Error ? e.message : 'Unable to save draft.',
                discardable: e instanceof CorruptDraftError ? e.editable : null,
            });
            throw e;
        }
    };
    let channel: BroadcastChannel | null = null;
    const restore = () => {
        disposed = false;
        if (!channel && typeof BroadcastChannel !== 'undefined') {
            channel = new BroadcastChannel('stockrage-pos-drafts');
            channel.onmessage = reload;
        }
        return run(
            async () =>
                (await storage.read(scope)) ??
                (await storage.create(scope, tabId)),
        );
    };
    const reload = () => {
        void restore().catch(() => {});
    };

    return {
        subscribe: (l: () => void) => {
            listeners.add(l);
            return () => {
                listeners.delete(l);
            };
        },
        snapshot: () => state,
        restore,
        saveCart: async (
            cart: Pick<
                PosDraftRecord,
                'lines' | 'quantityBuffers' | 'snapshots'
            >,
        ) => {
            const r = state.record;
            if (!r) throw new Error('Draft not restored.');
            if (r.owner.tabId !== tabId)
                throw new Error(
                    'Another tab owns this draft. Take over explicitly before saving.',
                );
            emit({ ...state, status: 'saving' });
            await run(() => storage.save(scope, r.owner, r.revision, cart));
            channel?.postMessage(scope);
        },
        takeover: async () => {
            const r = state.record;
            if (!r) return;
            await run(() => storage.takeover(scope, tabId, r.revision));
            channel?.postMessage(scope);
        },
        dispose: () => {
            disposed = true;
            channel?.close();
            channel = null;
            listeners.clear();
        },
    };
}
