# STOCKRAGE Task 4 Mixed Cart Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Use superpowers:subagent-driven-development only when delegation is selected. Steps use checkbox (`- [ ]`) syntax for tracking. This document authorizes planning, not product implementation.

**Execution status (2026-11-02):** Integrated cart preparation completed with independent review fixes; see [verification and Task 5 handoff](../../verification/2026-10-31-task-4-mixed-cart.md). Historical checkbox steps describe the intended sequence; the evidence report identifies actual automated/browser checks and supplementary limits.

**Goal:** Deliver an owner/store-scoped mixed retail/food cart with per-line add-ons, exact estimates, server-authoritative quotes, and clear aggregate stock warnings, ready for Task 5 payment integration.

**Architecture:** Laravel supplies a paginated POS catalog and caches five-minute quotes calculated from current store data without stock writes. React owns an in-memory cart and selected-item snapshots; JSON catalog/quote requests preserve cart state and reject stale responses. Extend the existing POS layout, stock-demand service, and navigation guard.

**Tech Stack:** Existing Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Radix/shadcn, Wayfinder, Vite Plus, PHPUnit, Larastan, and Pint. No new framework or dependency is planned.

**Spec:** [Task 4 design and confirmed interview decisions](../specs/2026-10-31-stockrage-task-4-mixed-cart-design.md), [parent Task 4](2026-10-29-stockrage-frontend-action-plan.md), and [POS v1 contract](../../contracts/pos-v1.md).

## Global constraints

- Paths and commands below are relative to `example-app/`. Use the user's current checkout or an agreed isolated worktree; preserve unrelated work.
- Confirmed scope: integrated cart + quotes; retain shortage quantities; portrait cart drawer; warn before discarding; persistent drafts and all payment/sale writes stay in Task 5.
- Require authenticated, verified owners and scope every catalog/category/component/add-on/quote ID to the route store and actor where applicable. Membership alone is insufficient.
- Money uses PHP integer centavos, 0 through 9007199254740991. Sold counts are positive integers no greater than 9007199254740. Scaled stock magnitude is at most 9007199254740991, transported with three decimals; `each` is integral. Reject overflow, never silently round.
- Direct-stock shortages block readiness; ingredient/supply shortages warn and may project negative balances. Quotes never reserve inventory or append movements.
- Preserve Inter, 6 px base radius, existing light/dark/system infrastructure, action red `#E10D18`, indicator red `#FF101C`, dark red `#8B0000`, black `#111111`, dark surface `#1C1C1C`, and light gray `#F5F5F5`; verify actual contrast.
- No discounts, tax engine, split payments, saved drafts, provider integration, completion endpoint, sale records, or offline completion. Task 5 owns final acknowledgment and durable idempotency. Do not imply live-sales readiness.
- Generate Wayfinder helpers from real routes; never hand-edit generated files. Use disposable databases/fake mail for tests and browser verification.

## Review focus

1. A slow quote/search for an older cart or different store arrives last: ignore it, retain the newest cart/results, and never switch the selected store as a background-request side effect (Tasks 2–4).
2. Shared ingredients and direct add-ons span multiple lines: aggregate once, with exact boundaries and checked overflow (Tasks 1–2).
3. Search/pagination, Back/Forward, and canceled store switching occur with a nonempty cart: preserve selections during search and honor explicit keep/discard on exit (Tasks 3–4).
4. Product prices, recipes, allowed add-ons, or active flags change mid-cart: explain authoritative changes; retain invalid lines for correction without presenting estimates as verified (Tasks 2–4).
5. A portrait drawer, long basket, software keyboard, or narrow viewport obscures controls: totals, correction, focus, and exit remain accessible on supported devices (Task 5).

## Planning evidence and prerequisite boundary

Read-only inspection on 2026-10-31 found catalog/inventory persistence, `StockDemand`, exact `Quantity` helpers, a preview-only `PosLayout`, and Vite Plus unit tests. No production POS/quote endpoints exist. `useUnsavedChanges` intercepts Inertia visits, so use JSON picker reads instead of bypassing the guard for every same-page navigation.

The [Task 3 readiness audit plan](2026-10-31-stockrage-task-3-audit-completion-plan.md) is present as an untracked document; no readiness report was found. Older [Task 3 evidence](../../verification/2026-10-30-task-3-catalog-inventory.md) records passing functional tests and baseline formatting failures. Neither source proves today's gates pass. Do not mark the audit done or claim tests were rerun during this planning session.

At execution, record branch/HEAD/status and establish a fresh baseline using the final gate commands. If the separate audit has finished, use its current evidence and avoid repeating its repairs. If it has not, check Task 4's catalog/access/quantity/demand prerequisites and record the audit as outstanding. Repair only demonstrated blockers in this work; unrelated formatting cleanup stays with the audit. Never report a failing repository gate as green.

## File and interface map

| Responsibility                 | Files                                                                                                                                                                                                                                                                   |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Pure cart and exact projection | Create `resources/js/lib/pos/cart.ts`, `cart.test.ts`                                                                                                                                                                                                                   |
| POS payloads and contract      | Modify `resources/js/types/pos.ts`, `types/pos.contract-check.ts`, `fixtures/pos/scenarios.ts`, `fixtures/pos/scenarios.test.ts`, `docs/contracts/pos-v1.md`                                                                                                            |
| Authoritative quotes           | Create `app/Services/Pos/CreateQuote.php`, `QuoteStore.php`, `app/Http/Requests/PosQuoteRequest.php`, `app/Http/Controllers/PosQuoteController.php`, `tests/Feature/Pos/QuoteTest.php`; modify `app/Services/Catalog/StockDemand.php`, `tests/Unit/StockDemandTest.php` |
| Catalog and page reads         | Create `app/Data/PosCatalogData.php`, `app/Http/Controllers/PosController.php`, `tests/Feature/Pos/CatalogTest.php`, `tests/Feature/Pos/AccessTest.php`; modify `routes/web.php`                                                                                        |
| Background store authorization | Modify `app/Http/Middleware/EnsureTeamMembership.php` and route defaults in `routes/web.php`; cover preserved selection in POS quote/access tests and retain `tests/Feature/ShellAccessTest.php` regressions                                                            |
| Request behavior               | Create `resources/js/lib/pos/requests.ts`, `requests.test.ts`, `resources/js/hooks/use-pos-quote.ts`                                                                                                                                                                    |
| Cart UI                        | Create `resources/js/pages/pos/index.tsx`, `components/pos/product-picker.tsx`, `addon-picker.tsx`, `cart-panel.tsx`, `quote-summary.tsx`; modify `layouts/pos-layout.tsx`, `app.tsx`                                                                                   |
| Entry and regression coverage  | Modify `resources/js/lib/shell-navigation.ts`, `shell-navigation.test.ts`; reuse `hooks/use-unsaved-changes.ts`, `components/team-switcher.tsx`, `components/unsaved-changes-prompt.tsx` unless a reproduced integration defect requires a small fix                    |
| Evidence and handoff           | Create `docs/verification/2026-10-31-task-4-mixed-cart.md` during execution; update parent Task 4 status and `docs/STOCKRAGE-POS-PROGRESS-SUMMARY.md` only with actual results                                                                                          |

### Shared interfaces to implement

- Keep `CartLine`, `CheckoutQuoteInput`, and `CheckoutQuote` shapes, adding required `inventoryItemName: string` to `StockEffect`. Update current fixtures atomically. Keep `payment`, `changeMinor` null in Task 4; reject non-null payment input.
- Define `PosCatalogFilters = { query: string; categoryId: number | null }` and `PosCatalogPage = { catalog: PageSlice<CatalogListItem>; addons: CatalogListItem[]; inventory: InventoryItem[]; filters: PosCatalogFilters; hasProducts: boolean }` in `types/pos.ts`. Include the referenced inventory/add-on closure for that page, deduplicated by ID. `hasProducts` reports whether the store has any active retail/food products before search/category filters, so an empty catalog is distinguishable from no matches. Define `PosPageProps = PosCatalogPage & { business: BusinessContext; categories: Category[]; enabledPaymentMethods: PaymentInput['method'][] }`; return an empty enabled-method list for this phase.
- `GET /{current_team}/pos` (`pos.index`) returns Inertia `pos/index`; `GET /{current_team}/pos/catalog` (`pos.catalog`) accepts `query/categoryId/page` and returns `PosCatalogPage`; `POST /{current_team}/pos/quotes` (`pos.quotes.store`) returns `CheckoutQuote` or `CheckoutErrorResponse`.
- Set trusted server route default `preserve_current_team: true` on `pos.catalog` and `pos.quotes.store`. After the existing membership/role checks, `EnsureTeamMembership` skips its `switchTeam` call only when this route default is true. Do not accept a query/body/header override or skip authorization. Other routes, including `pos.index` and explicit store switching, retain existing selection behavior.
- `PosCatalogData::page(Team $store, array $filters): array` supplies both initial and JSON pages. `CreateQuote::handle(Team $store, User $actor, array $validated): array` calculates the quote. `QuoteStore::put(Team $store, User $actor, array $input, array $quote): void` and `get(Team $store, User $actor, string $quoteId): ?array` store/retrieve an immutable `{input, quote}` envelope for 300 seconds under `pos:quote:{storeId}:{userId}:{quoteId}`. Missing, expired, or wrong-owner lookup returns null.
- In `cart.ts`, export `CartData = { catalog: ReadonlyMap<number, CatalogItem>; inventory: ReadonlyMap<number, InventoryItem> }`, `normalizeAddonIds(ids: readonly number[]): number[]`, `lineKey(productId: number, addonIds: readonly number[]): string`, `addLine(lines: readonly CartLine[], incoming: CartLine): CartLine[]`, `setLineQuantity(lines: readonly CartLine[], lineId: string, quantity: number): CartLine[]`, `setLineAddons(lines: readonly CartLine[], lineId: string, addonIds: number[]): CartLine[]`, `removeLine(lines: readonly CartLine[], lineId: string): CartLine[]`, and `estimateCart(lines: readonly CartLine[], data: CartData): CartEstimate`.
- `CartEstimate = { subtotalMinor: number | null; totalMinor: number | null; stockEffects: StockEffect[]; blockingIssues: ContractIssue[]; warnings: ContractIssue[] }`. Missing data, invalid configuration, or arithmetic failure produces null totals, no partial effects, and a blocking issue; valid stock shortages retain numeric totals. Mutation helpers throw a typed `CartInputError` for invalid counts/IDs, duplicate selections, or overflow; UI retains the field value/error and catches it. Helpers never mutate their arguments.

## Task 1: Implement and prove cart identity, arithmetic, and projections

**Deliverable:** Pure, tested cart operations and exact aggregate estimates using existing fixture data. **Depends on:** verified Task 3 representations.

- [ ] Read the design, contract, current fixtures, `Quantity.php`, and existing price/quantity helpers. Add the named-stock-effect field and fixture/type updates; document the additive contract change and planned routes as not yet implemented.
- [ ] Add behavioral tests in `cart.test.ts`: reordered selections merge; duplicate IDs reject; different combinations stay separate; merge retains target ID; modifier collision sums quantities into the existing target; quantity edit retains ID; removal changes only the selected line; input arrays remain unchanged. Test 0, negative, fractional, empty UI conversion, maximum count, merge overflow, and unknown line IDs (reject, rather than changing another line).
- [ ] Add estimate tests against the worked basket: total 41000; effects water/cheese `1.000`, beef `-100.000`, sauce `-35.000`, seasoning `0.625`, packaging `-1.000`. Water demand 2 has no blocking issue, demand 3 does; cheese demand 3 allowed and 4 blocked across separate lines. Recipe warnings never become direct blockers. Cover disallowed/inactive add-ons, missing snapshots, fractional counted components, and product/aggregate money and stock overflow.
- [ ] Run `npx vp test run resources/js/lib/pos/cart.test.ts`; observe relevant failures before implementation. Implement the exact interfaces above with `BigInt` intermediates for money/thousandths, returning only bounded JSON-safe numbers/decimal strings. Add no floating-point stock accumulation or incidental tax logic.
- [ ] Run the new tests plus `npm run test:contracts` and `npm run types:check`. Expected: all pass. Review only intended files and record this deliverable as complete; if committing is part of execution, use a focused `feat: add mixed cart calculations` commit.

## Task 2: Provide authoritative, store-scoped quotes

**Deliverable:** A real JSON quote endpoint with exact current snapshots and aggregate shortage classification, without financial or inventory writes. **Consumes:** existing cart payload and the amended named `StockEffect`. **Produces:** `CheckoutQuote`, `CreateQuote`, and `QuoteStore` interfaces above.

- [ ] Add Laravel tests in `tests/Feature/Pos/QuoteTest.php`: the worked basket returns 41000 and the exact effects above; submitted fake prices/totals cannot influence results; direct shortage returns 200 with blockers, ingredient shortage returns 200 with warnings; stock balances, revisions, operation/movement counts remain unchanged. Test price change to 11000 yields 44000; recipe beef change to 110.000 yields demand 380.000 and after -130.000.
- [ ] Add validation tests for empty cart, duplicate line UUIDs, invalid counts, duplicate/disallowed/inactive selections, inactive dependencies, direct add-on as base product, non-null payment, and safe-integer overflow. Validate referenced IDs store-first; foreign IDs return 404, invalid local choices return 422. Error bodies use `{error: {code,message,fieldErrors,issues}}` and dotted input paths. Map `Quantity` overflow explicitly to `numeric_range_exceeded`; do not leak generic Inertia validation envelopes from JSON actions.
- [ ] Add a two-owned-store regression: while B is selected, quote a basket belonging to A; the result contains A's data and the user's persisted `current_team_id` stays B. Implement the route-default opt-out in `EnsureTeamMembership` without changing ownership enforcement. This prevents a late request from undoing an intentional switch even if the browser aborts or ignores its response.
- [ ] Run `php artisan test --filter=QuoteTest` and observe relevant failures. Implement request validation, controller, and service with existing auth/owner middleware on `pos.quotes.store`. Reject unknown input keys carrying forged totals/authority or ignore them through a validated whitelist; never use them. Build snapshots/prices/effects under `StoreCatalogTransaction::run`; eagerly resolve product/add-on relationships and avoid per-line redundant queries where practical. Extend `StockDemand` output with names without changing its policies.
- [ ] Populate all quote fields: UUID ID/revision, UTC expiry now + 300 seconds, real business context, sorted normalized selections/snapshots, checked subtotal/total, null payment/change, catalog and recipe revisions, named aggregate effects, stable issue IDs. Use `direct-stock-{inventoryItemId}` and `recipe-stock-{inventoryItemId}` IDs for shortage issues; messages include item name and before/demand/after quantities.
- [ ] Implement the scoped cache envelope using Laravel's existing cache. Test retrieval by same actor/store, isolation under a different actor/store, five-minute expiry using the test clock, and cache eviction requiring a fresh quote. Quote creation/cache failure must return an error, never an apparently reusable ID. This cache is not the Task 5 attempt store.
- [ ] Run `php artisan test --filter=QuoteTest` and `php artisan test --filter=StockDemandTest`; expected PASS. Confirm repeated quotes still produce no stock movement. Record results and use a focused quote commit if committing.

## Task 3: Supply the paginated POS catalog without losing cart state

**Deliverable:** Initial Inertia POS props and JSON search/pagination with enough linked data for add-ons and estimates. **Consumes:** `PosCatalogPage`, existing serializers. **Produces:** `pos.index` and `pos.catalog` routes and generated helpers.

- [ ] Add `CatalogTest.php` and `AccessTest.php`: 25-row pagination with stable name/ID ordering; name/SKU query; category filter; active retail/food only; no standalone ingredients/supplies/add-ons; zero-stock products remain visible. Verify page-scoped allowed add-ons and component inventory are included once, foreign categories reject, and unrelated store data never appears. Distinguish no catalog items from no matching results.
- [ ] Cover guest/unverified/admin/member/outsider requests across all three POS routes, using JSON headers for JSON routes. Owner succeeds; authenticated nonowner is denied; foreign IDs under an owned store return 404. Validate query length 255, category integer, and positive page. Record query count for a fixture page with recipes/add-ons to catch N+1 behavior; set a bounded regression expectation from the batched implementation, not a guessed performance claim.
- [ ] Test that a catalog JSON read for owned store A while B is selected returns only A's data and leaves B selected. Verify an intentional page visit to A still selects A and the existing explicit store-switch tests remain green. Run `php artisan test --filter=ShellAccessTest` after the middleware integration.
- [ ] Run `php artisan test tests/Feature/Pos/CatalogTest.php` and `php artisan test tests/Feature/Pos/AccessTest.php`. Observe failures, implement `PosCatalogData::page`, controller/actions, and routes inside the existing owner route group. No production navigation link until the page works.
- [ ] Return resolved current-page dependencies with blocked/inactive add-on metadata where needed for an explanation, but disallow selecting inactive/misconfigured add-ons. Keep shortage-only add-ons selectable and flag their direct blockers or recipe warnings in the resulting cart. Optional unavailable add-ons must not block their parent without selection.
- [ ] Run the two test files and `php artisan wayfinder:generate --with-form`; expected successful tests and generated helpers for the three real routes. Verify the generator command with local Artisan help if flags differ; never fabricate helper files. Update contract endpoint status only for implemented routes.

## Task 4: Integrate cart controls, request freshness, and navigation protection

**Deliverable:** A usable integrated cart that preserves owner intent under edits, filtering, errors, and navigation. **Consumes:** Tasks 1–3. **Produces:** production `pos/index` and a reusable quote hook for Task 5.

- [ ] In `requests.ts`, export `requestJson<T>(url: string, init: RequestInit): Promise<T>` and `createRequestGate(): { next(scope: string, inputKey: string): number; accepts(generation: number, scope: string, inputKey: string): boolean; invalidate(): void }`. `requestJson` sends same-origin credentials, JSON accept/content headers as appropriate, and the existing Laravel CSRF cookie value in `X-XSRF-TOKEN` for POST. Reject HTTP errors/HTML/malformed bodies. Use generated URLs. Validate quote response shape and exact line UUID/product/quantity/normalized add-on correspondence before applying it.
- [ ] Add `requests.test.ts` with fake fetch/timers: older responses arriving last are ignored, store/user changes invalidate outstanding requests, clearing the cart invalidates a response, abort is harmless, 422 preserves field errors, 401/403/419 and HTML are errors, failed/retried requests preserve cart data, and CSRF header is present for quote POST. Run `npx vp test run resources/js/lib/pos/requests.test.ts` RED before implementation, then GREEN. Keep orchestration testable without installing a DOM runner.
- [ ] Define `QuoteState = { status: 'empty' | 'invalid' | 'pending' | 'current' | 'stale' | 'error'; quote: CheckoutQuote | null; error: ContractError | null }`; implement `usePosQuote({scopeKey, lines, valid, quoteUrl}): QuoteState & {retry: () => void}` in `hooks/use-pos-quote.ts`. The exact input type is `{scopeKey: string; lines: CartLine[]; valid: boolean; quoteUrl: string}`. Debounce 300 ms, expire after server `expiresAt`, revalidate on visible/focus/online recovery when stale, cancel on cleanup, and apply the generation/input guard. No response from old inputs may be labeled current. Extract timer/state transitions into `requests.ts` only when needed for the behavioral tests.
- [ ] Build picker/add-on/cart components and `pages/pos/index.tsx`. Use a selected-item snapshot map across JSON searches/pages; reset it by authenticated user/store identity. Add-ons open before adding configurable products and are editable per line. Implement identity/merge rules, explicit remove/cancel, input-buffer validation, immediate estimates, named stock effects, current server totals, and explained price/recipe changes. Failed catalog reads preserve the cart and show Retry; failed quotes show estimates/stale status, never verified readiness.
- [ ] Connect `useUnsavedChanges(lines.length > 0)` to the page and render its dialog. Use JSON picker reads so search/pagination bypass no navigation protection. Confirm cancellation of Cancel cart changes nothing; confirmation invalidates requests and clears state. A successful store/user change remounts the state; no persistent storage or remembered cart. Reuse current guard and switcher behavior, fixing only reproduced failures.
- [ ] Add focused pure-state tests for any newly extracted quote transition behavior: invalid field suppresses requests, expiry revokes current status, stale price response is ignored, visible expiry requests a new quote once, failure does not loop, Retry uses current inputs. Run all `resources/js/lib/pos` tests and `npm run types:check`; expected PASS. UI/focus/navigation behavior gets real-browser verification in Task 5 below, not implementation-mirroring tests.

## Task 5: Finish the responsive shell and prove cart preparation

**Deliverable:** Desktop/tablet cart preparation, safe navigation, current evidence, and an explicit Task 5 handoff. **Depends on:** Tasks 1–4. This is implementation-plan Task 5, not the parent plan's payment Task 5.

- [ ] Refactor existing `PosLayout` to accept a header slot along with picker/cart/summary/footer. Preserve preview labeling in `app.tsx`; production `pos/index` owns its slot-based layout, using an explicit pass-through resolver for that page instead of wrapping it in `AppLayout`. Reuse existing 768/1024 breakpoints, drawer focus behavior, and appearance initialization.
- [ ] Add persistent total/quote status on tablet, scrollable lines with fixed footer in the drawer, and desktop fixed summary/action. Display disabled `Payment unavailable` with the explanation in the design. Add store context, management return, and existing store-switch controls. Add owner-only POS navigation via generated helpers and update `shell-navigation.test.ts` to expect Dashboard, POS, Catalog, Inventory, Settings. Keep phones on the supported-screen notice with management access.
- [ ] Use a disposable SQLite preview and fake mail to create the contract basket through current catalog/inventory workflows. Prepare it entirely with keyboard, then touch emulation. Confirm PHP 410.00, distinct burger configurations, exact projected balances, warnings without blockers, and no inventory mutation. Increase water to 3: retain 3, show available 2, mark blocked. Correct to 2, remove lines, edit modifiers into a merge, cancel dialogs, and cancel the cart with both keep/discard outcomes.
- [ ] Exercise paginated searches with items already in cart; slow and reversed quote/search responses; expired quote; changed price/recipe/active status in another session; failed/HTML/422 quote response; offline/online; Back/Forward; management exit; refresh warning; canceled and confirmed store switch; logout/new user. Verify no stale success, false discard prompt during search, cross-store state, or accidentally recorded payment. For errors requiring injected responses, record the injection method and restore normal behavior.
- [ ] Inspect light/dark at 1440x900, 1024x768, 768x1024, and 390x844. Check long names/large prices/long cart, 200% zoom, visible focus, associated errors, dialog Escape/focus return, status announcements, 44 px targets, and absence of page-wide overflow. Check keyboard-reduced height; record physical-device/on-screen-keyboard checks as unverified if hardware is unavailable.
- [ ] Run the final gate commands below once after fixes; repeat only checks affected by subsequent changes. Review diffs, contract coverage, and all five review-focus items. Record actual failures and baseline ownership, not historical pass counts.
- [ ] Write `docs/verification/2026-10-31-task-4-mixed-cart.md` with tested commit/worktree, commands/results, isolated-preview reproduction steps, representative screenshots, scope limits, and remaining blockers. Update parent Task 4 checkboxes/status and progress summary only to match evidence. Task 4 can be verified for preparation; payments remain unavailable and overall release is incomplete.

## Final verification commands

Run from `example-app/` with isolated test configuration. Expected exit code is 0 for each green gate; preserve and disclose unrelated baseline failures if the separate audit has not cleared them.

```powershell
npx vp test run
npm run types:check
npm run check
npm run build
php artisan test
php vendor/bin/phpstan analyse --memory-limit=512M
php vendor/bin/pint --test
php artisan route:list --path=pos
```

The route listing must contain production index/catalog/quotes with owner protection; checkout completion/attempt routes remain absent. A successful build is insufficient without the basket, interruption, and device checks above.

## Completion and parent Task 5 handoff

Task 4 is complete when real store products can form the mixed cart, authoritative quotes match exact totals/effects, direct-stock excess remains visible and blocked, ingredient warnings remain nonblocking, keyboard/tablet preparation works, and documented checks support these claims. Quotes may write cache entries only; no business inventory or financial records change.

Parent Task 5 receives `CartLine[]`, `usePosQuote`, immutable scoped quote envelopes, named stock effects, and the production POS shell. It must add durable store/user drafts, tab conflict handling, payment input, final acknowledged quote review, atomic completion, durable attempt lookup/idempotency, and success/unknown-result recovery. It must resolve original confirmed attempts before checking cache/quote expiry. Cache loss never justifies a fresh checkout key or invalidating an already confirmed sale.

**Recommended execution approach:** native, sequential implementation with a final independent review. These tasks share types, routes, and cart/quote state; parallel implementation would create avoidable coordination work. Execution method and implementation approval remain for the user's next instruction.
