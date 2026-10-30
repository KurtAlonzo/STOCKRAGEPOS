# STOCKRAGE Task 3 — Catalog, recipes, and minimum stock design

Status: Implemented locally after user authorization on 2026-10-30; see [verification](../../verification/2026-10-30-task-3-catalog-inventory.md).

Sources: [parent Task 3](../plans/2026-10-29-stockrage-frontend-action-plan.md), [POS v1 contract](../../contracts/pos-v1.md), and [Task 2 evidence](../../verification/2026-10-30-task-2-shell.md). Existing confirmed operating rules remain authoritative except for the explicit contract amendments below.

## Intent and scope

Give the owner a persisted catalog and stock setup that the later mixed cart can consume: directly stocked retail products, ingredients, supplies, recipe food, directly stocked add-ons, and recipe add-ons. The owner can enter opening stock, receive more, correct a balance with a reason, inspect the resulting movements, and reload without losing saved data.

Task 3 includes its Laravel models, migrations, authorization, validation, actions, Inertia screens, generated routes, tests, and browser verification. It does not implement the POS cart, checkout quotes/completion, payments, draft recovery, sales/refunds, real dashboard aggregates, or the full Task 7 stock-history experience. No supplier, purchasing, costing, barcode, image-upload, tax, unit-conversion, or variant subsystem is added.

## User-confirmed decisions — 2026-10-30

1. Each inventory item may have an optional low-stock threshold in its base unit. Unset means no low-stock alert.
2. Implement connected slices: each workflow gets its screen, Laravel persistence, and tests before moving to the next workflow.
3. Saving a retail product or directly stocked add-on automatically creates its dedicated zero-balance inventory record. Opening stock is received afterward.
4. Block deactivation of an ingredient, supply, or add-on used by an active product. Show the affected products; the owner must edit or deactivate them first.

## Approaches considered

- **Chosen: connected slices.** Begin with inventory metadata and movements, then retail, recipes, and add-ons. This exposes integration mistakes early and delivers usable intermediate workflows.
- Backend first would delay feedback on forms and stock setup. Frontend first would postpone proof of persistence and authorization. The user chose connected slices over both alternatives.
- Dedicated automatic direct-stock records were chosen over manually creating and linking inventory. A direct product owns one record; shared components belong to recipes.
- Dependency blocking was chosen over silently making dependent products unavailable after deactivation.

## Baseline and reuse

The inspected repository contains business TypeScript contracts and fixtures but no catalog/inventory domain models, migrations, or production routes. Task 2 provides owner access, store switching, React layouts, branding, navigation matching, and a local checkout-shell preview. Reuse them. Existing PHP tests use SQLite in memory; Vite Plus tests use the Node environment. Do not add a second test runner or UI kit.

Keep the existing Laravel/Inertia/React/TypeScript stack, numeric resource IDs, Team internals, camelCase business payloads, PHP centavos, Asia/Manila business dates, and UTC transport timestamps. Business routes require auth, verified, and `EnsureTeamMembership:owner`. Scope every resource query and referenced ID to the route store, regardless of session currentTeam. Known inaccessible store: 403; foreign resource beneath an accessible store: 404.

The Stock Management and Add Stock Item reference PNGs were inspected during planning. Retain their clear item types, stock-state labels, red primary actions, and recipe rows. Confirmed contracts supersede the mockups' kg/bottle units and blanket nonblocking-shortage copy. Management works on phones; checkout's viewport restriction does not apply here.

## Proposed rules to make implementation unambiguous

These are implementation recommendations, not additional user interview answers.

### Inventory, amounts, and stock status

- Persist measured quantities as signed integer thousandths in BIGINT columns, transporting canonical three-decimal strings. Use bounded integer arithmetic; validate before addition/multiplication can overflow. Keep the contract maximum absolute scaled quantity `9007199254740991` and nonnegative money maximum `9007199254740991` centavos. Prices are integer centavos, not floating point.
- Base units remain `each`, `g`, `ml`; each quantities must be integral. Ingredient/supply units are selected at creation. Kind, unit, direct-stock ownership, catalog kind, and add-on stock behavior are immutable after creation in this release. Correct a wrong type by deactivating it and creating the right item; do not implement conversion workflows.
- New stock starts at zero. Opening stock and subsequent receipts are positive receipt movements; adjustments use a nonzero signed delta and a required trimmed reason. Direct-stock adjustments cannot produce a negative balance; ingredient/supply adjustments may, with an explicit warning showing the resulting balance. This extends the agreed shortage distinction consistently to corrections.
- Threshold is null or a nonnegative quantity in the item's unit, with the same precision/range checks. Stock labels have precedence: Negative (< 0), Out of stock (= 0), Low stock (positive and at or below a configured threshold), In stock (otherwise). Zero/negative still display without a threshold. Thresholds are alerts, never extra sale blockers.
- Inventory metadata can be edited independently of balance. Product-owned stock gets its name/active state from its product; its threshold remains editable through Inventory. Ingredients/supplies have their own name and active state. Receipts/adjustments remain available for inactive records so leftover stock can be reconciled; inactive records cannot be newly selected in recipes.

### Catalog and recipes

- Names are trimmed nonempty text, max 255 characters. SKU is required, trimmed, normalized to uppercase ASCII, 1–64 characters from letters/digits/dash/underscore/dot, unique per store including inactive records. Show that normalization in the form. Category is optional; names are trimmed and unique case-insensitively within a store, max 100 characters. Prices may be zero under the existing contract; label zero prices clearly.
- Categories live inside Catalog. Deactivating a category with active products is blocked with a dependency list; historical/inactive associations remain. Product/category reactivation validates references again.
- Product save creates/updates its recipe and allowed add-on selections atomically. The main editor has one Save action; no hidden second save for recipe rows. Preserve the proposed dedicated recipe endpoint for clients that explicitly use it; both paths call the same recipe validation/write action.
- Food and recipe add-ons require at least one component when active. Inactive drafts may have an empty recipe. Components reference active same-store ingredients/supplies only; use their actual base units, positive quantities, and unique component IDs. No retail stock, other products, recipes, or add-ons may be recipe components.
- Allowed add-ons may be assigned to retail or food products. An add-on cannot have add-ons. Reject duplicate selections; normalize IDs in sorted order. Direct add-ons own stock; recipe add-ons consume their components. Each selected add-on applies once per sold product unit, as already confirmed.
- Deactivation checks active dependents, including recipe add-ons that use an ingredient/supply. Deactivating an add-on never silently removes its assignment. Inactive products may retain existing inactive references, but cannot introduce new inactive references; activating them requires every dependency to be active and valid. Display retained inactive references and the reason activation is blocked.
- Archive means reversible `active=false`, not deletion. No delete routes for domain records or movement editing. Preserve category/product/recipe/stock relationships. Deactivation of a retail/direct add-on synchronizes its owned stock's active state; direct stock cannot be independently reactivated behind an inactive product.

### Persistence, concurrency, and history

- Use separate `inventory_items`, `stock_operations`, `stock_movements`, `categories`, `catalog_items`, `recipe_components`, and `catalog_allowed_addons` tables. Each domain record is store-scoped directly or through a constrained parent. Direct stock ownership is unique. Foreign keys must not cascade-delete stock history.
- `stock_operations` records receipt/adjustment type, actor, reason, store, and timestamp. One operation and its movement are inserted atomically with the balance/revision update. Movement `sourceType` is receipt/adjustment and `sourceId` is the operation ID. Movement captures before/delta/after/unit/actor/time and item/actor name snapshots for a readable audit after renames. Historical actor ID/name are immutable snapshots, without cascading user deletion; reading history never requires a surviving user row.
- Return opaque revision strings; persist integer revision counters. Inventory writes and product/category edits require expectedRevision. Stale writes return a readable Inertia validation error, preserve unsaved inputs, and offer reload; do not silently overwrite or automatically retry with the newest revision.
- Balance writes use an atomic conditional update on ID/store/expected revision inside the movement transaction. A stale revision updates zero rows and creates no operation or movement. This must work on SQLite, where `lockForUpdate` alone is insufficient. Catalog/category/recipe/inventory-metadata mutations share a store transaction boundary: lock the store row before reading dependencies on row-locking databases. On SQLite, a busy/locked/snapshot-write conflict must abort and restart the entire transaction, re-reading dependencies, with at most three total attempts; it must never resume a partly evaluated write. Stale client revisions are not automatically retried. Test deactivation versus activation races. Balance-only operations use their item revision and need not take the catalog store lock.
- Disable double submission in forms. On an uncertain stock write, preserve the original revision and input, explain uncertainty, and refresh balance/movements before a new action. A repeated request with the old revision cannot apply the delta twice. Task 3 does not build checkout's durable attempt engine.
- Name/price/category/active/add-on changes advance catalog revision. Recipe changes advance both catalog and recipe revisions. Stock changes advance inventory revision only. Metadata writes also advance their relevant revision; later quotes will re-read these inputs.

### Stock visibility and demand calculation

- Catalog rows receive explicit availability summaries; do not infer from omitted data. Retail/direct add-on rows show their real stock balance and status. Food/recipe add-ons show recipe requirement warnings for one unit, not a fictitious finished-product quantity.
- Negative or insufficient recipe ingredients/supplies warn but do not make a valid active food product unavailable. A missing/inactive dependency is a configuration error and does block availability. Optional add-on shortage does not make its parent unavailable unless that add-on is selected.
- A reusable server `StockDemand` service accepts validated same-store cart-line shapes and aggregates exact demand by inventory ID across all products and add-ons. It returns the existing `StockEffect` shape. It does not create quotes, reserve stock, or mutate balances. Task 3 tests the parent's aggregate-demand acceptance requirement; Tasks 4/5 consume this service later.

## Required contract amendments during implementation

Update the contract document, types, fixtures, and negative type checks together; do not silently implement payload drift.

- Inventory: add `active`, `lowStockThreshold: DecimalQuantity | null`, and server-derived `stockState`. Add metadata PATCH with `expectedRevision`; extend filters with active and low-stock selection. Direct stock creation is internal to product save, not a public free-floating inventory form.
- Catalog writes: separate create/update input from returned CatalogItem so the browser does not supply `stockItemId`. Update input adds expectedRevision; kind/stockBehavior are immutable. Add create-time direct-stock threshold input; edit it through Inventory thereafter. Add category revision/update input and dependency-error fields.
- Pages: add `CatalogAvailability` and row/detail availability; include inactive current dependencies in editor props, without offering them as new choices. Recipe rows need component active state and unit; direct products expose their owned inventory summary.
- Inventory history: add a scoped per-item movement list to Inventory props using `PageSlice<StockMovement>` plus display names. This is the minimum audit view. Keep the parent `/stock-history` global route and its full filters for Task 7.
- New route: PATCH `/{current_team}/inventory/items/{inventoryItem}`, name `inventory.items.update`. Existing Task 3 endpoint names remain those in POS v1. Ingredients/supplies use public inventory create; retail/direct add-on records are created by the product action.

## Experience and acceptance

Inventory offers type/status/active filters, search, pagination, add/edit ingredient/supply, receive stock, adjust stock, and a per-item movement panel. Catalog offers search by name/SKU, kind/category/active filters, pagination, nested categories, product create/edit, recipe rows, and allowed add-ons. Preserve filters in URLs and reset page when a filter changes. Start with 25 rows/page; bound server page sizes. Distinguish empty store from no matching results.

Use existing AppLayout, forms, dialogs, alerts, badges, and toast patterns. At phone width, prioritize name/type/status and expose secondary details in cards or a detail panel. Do not add a table/component framework. Mark field errors accessibly, retain entered values on validation failures, guard unsaved navigation including store switch, and offer cancel/discard explicitly. Use text with color; preserve light/dark/system appearance.

Acceptance data is the contract example: water stock 2, cheese 3, beef 250g, sauce -5g, seasoning 1g, packaging 2 each; burger recipe 100g beef + 10g sauce + 0.125g seasoning + 1 packaging; extra beef add-on 50g. Set sauce negative with a reasoned adjustment, not an opening receipt. Two cheese burgers plus one extra-beef burger and one water project beef -100g, sauce -35g, seasoning 0.625g, packaging -1 each, water 1, cheese 1, without writing movements. No sale exists yet.

Verify store/owner isolation for every endpoint and foreign category/component/add-on ID; exact numeric validation and overflow; duplicate SKU; dependency blocking and reactivation; stale writes and response-loss replay; atomic stock movements; no historical edits; URL filters; unsaved edits; 390px phone and tablet/desktop management; both themes. Run existing contract/shell regressions and appropriate PHP, frontend, type, formatting, static-analysis, and build checks. Record current results and baseline failures honestly. No runtime verification is claimed by this planning document.

Execution may choose local component extraction and query/index tuning. Any change to business scope or a confirmed decision must return to the owner. Review the companion execution plan before product implementation.
