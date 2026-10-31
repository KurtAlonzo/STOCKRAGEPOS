# STOCKRAGE Task 3 Audit and Completion Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to execute this plan task-by-task. Use superpowers:subagent-driven-development only when delegation is selected. Steps use checkbox syntax for tracking. This document authorizes planning, not product implementation.

**Goal:** Establish that the existing catalog, recipes, add-ons, and minimum stock operations are ready for Task 4 development, with current evidence and passing repository checks.

**Architecture:** Audit the existing Laravel/Inertia/React implementation against its accepted contracts. Extend meaningful regression coverage, fix only reproduced defects, and verify the owner workflow in a disposable SQLite preview. Preserve the current service and page boundaries.

**Tech Stack:** Existing Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Wayfinder, PHPUnit, Larastan, Pint, and Vite Plus; no new test framework.

**Spec:** [Parent Task 3](2026-10-29-stockrage-frontend-action-plan.md), [accepted Task 3 design](../specs/2026-10-30-stockrage-task-3-catalog-inventory-design.md), [original execution plan](2026-10-30-stockrage-task-3-execution-plan.md), and [POS v1 contract](../../contracts/pos-v1.md). The interview decisions below narrow this follow-up to development readiness.

## Confirmed interview decisions

- Audit and finish the existing implementation; do not rebuild Task 3.
- Completion means ready for Task 4 development. Deployment and physical-device acceptance remain follow-ups.
- Include a separate formatting-only cleanup of the five currently failing files; require all repository checks to pass.
- Use existing automated tests plus a focused browser walkthrough. Broader end-to-end automation remains Task 8.

Alternatives considered: redesign or explanation-only work; operational readiness or two readiness milestones; accepting baseline formatting failures; adding a browser test framework. The selected approach addresses current readiness with bounded changes and no new infrastructure.

## Planning evidence — 2026-10-31

- Checkout: `feature/layout`, clean at inspection, HEAD `d0ae64f` (`Add feature tests for catalog and inventory management`). Recheck at execution; do not rely on the older report's statement that changes were uncommitted.
- Local configuration selects SQLite; PHPUnit selects SQLite `:memory:` with array mail transport. File-backed SQLite concurrency tests already exist.
- Fresh `npm run check` failed on formatting in `docs/STOCKRAGE-POS-Documentation.md`, `docs/STOCKRAGE-POS-PROGRESS-SUMMARY.md`, `docs/superpowers/plans/2026-10-29-stockrage-frontend-action-plan.md`, and `public/css/stockrage.css`.
- Fresh `php vendor/bin/pint --test` failed on formatting in `app/Actions/Teams/ResolveOwnedTeam.php`.
- `tests/Unit/StockDemandTest.php` currently contains the successful mixed-basket case. Direct service tests for shortage boundaries, invalid selections, and aggregate overflow are missing there; this is a coverage finding, not proof of a runtime defect.
- The [October 30 report](../../verification/2026-10-30-task-3-catalog-inventory.md) records earlier passing tests, static analysis, build, and browser checks. Those are historical results. The full suite and browser workflow were not rerun while writing this plan.

## Global constraints

- All application paths and commands below are relative to `example-app/`.
- Preserve owner-only access, verified authentication, route-store scoping, and foreign-resource rejection. Store wording in the UI does not replace team tenancy internals.
- Preserve integer PHP centavos; exact integer thousandths internally and decimal strings externally; units `each`, `g`, `ml`; integral counted units; maximum absolute scaled quantity `9007199254740991`. Reject excess precision and overflow without rounding.
- Direct retail/add-on shortages have blocking policy; ingredient/supply shortages have warning policy and can project negative balances. `StockDemand` returns effects; it does not implement checkout submission blocking.
- Stock operations append immutable movements and use revisions. Calculation, catalog editing, and recipe editing must not silently mutate stock.
- Preserve existing branding and light/dark/system appearance. Management remains usable at 390px width.
- Do not hand-edit generated Wayfinder helpers or introduce dependency upgrades, new checkout endpoints, payments, global stock history, dashboard aggregates, or unrelated refactors.
- Use disposable test/preview data and fake mail. Do not migrate the working database or deploy as part of this audit.
- Additional tests must verify business behavior or reproduced regressions. Formatting-only edits need diff review and existing checks, not new tests.

## Review focus

1. Direct stock exactly meets demand versus exceeds it: correct projected balance and `block` policy, with no persistence (Task 2).
2. Shared recipe demand overflows during multiplication, aggregation, or projection: reject before returning misleading effects or writing anything (Task 2).
3. Foreign, inactive, duplicated, or unassigned selections reach the demand service directly: reject even though the UI normally hides them (Task 2).
4. Two tabs or a lost response replay a stock write: one movement, preserved input, and a clear refresh path (Tasks 2–3).
5. Back/Forward, store switching, and dialog dismissal interrupt dirty forms: retaining edits and explicit discard both work without leaking edits into another store (Task 3).

## Files and responsibility

| Area                                       | Files                                                                                                                                                                                                                                                                                                                                           |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Separate formatting cleanup                | The five exact paths listed in planning evidence                                                                                                                                                                                                                                                                                                |
| Demand regression coverage                 | `tests/Unit/StockDemandTest.php`; `tests/Unit/QuantityTest.php` only for separately uncovered arithmetic boundaries                                                                                                                                                                                                                             |
| Existing domain/access regression coverage | `tests/Feature/Task3AccessTest.php`, `tests/Feature/Catalog/{ProductTest,CategoryTest,RecipeTest,AddonTest,CatalogLifecycleTest}.php`, `tests/Feature/Inventory/{InventoryItemTest,StockOperationTest,StockConcurrencyTest}.php`                                                                                                                |
| Conditional domain fixes                   | `app/Services/Catalog/StockDemand.php`, `app/Support/{Quantity,StoreCatalogTransaction}.php`, `app/Actions/Catalog/{SaveCatalogItem,SaveRecipe,SaveCategory,CheckActiveDependents}.php`, `app/Actions/Inventory/{SaveInventoryItem,RecordStockOperation}.php`, corresponding controllers/data serializers only when a reproduction points there |
| Conditional UI fixes                       | `resources/js/pages/products/{index,create,edit}.tsx`, `resources/js/pages/categories/index.tsx`, `resources/js/pages/inventory/index.tsx`, existing catalog/inventory components, `resources/js/hooks/use-unsaved-changes.ts`, `resources/js/lib/navigation-history.ts`, `resources/js/components/team-switcher.tsx`                           |
| Existing frontend checks                   | `resources/js/lib/catalog/form-values.test.ts`, `resources/js/lib/navigation-history.test.ts`, existing navigation and contract tests selected by `npx vp test run`                                                                                                                                                                             |
| New audit evidence                         | Create `docs/verification/2026-10-31-task-3-readiness-audit.md` during execution                                                                                                                                                                                                                                                                |
| Current status and handoff                 | Update `docs/STOCKRAGE-POS-PROGRESS-SUMMARY.md`, parent plan Task 3 status, and original Task 3 plan status to link the new evidence; preserve historical reports                                                                                                                                                                               |

## Task 1: Establish the baseline and clear formatting gates

**Deliverable:** Current baseline results, a reviewed formatting-only change, and a requirement-to-evidence matrix.

- [ ] Inspect branch, HEAD, worktree, `git status --short`, applicable `AGENTS.md`, and the linked specifications. Preserve unrelated work. Use the worktree workflow if execution requires isolation; this planning document does not select a new checkout.
- [ ] Confirm installed tooling, test database isolation, and absence of a cached configuration that redirects tests to working data. Do not print credentials or use `composer setup` as a verification shortcut.
- [ ] Run the seven commands in the final gate once to record the current baseline. Capture exit codes and actual failures. Do not inherit historical exemptions or require the historical test count.
- [ ] Create the new evidence file with audit commit/environment, command results, and six rows corresponding to the parent Task 3 checklist. Each row records implementation paths, automated test method/browser case, and `pass`, `fail`, or `not run`.
- [ ] Format only the four named frontend/documentation files using `npx vp fmt docs/STOCKRAGE-POS-Documentation.md docs/STOCKRAGE-POS-PROGRESS-SUMMARY.md docs/superpowers/plans/2026-10-29-stockrage-frontend-action-plan.md public/css/stockrage.css`. Format the named PHP file with `php vendor/bin/pint app/Actions/Teams/ResolveOwnedTeam.php`.
- [ ] Review this diff before functional changes. Preserve CSS values, selectors, rule order, PHP behavior, and documentation meaning. Run `npm run check` and `php vendor/bin/pint --test`; success requires exit 0. If a newly exposed lint failure appears after formatting, record it as a separate finding and fix its cause rather than suppressing the check.

Keep this as a separately reviewable change. Suggested commit, if commits are requested: `style: clear existing repository formatting failures`.

## Task 2: Verify domain guarantees and fill demand-test gaps

**Interfaces:** Preserve `StockDemand::calculate(Team $store, array $lines): array` and `StockDemand::forItem(Team $store, CatalogItem $item): array`. Input lines contain a distinct UUID `id`, integer `productId`, positive integer `quantity`, and `addonIds`. Effects contain `inventoryItemId`, `unit`, canonical decimal `before/demand/after`, and `policy` (`block` or `warn`). No sale, quote, or reservation is created.

- [ ] Run the existing domain subset: `php artisan test --filter='Task3AccessTest|QuantityTest|StockDemandTest|ProductTest|CategoryTest|RecipeTest|AddonTest|CatalogLifecycleTest|InventoryItemTest|StockOperationTest|StockConcurrencyTest'`. Inspect test assertions against the requirement matrix, not just names or pass counts.
- [ ] Extend `tests/Unit/StockDemandTest.php` with the following behavior cases. Reuse the existing setup locally; do not create a general fixture framework.

| Test method                                                                | Required assertions                                                                                                                                                                                                                                                                                                                             |
| -------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `test_direct_stock_boundaries_return_block_policy_without_writes`          | Water balance `2.000`, demand 2 gives `after = 0.000`; demand 3 gives `after = -1.000`; both return `policy = block`. Cheese balance `3.000`, selected for 3 versus 4 burgers, gives `0.000` versus `-1.000` with `block`. Calculation does not throw merely for a shortage.                                                                    |
| `test_contract_basket_aggregates_without_mutating_stock` (extend existing) | Retain beef `-100.000`, sauce `-35.000`, seasoning `0.625`, packaging `-1.000`, water/cheese `1.000`. Assert ingredient/supply `warn` and direct stock `block`. Snapshot all involved balances/revisions and operation/movement counts before calculation; assert all unchanged afterward.                                                      |
| `test_invalid_demand_lines_are_rejected_without_writes`                    | Separate data cases for quantity 0, negative/fractional quantity, invalid or duplicate line UUID, duplicate add-on IDs, inactive product, add-on used as a top-level product, inactive add-on, and active but unassigned add-on. Expect `ValidationException`; assert no changed balances/revisions or new operations/movements for every case. |
| `test_demand_rejects_foreign_resources_and_inactive_dependencies`          | Foreign product/add-on IDs raise 404; inactive recipe ingredient/supply raises validation failure. `forItem` rejects an item from another store. Assert no writes and no returned foreign effects.                                                                                                                                              |
| `test_demand_rejects_overflow_at_each_calculation_stage`                   | Three isolated valid-input setups: recipe component scaled quantity `MAX` times count 2; two lines each demanding scaled `4503599627370496` of a shared component; ingredient starting at scaled `-MAX` with demand `0.001`. Expect `ValidationException` containing `numeric_range_exceeded`, with no balance/revision or ledger changes.      |

- [ ] Run `php artisan test --filter=StockDemandTest`. New coverage can pass immediately against correct existing behavior. If a case fails, distinguish a bad test setup from a reproduced implementation defect before changing runtime code.
- [ ] Confirm existing endpoint tests prove store ownership, SKU uniqueness, recipe duplication/precision, inactive-dependency protection, stale revisions, rollback on movement failure, immutable snapshots, and competing SQLite writes. Add focused cases in the owning existing test file only where the requirement matrix identifies an uncovered invariant.
- [ ] For each reproduced defect, record input, expected/actual result, affected path, and failing test. Apply the smallest fix, rerun the failing test, then the affected domain subset. Preserve contracts; a genuine business-rule change requires returning to the owner.

Suggested commit for this independently reviewable deliverable: `test: verify task 3 demand and stock boundaries`; keep any substantive fixes identifiable in review.

## Task 3: Verify the connected owner workflow in a browser

**Deliverable:** Recorded browser results against persisted disposable SQLite data, with targeted fixes for any reproduced failures. No new browser test framework.

- [ ] Prepare an isolated local preview using the existing app and documented owner setup. Use an absolute disposable SQLite path, fake mail, and test-only users/stores. Apply migrations only to that preview database. Record exact setup/start commands and the database path in the evidence so the walkthrough is reproducible.
- [ ] Walk through the following cases, recording actual results and screenshots for significant failures and representative layouts. Reload after successful saves to distinguish persisted data from local state.

| Browser case                        | Expected result                                                                                                                                                                                                                                                                                                                                          |
| ----------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Create catalog and inventory        | Create category, water at 5000 centavos, ingredient records, packaging supply, direct cheese add-on, and recipe extra-beef add-on. Direct products own dedicated zero-balance stock; ingredients/supplies are not standalone sellable catalog products.                                                                                                  |
| Opening receipts and correction     | Receive water 2 each, cheese 3 each, beef 250g, seasoning 1g, packaging 2 each. Correct sauce from zero to -5g with a reason. Verify persisted before/delta/after, actor, reason, and timestamp. Blank correction reason rejects without a movement.                                                                                                     |
| Recipe and add-ons                  | Save burger recipe: beef 100g, sauce 10g, seasoning 0.125g, packaging 1 each; assign cheese and extra beef (50g recipe). Reload and edit. Reject duplicate components and 0.5 each packaging while retaining entered rows and showing associated errors. Food shows recipe warnings rather than finished-product stock.                                  |
| Invalid and inactive records        | Duplicate normalized SKU rejects in the same store. Deactivating a dependency of an active product is blocked with an explanation. Inactive retained references remain visible for repair; invalid nesting is rejected. Optional unavailable add-on does not alone make its parent unavailable.                                                          |
| Filtering and pagination            | Use enough disposable records for a second 25-row page. Combine name/SKU, category/type, and active filters; reload and navigate Back. URLs preserve filters; filter changes reset pagination. Empty store and no matching results have distinct messages. Inventory distinguishes low, zero, and negative balances.                                     |
| Dirty edits                         | Exercise cancel/escape, app links, browser Back/Forward, refresh, and store switch. Keep editing preserves values; explicit discard proceeds. Canceling a store switch leaves its control usable. Another store does not receive the previous form's edits.                                                                                              |
| Pending, rejected, and stale writes | Delay a save using available browser controls; prevent double submission and retain input on rejection. Open stock in two tabs, save once, then submit the stale revision: one accepted operation, clear conflict/refresh guidance, and no silent overwrite.                                                                                             |
| Uncertain stock response            | Where browser tooling permits, interrupt the response to a disposable stock write: show uncertainty, prevent an unverified repeat, refresh balance/history before a new action. If precise post-commit response loss cannot be induced, record that limitation and cite the backend replay/rollback test separately; do not claim it was browser-tested. |
| Access and navigation               | Use a second owned store and a forbidden store URL. Confirm correct scope, no foreign data, working Catalog/Categories selection, and no newly exposed unfinished modules. Automated access tests remain authoritative for the full role/endpoint matrix.                                                                                                |

- [ ] Inspect management at 1440x900, 1024x768, 768x1024, and 390x844 in light/dark appearance; check system appearance follows the existing setting. Verify no page-wide horizontal overflow, readable long names/large prices, keyboard focus, dialog focus return/escape, field-error association, status labels, 200% zoom, and usable touch controls. Emulated viewports do not establish physical-tablet acceptance.
- [ ] Map each failure to an existing component/controller and reproduce it before editing. Add a unit/feature regression test where that layer can meaningfully pin the behavior; for browser-only interactions, record repeatable steps and recheck the corrected flow in the browser. Recheck affected neighboring flows, not the entire matrix after every small fix.

Suggested commit for any resulting changes: `fix: address task 3 readiness audit findings`. If no runtime defects reproduce, this task produces evidence only.

## Task 4: Run final gates and publish the Task 4 handoff

**Deliverable:** A current readiness report with every parent Task 3 requirement accounted for and a clear downstream contract.

- [ ] Complete the requirement matrix with exact test methods/browser case evidence. All required Task 3 behaviors must pass. Record unavailable supplementary checks as limitations; an unverified core workflow prevents a readiness claim.
- [ ] Update the new evidence, parent Task 3 status, original plan status, and progress summary. Preserve historical evidence and distinguish this audit's commit/results. Format only documents changed in this pass with `npx vp fmt <explicit-paths>` before the final gates.
- [ ] If backend routes changed, run `php artisan wayfinder:generate --with-form` before validation. Generated output must match the real routes.
- [ ] Run each command below after the relevant changes are complete. All must exit 0; record actual counts and results. Do not claim completion while a baseline failure remains. If an unrelated substantive failure appears, document it and return for scope direction rather than weakening the gate.

```powershell
php artisan test
php vendor/bin/phpstan analyse --memory-limit=512M
php vendor/bin/pint --test
npx vp test run
npm run types:check
npm run check
npm run build
```

- [ ] Review `git diff --check`, the final diff, and status for unrelated edits, accidental fixture exposure, generated-file mistakes, and contract drift. Record final results in the evidence; if only that report changes afterward, format it and rerun the documentation formatting check.
- [ ] Hand Task 4 the existing catalog/inventory types, revision semantics, persisted recipes/allowed add-ons, `StockDemand` input/output behavior, shortage policies, and the verified mixed-basket example. State that cart identity/merging, interactive picker/cart UI, and immediate display totals remain Task 4; authoritative quotes/payment/completion/recovery remain Task 5.
- [ ] List deployment follow-ups separately: intended database migration rehearsal/backup procedure, engine-specific verification if moving beyond SQLite, physical devices/on-screen keyboards, and real-environment checks. Tax configuration remains a blocker for live sales, not for this Task 4 development handoff. Do not mark deployment or the overall POS release ready.

## Completion and execution recommendation

Complete when the six parent Task 3 requirements have current evidence, the demand boundary/security/overflow cases pass, the connected browser workflow passes, all seven repository commands pass, and the Task 4 handoff identifies remaining release work accurately.

Recommended execution is sequential in one chat: baseline and formatting → domain audit/tests/fixes → browser walkthrough/fixes → final gates and handoff. These tasks share contracts and state; a parallel implementation offers little benefit. A delegated implementation/review workflow remains an option if explicitly selected. Implementation, commits, merging, and deployment are not performed by creating this plan.
