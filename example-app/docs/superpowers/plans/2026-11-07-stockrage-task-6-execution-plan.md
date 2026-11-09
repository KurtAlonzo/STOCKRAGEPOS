# STOCKRAGE Task 6 Sales Review and Corrections Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Use superpowers:subagent-driven-development only if implementation delegation is subsequently authorized. Steps use checkbox (`- [ ]`) syntax for tracking. This document is a plan, not authorization to implement.

**Goal:** Close Task 5's outstanding verification/documentation work, then deliver sales history, one safe full-sale refund with explicit stock returns, and optional A5 receipts.

**Architecture:** First establish the payment/recovery completion gate. Then extend the Laravel historical read model and implement atomic, idempotent reversals before connecting React management screens. Keep checkout confirmation snapshots immutable and use a separate persisted refund request for interruption recovery.

**Tech Stack:** Existing Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Radix, Wayfinder, Vite Plus, PHPUnit, Playwright and IndexedDB. No new dependency is planned.

**Spec:** [Task 6 sales corrections design](../specs/2026-11-07-stockrage-task-6-sales-corrections-design.md), read with the [parent Task 6](2026-10-29-stockrage-frontend-action-plan.md), [Task 5 execution plan](2026-11-06-stockrage-task-5-execution-plan.md) and [POS contract](../../contracts/pos-v1.md).

## Global constraints

- User-confirmed order: close Task 5 first; Task 6 backend tests/integrity gate before screens and receipts.
- Verified store owner only. Scope every sale, reversal, stock ID and replay to the route store; actor headers are preconditions, not credentials.
- PHP integer centavos; Asia/Manila business dates; tax remains unconfigured. Money/scaled-stock absolute bound `9007199254740991`; sold count maximum `9007199254740`; stock transport has three decimal places and counted stock is integral.
- One full reversal per sale, for original `totalMinor`, never cash tender. Preserve original payment, sale lines/prices/recipes and sale movements.
- User-selected retail/direct-stock add-on return quantities only; default none. Never automatically restore food ingredients/supplies. Subsequent corrections use reasoned inventory adjustments.
- User-confirmed refund methods: cash, GCash, Maya; actual method and optional reference are recorded, original method is the form default. The app never transfers money.
- Uncertain refund recording retains the original request/key. No automatic POST retries, second refund prompt, new key to escape uncertainty, or optimistic success.
- New management screens support phone/tablet/desktop. Existing checkout split at 1024 px, drawer at 768–1023 px, larger-screen notice below 768 px stays intact.
- No partial refunds, exchanges, tax engine, provider integration, global stock history, dashboard aggregation, business settings or printer SDK.
- Preserve existing dirty work/historical migrations. Generate Wayfinder helpers; never hand-edit generated files. Tests use disposable databases and isolated cache/session paths.

## Review focus

1. Refund commits but response/local persistence fails: one reversal and one restock set, with no instruction to return money again. Tasks 3 and 5.
2. Current catalog/recipe differs from the original sale: original snapshots alone determine refund and stock eligibility. Tasks 2 and 3.
3. Different keys/tabs/accounts race: database uniqueness and scoped recovery prevent duplicate records, stock writes or cross-account disclosure. Tasks 3–5.
4. Reversal changes the current sale but old checkout recovery still runs: original cached confirmation remains valid and current detail reflects the refund. Tasks 2, 3 and 5.
5. Midnight dates, long receipts and canceled printing: list boundaries stay correct and presentation actions never mutate financial state. Tasks 2 and 6.

## Baseline, order and working method

All paths/commands below are relative to `example-app/` unless identified as repository-root paths.

`0 close Task 5 -> 1 contract/persistence -> 2 historical reads -> 3 atomic refund -> BACKEND GATE -> 4 sales screens -> 5 refund UI/recovery -> 6 receipts and final evidence`.

Planning baseline, 2026-11-07: POS PHP suite passed **21 tests / 272 assertions**; TypeScript passed; frontend check passed **177 formatted / 148 linted files**. These are limited planning checks, not Task 5 completion or Task 6 verification. HEAD at inspection was `ec68cc8`; preserve dirty `composer.json`, `vite.config.ts`, `resources/js/lib/pos/checkout-requests.test.ts`, `tests/Browser/pos-recovery.spec.ts`, `tests/Support/pos-browser-fixture.php` and root `.vscode/`.

At execution start snapshot status/diff, use an isolated checkout if needed, and retain the user's baseline changes. For each behavioral task: add the named focused tests, observe the intended failure, implement, rerun and review its diff. An unrelated baseline failure is not the intended failing test. Use exact-path/hunk commits per completed task when execution is authorized; never stage the whole dirty tree.

## Task 0: Close Task 5 before extending the workflow

**Files:** Modify `tests/Browser/{pos-payment,pos-recovery,pos-drafts,pos-layout}.spec.ts`, `tests/Browser/support/app.ts`, `tests/Support/{pos-browser-fixture,checkout-worker}.php` only as needed; relevant existing POS implementation files only for reproduced defects. Create `docs/verification/2026-11-07-task-5-payment-recovery.md`. Update Task 5 checklists in both plans, `docs/contracts/pos-v1.md` and `docs/STOCKRAGE-POS-PROGRESS-SUMMARY.md` to evidence-backed status.

**Interfaces:** Preserve the existing checkout/draft protocol. Reuse CLI-only test fixtures; add no production inspection, login or reset endpoints. Produce a signed-off local Task 5 gate and an explicit external-release checklist.

- [x] Build a coverage ledger mapping every Task 5 acceptance item to its existing test/evidence. Classify implemented/passing, missing integrated coverage, reproduced defect, documentation-only, and external release check. Unchecked historical boxes alone do not imply missing implementation.
- [x] Add real-browser `pending_submission_survives_refresh_and_explicit_same_key_retry`: durably register the real original request through an isolated worker/barrier, interrupt processing, refresh, confirm unresolved state, then use the UI's explicit retry. Assert identical key/request, one sale/payment and six sale movements for the mixed basket. No sleep-based assumption that a request reached the server; inspect its durable registration.
- [x] Add `duplicate_reference_requires_review`, `recipe_and_shortage_changes_require_renewed_review`, `off_page_saved_selection_restores`, and `editable_drafts_stay_isolated_across_store_switch_and_logout`. Assert actual UI acknowledgment, stale confirmation invalidation, independently resolved saved items, and no other user's/store's visible draft/payment. Preserve response-loss and storage-failure coverage already present.
- [x] Run the added cases first and distinguish missing behavior from missing evidence. Fix only demonstrated defects. Reuse existing tests for crash after registration, transactional rollback, competing stock, corrupt draft and takeover races rather than duplicate them.
- [x] Extend keyboard checks through completion and unknown-result recovery, verify focus return/errors/announcements and touch controls. Verify actual browser 200% zoom where available; preserve the existing CSS zoom artifact under its accurate label. Record screen-reader, physical keyboard/device, connection and performance limits honestly.
- [x] Run `php artisan test`, `php vendor/bin/phpstan analyse --memory-limit=512M`, `php vendor/bin/pint --test`, `npx vp test run`, `npm run types:check`, `npm run check`, and `npm run test:pos-browser` (includes production build). All applicable checks must pass; record/fix attributable baseline failures before closing the gate. Use only the test harness's verified disposable paths.
- [x] Write the dated report with commit/tree identity, exact commands/results, coverage ledger and remaining external checks. Reconcile stale payment-unavailable claims and Task 5 checkboxes; link the new report from the old Task 5 plan. Do not mark Task 6, live deployment, tax or physical-device readiness complete.
- [x] Review and commit owned changes: `test(pos): close payment recovery verification gaps`. **Gate:** do not start Task 1 until this deliverable is complete.

## Task 1: Define reversal records and the public contract

**Files:** Create `database/migrations/2026_11_07_000001_create_reversal_attempts_and_sale_reversals_tables.php`, `2026_11_07_000002_add_sale_reversal_id_to_stock_operations_table.php`, `app/Models/{ReversalAttempt,SaleReversal}.php`, `app/Data/SaleReversalData.php`, `tests/Feature/Sales/ReversalPersistenceTest.php`. Modify `app/Models/{Sale,Payment,StockOperation}.php` as needed for explicit relations, `resources/js/types/sales.ts`, and `docs/contracts/pos-v1.md`.

**Interfaces:**

- Extend `ReversalInput` with `refundMethod: PaymentInput['method']`, `refundReference: string|null`; retain original fields. `SaleReversal` additionally exposes `actorName: string`; its existing `restock` is the canonical approved return list.
- `SaleReversalData::make(SaleReversal $reversal): array` emits immutable `SaleReversal`; no live stock balances or current catalog names in replay payloads.
- `reversal_attempts`: sale/actor/store identity, unique `(team_id, actor_id, idempotency_key)`, canonical `request_json`/`request_hash` including sale ID, status pending/recorded/rejected and nullable terminal result JSON. Identity is immutable; retain records for this release.
- `sale_reversals`: unique `sale_id`, unique `reversal_attempt_id`, canonical `request_json`/`request_hash`, `total_minor`, refund method/reference, reason/confirmation, actor/name and `occurred_at`. Expose key through its attempt. Store canonical restock in request JSON. Restrictive historical FKs, integer money, immutable update/delete hooks.
- `StockOperation.sale_reversal_id` is nullable; reversal operations also retain parent `sale_id`. Task 3 commits all business effects with the attempt's recorded outcome.

- [x] Add persistence tests for one reversal per sale, operation-key uniqueness, immutable reversal fields/deletion, retained original payment, valid restrictive links and unchanged legacy stock records. Example required assertions: `assertDatabaseCount('payments', 1)`, original `input_json` equality, and database uniqueness failure for a second reversal of the same sale.
- [x] Run `php artisan test tests/Feature/Sales/ReversalPersistenceTest.php`; observe the intended schema/model failures.
- [x] Implement new migrations/model/serializer and type amendments. Do not edit the Task 5 migration files. Document reason/reference bounds, method defaults and full financial correction semantics.
- [x] Run the focused test plus `php artisan test tests/Feature/Pos/SalePersistenceTest.php`. On a disposable database verify migration rollback/reapply and that existing sales remain readable.
- [x] Review and commit: `feat(sales): define immutable reversal records`.

## Task 2: Add scoped historical reads and stock provenance

**Files:** Create `app/Http/Controllers/SalesController.php`, `app/Data/SalesData.php`, `app/Services/Sales/RestockEligibility.php`, `tests/Feature/Sales/{SalesRead,SalesAccess}Test.php`. Modify `app/Data/{SaleData,InventoryData}.php`, `app/Models/Sale.php`, `routes/web.php`, `resources/js/types/sales.ts`, `tests/Feature/Inventory` tests and `docs/contracts/pos-v1.md`.

**Interfaces:**

- `SalesController::index(Request $request, string $current_team): Inertia\Response` and `show(Request $request, string $current_team, string $sale): Inertia\Response`; GET `sales` -> `sales.index`, GET `sales/{sale}` -> `sales.show` within existing verified-owner group.
- `SalesData::summary(Sale $sale): array` emits `SaleSummary = Pick<Sale,'id'|'reference'|'status'|'occurredAt'|'totalMinor'> & {paymentMethod: PaymentInput['method']; refundedMinor: number; netMinor: number}`. Change `SalesIndexProps.sales` to `PageSlice<SaleSummary>`.
- `SalesIndexProps.filters` stays the existing reference/dateFrom/dateTo/payment/status shape. `SaleShowProps` adds `restockCandidates: {inventoryItemId:number; name:string; unit:'each'; maxQuantity:DecimalQuantity; active:boolean}[]`, `storeName:string`, and `returnTo: {filters:SalesIndexProps['filters']; page:number}`. Validate this return context as filter data, not an arbitrary redirect URL.
- `RestockEligibility::forSale(Sale $sale): array` produces those candidates from original direct-stock snapshots and negative sale movements, aggregated by inventory ID with historical display names. Task 3 consumes this helper inside its locked transaction. Include tests for shared direct items, direct add-ons, recipe exclusion and changed/inactive catalog records here.
- `SaleData::make(Sale $sale): array` remains the full `Sale` serializer, now with linked reversal and sale/reversal movement groups derivable by `kind`. Eager-load/batch relationships; list rendering does not call this heavy serializer for each row.
- `InventoryData::movement` uses reversal ID as `sourceId` for reversal operations, sale ID for original sale movements, operation ID for receipt/adjustment. Preserve historical item/actor names.

- [x] Add `test_filters_use_manila_sale_date_and_stable_pagination`: page size 25; deterministic ties by descending ID; 2026-11-06 15:59:59Z excluded and 16:00:00Z included for November 7; November 7 16:00:00Z excluded. Invalid dates and reversed ranges return field errors. Reference treats `%`/`_` as literal search characters, not user-supplied wildcard operators.
- [x] Add `test_original_and_adjusted_amounts_are_distinct`: original total 41000, refund 41000, net 0; original cash tender 50000/change 9000 remain. Payment filter uses original method even when refund method differs. Catalog edits do not change receipt lines/recipes.
- [x] Add owner/verification/store isolation cases, original versus reversal movement source IDs, empty/no-results payloads, summary payload omitting lines/movements, and bounded relationship-query count independent of list row count.
- [x] Run focused tests; implement controller/queries/serializers. Add `database/migrations/2026_11_07_000003_add_sales_history_index.php` with composite `(team_id, occurred_at, id)` for scoped ordered history. Backend Inertia tests assert component names/props without requiring unfinished page files; Task 4 owns browser rendering. Preserve existing checkout `result_json`; current sales reads are separate from cached completion results.
- [x] Run `php artisan test tests/Feature/Sales tests/Feature/Pos tests/Feature/Inventory`, then `php artisan wayfinder:generate --with-form` and `npm run types:check`. At this backend stage do not expose a navigation link to unfinished pages.
- [x] Review and commit: `feat(sales): add scoped history and sale detail data`.

## Task 3: Record one atomic full refund and explicit stock return

**Files:** Create `app/Services/Sales/{ReversalRequest,ReversalAttempts}.php`, `app/Actions/Sales/ReverseSale.php`, `app/Http/Controllers/SaleReversalController.php`, `tests/Feature/Sales/{Reversal,ReversalConcurrency}Test.php`, `tests/Support/reversal-worker.php`. Modify `routes/web.php`, `resources/js/types/sales.ts`, `tests/Feature/Pos/CheckoutTest.php`, and contract documentation. Consume Task 2's `RestockEligibility`, existing `StoreCatalogTransaction` and `Quantity`.

**Interfaces:**

- `ReversalRequest::canonical(int $saleId, array $input): array` normalizes reason/reference, validates method/confirmation/key, canonicalizes positive whole-unit quantities and sorts unique inventory IDs. `hash(array $canonical): string` uses stable SHA-256 JSON identity including sale ID and all business fields.
- `ReversalAttempts::register(Team $store, User $actor, Sale $sale, array $canonical): ReversalAttempt` durably binds the canonical request; changed fingerprints conflict. `lookup(Team $store, User $actor, Sale $sale, string $key): array` reads only that operation, without writes.
- Consume `RestockEligibility::forSale(Sale $sale): array` from Task 2 under transaction locks, then validate submitted quantities against its historical bounds.
- `ReverseSale::handle(Team $store, User $actor, Sale $sale, array $input): array` returns `{status:int, body:array}`. POST `/sales/{sale}/reversals`, name `sales.reversals.store`; GET `/sales/{sale}/reversal`, name `sales.reversals.show`; GET `/sales/{sale}/reversal-attempts/{key}`, name `sales.reversal-attempts.show`. All use `EnsurePosActor` and `preserve_current_team`.
- Current-reversal GET: `{status:'recorded', reversal:SaleReversal}` or `{status:'not_recorded'}`. Attempt GET: `{saleId, idempotencyKey, status:'not_found'|'pending'}`, `{saleId,idempotencyKey,status:'recorded',reversal:SaleReversal}`, or `{saleId,idempotencyKey,status:'rejected',error:ContractError}`. POST recorded/pending use the corresponding attempt shape. Persist business rejections with their original 422/409 HTTP response and expose the rejected union through lookup.
- 422 structured field errors, 409 `idempotency_conflict` or `already_reversed`, 202 pending for registered unresolved work; 503 `reversal_status_unknown` if durable outcome cannot be read. Scope/auth/CSRF failures are never financial rejection/success. Envelope-invalid 422 before registration is distinct from a durable business rejection.

- [x] Add `test_mixed_refund_restocks_selected_direct_quantities_once` using the existing 41000 basket. Return water 1.000 and cheese 1.000; assert total refund 41000, net 0, one reversal, eight total movements (six sale/two reversal), water 2.000 and cheese 2.000, unchanged beef -100.000/sauce -35.000/seasoning 0.625/packaging -1.000. Assert one revision increment per returned item and identical original snapshots/payment.
- [x] Add cases: no restock, original-method and cross-method refund, all three methods when checkout configuration disables one, optional/trimmed reference, empty/overlong reason, unconfirmed refund, duplicate IDs, foreign/not-sold IDs, recipe supplies, fractions/zero/negative, cheese 3.000 exceeding sold 2.000, balance overflow, inactive historical stock and missing historical linkage. Invalid input produces zero financial/stock effects.
- [x] Add replay cases: identical canonical request returns identical reversal; changed reason/method/reference/quantity under same key conflicts; another key after reversal conflicts; key reused for another sale conflicts within actor/store. Same key in a distinct authorized scope remains independent.
- [x] Run `php artisan test tests/Feature/Sales/ReversalTest.php`; implement short durable registration, then store -> attempt -> sale -> sorted-stock locks. Resolve terminal recorded/rejected replay before current stock validation. Commit reversal/status/stock writes and recorded outcome together; commit definitive rejection without throwing away that terminal record. Use original sale total/deduction bounds. Reread durable state after ambiguous exceptions; transient errors remain pending.
- [x] Build multi-process SQLite tests with real barriers adapting `checkout-worker.php`: same-key race, different-key race, refund competing with receipt/adjustment, worker death after registration, interruption before commit, and rollback after the first stock write. Expected: one reversal at most, exact aggregate stock, no partial status/refund/movement state. not_found/pending followed by retry serializes safely against the original.
- [x] Add the review regression `test_rejected_key_stays_rejected_after_stock_changes_and_overlapping_retry`: force overflow rejection, change balance through a valid stock action, then release an older same-key worker. Both return the same rejection, zero reversals/restocks; only a newly reviewed request/key can succeed. This is required before labeling a rejection definitive.
- [x] Extend checkout regression: complete -> refund -> replay/lookup original checkout. Assert original confirmation JSON equality, current sales detail reversed, no new deduction, and original frontend decoder still accepts the confirmation. Do not broaden the checkout decoder to accept arbitrary current sale data.
- [x] Export `CurrentReversalResult` and `ReversalAttemptResult` in `resources/js/types/sales.ts` using the exact GET unions above. Run `php artisan test tests/Feature/Sales tests/Feature/Pos tests/Feature/Inventory`, `php artisan wayfinder:generate --with-form`, `npm run types:check`, PHPStan and Pint. **Backend gate:** all financial, scope, concurrency and replay checks pass before wiring refund controls.
- [x] Review and commit: `feat(sales): record idempotent refunds and selective restocking`.

## Task 4: Connect sales browsing and current sale detail

**Files:** Create `resources/js/pages/sales/{index,show}.tsx`, `resources/js/components/sales/stock-movements.tsx`, `tests/Browser/sales-history.spec.ts`. Modify `resources/js/lib/{shell-navigation,shell-navigation.test}.ts`, `resources/js/components/pos/checkout-result.tsx`, `resources/js/pages/pos/index.tsx`, and browser fixture helpers.

**Interfaces:** Pages consume Task 2 props and generated routes. `CheckoutResult` receives a scoped current-sale URL supplied by its page rather than inferring a store from mutable global state. `StockMovements` consumes historical `StockMovement[]` and store slug.

- [x] Add browser assertions for all five filters, page 2 -> detail -> return with the same filters/page, refresh/back/forward, no-results versus no-sales, and switching stores with no stale sale display. Assert sale snapshots remain original after catalog edits and refunded totals stay separately labeled.
- [x] Run the targeted browser cases to observe missing screens, then implement the pages with existing management shell and controls. Add Sales navigation only once the pages exist; nested detail keeps Sales active. Link confirmed POS to current sale detail and inventory rows to the existing movement panel.
- [x] Verify 1440x900, 1024x768, 768x1024 and 390x844; prioritize summary fields on phones, allow deliberate internal tables only where necessary, and keep page-wide overflow absent. Provide labels, focus and loading/error states. No dashboard/global-history expansion.
- [x] Run `npx playwright test tests/Browser/sales-history.spec.ts` after building current assets, shell navigation tests, `npm run types:check` and `npm run check`.
- [x] Review and commit: `feat(sales): add history and transaction review screens`.

## Task 5: Connect refund confirmation and interruption recovery

**Files:** Create `resources/js/components/sales/correction-dialog.tsx`, `resources/js/lib/sales/{reversal-input,reversal-storage,reversal-session,reversal-requests}.ts`, corresponding focused `.test.ts` files, `resources/js/hooks/use-sale-reversal.ts`, and `tests/Browser/sales-reversal.spec.ts`. Modify sales detail, types and isolated CLI fixtures as needed.

**Interfaces:**

- `ReversalScope = {userId:number; storeId:number; storeSlug:string; saleId:number}`; frozen record `{schemaVersion:1; scope:ReversalScope; request:ReversalInput}` in `stockrage-sales` v1, store `reversals`, key actor/store/sale.
- `openReversalStorage(): Promise<ReversalStorage>` with `read(scope)`, `begin(scope, request)` (atomic insert or return identical existing; reject conflicting unresolved content), and `clear(scope, expectedKey)` after validated recorded/definitively rejected outcomes. Corrupt uncertainty never silently overwrites. No POS database upgrade.
- `createReversalSession(scope, storage)` exposes immutable snapshot/subscription, `restore()`, `submit(input)`, `check()`, `retryOriginal()`, `dispose()`. States follow the design. `useSaleReversal(scope)` connects React. Request decoders validate echoed actor/store, sale identity, reversal payload and operation key without assuming original sale actor equals refund actor.
- `CorrectionDialog` is controlled by form/session props, consumes historical eligibility and current sale total, and has no independent persistence. Reason/method/reference/quantity validation is advisory; server remains authoritative.

- [x] Write unit tests for exact integer quantity parsing, request canonical identity, default original method, empty return list, stale response generations, actor mismatch, malformed results, storage errors and no automatic POST. Assert storage failure means `POST count === 0`; unknown preserves exact key/body; retry requires checked not_found/pending plus no current reversal. Editing a registered request requires durable rejected status.
- [x] Add actual IndexedDB/browser tests: concurrent tab `begin` claims one request, response commits then is lost, refresh recovers the recorded reversal, pending plus explicit same-key retry, durable rejection followed by correction, response-loss account switch, storage cleanup failure, and another key already recorded. Assert one reversal, two restock movements for the mixed example, and unchanged sale/payment counts.
- [x] Implement the controller/hook and scoped request adapter, reusing the existing low-level JSON/header and auth-invalidation patterns without routing reversal payloads through the checkout-only decoder. Revalidate identity on focus/visibility and prior to showing restored content; stop polling/writes on account mismatch. Keep uncertainty through logout.
- [x] Implement full-amount confirmation, actual method/reference and unselected eligible quantity fields. Explain external money return, ingredient non-restoration and final stock selection. Refresh current sale status before final confirmation. Clear the checkbox when method/restock changes. Known offline/storage failure disables submission.
- [x] On uncertain recording, show the design's exact warning, Check again and allowed original-request retry; do not instruct a second external refund. A durable rejected attempt permits retaining form input and rereading the sale before correction/new key; malformed envelope errors require confirming no registered original. On recorded or already-recorded reversal show read-only audit details; cleanup failure must not re-enable refund.
- [x] Exercise keyboard-only refund/recovery, dialog Escape/focus return, screen-reader status text, touch targets and phone scrolling. Run focused unit/browser tests, sales/POS PHP tests, TypeScript and frontend check.
- [x] Review and commit: `feat(sales): add recoverable refund confirmation`.

## Task 6: Add receipts and finish integrated evidence

**Files:** Create `resources/js/components/sales/receipt.tsx`, `resources/css/sales-receipt.css`, `tests/Browser/sales-receipt.spec.ts`, `docs/verification/2026-11-07-task-6-sales-corrections.md`. Modify sale detail and documentation: parent Task 6, this plan, contract and progress summary.

**Interfaces:** `Receipt({sale, storeName}: {sale:Sale; storeName:string})` renders original immutable sale/payment values plus an explicit linked refund section. Import print styling through sale detail; user-triggered `window.print()` has no mutation endpoint.

- [x] Add browser assertions for original sale and reversed receipt, original tender/change, cross-method refund, null/long reference, long names/modifiers and multi-page content. Simulate print media; assert chrome/controls hidden, readable light colors and A5 layout. Before/after print invocation and cancellation, database counts/financial values are identical.
- [x] Implement on-screen receipt and optional Print / Save as PDF control. Use `@page { size: A5; }`, print-only chrome suppression, wrapping and page-break rules; no new PDF dependency. Keep current store-name provenance explicit; do not invent stored historical details or tax fields. Detailed internal reason/stock audit stays on sale detail.
- [x] Verify actual print preview and cancel where the environment supports it. Automated media emulation alone does not prove a physical printer, browser save dialog, or print cancellation. Record those limits; optional printing never gates an otherwise confirmed sale/refund.
- [x] Run the final acceptance workflow against the real application: mixed sale -> current detail -> full 41000 refund with water/cheese 1.000 each -> response-loss recovery -> list/status/payment/date filters -> original checkout replay -> receipt -> linked inventory movements. Assert one original sale/payment, one reversal and exactly eight total movements.
- [x] Run the final suite once after changes stabilize: `php artisan test`, PHPStan, Pint, `npx vp test run`, `npm run types:check`, `npm run check`, `npm run test:pos-browser`. Investigate failures; do not keep rerunning passing checks without a relevant change.
- [x] Record commit/tree, commands/counts, screenshots, new migration/route contracts, reproduction steps, known release limitations and the Task 7 handoff. Mark parent Task 6 boxes complete only to the extent proved. Keep dashboard/settings/global-history, tax and physical-device readiness separate.
- [x] Review and commit owned changes: `feat(sales): add receipts and verify sale corrections`.

## Completion criteria and handoff

- [x] Task 5 has current integrated evidence and accurate documentation; remaining external checks are listed explicitly.
- [x] Owner can find/review original sales and current refunded status without changing historical snapshots.
- [x] Full refund records actual method/reference and returns only selected eligible stock once, even across retries/races.
- [x] An uncertain recording cannot become a second refund prompt or duplicate restock; current sales reads and original checkout recovery coexist.
- [x] Receipt display/optional A5 printing is read-only; new sales management works on phones/tablets/desktops.
- [x] All applicable checks pass and Task 7/release boundaries remain honest.

**Execution resumed 2026-11-09:** user authorized implementation in chat; Tasks 0–6 local gates passed; [Task 6 evidence](../../verification/2026-11-07-task-6-sales-corrections.md) records results, review fix and remaining external release checks. [Task 5 evidence](../../verification/2026-11-07-task-5-payment-recovery.md).

**Execution recommendation:** Native execution with explicit gates after Task 0 and Task 3, followed by one integrated final review. The user authorized resumed implementation on 2026-11-09. Backend-first gates were observed and no implementation was delegated. Fresh read-only reviews were used; all remaining external release checks are documented in the verification report.
