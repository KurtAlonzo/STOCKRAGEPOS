import type {
    BusinessContext,
    DecimalQuantity,
    MinorUnits,
    PageSlice,
} from './business';
import type { RecipeComponent } from './catalog';
import type { StockMovement } from './inventory';
export type PaymentInput =
    | { method: 'cash'; tenderedMinor: MinorUnits }
    | {
          method: 'gcash' | 'maya';
          receivedConfirmed: true;
          reference: string | null;
      };
export type RecordedPayment = {
    id: number;
    receivedMinor: MinorUnits;
    changeMinor: MinorUnits;
    recordedAt: string;
    input: PaymentInput;
};
export type SaleLineSnapshot = {
    lineId: string;
    productId: number;
    name: string;
    sku: string;
    quantity: number;
    unitPriceMinor: MinorUnits;
    addons: {
        id: number;
        name: string;
        unitPriceMinor: MinorUnits;
        directStockItemId: number | null;
        components: RecipeComponent[];
    }[];
    totalMinor: MinorUnits;
    components: RecipeComponent[];
    directStockItemId: number | null;
};
export type ReversalInput = {
    idempotencyKey: string;
    reason: string;
    refundConfirmed: true;
    restock: { inventoryItemId: number; quantity: DecimalQuantity }[];
};
export type SaleReversal = ReversalInput & {
    id: number;
    saleId: number;
    totalMinor: MinorUnits;
    actorId: number;
    occurredAt: string;
};
export type Sale = {
    id: number;
    reference: string;
    storeId: number;
    actorId: number;
    status: 'completed' | 'reversed';
    occurredAt: string;
    currency: 'PHP';
    subtotalMinor: MinorUnits;
    totalMinor: MinorUnits;
    lines: SaleLineSnapshot[];
    payment: RecordedPayment;
    stockMovements: StockMovement[];
    reversal: SaleReversal | null;
};
export type SalesIndexProps = {
    business: BusinessContext;
    sales: PageSlice<Sale>;
    filters: {
        reference: string;
        dateFrom: string | null;
        dateTo: string | null;
        payment: PaymentInput['method'] | null;
        status: Sale['status'] | null;
    };
};
export type SaleShowProps = { business: BusinessContext; sale: Sale };
