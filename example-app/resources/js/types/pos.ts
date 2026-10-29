import type {
    BusinessContext,
    ContractError,
    ContractIssue,
    DecimalQuantity,
    PageSlice,
    Revision,
} from './business';
import type { CatalogItem, Category } from './catalog';
import type { InventoryItem } from './inventory';
import type { PaymentInput, Sale, SaleLineSnapshot } from './sales';
export type CartLine = {
    id: string;
    productId: number;
    quantity: number;
    addonIds: number[];
};
export type CheckoutQuoteInput = {
    lines: CartLine[];
    payment: PaymentInput | null;
};
export type StockEffect = {
    inventoryItemId: number;
    unit: InventoryItem['unit'];
    before: DecimalQuantity;
    demand: DecimalQuantity;
    after: DecimalQuantity;
    policy: InventoryItem['shortagePolicy'];
};
export type CheckoutQuote = {
    id: string;
    revision: Revision;
    expiresAt: string;
    business: BusinessContext;
    lines: SaleLineSnapshot[];
    subtotalMinor: number;
    totalMinor: number;
    payment: PaymentInput | null;
    changeMinor: number | null;
    catalogRevisions: Record<string, Revision>;
    recipeRevisions: Record<string, Revision>;
    stockEffects: StockEffect[];
    blockingIssues: ContractIssue[];
    warnings: ContractIssue[];
};
export type CheckoutCompletionInput = {
    idempotencyKey: string;
    quoteId: string;
    quoteRevision: Revision;
    lines: CartLine[];
    payment: PaymentInput;
    acknowledgedWarningIds: string[];
};
type AttemptIdentity = {
    idempotencyKey: string;
    draftId: string;
    storeId: number;
    userId: number;
};
export type CheckoutAttempt = AttemptIdentity &
    (
        | { state: 'draft' }
        | {
              state: 'submitting' | 'unknown';
              originalRequest: CheckoutCompletionInput;
          }
        | { state: 'confirmed'; sale: Sale }
        | { state: 'rejected'; error: ContractError }
    );
export type AttemptLookupResult =
    | { status: 'not_found' }
    | { status: 'pending'; idempotencyKey: string }
    | { status: 'confirmed'; idempotencyKey: string; sale: Sale }
    | { status: 'rejected'; idempotencyKey: string; error: ContractError };
export type CheckoutCompletionResult = Extract<
    AttemptLookupResult,
    { status: 'confirmed' | 'pending' }
>;
export type CheckoutErrorResponse = {
    error: ContractError;
    replacementQuote?: CheckoutQuote;
};
export type PosPageProps = {
    business: BusinessContext;
    catalog: PageSlice<CatalogItem>;
    categories: Category[];
    enabledPaymentMethods: PaymentInput['method'][];
};
