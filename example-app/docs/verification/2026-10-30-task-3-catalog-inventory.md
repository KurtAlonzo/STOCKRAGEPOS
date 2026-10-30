# Task 3 verification — 2026-10-30

Implemented in the user-approved `feature/layout` checkout. Existing tracked files were clean before editing; only the two Task 3 planning documents were untracked. Task 1/2 behavior and fixtures remain covered by regression checks. Changes are uncommitted for review; no previous work was reset or discarded.

## Delivered

- Store-scoped catalog/categories; retail, food and direct/recipe add-ons; automatic dedicated zero-balance direct-stock records; immutable kinds/units and normalized store-unique SKUs.
- Ingredient/supply metadata, optional thresholds, stock-state filters, opening receipts, reasoned corrections, and read-only paginated item movements with immutable snapshots.
- Exact integer centavos/thousandths, bounded arithmetic, recipe components, allowed add-ons, active dependency protection and revision-checked edits.
- Shared demand calculations reproduce the mixed contract basket without changing stock or creating a sale.
- React management forms, URL filters/pagination, truthful availability, generated route helpers, keyboard-accessible dialogs, associated field errors, phone layouts and existing appearance support.
- Unsaved editing protection for dialogs, Inertia navigation, browser history and store switching. Canceling a switch leaves its control enabled. Uncertain stock writes disable recording until refresh.

## Automated evidence

Baseline: PHP 97 tests / 313 assertions; frontend 16 tests; TypeScript, PHPStan and production build passed.

Final verification: `php artisan test` **120 tests / 523 assertions passed**; `npx vp test run` **20 tests / 7 files passed**; `npm run types:check` passed; `php vendor/bin/phpstan analyse --memory-limit=512M` passed; `npm run build` passed. Changed runtime PHP sources pass scoped Pint, and changed frontend sources pass scoped formatting/lint checks.

Tests cover owner/admin/member/outsider and verification boundaries; foreign catalog/category/component/add-on/stock/history IDs; exact quantity and money precision/ranges; SKU conflicts; stock replay; forced movement-failure rollback; immutable movement snapshots; dependency deactivation/reactivation; and full contract-basket demand. Separate-process file-backed SQLite tests prove one application for competing stock revisions and preserve invariants during competing product activation/component deactivation. Tests use temporary databases, not the working database.

Observed RED→GREEN checks include missing inventory/parser/routes, missing stock actions, unsupported retail/food/add-ons, missing aggregate-demand service, exact money/quantity preview boundaries, filter normalization, history metadata, and catalog query batching. A 25-row retail catalog regression initially used 127 domain queries; eager loading brought it within the asserted maximum of 12.

Independent review identified active-filter serialization, canceled-switch busy state, history dirty protection, uncertain-write gating, missing contract amendments and field-error associations. These were addressed in one fix pass, with unit/feature checks and live navigation confirmation. No deferred reviewer findings.

## Browser evidence

Used an isolated SQLite preview database under the ignored `.superpowers/task3/` directory, fake mail transport and a local-only server on 8012. No real owner account, outgoing email or working database was used.

Verified sign-in; created Packaging boxes as a supply; received 10 each; inspected the before/delta/after movement; combined active/type/search filters; edited a recipe on phone; rejected 0.5 counted packaging while retaining the rows; corrected it to 1 and reopened the persisted recipe as 1.000 each.

Verified browser Back opens the discard dialog and Keep editing preserves the typed name. Canceled store switching preserves edits and keeps the switcher enabled; explicit discard reaches the second store dashboard. Inspected dark recipe management at 1440x900, 1024x768 and 768x1024; phone recipe and light inventory at 390x844. Measured content width did not exceed the viewport in these views. Existing settings changed light/dark appearance successfully.

## Remaining limits

- Repository-wide `npm run check` still reports baseline formatting in `docs/STOCKRAGE-POS-Documentation.md`, `docs/STOCKRAGE-POS-PROGRESS-SUMMARY.md`, the parent frontend action plan, and `public/css/stockrage.css`. `php vendor/bin/pint --test` reports the unchanged baseline `app/Actions/Teams/ResolveOwnedTeam.php`. Unrelated formatting was preserved; these full-repository checks are not green.
- Verification used SQLite. Other database engines, physical tablets/on-screen keyboards, formal contrast measurement, and real network response-loss simulation in a browser were not verified. Stock replay after a discarded response is covered by backend tests; checkout recovery remains Task 5.
- Migrations were applied only to isolated test/preview databases. Apply `php artisan migrate` to the intended development/deployment database as part of its normal rollout before using these routes there.
- Checkout, payments, sales/refunds, global Stock History, real dashboard aggregates and business settings remain assigned to later tasks. Tax treatment remains unconfigured and blocks live sales use.

Execution details and rulings: [ledger](task-3-execution-ledger.md).
