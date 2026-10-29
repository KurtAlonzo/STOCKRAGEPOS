import type {
    BusinessContext,
    CatalogItem,
    InventoryItem,
    CheckoutQuote,
    CheckoutCompletionInput,
    Sale,
    StockEffect,
    SaleLineSnapshot,
    ContractError,
    AttemptLookupResult,
    PaymentInput,
} from '../../types';
export const fixtureSource = 'fixture' as const;
const business = {
    storeId: 1,
    storeSlug: 'demo',
    currency: 'PHP',
    timezone: 'Asia/Manila',
    taxTreatment: 'unconfigured',
} satisfies BusinessContext;
const common = {
    categoryId: null,
    active: true,
    revision: 'v1',
    allowedAddonIds: [],
};
export const catalogFixture: CatalogItem[] = [
    {
        ...common,
        id: 101,
        name: 'Water',
        sku: 'WATER',
        kind: 'retail',
        priceMinor: 5000,
        stockItemId: 201,
    },
    {
        ...common,
        id: 102,
        name: 'Burger',
        sku: 'BURGER',
        kind: 'food',
        priceMinor: 10000,
        allowedAddonIds: [103, 104],
        recipeRevision: 'r1',
        components: [
            { inventoryItemId: 203, quantity: '100.000', unit: 'g' },
            { inventoryItemId: 204, quantity: '10.000', unit: 'g' },
            { inventoryItemId: 205, quantity: '0.125', unit: 'g' },
            { inventoryItemId: 206, quantity: '1.000', unit: 'each' },
        ],
    },
    {
        ...common,
        id: 103,
        name: 'Cheese',
        sku: 'CHEESE',
        kind: 'addon',
        stockBehavior: 'direct',
        priceMinor: 1500,
        stockItemId: 202,
    },
    {
        ...common,
        id: 104,
        name: 'Extra beef',
        sku: 'BEEF',
        kind: 'addon',
        stockBehavior: 'recipe',
        priceMinor: 3000,
        recipeRevision: 'r1',
        components: [{ inventoryItemId: 203, quantity: '50.000', unit: 'g' }],
    },
    {
        ...common,
        id: 105,
        name: 'Unavailable seasonal sandwich with an unusually long descriptive name for narrow layouts',
        sku: 'INACTIVE',
        kind: 'food',
        active: false,
        priceMinor: 12000,
        recipeRevision: 'r1',
        components: [],
    },
];
export const inventoryFixture: InventoryItem[] = [
    {
        id: 201,
        name: 'Water',
        kind: 'retail',
        unit: 'each',
        balance: '2.000',
        revision: 'v1',
        shortagePolicy: 'block',
    },
    {
        id: 202,
        name: 'Cheese',
        kind: 'addon',
        unit: 'each',
        balance: '3.000',
        revision: 'v1',
        shortagePolicy: 'block',
    },
    {
        id: 203,
        name: 'Beef',
        kind: 'ingredient',
        unit: 'g',
        balance: '250.000',
        revision: 'v1',
        shortagePolicy: 'warn',
    },
    {
        id: 204,
        name: 'Sauce',
        kind: 'ingredient',
        unit: 'g',
        balance: '-5.000',
        revision: 'v1',
        shortagePolicy: 'warn',
    },
    {
        id: 205,
        name: 'Seasoning',
        kind: 'ingredient',
        unit: 'g',
        balance: '1.000',
        revision: 'v1',
        shortagePolicy: 'warn',
    },
    {
        id: 206,
        name: 'Packaging',
        kind: 'supply',
        unit: 'each',
        balance: '2.000',
        revision: 'v1',
        shortagePolicy: 'warn',
    },
];
const lines = [
    {
        id: '11111111-1111-4111-8111-111111111111',
        productId: 101,
        quantity: 1,
        addonIds: [],
    },
    {
        id: '22222222-2222-4222-8222-222222222222',
        productId: 102,
        quantity: 2,
        addonIds: [103],
    },
    {
        id: '33333333-3333-4333-8333-333333333333',
        productId: 102,
        quantity: 1,
        addonIds: [104],
    },
];
const snapshots: SaleLineSnapshot[] = lines.map((line) => {
    const item = catalogFixture.find((i) => i.id === line.productId)!;
    const addons = line.addonIds.map((id) =>
        catalogFixture.find((i) => i.id === id)!,
    );
    return {
        lineId: line.id,
        productId: item.id,
        name: item.name,
        sku: item.sku,
        quantity: line.quantity,
        unitPriceMinor: item.priceMinor,
        addons: addons.map((a) => ({
            id: a.id,
            name: a.name,
            unitPriceMinor: a.priceMinor,
            directStockItemId: 'stockItemId' in a ? a.stockItemId : null,
            components: 'components' in a ? a.components : [],
        })),
        totalMinor:
            line.quantity *
            (item.priceMinor +
                addons.reduce((sum, a) => sum + a.priceMinor, 0)),
        components: ('components' in item ? item.components : []).concat(
            addons.flatMap((a) => ('components' in a ? a.components : [])),
        ),
        directStockItemId: 'stockItemId' in item ? item.stockItemId : null,
    };
});
const stockEffects: StockEffect[] = [
    {
        inventoryItemId: 201,
        unit: 'each',
        before: '2.000',
        demand: '1.000',
        after: '1.000',
        policy: 'block',
    },
    {
        inventoryItemId: 202,
        unit: 'each',
        before: '3.000',
        demand: '2.000',
        after: '1.000',
        policy: 'block',
    },
    {
        inventoryItemId: 203,
        unit: 'g',
        before: '250.000',
        demand: '350.000',
        after: '-100.000',
        policy: 'warn',
    },
    {
        inventoryItemId: 204,
        unit: 'g',
        before: '-5.000',
        demand: '30.000',
        after: '-35.000',
        policy: 'warn',
    },
    {
        inventoryItemId: 205,
        unit: 'g',
        before: '1.000',
        demand: '0.375',
        after: '0.625',
        policy: 'warn',
    },
    {
        inventoryItemId: 206,
        unit: 'each',
        before: '2.000',
        demand: '3.000',
        after: '-1.000',
        policy: 'warn',
    },
];
const payment = { method: 'cash', tenderedMinor: 50000 } satisfies PaymentInput;
const quote: CheckoutQuote = {
    id: 'quote-1',
    revision: 'q1',
    expiresAt: '2026-10-29T04:05:00Z',
    business,
    lines: snapshots,
    subtotalMinor: 41000,
    totalMinor: 41000,
    payment,
    changeMinor: 9000,
    catalogRevisions: { 101: 'v1', 102: 'v1', 103: 'v1', 104: 'v1' },
    recipeRevisions: { 102: 'r1', 104: 'r1' },
    stockEffects,
    blockingIssues: [],
    warnings: [203, 204, 206].map((id) => ({
        id: 'short-' + id,
        code: 'ingredient_shortage',
        message: 'Projected negative stock; review before completion.',
        field: null,
        inventoryItemId: id,
    })),
};
const completionInput: CheckoutCompletionInput = {
    idempotencyKey: '44444444-4444-4444-8444-444444444444',
    quoteId: quote.id,
    quoteRevision: quote.revision,
    lines,
    payment,
    acknowledgedWarningIds: quote.warnings.map((w) => w.id),
};
const sale: Sale = {
    id: 1,
    reference: 'DEMO-0001',
    storeId: 1,
    actorId: 1,
    status: 'completed',
    occurredAt: '2026-10-29T04:00:00Z',
    currency: 'PHP',
    subtotalMinor: 41000,
    totalMinor: 41000,
    lines: snapshots,
    payment: {
        id: 1,
        receivedMinor: 41000,
        changeMinor: 9000,
        recordedAt: '2026-10-29T04:00:00Z',
        input: payment,
    },
    stockMovements: stockEffects.map((s, i) => ({
        id: i + 1,
        inventoryItemId: s.inventoryItemId,
        kind: 'sale',
        before: s.before,
        delta: '-' + s.demand,
        after: s.after,
        unit: s.unit,
        reason: 'Sale DEMO-0001',
        actorId: 1,
        occurredAt: '2026-10-29T04:00:00Z',
        sourceType: 'sale',
        sourceId: 1,
    })),
    reversal: null,
};
export const mixedBasketFixture = {
    quoteInput: { lines, payment },
    completionInput,
    quote,
    sale,
    expected: { totalMinor: 41000, changeMinor: 9000 },
};
export const errorFixture = (code: string): ContractError => ({
    code,
    message: code,
    fieldErrors: {},
    issues: [],
});
type Scenario = {
    id: string;
    request: unknown;
    httpStatus: number;
    response: unknown;
    expected: string;
};
export const contractScenarios: Scenario[] = [
    {
        id: 'cash-insufficient',
        request: { method: 'cash', tenderedMinor: 40000 },
        httpStatus: 422,
        response: { error: errorFixture('validation_failed') },
        expected: 'rejected',
    },
    {
        id: 'cash-exact',
        request: { method: 'cash', tenderedMinor: 41000 },
        httpStatus: 200,
        response: { changeMinor: 0 },
        expected: 'allowed',
    },
    {
        id: 'cash-over',
        request: { method: 'cash', tenderedMinor: 50000 },
        httpStatus: 200,
        response: { changeMinor: 9000 },
        expected: 'allowed',
    },

    ...['gcash', 'maya'].map((method) => ({
        id: method + '-no-reference',
        request: { method, receivedConfirmed: true, reference: null },
        httpStatus: 200,
        response: { accepted: true },
        expected: 'allowed',
    })),
    {
        id: 'blank-reference',
        request: { reference: '  ' },
        httpStatus: 200,
        response: { reference: null },
        expected: 'normalized',
    },
    {
        id: 'duplicate-reference',
        request: {
            method: 'gcash',
            reference: 'TX-1',
            receivedConfirmed: true,
        },
        httpStatus: 200,
        response: {
            warnings: [
                { id: 'duplicate-1', code: 'duplicate_payment_reference' },
            ],
        },
        expected: 'review_then_allow',
    },
    {
        id: 'unconfirmed-digital',
        request: { method: 'gcash', receivedConfirmed: false, reference: null },
        httpStatus: 422,
        response: { error: errorFixture('validation_failed') },
        expected: 'rejected',
    },
    ...[
        'fractional-sale',
        'fractional-each',
        'excess-precision',
        'invalid-money',
        'inactive-item',
        'disallowed-addon',
        'duplicate-addon',
    ].map((id) => ({
        id,
        request: (
            {
                'fractional-sale': { quantity: 0.5 },
                'fractional-each': { quantity: '0.500', unit: 'each' },
                'excess-precision': { quantity: '0.0001' },
                'invalid-money': { priceMinor: 1.5 },
                'inactive-item': { productId: 105 },
                'disallowed-addon': { productId: 101, addonIds: [104] },
                'duplicate-addon': { productId: 102, addonIds: [103, 103] },
            } as Record<string, unknown>
        )[id],
        httpStatus: 422,
        response: { error: errorFixture('validation_failed') },
        expected: 'rejected',
    })),
    ...[
        'quote_expired',
        'quote_changed',
        'stock_conflict',
        'idempotency_conflict',
    ].map((code) => ({
        id: code,
        request:
            code === 'idempotency_conflict'
                ? {
                      ...completionInput,
                      payment: { method: 'cash', tenderedMinor: 60000 },
                  }
                : completionInput,
        httpStatus: 409,
        response: {
            error: errorFixture(code),
            ...(code === 'quote_changed'
                ? {
                      replacementQuote: {
                          ...quote,
                          revision: 'q2',
                          catalogRevisions: {
                              ...quote.catalogRevisions,
                              102: 'v2',
                          },
                          lines: quote.lines.map((line) =>
                              line.productId === 102
                                  ? {
                                        ...line,
                                        unitPriceMinor: 11000,
                                        totalMinor:
                                            line.totalMinor +
                                            1000 * line.quantity,
                                    }
                                  : line,
                          ),
                          totalMinor: 44000,
                          subtotalMinor: 44000,
                          changeMinor: 6000,
                      },
                  }
                : {}),
        },
        expected: 'rejected',
    })),
    ...['nonowner', 'forbidden-store'].map((id) => ({
        id,
        request: { storeId: 2 },
        httpStatus: 403,
        response: { error: errorFixture('forbidden') },
        expected: 'no_data',
    })),
    {
        id: 'foreign-resource',
        request: { saleId: 99 },
        httpStatus: 404,
        response: { error: errorFixture('not_found') },
        expected: 'no_data',
    },
];
export const recoveryExamples: {
    event: string;
    request: CheckoutCompletionInput;
    result: AttemptLookupResult;
}[] = [
    {
        event: 'not_found',
        request: completionInput,
        result: { status: 'not_found' },
    },
    {
        event: 'pending',
        request: completionInput,
        result: {
            status: 'pending',
            idempotencyKey: completionInput.idempotencyKey,
        },
    },
    {
        event: 'response_lost_after_commit',
        request: completionInput,
        result: {
            status: 'confirmed',
            idempotencyKey: completionInput.idempotencyKey,
            sale,
        },
    },
    {
        event: 'same_key_replay_after_expiry',
        request: completionInput,
        result: {
            status: 'confirmed',
            idempotencyKey: completionInput.idempotencyKey,
            sale,
        },
    },
    {
        event: 'known_rejection',
        request: completionInput,
        result: {
            status: 'rejected',
            idempotencyKey: completionInput.idempotencyKey,
            error: errorFixture('validation_failed'),
        },
    },
];
