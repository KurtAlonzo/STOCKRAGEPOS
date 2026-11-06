/** Transport aliases require server-side range/precision validation. */
export type MinorUnits = number;
export type DecimalQuantity = string;
export type Revision = string;
export type BaseUnit = 'each' | 'g' | 'ml';
export type BusinessContext = {
    storeId: number;
    storeSlug: string;
    currency: 'PHP';
    timezone: 'Asia/Manila';
    taxTreatment: 'unconfigured';
};
export type ContractIssue = {
    id: string;
    code: string;
    message: string;
    field: string | null;
    inventoryItemId: number | null;
};
export type ContractError = {
    code: string;
    message: string;
    fieldErrors: Record<string, string[]>;
    issues: ContractIssue[];
};
export type PageSlice<T> = {
    data: T[];
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
};
