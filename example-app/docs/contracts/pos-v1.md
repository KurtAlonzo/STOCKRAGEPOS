# POS v1 contract — catalog, inventory, checkout and recovery

## Task 4 implementation amendments — 2026-10-31

Order preparation now implements `GET /{current_team}/pos` (`pos.index`), `GET /{current_team}/pos/catalog` (`pos.catalog`) and `POST /{current_team}/pos/quotes` (`pos.quotes.store`). Payment recording, completion and attempt lookup are now implemented by Task 5 (see amendments below). Sales management and reversals remain Task 6 work.

- `StockEffect` adds required `inventoryItemName` so projections remain readable after recipes change. Task 5 now accepts validated final payment/change; preparation quotes may still use null payment.
- `PosCatalogPage` carries 25-row `catalog`, page-scoped resolved `addons` and `inventory`, `filters {query,categoryId}`, and `hasProducts` before filters. `PosPageProps` adds business context, categories and the configured enabled-payment-method list. Zero-stock direct products remain selectable; structural configuration errors block selection.
- JSON catalog and quote routes use the trusted `preserve_current_team` route default after owner authorization. Their data always comes from the route store; background requests do not change the selected store.
- Quotes use exact current catalog/recipe snapshots and named aggregate effects inside the store transaction. Direct shortages are blockers; recipe shortages are warnings. Quoting never changes inventory, revisions, stock operations or movements.
- An immutable `{input,quote}` envelope is cached for 300 seconds under `pos:quote:{storeId}:{userId}:{quoteId}`. Cache failure returns 503 `quote_unavailable`; eviction/expiry requires a fresh quote. This is not a durable checkout-attempt record and cannot resolve an already confirmed sale.
- Cart IDs are UUIDs; identity is product plus sorted unique add-ons. Counts are positive whole numbers up to `9007199254740`, bounded by safe money/thousandth arithmetic. Invalid fields retain input and suppress quoting. The browser holds cart state only in memory and guards nonempty navigation.

These implemented routes supersede their proposed status in the historical matrix below. No checkout completion or attempt routes have been added.

## Task 3 implementation amendments — 2026-10-30

Catalog and inventory use the Task 3 design and exported types below. Checkout, payment and attempt lookup are implemented by Task 5. Sales management and reversal endpoints remain Task 6 work. The endpoint matrix includes intended release routes; use implementation amendments for current availability.

- Inventory read records add `active`, `lowStockThreshold` (nullable canonical decimal) and `stockState` (`negative/zero/low/positive`). Optional thresholds are in the item's base unit. Negative and zero take precedence; a positive balance at or below a configured threshold is low. Thresholds do not add sale-blocking rules.
- Public `InventoryItemInput` creates ingredients/supplies only, with `name/kind/unit/active/lowStockThreshold`. Retail/direct add-on records are created automatically and atomically by catalog save, initially at zero. They cannot be shared or selected by a supplied `stockItemId`.
- Add PATCH `/{current_team}/inventory/items/{inventoryItem}`, name `inventory.items.update`, using `InventoryItemUpdateInput {name,active,lowStockThreshold,expectedRevision}`. Kind/unit are immutable. Product-owned name/active state is synchronized by Catalog; Inventory may edit its threshold only. Metadata writes never accept balances.
- `CatalogCreateInput`/`CatalogItemInput` carries common name, SKU, category, active, price and allowed add-on IDs. Direct kinds carry nullable `lowStockThreshold`; recipe kinds carry components; add-ons carry `stockBehavior`. `CatalogUpdateInput` replaces editable fields with `expectedRevision`, omitting threshold and stockItemId. Returned CatalogItem retains server-owned stockItemId/revisions. Type and stock behavior are immutable in this release.
- Category reads add revision; category PATCH uses `CategoryUpdateInput` with expectedRevision. SKU is trimmed uppercase ASCII, unique per store including inactive items. Category names are trimmed and case-insensitively unique per store. Kind/unit/stock behavior cannot be converted by editing.
- Product and recipe saves are atomic. Dedicated `RecipeInput.expectedRevision` compares the parent catalog revision; component changes advance catalog and recipe revisions. Stale writes preserve browser inputs and return an Inertia `expectedRevision` error. Main product editor saves details, components and allowed add-ons together.
- Catalog list rows add `CatalogAvailability {status,stock,issues}`. Status is inactive/available/warning/blocked; direct stock is real, recipe warnings project one serving rather than a finished-goods count. Inactive/missing configuration blocks; valid ingredient shortages warn. Optional unselected add-ons do not block their parent.
- Inventory list filters add `active` and `low` stockState. `itemId` selects a store-scoped read-only movement panel and `movementPage` paginates it. Inventory props add selectedItem and nullable `PageSlice<InventoryMovementRow>`; rows retain StockMovement plus historical itemName/actorName snapshots. Global stock-history filters remain Task 7.
- Deactivation of a component, add-on or category with active dependents is rejected with affected product names. Inactive parents retain existing links, but cannot introduce new inactive references. Reactivation validates every dependency again. Receipts/adjustments can reconcile inactive stock.
- Receipts are positive, adjustments nonzero signed deltas with reasons. Direct-stock corrections cannot go negative; ingredient/supply corrections may, with a projected-balance warning. Operations and movements append atomically with a conditional stock revision update. Replaying an old revision cannot post twice; after an uncertain response, refresh balance/history before a new action.
- Exact thousandths are stored as bounded integers; decimal transport and existing limits remain. The server StockDemand service aggregates shared components without mutating stock. Money remains integer centavos; no tax engine or checkout completion is added.

These amendments supersede the earlier descriptions that derive write payloads directly from returned CatalogItem/InventoryItem and describe all endpoints as unimplemented. The runtime field dictionary is `resources/js/types/{catalog,inventory}.ts`; Task 3 verification is recorded separately.

## Confirmed interview decisions

1. Directly stocked add-ons block when short. Add-ons consuming ingredients/supplies warn and may take those balances negative.
2. Business currency is PHP; money travels as integer centavos. Business timezone is `Asia/Manila`.
3. Sold retail items, food portions, and add-ons use whole positive quantities. Counted stock uses whole quantities. Measured ingredients use decimal strings with up to three decimal places.
4. Cash, GCash, and Maya record payments received outside the application. GCash/Maya require the owner's confirmation of receipt; **references are optional**. This supersedes the earlier interview answer making references required.
5. Initial prices are final selling prices. Do not add or extract tax. Tax treatment remains unresolved and must be confirmed before live use; this does not assert exemption or compliance.
6. Each selected add-on applies once per product unit. Different selections stay on separate lines. Multiple portions of the same add-on per unit are deferred.
7. A supplied duplicate reference within the same store and provider produces a warning. The owner may correct it or continue. Payment references are never idempotency keys or unique sale identifiers.

Retain the parent plan's owner-only access, store isolation, one payment method per sale, server-authoritative completion, recoverable drafts, no offline completion, full-sale correction, immutable history, and no automatic ingredient restoration.

## Technical proposals for the implementer

These are recommended implementation decisions, not additional answers attributed to the user.

### Representation and inventory

- Use existing numeric database IDs for domain resources and store IDs, the team slug for `{current_team}`, UUID strings for cart-line IDs and operation keys, opaque strings for revisions, and UTC ISO-8601 timestamps for transport.
- Use camelCase for new business payloads. Keep existing shared props (`auth`, `currentTeam`, `teams`, etc.) unchanged; business UI says Store while tenancy internals remain Team.
- Transport quantities canonically with three decimal places, including counted balances such as `"2.000"`; enforce integral values for `each`. Start with base units `each`, `g`, and `ml`. No unit conversion. Reject excess precision instead of silently rounding. Balances/deltas may be signed; recipe demand is positive.
- Bound money and calculated totals to nonnegative safe integers (`0` through `9007199254740991` centavos). Bound the absolute quantity in thousandths to `9007199254740991` (maximum magnitude `9007199254740.991` base units); sold counts are positive integers no greater than `9007199254740`. Reject arithmetic overflow, including aggregate demand and resulting balances, with `numeric_range_exceeded`. Calculate measured demand using exact thousandths, never binary floating-point accumulation. No discount, split-payment, or tax arithmetic is added.
- Distinguish sellable `CatalogItem` kinds (`retail`, `food`, `addon`) from inventory records (`retail`, `ingredient`, `supply`, `addon`). An add-on is either directly stocked or has a nonrecursive component list. Food has components rather than a finished-stock balance.
- Normalize add-on IDs by sorting and rejecting duplicates. Line equivalence is product ID plus normalized selections; line ID stays stable through edits. Validate selections against the product's allowed add-ons.
- Aggregate all demand by inventory-item ID, including overlapping recipes and add-ons, before checking balances. Each inventory item has one shortage policy: direct stock blocks; ingredients/supplies warn.

### Quotes, payments, and recovery

- A quote returns authoritative line snapshots, centavo totals, item/recipe revisions, expiry, aggregated projected stock, blocking issues, and warnings with stable IDs. Use a five-minute expiry as an implementation default. Quotes do not reserve stock.
- Initial cart quotes may omit payment details. The final review quote includes the chosen payment details so a duplicate-reference warning can be acknowledged. Completion binds to that reviewed quote, its inputs, and acknowledged warnings; it recalculates under server control. Changed prices, recipes, availability, payment details, or material warnings require renewed review. Stock is still rechecked atomically.
- Cash requires sufficient integer-centavo tender; change is server calculated. External payment confirmation must be `true`. Empty/whitespace references normalize to `null`; otherwise trim the supplied value. Match duplicates exactly after trimming within store/provider, including reversed sales. Do not silently case-fold or remove characters. Do not impose a uniqueness constraint. Concurrent duplicate references must be detected during completion too, with renewed review if first discovered there.
- Distinguish client states `draft/submitting/confirmed/rejected/unknown` from lookup results `not_found/pending/confirmed/rejected`. `unknown` means the client cannot determine the outcome, not that the sale failed.
- Bind an operation key to authenticated user, store, and the original canonical business request. Same key + same request returns the original result. Same key + different request returns an idempotency conflict. Confirmed replay is resolved before checking quote expiry or changed catalog data.
- Persist an attempt before processing; commit its confirmed result with the sale, payment, snapshots, and stock movements in one transaction. Unique operation constraints and transactional locking prevent concurrent duplication. A worker crash leaves a recoverable pending attempt; safe same-key processing must serialize against existing work and never expire a pending key into permission for another sale.
- After response loss, refresh, or `not_found`, reconcile the original key. Retry only the same original request/key when permitted; never manufacture a new key to escape uncertainty. A corrected cart starts a new attempt only after the old attempt is definitively rejected, or a new sale is explicitly started after confirmation. No online client can guarantee outcomes merely from `navigator.onLine`.
- Retain lookup records and idempotency protection for this release; do not introduce a cleanup/expiry job. Retention and recovery operations can be revisited with evidence later.

### Actions, access, and history

- Use Inertia page props for management pages and JSON for quote, checkout, and attempt lookup. Document management validation redirects separately from JSON error responses.
- Proposed business routes require authenticated, verified store owners. Existing `EnsureTeamMembership` accepts a minimum role; resource queries/policies must also scope every referenced ID to the route's store. Never authorize from a submitted `teamId` or the mutable current-team session alone.
- Use stable machine error codes, readable messages, dotted field paths, and issue IDs. An inaccessible store returns 403; another store's resource under an accessible store returns 404 without leaking its existence. JSON authentication/session/CSRF failures never look like successful sale responses.
- Persist only completed sales and their linked full reversals, not editable carts as sales. Keep original prices, recipe deductions, payments, and timestamps as snapshots. Reversal records external refund confirmation and reason; retail/direct-stock returns restore only explicitly restockable quantities once. Food ingredient/supply usage remains consumed.
- Receipts/stock adjustments append movements with actor, source, reason, before/delta/after quantities, and time. Archive referenced catalog records instead of destroying historical meaning.

## Worked acceptance example

Prices: water `5000`, burger `10000`, directly stocked cheese add-on `1500`, ingredient-consuming extra-beef add-on `3000` centavos.

Use catalog IDs 101/102/103/104 respectively. Use inventory IDs 201 water, 202 cheese, 203 beef, 204 sauce, 205 seasoning, and 206 packaging. This keeps document and fixture references deterministic.

Basket: one water, two burgers with cheese, one burger with extra beef. Each burger consumes `100.000 g` beef, `10.000 g` sauce, `0.125 g` seasoning, and one packaging unit. Extra beef adds `50.000 g` beef per selected product unit.

**Total:** `5000 + 2 * (10000 + 1500) + (10000 + 3000) = 41000` centavos (PHP 410.00). Cash tender `50000` gives `9000` change.

| Inventory       | Before  | Aggregate deduction | After    | Result  |
| --------------- | ------- | ------------------- | -------- | ------- |
| Water, each     | 2.000   | 1.000               | 1.000    | Allowed |
| Cheese, each    | 3.000   | 2.000               | 1.000    | Allowed |
| Beef, g         | 250.000 | 350.000             | -100.000 | Warning |
| Sauce, g        | -5.000  | 30.000              | -35.000  | Warning |
| Seasoning, g    | 1.000   | 0.375               | 0.625    | Allowed |
| Packaging, each | 2.000   | 3.000               | -1.000   | Warning |

Boundary examples: water quantity 2 is allowed and quantity 3 blocks; cheese demand 3 is allowed and demand 4 blocks. Raising the burger price to `11000` makes the same basket total `44000`; it requires a reviewed replacement quote. Raising burger beef consumption to `110.000 g` makes aggregate beef demand `380.000 g` and projected balance `-130.000 g`.

## Payload dictionary

The authoritative field dictionary is the exported TypeScript in resources/js/types/{business,catalog,inventory,sales,pos}.ts. All fields are required unless nullable or explicitly optional; IDs are scoped by store. Inputs are untrusted JSON and must be validated at runtime. Type aliases alone enforce no numeric ranges.

### Proposed endpoint matrix

Every path is prefixed `/{current_team}`; every action requires an authenticated, verified owner and store-scoped IDs. Define route names under `products.*`, `categories.*`, `inventory.*`, `pos.*`, and `sales.*` in the contract; generate helpers only when real routes are implemented later.

| Method and path                                                         | Input -> output                                                   | Transport    |
| ----------------------------------------------------------------------- | ----------------------------------------------------------------- | ------------ |
| GET `/products`; GET `/products/create`; GET `/products/{product}/edit` | filters -> `CatalogIndexProps`; editor -> `CatalogEditorProps`    | Inertia      |
| POST `/products`; PATCH `/products/{product}`                           | `CatalogItemInput` -> redirect to scoped edit/index               | Inertia form |
| GET/POST `/categories`; PATCH `/categories/{category}`                  | category list/`CategoryInput` -> `CategoryIndexProps`/redirect    | Inertia      |
| PATCH `/products/{product}/recipe`                                      | `RecipeInput` -> refreshed editor                                 | Inertia form |
| GET `/inventory`; GET `/stock-history`                                  | filters -> `InventoryIndexProps`/`StockHistoryProps`              | Inertia      |
| POST `/inventory/items`                                                 | `InventoryItemInput` -> refreshed inventory                       | Inertia form |
| POST `/inventory/receipts`; POST `/inventory/adjustments`               | `StockReceiptInput`/`StockAdjustmentInput` -> refreshed inventory | Inertia form |
| GET `/pos`                                                              | filters -> `PosPageProps`                                         | Inertia      |
| GET `/pos/catalog`                                                      | query/categoryId/page -> `PosCatalogPage`                         | JSON         |
| POST `/pos/quotes`                                                      | `CheckoutQuoteInput` -> `CheckoutQuote`                           | JSON         |
| POST `/pos/checkouts`                                                   | `CheckoutCompletionInput` -> `CheckoutCompletionResult`           | JSON         |
| GET `/pos/attempts/{idempotencyKey}`                                    | operation key -> `AttemptLookupResult`                            | JSON         |
| GET `/sales`; GET `/sales/{sale}`                                       | filters/id -> `SalesIndexProps`/`SaleShowProps`                   | Inertia      |
| POST `/sales/{sale}/reversals`                                          | `ReversalInput` with reversal operation key -> refreshed sale     | Inertia form |

Catalog/category removal means archive/deactivate through PATCH. Do not specify destructive deletion of referenced records. Every matrix entry refers to the named payload in its owning type file.

## Error and response protocol

JSON endpoints return structured errors: { error: ContractError, replacementQuote?: CheckoutQuote }. Quote returns 200; completion returns 200 confirmed or 202 pending; authenticated lookup returns 200 with a discriminated result. Validation returns 422 validation_failed with dotted field paths (e.g. lines.0.quantity); numeric overflow uses numeric_range_exceeded. Expiry/change/stock conflict return 409 quote_expired/quote_changed/stock_conflict with a replacement quote when re-review is possible. Changed-payload replay returns 409 idempotency_conflict. Form endpoints use Inertia validation redirects/error bags, not this JSON envelope. Auth/session failures (401,403,419) and unexpected HTML responses cannot be interpreted as success. Another store's resource returns 404 without exposing its data.

Route names: products.index/create/edit/store/update/recipe.update; categories.index/store/update; inventory.index/items.store/receipts.store/adjustments.store/history; pos.index/catalog/quotes.store/checkouts.store/attempts.show; sales.index/show/reversals.store. Catalog, inventory management and POS preparation routes have generated helpers. Global history, checkout completion, attempt lookup and sales routes remain proposed. Successful form writes redirect to their scoped detail/editor or inventory/category index.

Inventory starts at zero; opening balances are receipt movements. Retail and directly stocked add-ons use each and block shortages; ingredients/supplies use their configured unit and warn. Recipes may consume ingredient/supply records only, cannot nest other recipes, and cannot repeat a component ID. Add-ons cannot own add-ons. SKU is unique within a store; inactive or foreign-store references are invalid. Receipt quantities and recipe quantities are positive, adjustments signed with a required reason. Counted units cannot be fractional. New business field names are camelCase; lists carry filters plus PageSlice metadata. CatalogItemInput is a full replacement of editable fields on PATCH; kind changes involving existing stock/history must be rejected rather than silently rewriting history.

## Attempt examples and atomicity requirements

| Event                                     | Expected result                        | Safe next action                                   |
| ----------------------------------------- | -------------------------------------- | -------------------------------------------------- |
| Original valid completion                 | 200 confirmed, or 202 pending          | Keep original request/key until resolved           |
| Concurrent identical request              | Pending or the same sale               | Lookup original key                                |
| Confirmed replay, even after quote expiry | Same immutable sale                    | Display confirmation                               |
| Same key, different request               | 409 idempotency_conflict               | Recover original operation; do not overwrite       |
| Definite domain rejection                 | Rejected with field/issue details      | Correct cart and explicitly create a new attempt   |
| Lookup not_found                          | No current attempt observed            | Retry original key/request; never infer failure    |
| Response lost after commit                | Unknown client state; lookup confirmed | Show existing sale, clear draft only now           |
| Worker crashes while pending              | Durable pending record                 | Serialized same-key recovery under backend control |

Attempt identity is user + store + key. Authenticate every lookup/replay and do not trust currentTeam or body team IDs. Resolve existing confirmed attempts before quote expiry/revision checks. Persist the pending attempt before processing; lock its processing and all affected stock rows in a stable order. Commit the sale, snapshots, payment, movements, and confirmed attempt together. Transaction rollback must leave no partial sale/payment/deductions; only definitive rejection becomes rejected. Recovery must serialize against active processing, not merely rely on an elapsed timeout. Do not delete/expire operation records in this release. Unknown network outcomes keep the exact original request and key through refresh; no automatic new operation is allowed.

Warning acknowledgment is bound to the final quote ID/revision and payment input, not a blanket client boolean. Changed prices/recipes or new shortage/duplicate-reference warnings require another review. Provider references are optional normalized text, not proof of payment, unique constraints, or operation IDs. Detect duplicates within store/provider, including reversed sales. Confirmation must remain true even when reference is null. No payment credentials or proof images are stored.

## Reversal and historical semantics

One full-sale reversal per sale, with its own idempotency key, reason and refundConfirmed=true, records an external refund; it does not transfer funds. An identical reversal retry returns its original result; conflicting repeats reject. Restock only explicit eligible direct-stock quantities, bounded by original deducted quantities and applied once. Never automatically restore food ingredients/supplies. The original sale's monetary/line/payment/recipe snapshots remain unchanged; status is reversed and a linked reversal records the financial correction. All stock corrections append movements with actor, time, source and reason. Business date filtering uses Asia/Manila dates converted to UTC boundaries, with an inclusive start and exclusive next-day end.

## Fixtures and limits of verification

resources/js/fixtures/pos/scenarios.ts is explicitly fixture-only. It contains the mixed basket, long/inactive catalog data, raw invalid requests, payment/reference examples, changed quotes, access errors and lookup outcomes. These are examples of the required protocol, not a mock server or an implemented authorization/recovery engine. Use the same examples in later backend tests.

## Backend acceptance checklist (Task 5 local evidence linked below)

- [x] Owner and route-store isolation for every action, referenced ID and lookup/replay.
- [x] Runtime money/quantity/recipe validation and exact aggregation with overflow rejection.
- [x] Concurrent stock competition blocks direct shortages without partial persistence.
- [x] Concurrent identical attempts and process-crash recovery produce only one sale/payment/movement set.
- [x] Response loss and confirmed replay after quote expiry recover the original sale.
- [x] New duplicate-reference warnings require review but never force a unique reference.
- [ ] Full reversal/restock applies once and preserves original snapshots after catalog edits.

## Handoff

User confirmed business-rule agreement on 2026-10-30. Tasks 1–5 are implemented locally, including checkout and interruption recovery. See [Task 5 verification](../verification/2026-11-07-task-5-payment-recovery.md) for current evidence and external checks. Task 6 sales management/corrections is next. Tax treatment and deployment readiness remain unresolved; no provider integration is implied.

## Verification record — 2026-10-29

- Contract tests: 12 passed, including four RED-to-GREEN review regressions.
- TypeScript: npm run types:check passed, including negative type checks.
- Scoped lint and formatting: passed for contract source/config files.
- Repository-wide npm run check: blocked by pre-existing formatting outside this package (see execution ledger).
- Fresh review completed; all four important findings corrected and covered. No deferred review findings.
- Backend acceptance remains unchecked. Tax remains a live-use blocker. User agreement recorded on 2026-10-30; parent Task 1 is complete at the contract level.

## Task 5 payment and recovery protocol

POS JSON requests (catalog, draft-catalog, quotes, checkouts and attempt lookup) require `X-POS-Actor-Id` equal to the authenticated user. Authentication, verification and store-owner authorization run first. Missing/mismatched actors receive 409 `session_changed` without operation writes. Success and business errors echo `X-POS-Actor-Id` and `X-POS-Store-Id`; clients must validate both before accepting results. These headers are preconditions, never credentials.

`config/pos.php` enables cash, GCash and Maya by default, with `store_payment_methods[storeId]` overrides. Preparation quotes accept null payment; final quotes validate tender or confirmed external receipt. References trim to null when blank and are optional. Exact case-sensitive duplicates within store/provider, including reversed sales, warn rather than enforce uniqueness. Cash receivedMinor equals the sale total; tender remains in input and change is separate.

POST `/{store}/pos/draft-catalog` resolves selected lines independently of picker pagination, retaining inactive store records and generic issues for missing/foreign/removed selections. It never changes stock or reveals foreign records. Final review compares immutable item/add-on/recipe, money, payment and warning content; fresh quote IDs and harmless positive stock changes alone do not require renewed review.

POST `/{store}/pos/checkouts` registers a durable actor/store/key-scoped canonical request, then atomically posts one sale, one payment, and one operation/movement per aggregated stock item. 200 confirms; 202 remains pending. GET `/{store}/pos/attempts/{key}` returns not_found/pending/confirmed/rejected without writes. Confirmed/rejected replay precedes quote-cache checks. A changed fingerprint returns idempotency_conflict. Rejections preserve the original key; only definitive rejection permits new review and a new operation. Transient database/cache errors preserve pending.

Browser drafts use IndexedDB with atomic revision/owner-epoch checks. Only explicit takeover changes editors; pending/unknown requests cannot be taken over or discarded. Unsubmitted payment stays in memory. The exact request is saved before POST; uncertainty retains it for explicit same-key retry and bounded GET reconciliation. Confirmation becomes a durable key/sale marker before New Sale. A verified sale whose marker write fails is shown honestly with New Sale disabled. No provider integration, receipt route, tax engine or offline completion is implied.

## Task 6 persistence amendments — 2026-11-09

Reversal persistence adds retained actor/store/key-scoped `reversal_attempts`, one immutable `sale_reversals` row per sale/attempt, and nullable `stock_operations.sale_reversal_id` while retaining the parent sale ID. New migrations leave Task 5 migrations and original sale/payment snapshots unchanged. Foreign keys restrict deletion of historical references; terminal attempt identity/outcomes and reversal updates/deletions are immutable through models.

A full reversal records the original sale total, never tender. `ReversalInput` includes actual `refundMethod` (cash/GCash/Maya) and optional `refundReference`; blank references normalize to null, maximum 255 characters. Reason trims to 1–1000 characters. The UI defaults to the original payment method. `SaleReversal` exposes historical actor name and the canonical approved return list; replay does not include live inventory balances. No transfer is performed by the app. The read/write endpoint implementation is gated separately below.

Task 6 historical reads implement owner/verified `GET /{store}/sales` and `GET /{store}/sales/{sale}`. The index sends compact summaries, original payment filters, literal reference substrings and 25 rows ordered by descending UTC time/ID. Manila from/to dates map to inclusive UTC start/exclusive next-day end. Detail returns immutable snapshots and reversal/movement history, current store display name (not a historical snapshot), bounded original direct-stock return candidates and validated filter/page return context. Navigation remains hidden until the sales screens are connected. Reversal movement source IDs identify the reversal; original sale movement IDs identify the sale.

Task 6 refund writes implement JSON `POST /{store}/sales/{sale}/reversals`, plus read-only current reversal and actor-scoped attempt lookups at `/reversal` and `/reversal-attempts/{key}`. These financial JSON responses are an intentional exception to management form redirects. All three require verified owner access and matching actor headers; echoed actor/store identity must be validated before applying a response. Registration binds a canonical sale/request/key before store → attempt → sale → sorted-stock transaction locks. One sale reversal and the terminal outcome commit together with selective stock returns. Definitive 422/409 business rejections are retained; lookup exposes rejected, and replay remains rejected after inventory changes. Envelope-invalid 422 is before registration. Transient contention remains 202 pending; unreadable durable outcome is 503 `reversal_status_unknown`. Current reversal lookup may find a different recorded operation. Neither not_found nor pending grants a new operation identity.
