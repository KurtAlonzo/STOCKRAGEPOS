# STOCKRAGE Task 1 Contracts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Use superpowers:subagent-driven-development only when delegation is authorized. Steps use checkbox (`- [ ]`) syntax for tracking. This document authorizes planning, not product implementation.

**Goal:** Execute Task 1 of the frontend action plan by delivering agreed operating rules, frontend/backend payloads, typed contracts, and realistic checked fixtures before persistent business screens are built.

**Architecture:** Keep Laravel/Inertia and its team tenancy. A concise contract document owns the behavioral rules; manually maintained TypeScript definitions and deterministic fixtures demonstrate the same contract. Quotes, completion, idempotency, and atomic stock/payment posting are specified here and implemented in later work.

**Tech Stack:** Existing Laravel 13, Inertia 3, React 19, TypeScript, Vite Plus 1.4.0 with its bundled test runner. No new dependencies.

**Spec:** [Task 1 design and confirmed interview decisions](../specs/2026-10-29-stockrage-task-1-contracts-design.md). Read alongside [parent Task 1](2026-10-29-stockrage-frontend-action-plan.md#task-1-lock-the-operating-rules-and-data-contracts). The latest interview decisions in the spec supersede earlier answers, especially optional payment references.

## Global Constraints

- Business currency is PHP; money travels as integer centavos. Business timezone is `Asia/Manila`.
- Sold retail items, food portions, and add-ons use whole positive quantities. Counted stock uses whole quantities. Measured ingredients use decimal strings with up to three decimal places.
- GCash/Maya require the owner's confirmation of receipt; references are optional.
- Initial prices are final selling prices. Do not add or extract tax. Tax treatment remains unresolved and must be confirmed before live use.
- Each selected add-on applies once per product unit. Different selections stay on separate lines.
- A supplied duplicate reference within the same store and provider produces a warning. The owner may correct it or continue.
- Directly stocked add-ons block when short. Add-ons consuming ingredients/supplies warn and may take those balances negative.
- Preserve owner-only access, store isolation, immutable sale/stock history, same-key recovery, one payment method per sale, and server authority. No offline completion.
- No persistent screens, backend routes/controllers/migrations, generated Wayfinder edits, production cart/recovery logic, or new dependency installation in this work package.

## Review Focus

1. Lost response after commit: lookup and same-key replay identify the existing sale; no new operation is created. Fixtures in Task 3; backend acceptance requirement in Task 2.
2. Shared components across multiple lines and add-ons: aggregate before applying shortage policies. Worked example and independent fixture arithmetic in Task 3.
3. Optional/duplicate references: null is valid, confirmation is mandatory, duplicates only warn, and references never deduplicate checkout. Positive and negative fixture/type checks in Task 3.
4. Store/user/role boundaries: foreign resource IDs, lookup keys, and stale current-team state cannot bypass the URL store's ownership scope. Contract examples in Task 2 and fixture checks in Task 3.
5. Precision and revision boundaries: reject fractional counted units or excess measured precision; changed price/recipe/warnings require renewed review. Examples and checks in Task 3.

## Current evidence and preparation

- Existing business types are absent; `resources/js/types/index.ts` currently re-exports auth/navigation/team/UI types.
- `routes/web.php` has a team-scoped authenticated dashboard but no business routes. Document the routes below as **proposed**, never working endpoints.
- `EnsureTeamMembership::handle(..., ?string $minimumRole = null)` supports an owner minimum. Today's dashboard route does not pass that minimum. Later business routes still need scoped policies/queries.
- `HandleInertiaRequests.php` and `types/global.d.ts` already define the shared team/auth context; do not replace it.
- Package scripts include `types:check`, `check`, and `build`; Vite Plus already exports `vite-plus/test`. Its test CLI help succeeded during planning. Test execution has not yet been proven.
- The checkout contains pre-existing tracked changes and untracked documentation/assets. Inspect `git status --short` before editing; preserve that work. Do not use blanket staging, resets, cleanups, or whole-repo formatting.
- Paths below are relative to `example-app/`; run commands from that directory. If execution needs isolation, use `using-git-worktrees` and preserve the source plan/spec, which may still be untracked in the original checkout. Never assume a new worktree contains them.

## File map

| File                                                 | Responsibility                                                                                               |
| ---------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Create `docs/contracts/pos-v1.md`                    | Rules, field dictionary, endpoint/props matrix, errors, recovery, worked examples, future backend acceptance |
| Create `resources/js/types/business.ts`              | Shared money/quantity/revision/context/issue/pagination contracts                                            |
| Create `resources/js/types/catalog.ts`               | Catalog, components, permitted add-ons, category and editor payloads/props                                   |
| Create `resources/js/types/inventory.ts`             | Stock records, movements, receipt/adjustment payloads, page props                                            |
| Create `resources/js/types/pos.ts`                   | Cart, quote, completion, attempt and lookup unions, POS page props                                           |
| Create `resources/js/types/sales.ts`                 | Historical snapshots, payment, reversal payloads, sales page props                                           |
| Modify `resources/js/types/index.ts`                 | Export these domain types using the existing pattern                                                         |
| Create `resources/js/fixtures/pos/scenarios.ts`      | Explicitly fixture-only deterministic catalog, stock, requests, results, and expected examples               |
| Create `resources/js/fixtures/pos/scenarios.test.ts` | Independent arithmetic, fixture invariants, and scenario coverage                                            |
| Create `resources/js/types/pos.contract-check.ts`    | Compile-time discriminated-union checks using `@ts-expect-error`                                             |
| Modify `vite.config.ts`                              | Add only a Node test include for `resources/js/**/*.test.ts` in its existing config                          |
| Modify `package.json`                                | Add `"test:contracts": "vp test run resources/js/fixtures/pos/scenarios.test.ts"`                            |

No lockfile change is expected. Keep the dependency order `business`, then `catalog/inventory`, then `sales`, then `pos` acyclic: shared immutable sale-line/payment snapshot definitions live in `sales.ts` and `pos.ts` imports them; `sales.ts` must not import `pos.ts`.

## Task 1.1: Write operating rules and domain types

**Deliverable:** A readable domain contract and compiling types with no runtime business implementation. Depends on the reviewed design.

**Files:** Create `docs/contracts/pos-v1.md`, `types/business.ts`, `types/catalog.ts`, `types/inventory.ts`, `types/sales.ts`; modify `types/index.ts` (all type paths under `resources/js/`).

**Interfaces:**

- `business.ts`: `MinorUnits = number`, `DecimalQuantity = string`, `Revision = string`; `BusinessContext { storeId: number; storeSlug: string; currency: 'PHP'; timezone: 'Asia/Manila'; taxTreatment: 'unconfigured' }`; `ContractIssue { id: string; code: string; message: string; field: string | null; inventoryItemId: number | null }`; `ContractError { code: string; message: string; fieldErrors: Record<string, string[]>; issues: ContractIssue[] }`; `PageSlice<T> { data: T[]; currentPage: number; lastPage: number; perPage: number; total: number }`.
- `catalog.ts`: `RecipeComponent { inventoryItemId: number; quantity: DecimalQuantity; unit: 'each' | 'g' | 'ml' }`; discriminated `CatalogItem` with shared `id/name/sku/categoryId/active/priceMinor/revision/allowedAddonIds`, retail `stockItemId`, food `components/recipeRevision`, and add-on `stockBehavior: 'direct' | 'recipe'` branches carrying respectively `stockItemId` or `components/recipeRevision`. `Category { id: number; name: string; active: boolean }`, `CategoryInput { name: string; active: boolean }`, `CategoryIndexProps { business: BusinessContext; categories: PageSlice<Category> }`, `CatalogItemInput`, `RecipeInput`, `CatalogIndexProps`, and `CatalogEditorProps`.
- `inventory.ts`: `InventoryItem` with `id/name/kind/unit/balance/revision/shortagePolicy`, with kind-to-unit/policy constraints in the document; `StockMovement` with `id/inventoryItemId/kind/before/delta/after/unit/reason/actorId/occurredAt/sourceType/sourceId`; `InventoryItemInput { name: string; kind: 'retail' | 'ingredient' | 'supply' | 'addon'; unit: 'each' | 'g' | 'ml' }`, `StockReceiptInput`, `StockAdjustmentInput`, `InventoryIndexProps`, and `StockHistoryProps`. Derive shortage policy from kind, require `each` for retail/direct add-on stock, and initialize new balances at zero; opening stock is a receipt movement. Receipt quantity is positive; adjustments use a signed delta and require a reason.
- `sales.ts`: `SaleLineSnapshot`, `PaymentInput`, `RecordedPayment`, `Sale`, `SaleReversal`, `ReversalInput`, `SalesIndexProps`, and `SaleShowProps`. `PaymentInput` is cash `{ method: 'cash'; tenderedMinor: MinorUnits }` or external `{ method: 'gcash' | 'maya'; receivedConfirmed: true; reference: string | null }`. `Sale.status` is `completed | reversed`; preserve original totals/snapshots and a linked reversal. Reversal requires reason, external refund confirmation, and explicit restock selections for eligible direct-stock lines.

- [x] **Step 1:** Record `npm run types:check` as the baseline. Record failures without fixing unrelated files.
- [x] **Step 2:** Write the contract's rules/field dictionary from the spec. Explicitly state that the aliases do not enforce runtime integer, range, or precision validation. Specify canonical three-place strings, whole `each` values, exact thousandths, money maximum `9007199254740991`, absolute scaled-quantity maximum `9007199254740991`, sold-count maximum `9007199254740`, `numeric_range_exceeded` for overflow, optional reference normalization, and no assumed tax exemption.
- [x] **Step 3:** Define the interfaces above, including the full snapshot fields needed to preserve item/add-on names, prices, quantities, totals, component deductions, original payment, and sale/reference timestamps after catalog edits. Use existing numeric IDs; make absent values explicitly nullable rather than ambiguous optional fields.
- [x] **Step 4:** Define list filters and page props: catalog uses query/category/kind/active; inventory uses query/kind/stockState; history uses item/type/date; sales uses reference/date/payment/status. Each page supplies `business: BusinessContext`, its typed records/pagination, and its filter values, alongside existing Inertia shared props.
- [x] **Step 5:** Verify `npm run types:check`. Read the rule/field dictionary against the seven interview decisions. Check that none of these types exposes an ingredient/supply as an automatic POS tile or represents a food product with directly stocked finished units.
- [x] **Step 6:** Review and commit only this task's paths, e.g. `docs: define POS operating rules and domain contracts`. Do not mark parent Task 1 complete yet.

## Task 1.2: Specify actions, quotes, errors, and safe attempts

**Deliverable:** Complete frontend/backend interaction shapes with replay/rejection examples. Depends on Task 1.1.

**Files:** Extend `docs/contracts/pos-v1.md`; create `resources/js/types/pos.ts`; export it from `types/index.ts`.

**Interfaces:**

- `CartLine { id: string; productId: number; quantity: number; addonIds: number[] }`; `CheckoutQuoteInput { lines: CartLine[]; payment: PaymentInput | null }`.
- `CheckoutQuote`: `id/revision/expiresAt/business/lines/subtotalMinor/totalMinor/payment/changeMinor/catalogRevisions/recipeRevisions/stockEffects/blockingIssues/warnings`. `lines` are authoritative snapshots; `stockEffects` include item/unit/before/demand/after/policy. `payment` and `changeMinor` are nullable for preliminary quotes. No `taxMinor: 0` assertion.
- `CheckoutCompletionInput { idempotencyKey: string; quoteId: string; quoteRevision: Revision; lines: CartLine[]; payment: PaymentInput; acknowledgedWarningIds: string[] }`. The server binds inputs to the quote and canonicalizes the complete original request for replay/conflict comparison.
- `CheckoutAttempt`: discriminated client union with common `idempotencyKey/draftId/storeId/userId`; `draft`, `submitting`, `confirmed { sale: Sale }`, `rejected { error: ContractError }`, and `unknown { originalRequest: CheckoutCompletionInput }`. Keep the original completion request recoverable during `submitting` too.
- `AttemptLookupResult`: `not_found`, `pending { idempotencyKey }`, `confirmed { idempotencyKey; sale: Sale }`, or `rejected { idempotencyKey; error: ContractError }`. `CheckoutCompletionResult` returns confirmed or pending. HTTP conflicts/rejections use typed error payloads, with a replacement quote when review is required.
- `PosPageProps { business: BusinessContext; catalog: PageSlice<CatalogItem>; categories: Category[]; enabledPaymentMethods: ('cash' | 'gcash' | 'maya')[] }`, alongside existing shared Inertia props.

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
| POST `/pos/quotes`                                                      | `CheckoutQuoteInput` -> `CheckoutQuote`                           | JSON         |
| POST `/pos/checkouts`                                                   | `CheckoutCompletionInput` -> `CheckoutCompletionResult`           | JSON         |
| GET `/pos/attempts/{idempotencyKey}`                                    | operation key -> `AttemptLookupResult`                            | JSON         |
| GET `/sales`; GET `/sales/{sale}`                                       | filters/id -> `SalesIndexProps`/`SaleShowProps`                   | Inertia      |
| POST `/sales/{sale}/reversals`                                          | `ReversalInput` with reversal operation key -> refreshed sale     | Inertia form |

Catalog/category removal means archive/deactivate through PATCH. Do not specify destructive deletion of referenced records. Every matrix entry refers to the named payload in its owning type file.

- [x] **Step 1:** Write the new POS types and concrete matrix entries, request/response examples, and pagination/filter shapes. Specify IDs, route names, success status codes (quote 200, checkout confirmed 200, pending 202, authenticated lookup 200), and redirects for forms. Auth/CSRF failures remain failures even if infrastructure returns HTML.
- [x] **Step 2:** Write JSON errors `{ error: ContractError, replacementQuote?: CheckoutQuote }` for 422 validation (`validation_failed`), 409 quote expiry/change or stock conflict (`quote_expired`, `quote_changed`, `stock_conflict`), and 409 changed-payload replay (`idempotency_conflict`). Define 401/403/404/419 handling and field paths such as `lines.0.quantity`. Distinguish Inertia validation error bags/redirects from these JSON responses.
- [x] **Step 3:** Specify five-minute quote expiry, current revisions, aggregate demand, allowed add-ons, payment review, and warning acknowledgment. Document that final revalidation can discover new stock or duplicate-reference warnings; return a replacement quote for renewed review instead of committing silently. A duplicate reference never becomes a blocking stock issue or uniqueness error.
- [x] **Step 4:** Document the attempt lifecycle from the spec with a request/result table: original completion, concurrent same-key request, confirmed replay, changed-payload replay, known rejection, `pending`, `not_found`, and commit followed by response loss. Include restart after known rejection and same-key recovery after process failure. An expired quote must not prevent retrieving/replaying a previously confirmed sale.
- [x] **Step 5:** Document later backend invariants: durable operation record, unique scoped operation key, serialized processing/row locks, transaction containing sale/payment/snapshots/movements/confirmed attempt, rollback behavior, and no attempt expiry/cleanup in this release. Require authorization on every lookup/replay and immutable original completion input during uncertainty.
- [x] **Step 6:** Specify reversal reason/refund confirmation, one full reversal per original sale, reversal-key replay, explicit eligible restock selection, and no automatic food ingredient/supply restoration. Map movements to original or reversal sources. These remain proposed backend requirements.
- [x] **Step 7:** Run `npm run types:check`; manually trace the lost-response and foreign-store cases from start to finish. Confirm `unknown` is not treated as rejected and a null reference does not disable idempotency. Commit only these task files, e.g. `docs: specify checkout actions and recovery protocol`.

## Task 1.3: Add realistic fixtures and meaningful contract checks

**Deliverable:** Typed fixture examples agree with the contract and independently checked arithmetic. Depends on Tasks 1.1-1.2.

**Files:** Create `resources/js/fixtures/pos/scenarios.ts`, its colocated `scenarios.test.ts`, and `resources/js/types/pos.contract-check.ts`; modify `vite.config.ts`, `package.json`, and `docs/contracts/pos-v1.md`.

**Interfaces:** Export `fixtureSource = 'fixture' as const`, `catalogFixture`, `inventoryFixture`, `mixedBasketFixture`, and `contractScenarios`. Use `satisfies` against the domain types. `mixedBasketFixture` contains `quoteInput`, `completionInput`, `quote`, `sale`, and independently authored `expected` totals/stock effects. Each `contractScenarios` entry has a stable `id`, typed request/response or typed form example, and an expected outcome. Fixture labels/wrappers never silently replace production props.

- [x] **Step 1:** Add the `test:contracts` script and a minimal `test: { environment: 'node', include: ['resources/js/**/*.test.ts'] }` block to the existing Vite config. Import test APIs from `vite-plus/test`. Retain the existing plugins and settings. No separate test framework, browser environment, or package installation.
- [x] **Step 2:** Write failing tests for `mixed_basket_total_and_change`, `shared_components_aggregate_across_lines_and_addons`, `direct_stock_boundaries`, `measured_precision_and_counted_units`, `optional_reference_and_duplicate_warning`, `revision_changes_require_review`, `attempt_examples_preserve_original_key`, and `forbidden_store_examples_do_not_leak_records`. Run `npm run test:contracts`; expect failure due to the fixture module not existing, not a configuration/dependency error. Resolve runner setup only if it prevents collecting the tests.
- [x] **Step 3:** Add the exact mixed basket and catalog/inventory IDs from the spec: total `41000`, cash tender `50000`, change `9000`; water stock `2.000`, cheese `3.000`, beef `250.000`, sauce `-5.000`, seasoning `1.000`, packaging `2.000`. Require six aggregated stock effects: water `-1.000`, cheese `-2.000`, beef `-350.000`, sauce `-30.000`, seasoning `-0.375`, packaging `-3.000`. Independently recompute line totals and component demand from source fixture definitions in the test, using exact integer/thousandth arithmetic, and compare with the authored quote and sale snapshots. The assertions include:

    ```ts
    expect(mixedBasketFixture.quote.totalMinor).toBe(41000);
    expect(mixedBasketFixture.quote.changeMinor).toBe(9000);
    expect(mixedBasketFixture.sale.totalMinor).toBe(41000);
    expect(mixedBasketFixture.quote.stockEffects).toHaveLength(6);
    expect(
        mixedBasketFixture.quote.stockEffects.find(
            (effect) => effect.inventoryItemId === 203,
        ),
    ).toMatchObject({ demand: '350.000', after: '-100.000' });
    expect(
        mixedBasketFixture.quote.stockEffects.find(
            (effect) => effect.inventoryItemId === 205,
        ),
    ).toMatchObject({ demand: '0.375', after: '0.625' });
    ```

- [x] **Step 4:** Add boundary/revision scenarios with independent expected values: water demand 2 allowed versus 3 blocked; cheese demand 3 allowed versus 4 blocked; changed burger price `11000` -> total `44000`; changed burger beef component `110.000` -> aggregate `380.000`, projected `-130.000`. Add a long-name item, an unavailable/inactive item, disallowed add-on, duplicate add-on ID, fractional sale quantity, fractional `each`, excess precision `0.0001`, and invalid money values.
- [x] **Step 5:** Add payment examples: cash insufficient/exact/over tender; GCash and Maya with `reference: null` and confirmed receipt; unconfirmed digital payment rejected; blank reference normalizes to null; supplied duplicate reference creates an acknowledgeable warning; owner continuation is permitted after review. Use a different operation key for a separate sale even if its payment reference repeats.
- [x] **Step 6:** Add typed interaction examples for validation rejection, stock conflict, expired/changed quote, same-key replay, changed-payload conflict, pending lookup, not-found same-key recovery, and response loss after commit ending in the same sale. Add 403 nonowner/forbidden-store and 404 foreign-resource examples with no leaked sale data. Invalid request examples use an explicitly labeled raw `unknown` payload, never pretend that malformed input satisfies the accepted request type. Tests check consistency of these examples and key/result identity; do not claim they exercise an absent backend.
- [x] **Step 7:** Add compile-time positive checks and `@ts-expect-error` negative checks for missing digital confirmation, unsupported payment method, a confirmed attempt missing its sale, and invalid catalog-kind fields. Run `npm run types:check`; an unused directive fails the check. Validate numeric ranges/precision in fixture tests and document backend runtime requirements rather than claiming a `number`/`string` alias enforces them.
- [x] **Step 8:** Run `npm run test:contracts`, `npm run types:check`, and `npm run check`. All must exit 0, or report baseline failures with their precise scope. Run `npm run build` only if the changed Vite config or normal project checks expose a build concern; no UI has been implemented here. Do not use a repository-wide autofix.
- [x] **Step 9:** Review generated/untracked changes before staging. The lockfile and generated routes should be unchanged. Commit only task paths, e.g. `test: add checked POS contract fixtures`.

## Task 1.4: Reconcile the contract and hand it off

**Deliverable:** A development-ready contract package with explicit limitations and future enforcement requirements. Depends on Tasks 1.1-1.3.

**Files:** Finalize `docs/contracts/pos-v1.md`; update the parent action plan's Task 1 checkboxes only when their proof is actually met.

- [x] **Step 1:** Map the parent Task 1 requirements to evidence:

    | Parent requirement                  | Evidence                                                                                                  |
    | ----------------------------------- | --------------------------------------------------------------------------------------------------------- |
    | Operating rules and representation  | Contract rules/field dictionary + `business.ts`                                                           |
    | Seven named domain shapes           | `CatalogItem`, `RecipeComponent`, `CartLine`, `CheckoutQuote`, `CheckoutAttempt`, `Sale`, `StockMovement` |
    | Page props and scoped actions       | Endpoint matrix + typed page/input/output definitions                                                     |
    | Authoritative quotes and revisions  | Quote protocol + changed-price/recipe/stock scenarios                                                     |
    | Attempts, lookup, atomicity, replay | Lifecycle/transaction requirements + response-loss examples                                               |
    | Confirmed add-on stock behavior     | Confirmed decisions + direct/recipe stock fixtures                                                        |
    | Realistic fixtures                  | Shared-demand basket, stock boundaries, packaging, negative stock, modifiers, long/inactive items         |

- [x] **Step 2:** Read both sides of every example as a frontend and backend implementer. Confirm the same totals, warning codes, field names, statuses, operation key, and tenant ownership follow from the document and types. Resolve omissions with concrete decisions and examples.
- [x] **Step 3:** Include a future backend acceptance checklist: actual store/owner enforcement, runtime validation, atomic competing-stock completion, concurrent idempotent replay, crash recovery, confirmed replay after quote expiry, duplicate-reference review, full reversal/restock once, and original snapshots after catalog changes. No fixture test marks those checks passed.
- [x] **Step 4:** Reconcile all payment-reference wording: optional, receipt confirmation mandatory, duplicates warn/allow, no payment-provider verification. Label tax treatment unresolved and a live-use blocker. Document any technical proposal changed during implementation and why.
- [x] **Step 5:** Run `npm run test:contracts` and `npm run types:check` after the final artifact changes; rerun `npm run check` if source/config changed since its last pass. Record commands, outcomes, and any baseline limitations in the handoff. No browser/checkout integration verification is implied.
- [x] **Step 6:** Present the contract package for agreement. Parent Task 1 says “agreed”; leave it pending if review is outstanding. Once agreed and checks pass, mark it **contract-ready for development**, retain the explicit tax live-use blocker, and identify Task 2 as the next work package. Commit only the final documentation paths.

## Execution handoff

Recommended execution: **Native**, completing the four deliverables in order. They share one vocabulary and fixture set, so sequential work keeps drift low. Use subagents only with explicit authorization. Review this plan/spec before product implementation; the current request is planning only.

This work does not create working business endpoints or prove production readiness. Tax treatment remains unresolved. Task 2 and later backend integration need their own authorized execution and relevant tests.

**Acceptance — 2026-10-30:** User confirmed the delivered contracts match the business rules. Task 1 is contract-ready for development. Task 2 is next; backend acceptance and tax treatment remain outstanding before live use.
