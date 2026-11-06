# STOCKRAGE Task 5 Payment and Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Use superpowers:subagent-driven-development only when implementation delegation is authorized. Steps use checkbox (`- [ ]`) syntax for tracking. This document authorizes planning, not product implementation.

**Goal:** Complete the existing mixed cart with recorded cash/GCash/Maya payments and recovery that cannot duplicate a sale, payment or stock deduction.

**Architecture:** Backend-first Laravel completion with durable operation identities, current-state validation and atomic sale/payment/stock posting. Then add an IndexedDB draft with atomic tab ownership, scoped request validation and bounded status recovery to the existing React POS.

**Tech Stack:** Existing Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Radix, Wayfinder, Vite Plus and PHPUnit; browser IndexedDB/BroadcastChannel. Add only `@playwright/test` for the currently missing real-browser integration tests, with a locked compatible version selected during execution.

**Spec:** [Task 5 payment and recovery design](../specs/2026-11-06-stockrage-task-5-payment-recovery-design.md). Read it with [POS v1](../../contracts/pos-v1.md) and [Task 4 handoff](../../verification/2026-10-31-task-4-mixed-cart.md). The four interview decisions in the design are user-confirmed; its implementation defaults and this plan are proposed for review.

## Global constraints

- Verified owner only; every resource/action is scoped to the authenticated actor and route store.
- PHP integer centavos; Asia/Manila business context; final prices without tax extraction/addition; tax remains unconfigured.
- Money/scaled stock absolute bound `9007199254740991`; sold count maximum `9007199254740`; stock transport has three decimal places and counted stock is integral.
- Direct shortages block; ingredient/supply shortages warn and may go negative. Aggregate demand before writing once per inventory item.
- One payment per sale. GCash/Maya record external receipt, require confirmation and allow optional references; duplicate references warn and are not unique keys.
- Keep the same operation key and exact original request through uncertainty. Confirmed replay precedes quote expiry/catalog checks. No automatic POST retries or new operation keys.
- Logout retains cart, clears unsubmitted payment input and preserves unresolved recovery. One editor per draft; explicit takeover cannot interrupt an unresolved submission.
- Desktop/tablet checkout only: split at 1024 px, drawer at 768–1023 px, larger-screen notice below 768 px. Preserve keyboard/touch and light/dark/system behavior.
- No provider SDK, tax/discount/split-payment engine, staff checkout, offline completion, held-order list, sales/reversal pages, receipt printing or settings UI in Task 5.
- Preserve existing uncommitted work and historical migrations. Generate Wayfinder helpers from routes, never edit generated files.

## Review focus

1. Account changes during response loss: an old actor's pending draft must never accept another actor's rejection or submit under that account. Tests: Tasks 2, 5 and 7.
2. Takeover races with submission: exactly one owner/epoch can persist the frozen request. A browser broadcast is not a lock. Tests: Tasks 4 and 7.
3. Worker death or cache expiry after durable registration: resume/reject the original operation safely; never infer failure from time alone. Tests: Tasks 3 and 7.
4. The last retail unit or a duplicate provider reference changes concurrently: completion serializes against stock/catalog/payment changes and cannot bypass renewed review. Tests: Tasks 2 and 3.
5. Storage fails after server confirmation or a restored record is corrupt: show verified outcomes honestly, preserve recovery, and prohibit unsafe New Sale/discard. Tests: Tasks 4, 5 and 7.

## Execution order and working rules

`1 persistence -> 2 review/scoping -> 3 atomic completion -> backend gate -> 4 drafts -> 5 client recovery -> 6 payment UI -> 7 integrated evidence`.

All paths and commands below are relative to `example-app/`. Snapshot `git status --short` and the Task 4 diff before edits; the planning baseline is dirty. Do not reset, overwrite or broadly stage those changes. Verify the app's local prerequisites and use disposable databases for tests. Keep each task's tests/code in one reviewed commit when execution is authorized; stage exact owned paths/hunks only.

For each task: write the named tests, run the focused command and observe the intended missing behavior, implement the smallest passing change, rerun, and review its diff. Do not treat an unrelated baseline failure as the expected red test. Commands and expected results below are future acceptance checks, not claims that Task 5 has been tested.

## Task 1: Add durable financial records and sale-linked stock history

**Files**

- Create `database/migrations/2026_11_06_000001_create_checkout_attempts_table.php`, `2026_11_06_000002_create_sales_and_payments_tables.php`, `2026_11_06_000003_add_sale_id_to_stock_operations_table.php`.
- Create `app/Models/{CheckoutAttempt,Sale,Payment}.php`, `app/Data/SaleData.php`, `tests/Feature/Pos/SalePersistenceTest.php`.
- Modify `app/Models/StockOperation.php`, `app/Data/InventoryData.php`.

**Interfaces**

- `CheckoutAttempt`: unique `(team_id, actor_id, idempotency_key)`; canonical `request_json`, SHA-256 `request_hash`, `status`, nullable `result_json`, timestamps. Terminal result stores the confirmed Sale payload or definitive error envelope/status. No expiry column/job.
- `Sale`: unique `checkout_attempt_id`; `team_id`, actor ID/name snapshot, unique display reference, status, UTC occurred-at, currency, subtotal/total, immutable `lines_json`. `Payment`: unique `sale_id`, method, normalized nullable reference, immutable input JSON, received/change amounts, recorded-at.
- `StockOperation.sale_id`: nullable FK/index; retain one movement per operation. `SaleData::make(Sale $sale): array` emits existing TypeScript `Sale`, with `reversal: null` for Task 5. `InventoryData::movement` reports sale source ID when linked.
- Payment `receivedMinor` is the sale total, matching existing fixtures; cash tender stays in `payment.input.tenderedMinor`, change separately. For 41000 total/50000 tender: received 41000, change 9000.

- [ ] Write `test_attempt_identity_and_one_payment_per_sale_are_unique`, `test_sale_snapshots_survive_catalog_changes`, `test_sale_movements_keep_one_operation_per_item`, and `test_receipt_source_serialization_is_unchanged`. Assert cross-actor/store key reuse is distinct, six-item snapshots preserve historical values, sale movement source is sale ID, old receipt source stays operation ID.
- [ ] Run `php artisan test tests/Feature/Pos/SalePersistenceTest.php`; confirm missing schema/model/serializer failures.
- [ ] Implement migrations/models/serializer. Use `SR-` plus sale ID padded to at least eight digits; assign inside the future sale transaction. Protect immutable sale/payment fields from updates/deletion; permit only future explicit sale status changes without modifying snapshots. Avoid destructive FK cascades on history.
- [ ] Run the focused test and `php artisan test tests/Feature/Inventory`; both pass. Inspect rollback on a disposable database and preserve all existing migration files.
- [ ] Review and commit only Task 1 changes: `feat(pos): add durable checkout and sale records`.

## Task 2: Make final quotes payment-aware and enforce actor preconditions

**Files**

- Create `config/pos.php`, `app/Http/Middleware/EnsurePosActor.php`, `app/Services/Pos/{QuoteCalculator,QuoteReview,PaymentRules}.php`, `app/Data/PosDraftCatalogData.php`.
- Modify `app/Actions/Pos/CreateQuote.php`, `app/Http/Controllers/PosController.php`, `routes/web.php`, `resources/js/types/pos.ts`, `docs/contracts/pos-v1.md`.
- Create `tests/Feature/Pos/{PaymentQuoteTest,DraftCatalogTest,ActorScopeTest}.php`; extend `tests/Feature/Pos/{QuoteTest,AccessTest}.php`.

**Interfaces**

- `PaymentRules::enabledMethods(Team $store): array` reads `pos.payment_methods` default `['cash','gcash','maya']` and optional `pos.store_payment_methods[storeId]` overrides. `validate(Team $store, mixed $input, int $totalMinor): ?array` yields canonical PaymentInput or null. `warnings(Team $store, ?array $payment): array` queries duplicate references.
- `QuoteCalculator::calculate(Team $store, array $input): array` computes existing CheckoutQuote under a caller-owned store transaction, without caching. Refactor existing arithmetic into this one path. `CreateQuote::handle` retains its public signature and wraps calculation plus QuoteStore publication.
- `QuoteReview::changed(array $reviewed, array $current): bool` compares semantic line/money/recipe/warning/payment content, excluding fresh IDs/expiry and non-warning positive-balance changes. Validate quoted input equality and acknowledged warning IDs separately.
- `EnsurePosActor::handle(Request $request, Closure $next): Response` checks required `X-POS-Actor-Id` against authenticated actor after owner middleware. Mismatch/missing -> 409 `session_changed`, no operation writes. Successful/business-error responses carry `X-POS-Actor-Id` and `X-POS-Store-Id` for client verification.
- Add `POST /{current_team}/pos/draft-catalog`, name `pos.draft-catalog`, with `preserve_current_team`. `PosDraftCatalogData::resolve(Team $store, array $lines): array` returns `{products: CatalogListItem[], addons: CatalogListItem[], inventory: InventoryItem[], issues: ContractIssue[]}` for saved lines, including off-page selections and readable inactive records from this store. Missing/foreign IDs yield generic per-line issues without exposing another store's data. Export `PosDraftCatalog` in `types/pos.ts`.

- [ ] Write payment tests: 41000 total with 50000 -> 9000 change; 40000 insufficient; exact cash -> zero; GCash/Maya true + null reference allowed; whitespace -> null; unconfirmed external rejected; disabled method rejected; duplicate same-store/provider including reversed sale warns; other store/provider does not warn; case differences remain distinct.
- [ ] Write actor/draft tests: owner header mismatch cannot quote/resolve; no header rejected; auth/owner checks still win; echoed scope headers match route store; background request preserves selected store; an off-page product resolves; missing/foreign/disabled/removed add-on retains line issue without hidden foreign details.
- [ ] Run `php artisan test tests/Feature/Pos`; observe intended failures, then implement. Keep numeric and aggregated-demand checks from Task 4. Warning IDs remain stable for equivalent warnings; duplicate warning ID may be `duplicate-reference-<provider>` while normalized reference stays in payment identity.
- [ ] Update existing quote/access tests with the required actor precondition. Add semantic comparison cases: identical re-quote IDs do not force changes; price/recipe/new or changed shortage amounts/duplicate warning do; unchanged warning set plus positive stock changes do not. Preparation quotes still never mutate business records.
- [ ] Document the header protocol, configuration, draft resolver, final quote semantics and optional reference behavior in `pos-v1.md`. Run focused tests and `php artisan wayfinder:generate`; inspect generated routes without manual edits.
- [ ] Review and commit Task 2: `feat(pos): validate payment quotes and request scope`.

## Task 3: Complete and reconcile one atomic checkout

**Files**

- Create `app/Services/Pos/{CheckoutRequest,CheckoutAttempts}.php`, `app/Actions/Pos/CompleteCheckout.php`, `app/Http/Controllers/CheckoutController.php`.
- Modify `routes/web.php` and, only if needed for typed contention handling, `app/Support/StoreCatalogTransaction.php` while preserving existing callers.
- Create `tests/Feature/Pos/{CheckoutTest,AttemptRecoveryTest,CheckoutConcurrencyTest}.php`, `tests/Support/checkout-worker.php`.

**Interfaces**

- `CheckoutRequest::canonical(array $input): array`, `CheckoutRequest::hash(array $input): string` normalize exact completion identity. Sort nested add-on/warning IDs, keep cart line order/IDs, reject duplicate IDs; include reviewed quote identity and normalized payment.
- `CheckoutAttempts::register(Team $store, User $actor, array $input): CheckoutAttempt` durably creates/retrieves a pending operation and rejects a different fingerprint. `lookup(Team $store, User $actor, string $key): array` returns the existing AttemptLookupResult, with no writes.
- `CompleteCheckout::handle(Team $store, User $actor, array $input): array` returns `{status: int, body: array}`. `CheckoutController::store` maps that result to JSON; `show` returns lookup. Owner/actor middleware applies to both.
- Routes: POST `/pos/checkouts` -> `pos.checkouts.store`, GET `/pos/attempts/{idempotencyKey}` -> `pos.attempts.show`, both `preserve_current_team`. 200 confirmed, 202 pending, 200 lookup result; existing structured 422/409 errors with optional replacementQuote. Never call a rejected business operation confirmed.

- [ ] Write `test_mixed_sale_commits_once_with_cash_change`: existing basket -> total 41000, change 9000, one sale/payment, six operations/movements. Expected final balances water 1.000, cheese 1.000, beef -100.000, sauce -35.000, seasoning 0.625, packaging -1.000. Assert revisions increment once per affected inventory item and all records link to the same sale/actor/store.
- [ ] Add replay tests: identical request returns identical sale with no writes; changed tender/lines/quote conflicts; confirmed replay still works after cache expiry and catalog change; lookup other user/store never exposes the operation; missing/invalid actor cannot register it.
- [ ] Add rejection tests: current-price/recipe change, expired quote, missing warning acknowledgment, concurrent duplicate-reference warning and direct shortage reject with original key retained and no financial/stock writes. Replacement quote can be reviewed only after definitive rejection; identical rejected replay remains rejected.
- [ ] Run `php artisan test tests/Feature/Pos/CheckoutTest.php tests/Feature/Pos/AttemptRecoveryTest.php` before implementing to establish failures.
- [ ] Implement durable short registration, then serialized processing using store -> attempt -> sorted inventory lock order. Under the transaction use QuoteCalculator/QuoteReview/PaymentRules; no independent arithmetic. Read terminal attempt before cache. Save terminal sale payload with all business writes; persist definitive rejection under those same locks without partial writes, returning its envelope rather than throwing away the rejection transaction. Lock exhaustion/transient database errors preserve pending and cannot become a fake validation rejection. Do not reinterpret an ambiguous commit exception without rereading the durable attempt.
- [ ] Add multi-process tests using a disposable file-backed SQLite database, a shared isolated file cache for quote envelopes, and start barriers, adapting `stock-worker.php`. Do not inherit PHPUnit's process-local array cache for worker quotes. Cases: identical key posts once; different keys compete for one retail unit with only one sale; same provider reference makes the second attempt re-review; terminate a worker after committed registration and recover the pending original key; inject failure after the first deduction and assert rollback of all business writes. Use process/transaction barriers, not sleep duration as evidence of concurrency.
- [ ] Run `php artisan test tests/Feature/Pos/CheckoutConcurrencyTest.php`, then all POS and Inventory tests. Generate route helpers. **Backend gate:** no payment button is connected until these pass; schema-only success is insufficient.
- [ ] Review and commit Task 3: `feat(pos): complete and recover atomic checkout attempts`.

## Task 4: Persist drafts with atomic tab ownership

**Files**

- Create `resources/js/lib/pos/{draft-storage,draft-session}.ts`, colocated `draft-session.test.ts`, `resources/js/hooks/use-pos-draft.ts`.
- Extend `resources/js/types/pos.ts` with the structures below.
- Create `playwright.config.ts`, `tests/Browser/pos-drafts.spec.ts`, `tests/Browser/support/app.ts`, `tests/Support/pos-browser-fixture.php`; modify `package.json` and its existing lockfile for one browser-test dependency/script. Keep Vite Plus node tests unchanged.

**Interfaces**

- `PosScope = {userId: number; storeId: number; storeSlug: string}`; `DraftOwner = {tabId: string; epoch: number}`.
- `PosDraftRecord = {schemaVersion: 1; scope: PosScope; draftId: string; revision: number; owner: DraftOwner; lines: CartLine[]; quantityBuffers: Record<string,string>; snapshots: PosDraftCatalog; attempt: CheckoutAttempt | null; terminal: {idempotencyKey: string; saleId: number} | null}`. Never put unsubmitted payment into the record. Retain only selected snapshots, not a growing catalog cache.
- `openDraftStorage(): Promise<DraftStorage>`; `DraftStorage.read(scope): Promise<PosDraftRecord|null>`, `create(scope, tabId): Promise<PosDraftRecord>` (insert only when absent), `takeover(scope, tabId, expectedRevision): Promise<PosDraftRecord>`, `save(scope, owner, expectedRevision, cart): Promise<PosDraftRecord>`, `begin(scope, owner, expectedRevision, request): Promise<PosDraftRecord>`, `reconcile(scope, key, result): Promise<PosDraftRecord>`, `newSale(scope, expectedRevision, tabId): Promise<PosDraftRecord>`, `discard(scope, owner, expectedRevision): Promise<void>`.
- Each write runs one atomic IndexedDB transaction, validates state/revision/owner/key, and returns a distinct conflict/storage failure instead of silent overwrite. `begin` creates submitting state with the exact request. `newSale` needs a durable terminal marker. `discard` refuses any unresolved/unknown/corrupt-uncertain record.
- `createDraftSession(scope: PosScope, tabId: string, storage: DraftStorage)` exposes `subscribe`, immutable `snapshot`, `restore`, `saveCart`, `takeover`, `dispose`. `usePosDraft(scope: PosScope)` wraps that session; expose saved/saving/storage-error/read-only/recovering status to the later page. Broadcasts trigger rereads, not unconditional overwrites.

- [ ] Write pure session tests for serialization, saved versus unsaved state, raw empty/invalid quantities, record version validation, selected snapshots, scope invalidation and memory-only payment exclusion. Run `npx vp test run resources/js/lib/pos/draft-session.test.ts` and observe failures.
- [ ] Install `@playwright/test` as a dev dependency and Chromium for tests; add `test:pos-browser` -> `playwright test`. Configure a dedicated loopback server with `reuseExistingServer: false`, one isolated test database, disposable cache/session paths, no existing application `.env` rewrite, and cleanup limited to verified test paths. Seed fixtures through a CLI-only script guarded by `APP_ENV=testing`; no production fixture/login/reset endpoints. Use normal test-account login.
- [ ] Write failing browser tests against the actual IndexedDB adapter: two simultaneous claims, concurrent takeovers, stale save, takeover versus `begin`, pending takeover refused, cross-user/store keys, quota/open failure, unsupported/corrupt record, confirmed-marker write failure and a crash/refresh retaining the original submission. Use two pages in one browser context for shared-origin races; separate contexts would miss the conflict.
- [ ] Implement IndexedDB/CAS/session/hook, retaining original unresolved envelopes on auth/storage errors. Notify through BroadcastChannel when available; fallback correctness comes from transaction checks and focus reread. Restoration does not automatically claim an existing editable draft owned by another tab.
- [ ] Run unit tests and `npm run test:pos-browser -- tests/Browser/pos-drafts.spec.ts`. All actual IndexedDB race assertions pass; an in-memory storage fake alone is not sufficient.
- [ ] Review and commit Task 4: `feat(pos): persist scoped drafts and coordinate editing tabs`.

## Task 5: Add a scoped checkout client and bounded recovery

**Files**

- Create `resources/js/lib/pos/{checkout-requests,checkout-session,auth-scope}.ts`, corresponding `.test.ts` files, and `resources/js/hooks/use-checkout-attempt.ts`.
- Modify `resources/js/lib/pos/requests.ts`, `resources/js/hooks/use-pos-quote.ts`, `resources/js/components/user-menu-content.tsx`, `resources/js/app.tsx`; ensure auth cleanup covers the verify-email logout path too.
- Extend `tests/Browser/pos-drafts.spec.ts` for logout/account-switch integration.

**Interfaces**

- `requestPosJson<T>(url: string, init: RequestInit, scope: PosScope, decode: (body: unknown) => T): Promise<T>` sends expected-actor header, validates echoed actor/store before accepting terminal success/business error, then runs a runtime decoder. Extend the structured error to preserve `httpStatus` and optional `replacementQuote`; existing requestJson currently discards that replacement.
- `decodeLookup(body: unknown): AttemptLookupResult` and `decodeCompletion(body: unknown): CheckoutCompletionResult` validate IDs, safe money, sale scope, payment and snapshots; the session also checks returned key against the persisted original. Missing/malformed data never reaches confirmed state.
- `createCheckoutSession(scope: PosScope, storage: DraftStorage, transport: CheckoutTransport, clock: CheckoutClock)` exposes `subscribe`, immutable `snapshot`, `submit(request, owner, revision)`, `recover()`, `checkAgain()`, `retryOriginal()`, `dispose()`. `CheckoutTransport` provides scoped `complete(input)` and `lookup(key)`; `CheckoutClock` injects time/timers for deterministic tests. `useCheckoutAttempt` wraps the session.
- `clearPosMemory(userId: number): void` invalidates live session state and broadcasts logout; application shared-prop actor changes do the same. It must not delete the user's saved cart or unresolved request.

- [ ] Write `persist_before_post`, `persist_failure_sends_zero_requests`, `lost_response_is_unknown`, `malformed_success_is_unknown`, `pending_is_not_rejected`, `not_found_keeps_original`, `same_key_retry_keeps_exact_body`, `replacement_quote_does_not_auto_submit`, `confirmed_marker_failure_blocks_new_sale` and `stale_actor_response_cannot_unlock_draft` tests. Assert exact POST counts and operation-key equality, not only rendered messages.
- [ ] Write fake-clock tests: lookups at 0/2/5/10/20 seconds, max one in flight, 10-second request timeout, skipped elapsed slots, no automatic POST, stop on terminal/auth/scope failure, hidden/offline pause, explicit restart after exhaustion. Reconnect/focus makes one reconciliation; it cannot create an endless polling loop.
- [ ] Run `npx vp test run resources/js/lib/pos/checkout-requests.test.ts resources/js/lib/pos/checkout-session.test.ts resources/js/lib/pos/auth-scope.test.ts`; implement the state machine after observing the expected failures.
- [ ] Persist a frozen request before calling completion. Treat aborting a POST as uncertain, not canceled server work. Only verified terminal states can unlock; 401/403/419/HTML/missing-scope/session-changed remain locked. Retain the original request for same-key retries. A replacement quote becomes editable only after definitive rejection, with an explicit new review and key.
- [ ] Add payment to quote request identity/generation checks; changing tender/reference/method/confirmation invalidates the old quote. Preserve preparation behavior. On focus use the existing scoped catalog endpoint to verify the session before revealing stale cart/payment data; failed scope verification clears memory and cannot modify another scope's record.
- [ ] Extend tests for logout from a management page while POS is open, missed broadcasts, A-to-B account switch during A's response loss, and relogin as A. The old tab cannot create B's attempt or apply B's rejection to A; A can still recover its confirmed sale.
- [ ] Run all POS frontend tests plus the affected browser tests. Review and commit Task 5: `feat(pos): reconcile checkout outcomes with scoped recovery`.

## Task 6: Connect payment entry, review and result screens

**Files**

- Create `resources/js/components/pos/{payment-panel,checkout-review,checkout-result,draft-status}.tsx`, `resources/js/lib/pos/payment-input.ts`, `payment-input.test.ts`.
- Modify `resources/js/pages/pos/index.tsx`, `resources/js/layouts/pos-layout.tsx`, `resources/js/lib/pos/render.test.ts`; reuse `use-unsaved-changes.ts` without changing unrelated forms' semantics.
- Create `tests/Browser/pos-payment.spec.ts`.

**Interfaces**

- `PaymentForm = {method: 'cash'|'gcash'|'maya'; tenderedText: string; reference: string; receivedConfirmed: boolean}` remains memory-only. `parsePayment(form: PaymentForm, totalMinor: number, enabledMethods: PaymentInput['method'][]): {payment: PaymentInput|null; errors: Record<string,string>}` uses exact centavo parsing and rejects excess precision/overflow; no float multiplication.
- `PaymentPanel` accepts controlled form/total/enabled methods/errors/disabled/onChange. `CheckoutReview` accepts current CheckoutQuote plus review-change explanation, `onConfirm`, `onBack`, disabled/busy state. `CheckoutResult` accepts checked attempt state and handlers for check/retry/new-sale. `DraftStatus` accepts persistence/ownership state and explicit takeover/reload handlers. Do not introduce independent stores inside components.
- The page orchestrates usePosDraft, selected-ID hydration, usePosQuote and useCheckoutAttempt. A submission request is built from the exact reviewed quote/input/warning IDs; its key is generated once and frozen by storage before network I/O.

- [ ] Add payment parser tests for 500.00 -> 50000, insufficient cash, exact tender, blank/intermediate/excess precision, safe-integer overflow, optional trimmed reference and unconfirmed external receipt. Run `npx vp test run resources/js/lib/pos/payment-input.test.ts` red then green.
- [ ] Add browser tests: mixed cart -> Review -> cash 500.00 -> confirmed total 410.00/change 90.00; both providers work with no reference; a duplicate reference warns; editing payment invalidates review; changed price/recipe/shortage requires fresh acknowledgment; disabled/missing product remains visibly repairable after reload.
- [ ] Wire draft restore before editable rendering and hydrate off-page selections. Keep existing catalog browsing, add-on identity, raw invalid buffers and quantity correction behavior. Replace blanket nonempty-cart navigation warnings with warnings for unsaved/failed persistence; explicit Cancel cart still confirms. Remove `Cart is not saved` and `Payment unavailable` only where the connected workflow actually works.
- [ ] Render final review with exact total, cash change, named ingredient/supply warnings and duplicate-reference warning. Completion remains disabled for empty/invalid/blocking/offline/read-only/stale/unconfirmed/storage-failure states. External copy states the owner checked receipt outside the app. Changed external amount requires resolving the difference outside the app and reconfirming.
- [ ] Render submitting/unknown without clearing cart. Unknown copy: `Payment recording status is unknown. Do not collect payment again while checking this sale.` Show Check again and appropriate same-key recovery action; no editable cart/discard/New Sale. Confirmed result uses only server snapshots/reference/payment/change; New Sale requires the durable terminal marker and creates a fresh draft.
- [ ] Verify desktop/tablet focus, dialog escape/return, announcements, scrolling, long content and 44 px targets; ensure phone notice and management return remain usable. Run payment browser tests, `npx vp test run resources/js/lib/pos`, `npm run types:check`, and `npm run check`.
- [ ] Review and commit Task 6: `feat(pos): connect payment review and confirmed sale flow`.

## Task 7: Prove interruption safety and record readiness

**Files**

- Create `tests/Browser/pos-recovery.spec.ts`, `docs/verification/2026-11-06-task-5-payment-recovery.md`.
- Extend backend concurrency/attempt tests and browser fixture helpers where the scenarios expose gaps.
- Update the parent Task 5 checklist, `docs/contracts/pos-v1.md` and `docs/STOCKRAGE-POS-PROGRESS-SUMMARY.md` only after the checks prove the corresponding behavior.

- [ ] Automate the decisive integration case: let the real checkout handler commit, then interrupt its downstream response before the UI receives it. Do not merely abort a request before it reaches Laravel or mock a sale response. Assert the UI enters unknown, refresh, lookup finds the original confirmed sale, and database inspection finds one sale/payment/six movements. Also suppress lookup temporarily to expose the unknown screen and prove no automatic POST occurs.
- [ ] Automate refresh while submission is pending, explicit same-key retry, confirmed replay after cache eviction, price change at final confirmation, inactive off-page product restore, actual browser offline mode, storage failure, two-tab takeover-versus-submit and delayed account-change notification. Inspect real database state through CLI fixture helpers; do not add production inspection endpoints.
- [ ] Run `npm run test:pos-browser`, `npx vp test run`, `npm run types:check`, `npm run check`, `npm run build`, `php artisan test`, `php vendor/bin/phpstan analyse --memory-limit=512M`, `php vendor/bin/pint --test`, `php artisan route:list --path=pos -v`, and `git diff --check`. Capture commands/results and classify baseline failures honestly.
- [ ] Inspect 1440x900 light/dark, 1024x768 landscape, 768x1024 portrait, reduced-height tablet and 390x844 phone notice. Exercise keyboard-only completion and error recovery, 200% zoom, long names/large money and focus after modal/result transitions. Record actual device, screen-reader and connection-simulation limits rather than claiming checks not performed.
- [ ] Write evidence with database before/after counts, operation IDs, reproducible fault controls, timings, screenshots and test commands. The report must distinguish automated browser interruption from physical-device/network evidence. Repeat concurrency tests against a different intended deployment database before claiming readiness on that engine.
- [ ] Mark Task 5 integrated/verified only for passed scope. Leave Task 6/7 features, tax/live-selling decisions, deployment and physical-device gaps explicitly separate. Review the final diff and commit owned tests/evidence: `test(pos): verify payment recovery and document task 5`.

## Acceptance checklist and handoff

- [ ] One submission/replay family creates one sale/payment and exactly the intended aggregate movements.
- [ ] A cache expiry, network failure, refresh or crashed processor never causes a new automatic operation or false success.
- [ ] Drafts survive refresh/logout in their own account/store; unsubmitted payment does not; conflicting tabs cannot both edit/submit one draft.
- [ ] Current prices, recipes, enabled methods, stock and warnings are checked at final review and atomically at completion.
- [ ] Confirmed sale displays the reference/items/payment/change and safely starts a new sale; unresolved outcomes do neither.
- [ ] Task 4 cart behavior and existing inventory workflows remain passing, and evidence records the actual backend/browser checks.

**Planning self-review:** Parent Task 5's eight bullets map to Tasks 4/6 (draft restore), 4/5 (logout/isolation), 4/5/7 (tabs and keys), 2/6 (payments), 2/3/6 (blocking), 2/3/6 (review), 3/5/7 (outcomes), and 5/6 (confirmation/New Sale). All five Review Focus cases have named tests. No implementation or new Task 5 test run is claimed by this plan.

**Execution recommendation:** Follow the selected backend-first order with native execution and checkpoints after Tasks 3 and 7. Implementation delegation was not selected in the interview; do not infer it from the design's builder review. Review this plan and its design before authorizing implementation.

**Browser-test references:** The installed Vite Plus configuration currently runs Node tests, and no Playwright dependency/config was found. A narrowly scoped browser runner fills the IndexedDB, multi-tab, reload and response-interruption gap; retain existing unit tests. Consult official [network interception documentation](https://playwright.dev/docs/network) and [local test server configuration](https://playwright.dev/docs/test-webserver) when implementing the harness.
