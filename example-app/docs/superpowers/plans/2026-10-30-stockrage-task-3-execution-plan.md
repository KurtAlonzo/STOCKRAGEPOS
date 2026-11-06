# STOCKRAGE Task 3 — Catalog, recipes, and minimum stock implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Use superpowers:subagent-driven-development only if delegation is selected for implementation. Steps use checkbox (`- [ ]`) syntax for tracking. User authorized implementation in the existing feature/layout checkout on 2026-10-30.

**Goal:** Let an owner create and maintain a real retail/food/add-on catalog, receive and correct stock, and inspect persisted movements before checkout is implemented.

**Architecture:** Extend the current Laravel/Inertia/React application in connected slices. Laravel owns resource isolation, validation, revisions, exact stock arithmetic, transactional movements, and recipe demand; React owns editing, feedback, filtering, and responsive management. Persist domain data immediately in each slice and keep fixtures explicitly test/development-only.

**Tech Stack:** Existing Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Radix/shadcn components, Wayfinder, Vite Plus, PHPUnit, and Larastan. No framework migration or extra test runner.

**Spec:** [Task 3 design](../specs/2026-10-30-stockrage-task-3-catalog-inventory-design.md), read with [parent Task 3](2026-10-29-stockrage-frontend-action-plan.md) and [POS v1 contract](../../contracts/pos-v1.md).

**Status:** Implemented with local verification; see [evidence](../../verification/2026-10-30-task-3-catalog-inventory.md) and [execution ledger](../../verification/task-3-execution-ledger.md). The implementation is committed in 3f98af1; the November 2 audit and accompanying working-tree fixes supersede the old baseline formatting failures. See [readiness audit](../../verification/2026-10-31-task-3-readiness-audit.md).

## Global constraints

- Implement connected slices. Automatically create dedicated zero-balance stock for retail/direct add-ons; receive opening stock afterward. Optional per-item thresholds; unset means no low-stock alert. Block deactivation when an active product depends on the record and show dependencies.
- Keep auth, verification, owner-only access, and route-store scoping. A user owning A but merely belonging to B cannot operate B. Never authorize a submitted teamId or rely solely on session currentTeam. Foreign resource IDs under an accessible store return 404.
- Money uses integer PHP centavos, `0..9007199254740991`; quantities use exact integer thousandths internally, bounded in absolute value by `9007199254740991`, and canonical decimal strings externally. Each quantities are integral. Reject excess precision and overflow; never silently round.
- Units are `each`, `g`, `ml`. Direct-stock shortages block; ingredient/supply shortages warn and may be negative. Tax remains unconfigured and a live-use blocker; Task 3 adds no tax arithmetic.
- Append movements. Do not delete referenced records, overwrite balances without movements, mutate previous movements, create sales, or expose fake connected data.
- Preserve Inter, 6px base radius, 8px spacing rhythm, existing red/black palette and React light/dark/system appearance. Labels accompany stock colors. Management works at 390px; POS viewport restrictions are irrelevant to these screens.
- Generate Wayfinder helpers from real routes. Add navigation only when its workflow works. Categories belong inside Catalog; global Stock History remains Task 7, with a scoped movement panel provided now.
- No cart/payment/recovery UI, dashboard aggregates, purchasing/suppliers, costing, unit conversion, product images, barcode system, variants, or unrelated refactors.
- All application paths below are relative to `example-app/`. Commands run there. Inventory the current checkout and preserve unrelated edits. No production data migration or deployment is part of planning or local verification.

## Review focus

1. An owner submits another store's category, component, add-on, item, or history ID: scope every reference and test 404 without leakage (3.1–3.5).
2. Double click, lost response, or two tabs update the same stock revision: only one atomic operation/movement changes balance; stale input is preserved with a reload path (3.2).
3. Multiple recipe lines and add-ons share a component, or huge quantities overflow: aggregate exact demand before shortage classification; reject overflow before persistence (3.4–3.5).
4. A dependency is deactivated while a product is saved/reactivated: enforce dependency rules transactionally, preserve old links, and reject stale/conflicting writes (3.3–3.5).
5. A name/unit/threshold/type edit changes the meaning of stock/history: keep identity/unit/kind immutable, snapshot movement display data, preserve SKU reservations, and recompute labels without changing balances (3.1–3.5).

## Preflight at implementation time

- [ ] Read the design and all applicable AGENTS.md files; inspect `git status --short`, branch, worktree, and relevant diffs. Follow the worktree skill if isolation is needed. Carry the actual Task 1/2 baseline into an isolated checkout; do not recreate it from stale documentation.
- [ ] Confirm installed PHP/Node tooling and the SQLite in-memory test configuration in `phpunit.xml`. Inspect runtime database configuration without printing credentials. Use isolated local data for browser tests; never run destructive migrations against the working or production database.
- [ ] Record baseline: `php artisan test`, `php vendor/bin/phpstan analyse --memory-limit=512M`, `npx vp test run`, `npm run types:check`, `npm run check`, and `npm run build`. Historical formatting failures are leads to investigate, not automatic exemptions. Capture current failing paths and distinguish them from new failures.
- [ ] Inspect Stock Management, Add Stock Item, Stock History, Mobile, Tablet, and Design System PNGs under `../context and md files/UI DESIGN/STOCKRAGE POS — UI Design-png/`. Follow the confirmed contracts where mockup units/copy differ.

## File map and ownership

| Area                       | Create                                                                                                                                                                                                                                                                                                                                                                                                          | Modify/reuse                                                                                                                                                                 |
| -------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Contract alignment         | —                                                                                                                                                                                                                                                                                                                                                                                                               | `docs/contracts/pos-v1.md`; `resources/js/types/{catalog,inventory}.ts`; `resources/js/types/pos.contract-check.ts`; `resources/js/fixtures/pos/scenarios.ts` and `.test.ts` |
| Inventory slice            | `database/migrations/2026_10_30_000001_create_inventory_items_table.php`; `app/Models/InventoryItem.php`; `app/Actions/Inventory/SaveInventoryItem.php`; `app/Http/Controllers/InventoryController.php`; `app/Http/Requests/Inventory/{StoreInventoryItemRequest,UpdateInventoryItemRequest}.php`; `app/Support/{Quantity,StoreCatalogTransaction}.php`; `app/Data/InventoryData.php`                           | `routes/web.php`; existing Team model/middleware only where relationships are needed                                                                                         |
| Stock operations           | `database/migrations/2026_10_30_000002_create_stock_operations_and_movements_tables.php`; `app/Models/{StockOperation,StockMovement}.php`; `app/Actions/Inventory/RecordStockOperation.php`; `app/Http/Controllers/StockOperationController.php`; `app/Http/Requests/Inventory/{StockReceiptRequest,StockAdjustmentRequest}.php`                                                                                | `InventoryController.php`, `InventoryData.php`                                                                                                                               |
| Catalog slice              | `database/migrations/2026_10_30_000003_create_categories_and_catalog_items_tables.php`; `app/Models/{Category,CatalogItem}.php`; `app/Actions/Catalog/{SaveCategory,SaveCatalogItem}.php`; `app/Http/Controllers/{CategoryController,ProductController}.php`; `app/Http/Requests/Catalog/{StoreCategoryRequest,UpdateCategoryRequest,StoreProductRequest,UpdateProductRequest}.php`; `app/Data/CatalogData.php` | `routes/web.php`                                                                                                                                                             |
| Recipes/add-ons            | `database/migrations/2026_10_30_000004_create_recipe_components_table.php`; `database/migrations/2026_10_30_000005_create_catalog_allowed_addons_table.php`; `app/Models/RecipeComponent.php`; `app/Actions/Catalog/{SaveRecipe,CheckActiveDependents}.php`; `app/Http/Controllers/RecipeController.php`; `app/Http/Requests/Catalog/UpdateRecipeRequest.php`; `app/Services/Catalog/StockDemand.php`           | Catalog save, serialization, validation, and inventory deactivation actions                                                                                                  |
| Inventory UI               | `resources/js/pages/inventory/index.tsx`; `resources/js/components/inventory/{item-dialog,stock-receipt-dialog,stock-adjustment-dialog,movement-panel}.tsx`                                                                                                                                                                                                                                                     | Existing AppLayout, form/error/dialog/badge components                                                                                                                       |
| Catalog UI                 | `resources/js/pages/products/{index,create,edit}.tsx`; `resources/js/pages/categories/index.tsx`; `resources/js/components/catalog/{item-form,recipe-editor,addon-selector,availability-badge}.tsx`; `resources/js/lib/catalog/form-values.ts`                                                                                                                                                                  | Existing form/layout components                                                                                                                                              |
| Navigation/edit protection | `resources/js/hooks/use-unsaved-changes.ts`                                                                                                                                                                                                                                                                                                                                                                     | `resources/js/lib/shell-navigation.ts`, `.test.ts`, `navigation-path.test.ts`; existing team-switcher behavior if dirty-navigation interception requires it                  |
| Tests                      | `tests/Unit/{QuantityTest,StockDemandTest}.php`; `tests/Feature/Inventory/{InventoryItemTest,StockOperationTest,StockConcurrencyTest}.php`; `tests/Feature/Catalog/{CategoryTest,ProductTest,RecipeTest,AddonTest,CatalogLifecycleTest}.php`; `tests/Feature/Task3AccessTest.php`; `resources/js/lib/catalog/form-values.test.ts`                                                                               | Existing contract/navigation tests; reuse current User factory/Team setup. Add small domain factories only where tests need them.                                            |
| Evidence                   | `docs/verification/2026-10-30-task-3-catalog-inventory.md`                                                                                                                                                                                                                                                                                                                                                      | Parent Task 3 checklist and progress summary, only after implementation is verified                                                                                          |

Do not create empty abstractions or edit every file mechanically. Adjacent controller/form classes may share a focused validation helper where constraints actually repeat. Preserve these public action boundaries even if implementation details are consolidated.

## Shared interface decisions

Apply these contract amendments in 3.1 and consume them consistently. Document why automatic stock creation changes the existing input derived from CatalogItem.

- `InventoryItem` keeps `id/name/kind/unit/balance/revision/shortagePolicy` and adds `active: boolean`, `lowStockThreshold: DecimalQuantity | null`, and `stockState: 'negative' | 'zero' | 'low' | 'positive'`. The label for positive is In stock; negative/zero take precedence over low.
- `InventoryItemInput` for public creation is `{name, kind: 'ingredient' | 'supply', unit, active, lowStockThreshold}`. Do not narrow the returned InventoryItem's four kinds by deriving it from this restricted create input. `InventoryItemUpdateInput` is `{name, active, lowStockThreshold, expectedRevision}`; for product-owned stock the server rejects changes to name/active and only accepts threshold changes with current name/active retained. Kind/unit are immutable.
- Inventory filters become `{query, kind, stockState, active}` plus pagination in the URL. `InventoryIndexProps` adds `selectedItem: InventoryItem | null` and `movements: PageSlice<InventoryMovementRow> | null`; `InventoryMovementRow = StockMovement & {itemName: string; actorName: string}`. Select a panel with `itemId` and paginate it with `movementPage`; a null item gives null movements. Actor/item display names are captured at movement creation.
- `CatalogCreateInput` contains common editable `name/sku/categoryId/active/priceMinor/allowedAddonIds` plus discriminated kind fields: retail `{kind:'retail', lowStockThreshold}`; food `{kind:'food', components}`; direct add-on `{kind:'addon', stockBehavior:'direct', lowStockThreshold}`; recipe add-on `{kind:'addon', stockBehavior:'recipe', components}`. Browser input never includes stockItemId. `CatalogItemInput` aliases this create shape for the original endpoint dictionary.
- `CatalogUpdateInput` is the distributive create union without lowStockThreshold, plus `expectedRevision: Revision`. It still carries kind/stockBehavior for full replacement validation, but changing them is rejected. Threshold editing is Inventory's responsibility after creation. The returned CatalogItem remains the original discriminated shape including server-owned stockItemId/revisions.
- `RecipeInput.expectedRevision` means the parent CatalogItem revision; both whole-product and dedicated-recipe saves compare it and advance catalog and recipe revisions when components change. One main editor Save posts the complete product update.
- `Category` adds `revision`; `CategoryInput` remains `{name,active}`; `CategoryUpdateInput` adds expectedRevision. Unique names use a stored normalized key. Archive checks active products.
- `CatalogAvailability = {status:'inactive' | 'available' | 'warning' | 'blocked'; stock: InventoryItem | null; issues: ContractIssue[]}`. `CatalogListItem = CatalogItem & {availability: CatalogAvailability}`; catalog index uses `PageSlice<CatalogListItem>`. Editor props add `availability: CatalogAvailability | null`, and inventory choices add active state. Include active choices plus current inactive references so the editor never drops a retained relationship silently.
- Dependency failures use dotted Inertia field errors with readable affected names. Editor/inventory props may expose `activeDependents: {id:number; name:string}[]` for the selected record. Never return dependents from other stores. Generic stale-write error key: `expectedRevision`; show “This item changed. Reload before saving.” while preserving local inputs.
- `Quantity::parse(string $value, string $unit, bool $allowNegative = false): int` accepts plain decimal input with at most three fractional digits, normalizes to scaled integer, rejects exponent/grouping/extra precision/fractional each/range overflow. `Quantity::format(int $scaled): string` emits three decimals. `Quantity::add(int $a, int $b): int` and `Quantity::multiply(int $scaled, int $count): int` check contract bounds before arithmetic. Callers enforce positive/nonzero requirements; errors are converted to field validation or `numeric_range_exceeded` as appropriate.
- `StockDemand::calculate(Team $store, array $lines): array` consumes the existing CartLine-shaped `{id, productId, quantity, addonIds}` list, revalidates store/product/selection/quantity, and returns an inventory-ID-sorted list of existing StockEffect objects. It computes demand without saving anything. Money totals and quotes remain Tasks 4/5.
- `StoreCatalogTransaction::run(Team $store, Closure $write): mixed` is the common transaction boundary for inventory metadata, categories, products, recipes, and add-on assignments. On row-locking databases, lock the store row before reading dependencies. On SQLite, restart the whole transaction after busy/locked/snapshot-write conflicts, at most three total attempts, re-reading dependencies each time. Never retry a stale expectedRevision with a replacement value. All related mutations must use this boundary; balance-only operations use their conditional item update separately. Exhausted transient conflicts return a readable retry/reload error with no partial writes.

All management writes use Inertia redirects/error bags, not checkout JSON envelopes. Successful creates/updates redirect to scoped editor or list; never generate routes with an unrelated current store. Existing context fields remain unchanged.

## Task 3.1: Editable inventory records and stock-state presentation

**Deliverable:** Owner creates and edits ingredient/supply metadata, optional thresholds, and active status; inventory list and URL filters work with real persistence. Zero stock is explicit. This slice includes its own schema/contracts and UI.

**Files:** Contract alignment and Inventory slice from file map; inventory page/item dialog, navigation helpers, QuantityTest, InventoryItemTest, Task3AccessTest. Introduce use-unsaved-changes when its first form needs it.

**Interfaces:** `SaveInventoryItem::handle(Team $store, User $actor, array $input, ?InventoryItem $item = null): InventoryItem`; `InventoryData::item(InventoryItem $item): array`. GET `/inventory` → `inventory.index`; POST `/inventory/items` → `inventory.items.store`; PATCH `/inventory/items/{inventoryItem}` → `inventory.items.update`. Store scoped numeric item ID.

- [ ] Update contract/type/fixture expectations for the shared amendments. Keep fixture labels and prior 12 contract scenarios. Add negative type cases: public ingredient creation cannot claim retail kind or a balance; catalog creation cannot supply stockItemId; catalog updates require expectedRevision.
- [ ] Write QuantityTest and InventoryItemTest cases: `0.125g → 125`, `2 each → 2000`, canonical formatting, reject `0.0001`, fractional each, exponent notation, overflow, blank name, invalid unit, negative threshold, and wrong-unit precision. Test thresholds null/zero/positive, including equality: balance `2.000`, threshold `2.000` is low; zero is zero; negative is negative.

```php
// QuantityTest::test_exact_measured_quantities_and_boundaries
$this->assertSame(125, Quantity::parse('0.125', 'g'));
$this->assertSame('0.125', Quantity::format(125));
// Separate dataset cases assert ValidationException for '0.0001', '1e3',
// '0.5' with unit each, and '9007199254740.992'.
```

- [ ] Write Task3AccessTest as a shared endpoint matrix extended with each later slice: guest login redirect, unverified verification redirect, owner success, admin/member/outsider 403, owner-A/member-B denial, forged foreign resource/reference 404. Include direct route store different from session currentTeam and selected history item from another store.
- [ ] Run `php artisan test --filter='QuantityTest|InventoryItemTest|Task3AccessTest'` and `npx vp test run`; observe expected new-test failures, not unrelated setup errors.
- [ ] Implement inventory migration with team FK, kind/unit, active, nullable threshold_thousandths, zero balance_thousandths, revision counter, timestamps, and store/list indexes. Implement exact Quantity operations, StoreCatalogTransaction, and metadata action; never accept balance on metadata writes. Apply expectedRevision comparison on updates and leave balance unchanged. Test retry callbacks re-read state and that exhausted conflicts cannot leave partial metadata writes.
- [ ] Build owner-scoped routes, form requests, controller, serialization, and inventory page with 25-row pagination, name search, kind/status/active filters, empty/no-results states, and create/edit dialog. Filter changes reset page and persist in query parameters. Add Inventory navigation only now; generate helpers with `php artisan wayfinder:generate --with-form`.
- [ ] Implement dirty-form guards for close/cancel and Inertia navigation/store switching; native beforeunload only while dirty. A rejected validation retains inputs. Saving/discarding clears the guard; switching stores must not carry fields to the next store. Use existing dialog focus behavior.
- [ ] Re-run focused PHP tests, `npm run types:check`, and `npx vp test run`. Browser-check create → reload → edit threshold → filter and a foreign-store URL. Record evidence. Commit only reviewed task files if commits are part of the selected implementation workflow; suggested message `feat: add scoped inventory management`.

## Task 3.2: Opening stock, receipts, corrections, and movement inspection

**Deliverable:** The owner can change stock through auditable atomic operations and inspect per-item history. Repeated stale submissions cannot duplicate movements.

**Files:** Stock operation area, inventory stock dialogs/movement panel, InventoryController/Data/props, StockOperationTest and StockConcurrencyTest.

**Interfaces:** `RecordStockOperation::handle(Team $store, User $actor, InventoryItem $item, string $kind, string $quantityOrDelta, string $reason, string $expectedRevision): StockMovement`. POST `/inventory/receipts` → `inventory.receipts.store`; POST `/inventory/adjustments` → `inventory.adjustments.store`. Receipt argument is positive quantity; adjustment is signed nonzero delta. Per-item history is read through GET `/inventory?itemId=…&movementPage=…`.

- [ ] Write feature tests for zero → receipt `250.000g` → adjustment `-5.000g`, with exact before/delta/after, actor, reason, source operation and revision. Test stock changes on inactive records, reason required, empty/zero/negative receipt, fractional each, overflow, negative direct-stock result blocked, and negative ingredient/supply result allowed. Direct-stock test fixtures create internal retail-kind records without exposing public creation.

```php
// StockOperationTest::test_receipt_appends_an_atomic_movement
$this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'balance_thousandths' => 250000]);
$this->assertDatabaseHas('stock_movements', [
    'inventory_item_id' => $item->id, 'before_thousandths' => 0,
    'delta_thousandths' => 250000, 'after_thousandths' => 250000,
]);
$this->assertDatabaseCount('stock_operations', 1);
$this->assertDatabaseCount('stock_movements', 1);
```

- [ ] Test same original request/revision twice: first applies, second returns expectedRevision error, balance and both table counts unchanged. Simulate response loss by discarding first response and reposting the original request. Force movement insertion failure and assert operation/balance/revision all roll back. Test rename after operation preserves movement name/unit snapshots; no PATCH/DELETE history route exists.
- [ ] Add bounded concurrency coverage on an isolated file-backed SQLite test database with independent connections/processes: two writers use the same revision; exactly one succeeds and there is one operation/movement. Record and assert the loser's handled stale/busy outcome. Never replace the application's configured database file. Repeat against the deployment database engine before claiming that engine is verified.
- [ ] Run `php artisan test --filter='StockOperationTest|StockConcurrencyTest|Task3AccessTest'`; observe new failures. Implement migrations/models/action with transaction, bounds checks, conditional balance/revision update, and movement insertion. On zero-row update reject; on transaction retry re-read but keep the original expectedRevision. Use operations for source IDs, not future sale tables. Snapshot historical actor ID/name without cascading user deletion, and test history still renders after the live actor row is absent.
- [ ] Build receive and adjustment dialogs showing unit, current balance, delta and resulting balance. Require reasons; distinguish negative ingredient warnings from direct-stock errors. Disable pending submit. On lost response preserve old request/revision, show uncertainty, and reload balance/history before offering another operation; never blindly resubmit with a fresh revision.
- [ ] Implement the paginated, read-only movement panel with time, actor, reason, before/delta/after and source. Keep global Stock History navigation absent. No fixture fallback on request failure.
- [ ] Run focused tests, frontend/type checks, and browser-check receipt, negative warning, direct-stock rejection, duplicate-submit handling, and movement reload. Suggested commit `feat: record auditable stock operations`.

## Task 3.3: Categories and directly stocked retail catalog

**Deliverable:** The owner creates a retail product and its stock automatically, edits price/details, deactivates/reactivates it, and reaches its stock operations. Categories remain nested within Catalog.

**Files:** Catalog slice migration/models/actions/requests/controllers/data; products pages/item form/availability badge; categories page; form-values helper/test; category/product/lifecycle/access tests; shell navigation.

**Interfaces:** `SaveCategory::handle(Team $store, array $input, ?Category $category = null): Category`; `SaveCatalogItem::handle(Team $store, User $actor, array $input, ?CatalogItem $item = null): CatalogItem`; `CatalogData::item(CatalogItem $item): array`. Routes from contract: products.index/create/edit/store/update and categories.index/store/update. No DELETE endpoints. Introduce CheckActiveDependents for category protection and extend it in later slices.

- [ ] Write tests for required/normalized SKU, case-insensitive duplicates in one store including inactive items, same SKU allowed in another store, valid zero/large centavo price, invalid fractional/negative/unsafe prices, optional category, inactive/foreign category rejection, type mutation denial, stale update and category revision errors. Test category deactivation with active products fails and lists scoped dependents.

```php
// ProductTest::test_retail_creation_owns_one_zero_balance_stock_record
$this->assertSame('retail', $product->kind);
$this->assertSame($product->team_id, $product->stockItem->team_id);
$this->assertSame(0, $product->stockItem->balance_thousandths);
$this->assertSame('each', $product->stockItem->unit);
$this->assertDatabaseCount('stock_movements', 0);
```

- [ ] Assert creation is atomic: one catalog row and dedicated stock row, rollback both on failure, no caller-selected stockItemId, no record sharing. Product name/active changes synchronize owned stock without movements; Inventory cannot independently rename/reactivate owned stock. Threshold edits preserve balance/history. Price/name edits advance product revision, not balances.
- [ ] Run `php artisan test --filter='CategoryTest|ProductTest|CatalogLifecycleTest|Task3AccessTest'`; implement schema with store-normalized SKU uniqueness, unique stock ownership, category FK, integer price and revisions; wire CRUD under owner/store middleware. Return existing CatalogItem shape, keeping stockItemId server-owned. No food/add-on create choice until its slice is functional.
- [ ] Build searchable/filterable/paginated catalog and one reusable product form with PHP price text converted to exact centavos. `parsePriceMinor(text: string): number | null` and `formatPriceMinor(value: number): string` live in form-values.ts; test `150.25 → 15025`, `0 → 0`, rejection of three decimals/unsafe totals, and roundtrip boundary values without floating multiplication.
- [ ] Build nested category list/create/edit and dependency errors. Product create includes optional threshold; edit links to its inventory record for threshold/opening stock. Preserve URL filters and active selection; catalog editing keeps Catalog navigation active, including nested category management. Generate helpers and extend navigation tests.
- [ ] Apply dirty-form protection and field-error associations. Test list empty vs filtered-empty, server validation retaining entered values, and successful save resetting dirty state. Browser-check water → stock receipt 2 each → reload product stock status and ₱50.00 price.
- [ ] Run focused PHP/frontend/type checks. Suggested commit `feat: add retail catalog and categories`.

## Task 3.4: Food recipes with persisted component editing

**Deliverable:** Owner creates food with ingredient/supply requirements and sees truthful availability without a finished-goods balance.

**Files:** Recipe migration/model/action/controller/request; recipe-editor; extend catalog form/controller/data and inventory dependency checks; RecipeTest/CatalogLifecycleTest/access tests.

**Interfaces:** `SaveRecipe::handle(Team $store, CatalogItem $item, array $components): void` is called inside the parent save transaction after parent revision validation; it validates/scopes/replaces component rows and advances recipe revision on material change. Dedicated PATCH `/products/{product}/recipe` → products.recipe.update delegates through the same transaction/revision rules. `CheckActiveDependents::forInventory(Team $store, InventoryItem $item): array` returns active dependent catalog `{id,name}` entries.

- [ ] Write RecipeTest using burger requirements `100.000g` beef, `10.000g` sauce, `0.125g` seasoning, `1.000 each` packaging. Assert persisted exact quantities/units after reload, no stock row for food, and no movements when recipes change. Assert stale expectedRevision rejects both whole-form and dedicated-recipe writes atomically.
- [ ] Cover duplicate component IDs, zero/negative demand, excess decimals, fractional each, wrong unit, foreign/inactive references, retail/add-on stock as components, and recursive/product IDs. Active empty recipes reject; inactive empty drafts save. Missing/inactive dependencies block activation; ingredient shortages only warn. Changing recipe bumps catalog+recipe revisions; unchanged save does not fabricate a recipe change.

```php
// RecipeTest::test_food_has_components_not_finished_stock
$this->assertNull($burger->stock_item_id);
$this->assertSame(125, $burger->components()->where('inventory_item_id', $seasoning->id)->firstOrFail()->quantity_thousandths);
$this->assertDatabaseCount('stock_movements', $movementCountBeforeRecipeSave);
```

- [ ] Test ingredient/supply deactivation with an active recipe is rejected with dependent names; deactivating parent first permits it. Editing the inactive parent retains old inactive references visibly, adding a new inactive reference rejects, and reactivation rejects until repaired. Test concurrent dependency change versus activation/deactivation with database-appropriate serialization and bounded handling.
- [ ] Run `php artisan test --filter='RecipeTest|CatalogLifecycleTest|Task3AccessTest'`; implement recipe persistence and dependency protection transactionally, avoiding check-then-write races. Reuse one validation path for product saves and dedicated recipe updates. Update inventory edits to consult the guard.
- [ ] Build rows with searchable ingredient/supply selection, locked base unit, decimal quantity, remove action, and dotted field errors. Exclude already selected choices; server still rejects duplicates. Include retained inactive components in edit state and identify the repair needed. Keep one product Save for details and recipe together.
- [ ] Return food availability for one serving from exact requirements: show shortage amounts/unit and warning text, never a made-up food stock count. A valid negative ingredient does not disable the food product. Show inactive state separately from stock warnings.
- [ ] Run focused PHP/type/frontend checks and browser-check create ingredients + supply → burger recipe → reload → edit component → stale edit → dependency deactivation. Suggested commit `feat: add food recipe management`.

## Task 3.5: Add-ons and shared-component demand

**Deliverable:** Direct and recipe add-ons can be created and assigned to products; shared demand is proven with the contract basket, ready for later cart/quote integration.

**Files:** Allowed-add-on migration, addon-selector; extend catalog save/types/data/form/recipe handling, dependency guard, StockDemand service; AddonTest, StockDemandTest, CatalogLifecycleTest and access tests.

**Interfaces:** `CheckActiveDependents::forAddon(Team $store, CatalogItem $addon): array`; `StockDemand::calculate(Team $store, array $lines): array` as defined above. Extend products CRUD; add no POS routes or cart UI. Parent form submits normalized allowedAddonIds.

- [ ] Write AddonTest: cheese direct add-on creates dedicated each stock at zero; extra-beef recipe add-on consumes `50.000g` and owns no finished stock. Retail/food can select active same-store add-ons; addon-owned add-ons, duplicate IDs, foreign/inactive selections, recursive components, and stock-behavior changes reject. Deactivating an add-on used by an active product rejects; retained inactive links do not disappear.
- [ ] Test inactive parent reactivation checks component AND allowed-addon validity. Deactivating an ingredient used by an active recipe add-on rejects even if no food currently selects that add-on. Optional out-of-stock add-on must not block the parent catalog row by itself.
- [ ] Write StockDemandTest seeded from the contract example using existing CartLine shape. Test mixed lines retaining different selections, shared components, aggregate direct-stock boundaries, duplicate/invalid addon selections, huge counts, overflow during multiplication/addition/projected balance, fractional sold counts, and foreign/inactive products. The service validates inputs; TypeScript shapes do not enforce them.

```php
// StockDemandTest::test_contract_basket_aggregates_without_mutating_stock
$effects = collect($service->calculate($store, $mixedLines))->keyBy('inventoryItemId');
$this->assertSame('350.000', $effects[$beef->id]['demand']);
$this->assertSame('-100.000', $effects[$beef->id]['after']);
$this->assertSame('0.375', $effects[$seasoning->id]['demand']);
$this->assertSame('-1.000', $effects[$packaging->id]['after']);
$this->assertSame('1.000', $effects[$water->id]['after']);
$this->assertSame('1.000', $effects[$cheese->id]['after']);
$this->assertDatabaseCount('stock_movements', $movementCountBeforeCalculation);
```

- [ ] Run `php artisan test --filter='AddonTest|StockDemandTest|CatalogLifecycleTest|Task3AccessTest'`; implement allowed-addon constraints/transactional updates, add-on form sections, and dependency guard. Aggregate all demand by inventory ID with Quantity bounds checks before classifying block/warn; no stock mutation, quote persistence, or checkout endpoint.
- [ ] Reuse the demand service for food/recipe-add-on one-unit availability where applicable; do not create contradictory arithmetic paths. Keep availability queries bounded for the 25-row catalog page and eager-load needed relationships. Selection lists are scoped and active plus retained references, never cross-store or the entire future POS suite.
- [ ] Browser-check direct cheese + recipe extra beef → product assignment → reload → remove/deactivate/reactivate dependencies. Test clear empty addon choices, long names, keyboard selection and validation error placement. Run existing contract tests to ensure no accidental payment/recovery contract drift.
- [ ] Run focused checks and save evidence. Suggested commit `feat: support add-ons and aggregate recipe demand`.

## Task 3.6: Integrated management verification and handoff

**Deliverable:** Verified Task 3 workflows, honest limits, updated parent checklist, and clear Task 4 inputs. This is integration verification, not a postponed pass for basic usability.

**Files:** All Task 3 tests as needed for actual gaps; evidence document, parent checklist, progress summary. Avoid speculative tests that merely mirror components.

- [ ] Run the complete acceptance walkthrough on isolated local data: category → water at 5000 centavos → receipt 2; ingredients/supply → opening receipts; sauce 0 then reasoned adjustment -5; burger recipe → cheese add-on + receipt 3 → extra-beef recipe add-on → allowed assignments. Reload every completed stage and verify persisted values. Exercise a stock correction and its movement panel.
- [ ] Verify the aggregate basket through the service test (no POS UI yet): beef `350.000` demand → `-100.000`; sauce `30.000` → `-35.000`; seasoning `0.375` → `0.625`; packaging `3.000` → `-1.000`; water/cheese each `1.000` remaining. Direct water quantity 2 fits, 3 exceeds; cheese demand 3 fits, 4 exceeds. No sale/payment/movement is produced by calculation.
- [ ] Run `php artisan test`; `php vendor/bin/phpstan analyse --memory-limit=512M`; `php vendor/bin/pint --test`; `npx vp test run`; `npm run types:check`; `npm run check`; `npm run build`. Generate Wayfinder first if route sources changed. Fix introduced failures; report remaining pre-existing failures with exact current evidence. A build does not establish domain correctness.
- [ ] Inspect 1440x900 desktop, 1024x768 landscape tablet, 768x1024 portrait tablet, and 390x844 phone management in light/dark modes. Verify keyboard focus, dialog escape/return, readable field errors/statuses, zoom/long names/large amounts, touch targets, and no page-wide horizontal overflow. Inspect save pending/failed/slow states and validation retention.
- [ ] Verify dirty product/recipe/inventory forms on cancel, browser navigation, refresh, and store switch: no silent loss on cancelable navigation; explicit discard leaves no data in the next store. Filters/back navigation and pagination remain understandable. No link points to an absent module; Catalog and Categories share active navigation correctly.
- [ ] Verify a second store and owner/admin/member/outsider cases, including forged category/component/add-on IDs and history-panel itemId. Verify movements remain unchanged after catalog/recipe/name changes, no destructive domain endpoint, and no fixture data rendered as connected state.
- [ ] Write evidence with commands/results, browser checks, database engine, concurrency coverage, known failures and manual limitations. Mark each parent Task 3 checkbox only when its proof passes. Update progress summary; do not claim checkout, payments, recovery, refunds, dashboard aggregates, or global history complete.
- [ ] Review the final diff for scope and contract consistency. Commit only reviewed task files under the selected workflow; suggested message `docs: record task 3 verification and cart handoff`. No automatic merge, deployment, or production migration.

## Task 4 handoff and completion gate

Task 4 receives real store-scoped CatalogItem/InventoryItem data, persisted recipes and allowed add-ons, stable stock ownership, revision fields, exact StockEffect aggregation, and the existing checkout shell. It still must build cart line identity/merging, picker/cart interactions, and immediate price totals. Task 5 owns authoritative quotes, completion, payment/attempt idempotency and recovery. Task 7 owns global stock history, dashboard aggregates and business settings.

Task 3 is complete only when an owner can create the retail/recipe/add-on example, receive and correct stock, reload the saved data, inspect immutable movement history, and demonstrate access/precision/dependency/concurrency checks. A fixture-only screen or a successful build does not satisfy that gate.

Implementation approach recommendation: execute these six slices sequentially in the current chat with one independent final review if authorized. They share schema and contracts; parallel implementation would increase coordination and conflicting edits. An agent-per-slice workflow is an alternative if the user prefers independent review gates throughout. Neither approach is selected by this planning document.
