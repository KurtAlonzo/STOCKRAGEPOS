import type {
    ReversalInput,
    SaleReversal,
    CurrentReversalResult,
    ReversalAttemptResult,
} from '@/types/sales';
import type { ReversalScope } from './reversal-storage';
const record = (value: unknown): value is Record<string, unknown> =>
    !!value && typeof value === 'object' && !Array.isArray(value);
const id = (value: unknown): value is number =>
    Number.isSafeInteger(value) && Number(value) > 0;
export function normalizeReversal(input: ReversalInput): ReversalInput {
    if (
        !record(input) ||
        typeof input.idempotencyKey !== 'string' ||
        !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(
            input.idempotencyKey,
        ) ||
        typeof input.reason !== 'string' ||
        !input.reason.trim() ||
        input.reason.trim().length > 1000 ||
        input.refundConfirmed !== true ||
        !['cash', 'gcash', 'maya'].includes(input.refundMethod) ||
        (input.refundReference !== null &&
            typeof input.refundReference !== 'string') ||
        (input.refundReference?.trim().length ?? 0) > 255 ||
        !Array.isArray(input.restock)
    )
        throw new Error('Review the refund reason, method and confirmation.');
    const seen = new Set<number>();
    const restock = input.restock
        .map((part) => {
            if (
                !record(part) ||
                !id(part.inventoryItemId) ||
                typeof part.quantity !== 'string' ||
                !/^\d+(?:\.0{1,3})?$/.test(part.quantity) ||
                seen.has(part.inventoryItemId)
            )
                throw new Error(
                    'Return positive whole quantities of each item once.',
                );
            const count = BigInt(part.quantity.split('.')[0]);
            if (count <= 0n || count * 1000n > 9007199254740991n)
                throw new Error(
                    'Returned quantity exceeds the supported range.',
                );
            seen.add(part.inventoryItemId);
            return {
                inventoryItemId: part.inventoryItemId,
                quantity: count.toString() + '.000',
            };
        })
        .sort((a, b) => a.inventoryItemId - b.inventoryItemId);
    return {
        idempotencyKey: input.idempotencyKey,
        reason: input.reason.trim(),
        refundConfirmed: true,
        refundMethod: input.refundMethod,
        refundReference: input.refundReference?.trim() || null,
        restock,
    };
}
function reversal(value: unknown, scope: ReversalScope): value is SaleReversal {
    if (
        !record(value) ||
        !id(value.id) ||
        value.saleId !== scope.saleId ||
        !id(value.actorId) ||
        typeof value.actorName !== 'string' ||
        !Number.isSafeInteger(value.totalMinor) ||
        Number(value.totalMinor) < 0 ||
        typeof value.occurredAt !== 'string' ||
        !Number.isFinite(Date.parse(value.occurredAt))
    )
        return false;
    try {
        normalizeReversal(value as SaleReversal);
        return true;
    } catch {
        return false;
    }
}
export function decodeCurrent(
    body: unknown,
    scope: ReversalScope,
): CurrentReversalResult {
    if (record(body) && body.status === 'not_recorded')
        return { status: 'not_recorded' };
    if (
        record(body) &&
        body.status === 'recorded' &&
        reversal(body.reversal, scope)
    )
        return body as CurrentReversalResult;
    throw new Error('Malformed refund status. Keep the original request.');
}
export function decodeAttempt(
    body: unknown,
    scope: ReversalScope,
    key: string,
): ReversalAttemptResult {
    if (
        !record(body) ||
        body.saleId !== scope.saleId ||
        body.idempotencyKey !== key
    )
        throw new Error('Refund operation identity mismatch.');
    if (body.status === 'pending' || body.status === 'not_found')
        return body as ReversalAttemptResult;
    if (
        body.status === 'recorded' &&
        reversal(body.reversal, scope) &&
        body.reversal.idempotencyKey === key &&
        body.reversal.actorId === scope.userId
    )
        return body as ReversalAttemptResult;
    if (
        body.status === 'rejected' &&
        record(body.error) &&
        typeof body.error.code === 'string' &&
        typeof body.error.message === 'string' &&
        record(body.error.fieldErrors) &&
        Array.isArray(body.error.issues)
    )
        return body as ReversalAttemptResult;
    throw new Error('Malformed refund outcome. Keep the original request.');
}
