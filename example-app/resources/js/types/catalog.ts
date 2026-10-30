import type {
    BaseUnit,
    BusinessContext,
    DecimalQuantity,
    MinorUnits,
    PageSlice,
    Revision,
    ContractIssue,
} from './business';
import type { InventoryItem } from './inventory';
export type RecipeComponent = {
    inventoryItemId: number;
    quantity: DecimalQuantity;
    unit: BaseUnit;
};
type CatalogFields = {
    id: number;
    name: string;
    sku: string;
    categoryId: number | null;
    active: boolean;
    priceMinor: MinorUnits;
    revision: Revision;
    allowedAddonIds: number[];
};
export type CatalogItem = CatalogFields &
    (
        | { kind: 'retail'; stockItemId: number }
        | {
              kind: 'food';
              components: RecipeComponent[];
              recipeRevision: Revision;
          }
        | { kind: 'addon'; stockBehavior: 'direct'; stockItemId: number }
        | {
              kind: 'addon';
              stockBehavior: 'recipe';
              components: RecipeComponent[];
              recipeRevision: Revision;
          }
    );
type Editable = Pick<
    CatalogFields,
    'name' | 'sku' | 'categoryId' | 'active' | 'priceMinor' | 'allowedAddonIds'
>;
export type CatalogCreateInput = Editable &
    (
        | { kind: 'retail'; lowStockThreshold: DecimalQuantity | null }
        | { kind: 'food'; components: RecipeComponent[] }
        | {
              kind: 'addon';
              stockBehavior: 'direct';
              lowStockThreshold: DecimalQuantity | null;
          }
        | {
              kind: 'addon';
              stockBehavior: 'recipe';
              components: RecipeComponent[];
          }
    );
export type CatalogItemInput = CatalogCreateInput;
type UpdateInput<T> = T extends CatalogCreateInput
    ? Omit<T, 'lowStockThreshold'> & { expectedRevision: Revision }
    : never;
export type CatalogUpdateInput = UpdateInput<CatalogCreateInput>;
export type CatalogAvailability = {
    status: 'inactive' | 'available' | 'warning' | 'blocked';
    stock: InventoryItem | null;
    issues: ContractIssue[];
};
export type CatalogListItem = CatalogItem & {
    availability: CatalogAvailability;
};
export type RecipeInput = {
    expectedRevision: Revision;
    components: RecipeComponent[];
};
export type Category = {
    id: number;
    name: string;
    active: boolean;
    revision: Revision;
};
export type CategoryInput = Pick<Category, 'name' | 'active'>;
export type CategoryUpdateInput = CategoryInput & {
    expectedRevision: Revision;
};
export type CategoryIndexProps = {
    business: BusinessContext;
    categories: PageSlice<Category>;
};
export type CatalogFilters = {
    query: string;
    categoryId: number | null;
    kind: CatalogItem['kind'] | null;
    active: boolean | null;
};
export type CatalogIndexProps = {
    business: BusinessContext;
    catalog: PageSlice<CatalogListItem>;
    categories: Category[];
    filters: CatalogFilters;
};
export type CatalogEditorProps = {
    business: BusinessContext;
    item: CatalogItem | null;
    categories: Category[];
    inventory: { id: number; name: string; unit: BaseUnit; active: boolean }[];
    addons: CatalogItem[];
    availability: CatalogAvailability | null;
};
