import type { PaymentInput } from './sales';
import type { CheckoutAttempt } from './pos';
import type { CatalogItem } from './catalog';
import type { CatalogCreateInput, CatalogUpdateInput } from './catalog';
import type { InventoryItemInput } from './inventory';
const valid: PaymentInput = {
    method: 'gcash',
    receivedConfirmed: true,
    reference: null,
};
// @ts-expect-error External confirmation is mandatory.
const missing: PaymentInput = { method: 'maya', reference: null };
// @ts-expect-error Card is deferred.
const card: PaymentInput = { method: 'card' };
// @ts-expect-error Confirmed attempts must carry their sale.
const confirmed: CheckoutAttempt = {
    state: 'confirmed',
    idempotencyKey: 'key',
    draftId: 'draft',
    storeId: 1,
    userId: 1,
};
// @ts-expect-error Ingredients are inventory, never sellable catalog kinds.
const ingredient: CatalogItem = { kind: 'ingredient' };
void [valid, missing, card, confirmed, ingredient];
const publicRetail: InventoryItemInput = {
    name: 'Water',
    // @ts-expect-error Direct stock is created by catalog save, not public inventory input.
    kind: 'retail',
    unit: 'each',
    active: true,
    lowStockThreshold: null,
};
const forgedStock: CatalogCreateInput = {
    name: 'Water',
    sku: 'W',
    kind: 'retail',
    categoryId: null,
    active: true,
    priceMinor: 100,
    allowedAddonIds: [],
    lowStockThreshold: null,
    // @ts-expect-error Browser creation never supplies the stock record ID.
    stockItemId: 9,
};
// @ts-expect-error Every update requires the editor's revision.
const staleUpdate: CatalogUpdateInput = {
    name: 'Water',
    sku: 'W',
    kind: 'retail',
    categoryId: null,
    active: true,
    priceMinor: 100,
    allowedAddonIds: [],
};
void [publicRetail, forgedStock, staleUpdate];
