import type {
    BaseUnit,
    BusinessContext,
    DecimalQuantity,
    MinorUnits,
    PageSlice,
    Revision,
} from './business';
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
export type CatalogItemInput = CatalogItem extends infer T
    ? T extends CatalogItem
        ? Omit<T, 'id' | 'revision' | 'recipeRevision'>
        : never
    : never;
export type RecipeInput = {
    expectedRevision: Revision;
    components: RecipeComponent[];
};
export type Category = { id: number; name: string; active: boolean };
export type CategoryInput = Omit<Category, 'id'>;
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
    catalog: PageSlice<CatalogItem>;
    categories: Category[];
    filters: CatalogFilters;
};
export type CatalogEditorProps = {
    business: BusinessContext;
    item: CatalogItem | null;
    categories: Category[];
    inventory: { id: number; name: string; unit: BaseUnit }[];
    addons: CatalogItem[];
};
