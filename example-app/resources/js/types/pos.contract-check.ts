import type { PaymentInput } from './sales';
import type { CheckoutAttempt } from './pos';
import type { CatalogItem } from './catalog';
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
