import type { CartLine, CheckoutQuote, ContractError } from '@/types';
import { lineKey } from './cart';
export class PosRequestError extends Error implements ContractError {
    code: string;
    fieldErrors: Record<string, string[]>;
    issues: ContractError['issues'];
    constructor(error: ContractError) {
        super(error.message);
        this.code = error.code;
        this.fieldErrors = error.fieldErrors;
        this.issues = error.issues;
    }
}
export function contractError(error: unknown): ContractError {
    return error instanceof PosRequestError
        ? {
              code: error.code,
              message: error.message,
              fieldErrors: error.fieldErrors,
              issues: error.issues,
          }
        : {
              code: 'request_failed',
              message:
                  error instanceof Error
                      ? error.message
                      : 'Unable to verify cart. Retry.',
              fieldErrors: {},
              issues: [],
          };
}
export async function requestJson<T>(
    url: string,
    init: RequestInit,
): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    if (init.body) headers.set('Content-Type', 'application/json');
    if (
        (init.method ?? 'GET').toUpperCase() !== 'GET' &&
        typeof document !== 'undefined'
    ) {
        const token = document.cookie
            .split('; ')
            .find((x) => x.startsWith('XSRF-TOKEN='));
        if (token)
            headers.set('X-XSRF-TOKEN', decodeURIComponent(token.slice(11)));
    }
    const response = await fetch(url, {
        ...init,
        headers,
        credentials: 'same-origin',
    });
    if (!response.headers.get('Content-Type')?.includes('application/json'))
        throw new Error(
            'Unexpected server response. Refresh your session and retry.',
        );
    const body: unknown = await response.json();
    if (!response.ok) {
        const error = record(body) && record(body.error) ? body.error : null;
        if (
            error &&
            typeof error.code === 'string' &&
            typeof error.message === 'string' &&
            record(error.fieldErrors) &&
            Array.isArray(error.issues)
        )
            throw new PosRequestError(error as ContractError);
        throw new Error(
            `Request failed (${response.status}). Refresh your session or retry.`,
        );
    }
    return body as T;
}
export function createRequestGate() {
    let generation = 0,
        scope = '',
        key = '';
    return {
        next(nextScope: string, inputKey: string) {
            scope = nextScope;
            key = inputKey;
            return ++generation;
        },
        accepts(candidate: number, candidateScope: string, inputKey: string) {
            return (
                candidate === generation &&
                candidateScope === scope &&
                inputKey === key
            );
        },
        invalidate() {
            generation++;
        },
    };
}
function record(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
function money(value: unknown): value is number {
    return (
        typeof value === 'number' && Number.isSafeInteger(value) && value >= 0
    );
}
function parts(value: unknown): boolean {
    return (
        Array.isArray(value) &&
        value.every(
            (x) =>
                record(x) &&
                money(x.inventoryItemId) &&
                typeof x.quantity === 'string' &&
                /^\d+\.\d{3}$/.test(x.quantity) &&
                ['each', 'g', 'ml'].includes(String(x.unit)),
        )
    );
}
function issues(value: unknown): boolean {
    return (
        Array.isArray(value) &&
        value.every(
            (x) =>
                record(x) &&
                typeof x.id === 'string' &&
                typeof x.code === 'string' &&
                typeof x.message === 'string' &&
                (x.field === null || typeof x.field === 'string') &&
                (x.inventoryItemId === null || money(x.inventoryItemId)),
        )
    );
}
export function validateQuote(
    value: unknown,
    lines: CartLine[],
    storeId: number,
): value is CheckoutQuote {
    if (
        !record(value) ||
        typeof value.id !== 'string' ||
        !value.id ||
        typeof value.revision !== 'string' ||
        !record(value.business) ||
        value.business.storeId !== storeId ||
        value.business.currency !== 'PHP' ||
        value.business.timezone !== 'Asia/Manila' ||
        typeof value.business.storeSlug !== 'string' ||
        value.business.taxTreatment !== 'unconfigured' ||
        typeof value.expiresAt !== 'string' ||
        !Number.isFinite(Date.parse(value.expiresAt)) ||
        !money(value.totalMinor) ||
        value.subtotalMinor !== value.totalMinor ||
        value.payment !== null ||
        value.changeMinor !== null ||
        !record(value.catalogRevisions) ||
        !record(value.recipeRevisions) ||
        !issues(value.blockingIssues) ||
        !issues(value.warnings) ||
        !Array.isArray(value.lines) ||
        value.lines.length !== lines.length ||
        !Array.isArray(value.stockEffects)
    )
        return false;
    try {
        const seen = new Set<string>();
        let total = 0n;
        for (const line of value.lines) {
            if (
                !record(line) ||
                typeof line.lineId !== 'string' ||
                seen.has(line.lineId) ||
                !money(line.unitPriceMinor) ||
                !money(line.totalMinor) ||
                typeof line.name !== 'string' ||
                typeof line.sku !== 'string' ||
                !parts(line.components) ||
                !(
                    line.directStockItemId === null ||
                    money(line.directStockItemId)
                ) ||
                !Array.isArray(line.addons)
            )
                return false;
            seen.add(line.lineId);
            const input = lines.find((x) => x.id === line.lineId);
            if (
                !input ||
                input.productId !== line.productId ||
                input.quantity !== line.quantity
            )
                return false;
            let unit = BigInt(line.unitPriceMinor);
            for (const addon of line.addons) {
                if (
                    !record(addon) ||
                    !money(addon.id) ||
                    typeof addon.name !== 'string' ||
                    !money(addon.unitPriceMinor) ||
                    !parts(addon.components) ||
                    !(
                        addon.directStockItemId === null ||
                        money(addon.directStockItemId)
                    )
                )
                    return false;
                unit += BigInt(addon.unitPriceMinor);
            }
            if (
                lineKey(input.productId, input.addonIds) !==
                    lineKey(
                        input.productId,
                        line.addons.map((x) => x.id),
                    ) ||
                unit * BigInt(input.quantity) !== BigInt(line.totalMinor)
            )
                return false;
            total += BigInt(line.totalMinor);
        }
        if (total !== BigInt(value.totalMinor)) return false;
        return value.stockEffects.every(
            (x) =>
                record(x) &&
                money(x.inventoryItemId) &&
                typeof x.inventoryItemName === 'string' &&
                ['each', 'g', 'ml'].includes(String(x.unit)) &&
                ['block', 'warn'].includes(String(x.policy)) &&
                ['before', 'demand', 'after'].every(
                    (key) =>
                        typeof x[key] === 'string' &&
                        /^-?\d+\.\d{3}$/.test(x[key] as string),
                ),
        );
    } catch {
        return false;
    }
}
export type QuoteState = {
    status: 'empty' | 'invalid' | 'pending' | 'current' | 'stale' | 'error';
    quote: CheckoutQuote | null;
    error: ContractError | null;
};
export type QuoteInputs = {
    scopeKey: string;
    lines: CartLine[];
    valid: boolean;
    quoteUrl: string;
};
export function cartInputKey(lines: CartLine[]): string {
    return JSON.stringify(
        lines.map((x) => [
            x.id,
            x.productId,
            x.quantity,
            [...x.addonIds].sort((a, b) => a - b),
        ]),
    );
}
export function createQuoteSession(notify: () => void) {
    const gate = createRequestGate();
    const listeners = new Set<() => void>();
    let state: QuoteState = { status: 'empty', quote: null, error: null },
        input: QuoteInputs | null = null,
        key = '',
        controller: AbortController | null = null;
    let busy = false;
    let debounce: ReturnType<typeof setTimeout> | undefined,
        expiry: ReturnType<typeof setTimeout> | undefined;
    const emit = (next: QuoteState) => {
        state = next;
        notify();
        listeners.forEach((listener) => listener());
    };
    const cancel = () => {
        clearTimeout(debounce);
        clearTimeout(expiry);
        controller?.abort();
        gate.invalidate();
        busy = false;
    };
    const schedule = () => {
        if (!input || !input.valid || !input.lines.length) return;
        cancel();
        busy = true;
        const captured = input,
            inputKey = key,
            generation = gate.next(captured.scopeKey, inputKey);
        emit({
            ...state,
            status: state.quote ? 'stale' : 'pending',
            error: null,
        });
        debounce = setTimeout(async () => {
            controller = new AbortController();
            try {
                const quote = await requestJson<unknown>(captured.quoteUrl, {
                    method: 'POST',
                    signal: controller.signal,
                    body: JSON.stringify({
                        lines: captured.lines,
                        payment: null,
                    }),
                });
                if (!gate.accepts(generation, captured.scopeKey, inputKey))
                    return;
                const storeId = Number(captured.scopeKey.split(':')[0]);
                if (
                    !validateQuote(quote, captured.lines, storeId) ||
                    Date.parse(quote.expiresAt) <= Date.now()
                )
                    throw new Error(
                        'Invalid or expired quote response. Retry.',
                    );
                busy = false;
                emit({ status: 'current', quote, error: null });
                expiry = setTimeout(
                    () => emit({ ...state, status: 'stale' }),
                    Date.parse(quote.expiresAt) - Date.now(),
                );
            } catch (error) {
                if (!gate.accepts(generation, captured.scopeKey, inputKey))
                    return;
                busy = false;
                if (error instanceof Error && error.name === 'AbortError')
                    return;
                emit({
                    ...state,
                    status: 'error',
                    error: contractError(error),
                });
            }
        }, 300);
    };
    return {
        subscribe(listener: () => void) {
            listeners.add(listener);
            return () => {
                listeners.delete(listener);
            };
        },
        update(next: QuoteInputs) {
            const nextKey = cartInputKey(next.lines);
            if (
                input?.scopeKey === next.scopeKey &&
                input.quoteUrl === next.quoteUrl &&
                key === nextKey &&
                input.valid === next.valid
            )
                return;
            const changedScope = input?.scopeKey !== next.scopeKey;
            cancel();
            input = next;
            key = nextKey;
            if (changedScope)
                state = { status: 'empty', quote: null, error: null };
            if (!next.lines.length)
                emit({ status: 'empty', quote: null, error: null });
            else if (!next.valid)
                emit({ ...state, status: 'invalid', error: null });
            else schedule();
        },
        snapshot: () => state,
        retry: schedule,
        recover: () => {
            if (!busy && (state.status === 'stale' || state.status === 'error'))
                schedule();
        },
        dispose: () => {
            cancel();
            input = null;
        },
    };
}
