import type {
    BaseUnit,
    BusinessContext,
    DecimalQuantity,
    PageSlice,
    Revision,
} from './business';
export type InventoryItemInput = {
    name: string;
    kind: 'retail' | 'ingredient' | 'supply' | 'addon';
    unit: BaseUnit;
};
export type InventoryItem = InventoryItemInput & {
    id: number;
    balance: DecimalQuantity;
    revision: Revision;
    shortagePolicy: 'block' | 'warn';
};
export type StockMovement = {
    id: number;
    inventoryItemId: number;
    kind: 'receipt' | 'adjustment' | 'sale' | 'reversal';
    before: DecimalQuantity;
    delta: DecimalQuantity;
    after: DecimalQuantity;
    unit: BaseUnit;
    reason: string;
    actorId: number;
    occurredAt: string;
    sourceType: 'receipt' | 'adjustment' | 'sale' | 'reversal';
    sourceId: number;
};
export type StockReceiptInput = {
    inventoryItemId: number;
    quantity: DecimalQuantity;
    reason: string;
    expectedRevision: Revision;
};
export type StockAdjustmentInput = {
    inventoryItemId: number;
    delta: DecimalQuantity;
    reason: string;
    expectedRevision: Revision;
};
export type InventoryIndexProps = {
    business: BusinessContext;
    inventory: PageSlice<InventoryItem>;
    filters: {
        query: string;
        kind: InventoryItem['kind'] | null;
        stockState: 'positive' | 'zero' | 'negative' | null;
    };
};
export type StockHistoryProps = {
    business: BusinessContext;
    movements: PageSlice<StockMovement>;
    filters: {
        itemId: number | null;
        type: StockMovement['kind'] | null;
        dateFrom: string | null;
        dateTo: string | null;
    };
};
