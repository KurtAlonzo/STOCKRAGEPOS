import type {
    AttemptLookupResult,
    CheckoutCompletionResult,
    PosScope,
} from '@/types/pos';
import type { Sale } from '@/types/sales';
import { requestJson, validateQuote } from './requests';
const record = (v: unknown): v is Record<string, unknown> =>
    !!v && typeof v === 'object' && !Array.isArray(v);
const money = (v: unknown): v is number =>
    Number.isSafeInteger(v) && Number(v) >= 0;
const id = (v: unknown): v is number => money(v) && v > 0;
function sale(value: unknown): value is Sale {
    if (
        !record(value) ||
        !id(value.id) ||
        typeof value.reference !== 'string' ||
        !/^SR-\d{8,}$/.test(value.reference) ||
        !id(value.storeId) ||
        !id(value.actorId) ||
        !['completed', 'reversed'].includes(String(value.status)) ||
        value.currency !== 'PHP' ||
        typeof value.occurredAt !== 'string' ||
        !Number.isFinite(Date.parse(value.occurredAt)) ||
        !record(value.payment) ||
        !id(value.payment.id) ||
        !money(value.payment.receivedMinor) ||
        value.payment.receivedMinor !== value.totalMinor ||
        !money(value.payment.changeMinor) ||
        typeof value.payment.recordedAt !== 'string' ||
        !Number.isFinite(Date.parse(value.payment.recordedAt)) ||
        !record(value.payment.input) ||
        !Array.isArray(value.lines) ||
        !value.lines.length ||
        !Array.isArray(value.stockMovements) ||
        value.reversal !== null
    )
        return false;
    const input = value.payment.input;
    if (input.method === 'cash') {
        if (
            !money(input.tenderedMinor) ||
            input.tenderedMinor - value.payment.receivedMinor !==
                value.payment.changeMinor
        )
            return false;
    } else if (
        !['gcash', 'maya'].includes(String(input.method)) ||
        input.receivedConfirmed !== true ||
        (input.reference !== null && typeof input.reference !== 'string') ||
        value.payment.changeMinor !== 0
    )
        return false;
    const lines = value.lines.map((x: Record<string, unknown>) => ({
        id: String(x.lineId),
        productId: Number(x.productId),
        quantity: Number(x.quantity),
        addonIds: Array.isArray(x.addons)
            ? x.addons.map((a) => Number(a.id))
            : [],
    }));
    if (lines.some((l) => !id(l.productId) || !id(l.quantity))) return false;
    const quote = {
        ...value,
        id: 'sale',
        revision: 'sale',
        expiresAt: value.occurredAt,
        business: {
            storeId: value.storeId,
            storeSlug: 'sale',
            currency: 'PHP',
            timezone: 'Asia/Manila',
            taxTreatment: 'unconfigured',
        },
        payment: null,
        changeMinor: null,
        catalogRevisions: {},
        recipeRevisions: {},
        blockingIssues: [],
        warnings: [],
        stockEffects: [],
    };
    return (
        validateQuote(quote, lines, value.storeId) &&
        value.stockMovements.every(
            (x) =>
                record(x) &&
                id(x.id) &&
                id(x.inventoryItemId) &&
                x.sourceId === value.id &&
                x.actorId === value.actorId &&
                x.kind === 'sale' &&
                x.sourceType === 'sale' &&
                typeof x.itemName === 'string' &&
                typeof x.actorName === 'string' &&
                typeof x.reason === 'string' &&
                typeof x.occurredAt === 'string' &&
                Number.isFinite(Date.parse(x.occurredAt)) &&
                ['each', 'g', 'ml'].includes(String(x.unit)) &&
                ['before', 'delta', 'after'].every(
                    (k) =>
                        typeof x[k] === 'string' &&
                        /^-?\d+\.\d{3}$/.test(x[k] as string),
                ),
        )
    );
}
export function decodeLookup(body: unknown): AttemptLookupResult {
    if (!record(body)) throw new Error('Malformed checkout status.');
    if (body.status === 'not_found') return { status: 'not_found' };
    if (typeof body.idempotencyKey !== 'string' || !body.idempotencyKey)
        throw new Error('Missing operation identity.');
    if (body.status === 'pending') return body as AttemptLookupResult;
    if (body.status === 'confirmed' && sale(body.sale))
        return body as AttemptLookupResult;
    if (
        body.status === 'rejected' &&
        record(body.error) &&
        typeof body.error.code === 'string' &&
        typeof body.error.message === 'string' &&
        record(body.error.fieldErrors) &&
        Array.isArray(body.error.issues)
    )
        return body as AttemptLookupResult;
    throw new Error('Malformed checkout outcome. Keep the original operation.');
}
export function decodeCompletion(body: unknown): CheckoutCompletionResult {
    const result = decodeLookup(body);
    if (result.status !== 'confirmed' && result.status !== 'pending')
        throw new Error('Malformed completion response.');
    return result;
}
export async function requestPosJson<T>(
    url: string,
    init: RequestInit,
    scope: PosScope,
    decode: (body: unknown) => T,
): Promise<T> {
    const body = await requestJson<unknown>(url, init, scope);
    const result = decode(body);
    if (
        record(result) &&
        result.status === 'confirmed' &&
        record(result.sale) &&
        (result.sale.storeId !== scope.storeId ||
            result.sale.actorId !== scope.userId)
    )
        throw new Error('Sale scope mismatch.');
    return result;
}
