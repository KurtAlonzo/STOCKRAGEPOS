# STOCKRAGE Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Use superpowers:subagent-driven-development only when delegation is authorized. Steps use checkbox (`- [ ]`) syntax for tracking. This document authorizes planning, not product implementation.

**Goal:** Give an owner one reliable workflow for selling retail and recipe-based food products, recording externally received payments, and reviewing the resulting sales and stock movements.

**Architecture:** Extend the existing Laravel/Inertia/React application. Use server props for persisted data, local React state for editing and checkout, and a recoverable draft scoped to the authenticated user and store. Laravel remains authoritative for prices, stock checks, totals, transaction completion, and reversals.

**Tech Stack:** Existing Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Radix/shadcn components, Sonner, Wayfinder, and Vite Plus. No framework migration is needed.

**Spec:** The confirmed interview decisions recorded below, interpreted alongside `../../STOCKRAGE-POS-PROGRESS-SUMMARY.md`, `../../STOCKRAGE-POS-Documentation.md`, and the original PNG mockups under the repository's `context and md files/UI DESIGN/` directory. Confirmed decisions supersede conflicting blueprint scope or schedule.

## Confirmed release decisions

| Decision | Agreed direction |
| --- | --- |
| Business | Both directly stocked retail products and recipe-based food products |
| Delivery | One complete operating workflow before expanding all modules |
| Frontend/backend | Frontend-led work with agreed data contracts and incremental backend integration |
| Shortages | Block directly stocked retail shortages; warn and permit negative recipe ingredient/supply stock |
| Payments | Record payments received outside the app; no payment-provider processing |
| Operator | Owner only initially; staff workflows and granular POS roles deferred |
| Product types | Direct-stock retail, recipe-based food, and add-ons attached to individual cart lines |
| Devices | Desktop/tablet checkout; phone management and sales review |
| Recovery | Recover drafts by store; require connection for completion; reconcile uncertain submissions before retrying |
| Visuals | Preserve mockup identity and workflows; adapt layouts using existing React components |
| Corrections | Preserve original sales; linked reversal/refund with reason; retail restock only when restockable; no automatic ingredient restoration |
| Receipts | On-screen sale confirmation; optional A5 browser printing/Save as PDF |
| Scheduling | Dependency-ordered milestones and completion criteria before calendar estimates |

## Evidence and current baseline

- The progress summary records environment setup, branding, navigation, and dashboard work. Those are foundations, not completed POS operations.
- `routes/web.php` currently defines the team-scoped dashboard but no business-module routes. Navigation to POS, products, categories, sales, inventory, customers, suppliers, purchases, expenses, promotions, and reports is ahead of implementation.
- The inspected models/migrations implement users, teams, memberships, invitations, authentication, and infrastructure. Product, recipe, sale, payment, and stock-ledger persistence still needs building.
- `DashboardController.php` supplies pending invitations, not sales or inventory metrics. `pages/dashboard.tsx` hardcodes zero figures and stock/sales messages. The summary's monthly-revenue/total-order description also differs from the actual cards.
- `TeamRole` and `TeamPermission` implement team administration, not POS permissions. Existing membership infrastructure does not make business operations authorized automatically.
- The Blade `/dashboard` preview and React `/{current_team}/dashboard` are separate implementations with separate theme mechanisms. The preview is not protected by the authenticated dashboard middleware.
- The original mockups explicitly describe recipes, ingredient/supply deductions, negative stock warnings, append-only stock history, PHP prices, and GCash/Maya.
- Current CSS uses Instrument Sans and a 10px base radius; the reference design specifies Inter and 6px. Current React money placeholders use dollars. The welcome page and sidebar footer retain starter-kit content.
- Existing shared components, account settings, theme behavior, team switching, and auth flows are valuable foundations to retain.
- `npm run types:check` passed during this assessment. `artisan route:list --except-vendor` confirmed the route gap. No new build, full test suite, or live-browser UX validation was performed for this plan; earlier summary claims remain historical evidence.

## Global constraints

- Keep team-scoped data and routes, with store terminology in the business interface. Owner-only scope does not remove authentication, ownership checks, or store isolation.
- Do not expose unfinished modules as working navigation or represent unavailable data as zero.
- Use the mockup identity: Inter, 6px base radius, 8px spacing rhythm, action red `#E10D18`, indicator brand red `#FF101C`, dark red `#8B0000`, black `#111111`, dark surface `#1C1C1C`, light gray `#F5F5F5`. Verify contrast in actual rendered states.
- Maintain light/dark/system appearance through the existing React theme infrastructure. Do not add another theme store.
- Build keyboard access, readable validation, loading/empty/error states, and tablet touch usability with each workflow.
- Do not offer offline sale completion. A recovered local draft is not a completed transaction.
- Optional printing must never block completion, confirmation, or starting the next sale.
- Do not mutate generated Wayfinder route files manually; generate helpers from working backend routes.
- Do not treat the blueprint's six-week schedule as a verified estimate.

## Recommended release boundary

**Include:** store context and owner access; catalog and categories; ingredients/supplies and opening stock; recipe editor; add-ons; retail/food checkout; cash and externally received digital payment recording; recoverable drafts; sales list/detail; controlled full-sale correction; stock adjustments/history; real dashboard; basic business settings; optional receipt output.

**Defer:** customer CRM/loyalty, supplier directory, purchase orders/payables, expenses, promotions engine, advanced reports, staff roles, split payments, partial refunds/exchanges, held-order libraries, barcode-specific workflows, offline sales, direct printer control, cross-store transfers, and variant/substitution builders. These remain potential later work, not deleted requirements.

**Planning defaults, not additional user-confirmed requirements:** PHP currency and Asia/Manila business-day calculations to match the Philippine mockups; one payment method per sale; a single active draft per owner/store; name/SKU search; quantities in a recipe component's configured base unit; full-sale correction before partial returns; no new discount or tax engine in the initial vertical slice. Confirm business currency, timezone, tax/rounding treatment, add-on deduction policy, and payment-reference requirements in the data contract before live use. Do not assume tax exemption or advertise compliance.

The schema and cart must accommodate the selected payment methods without implying provider verification. Cash, GCash, and Maya are the initial reference choices; card is an extension if needed.

## Review focus

1. Timeout after a successful server commit: reconcile the original operation identifier and show its existing sale; never blindly submit again. Owned by Tasks 1 and 5.
2. Several cart lines consume the same ingredient or retail stock: aggregate all deductions before showing warnings or blocking. Owned by Tasks 3 and 4.
3. Refresh, store switch, logout, or another tab: recover only the correct user's/store's draft and prevent conflicting tab submissions. Owned by Tasks 2 and 5.
4. Product price, recipe, or availability changes after draft creation: revalidate and explain changes before accepting payment confirmation. Owned by Tasks 3 and 5.
5. Refunded prepared food: preserve consumed-ingredient history and record financial reversal separately; retail restock requires an explicit restockable choice. Owned by Task 6.

## Work map

Paths below are relative to `example-app/`. These are proposed boundaries, not files that already exist.

| Responsibility | Main files |
| --- | --- |
| Shell, navigation, branding | Modify `resources/js/components/app-sidebar.tsx`, `nav-main.tsx`, `team-switcher.tsx`, `app-logo.tsx`, `resources/js/app.tsx`, `resources/css/app.css`, `vite.config.ts` |
| Entry and account experience | Modify `resources/js/pages/welcome.tsx`, existing auth layouts, and settings navigation |
| Shared business types | Create `resources/js/types/pos.ts`, `catalog.ts`, `inventory.ts`, `sales.ts` as each workflow needs them |
| Catalog | Create `resources/js/pages/products/index.tsx`, `create.tsx`, `edit.tsx`, `pages/categories/index.tsx`, `components/catalog/item-form.tsx`, `recipe-editor.tsx` |
| Inventory | Create `resources/js/pages/inventory/index.tsx`, `history.tsx`, `components/inventory/stock-adjustment-dialog.tsx` |
| Checkout | Create `resources/js/pages/pos/index.tsx`, `layouts/pos-layout.tsx`, `components/pos/product-picker.tsx`, `cart-panel.tsx`, `addon-picker.tsx`, `payment-panel.tsx` |
| Draft and calculations | Create `resources/js/hooks/use-pos-draft.ts`, `resources/js/lib/pos/cart.ts`, `draft-storage.ts`; colocate focused tests |
| Sales and corrections | Create `resources/js/pages/sales/index.tsx`, `show.tsx`, `components/sales/correction-dialog.tsx`, `receipt.tsx` |
| Dashboard and settings | Modify `resources/js/pages/dashboard.tsx`; create `resources/js/pages/settings/business.tsx` |
| Backend contracts/integration | Add team-scoped routes/controllers/requests/services/models/migrations for these workflows; extend middleware shared props only when required |

Reuse current form, dialog, button, badge, feedback, and layout patterns. Add shared table/filter/form pieces only as real consumers appear. Keep checkout components separate from management page layouts.

## Task 1: Lock the operating rules and data contracts

**Status:** Contract-ready for development; user confirmed business-rule agreement on 2026-10-30. Backend enforcement remains future work.

**Depends on:** no implementation tasks. **Deliverable:** agreed frontend/backend shapes plus realistic fixtures, before persistent business screens are implemented.

- [x] Create a concise contract document under `docs/contracts/` defining item kinds, money representation, stock base units/precision, recipe quantities, add-on deductions, sale status, and error shapes. Money travels as integer minor units; measured quantities use an agreed decimal representation and server precision.
- [x] Define `CatalogItem`, `RecipeComponent`, `CartLine`, `CheckoutQuote`, `CheckoutAttempt`, `Sale`, and `StockMovement` in the proposed business type files. A cart line has a stable ID, product ID, quantity, and sorted add-on selections; different modifiers cannot merge into one line.
- [x] Define page props and team-scoped actions for catalog CRUD, recipe editing, stock receipt/adjustment, checkout quote, checkout completion, attempt lookup, sales retrieval, and reversal. Document a proposed `/{current_team}/pos` route family and generate actual helpers once routes exist.
- [x] Define `CheckoutQuote` with authoritative prices/totals, catalog/recipe revision, blocking issues, and nonblocking ingredient/supply warnings. Completion must revalidate the quote rather than trust browser totals.
- [x] Define `CheckoutAttempt` with a stable idempotency key and `draft/submitting/confirmed/rejected/unknown` client states. Specify authenticated attempt lookup, same-key retry semantics, and atomic sale/payment/stock persistence.
- [x] Define add-on stock behavior explicitly: a directly stocked add-on blocks when short; an ingredient-consuming add-on warns according to recipe shortage rules. Confirm this recommended extension of decision 4 before encoding it.
- [x] Create matching fixtures for retail stock 2, food recipe consuming a shared ingredient, a low/negative ingredient, packaging, two different add-on combinations, long names, and unavailable items. Keep fixture use visibly separate from connected data.

**Proof:** frontend and backend can explain the same example total and aggregate stock deductions, including two recipe lines sharing an ingredient. Contract examples cover price changes, stock conflict, validation rejection, duplicate operation, timeout after commit, and forbidden store access.

## Task 2: Establish one honest, branded application shell

**Depends on:** Task 1 terminology/access decisions. **Files:** shell/branding/entry files in the work map, `routes/web.php`, relevant auth configuration and tests.

- [ ] Preserve React as the production UI. Keep the Blade dashboard as a clearly labeled development reference or remove its public exposure when retiring it; do not maintain parallel business screens.
- [ ] Replace starter welcome content with an owner-oriented sign-in/entry experience. Recommend controlled owner provisioning and restricted public registration for the initial private installation; document this as a deployment decision before enabling access.
- [ ] Align tokens, font loading, logos, favicon, and auth layouts with the hybrid design. Use text plus color for stock/error states and distinguish routine red actions from destructive actions through labels and context.
- [ ] Reduce release navigation to Dashboard, POS, Catalog, Inventory, Sales, Stock History, and Settings as those routes become available. Put Categories within catalog management rather than treating it as a top-level destination.
- [ ] Remove starter repository/documentation links from operator navigation. Use Store wording instead of Team in the business interface while retaining existing tenancy internals.
- [ ] Use generated route helpers and parent-route selection so editing a product keeps Catalog active. Scope POS entry and business actions to the authenticated owner/store; retaining membership alone is insufficient authorization.
- [ ] Set up the desktop/tablet checkout layout and phone management navigation. On phones, explain that checkout requires a supported larger screen instead of exposing a broken payment flow; preserve access to management.

**Proof:** no visible link leads to an absent route; a nonowner cannot perform business actions by calling their URLs; store changes refresh scoped data; light/dark/system works consistently. Test visible navigation and ownership boundaries, and inspect desktop/tablet/phone shells.

## Task 3: Build catalog, recipes, and minimum stock operations

**Depends on:** Tasks 1–2. **Files:** catalog/inventory pages and components in the work map, corresponding Laravel persistence/actions.

- [ ] Build searchable, filtered, paginated catalog views with explicit retail/food/add-on types, active status, price, and stock/availability indicators. Preserve filters in the URL and distinguish no items from no search results.
- [ ] Build create/edit forms with reusable fields and type-specific sections. Support categories, SKU uniqueness, name, selling price, and active status; ingredients/supplies are inventory records, not automatically sellable POS tiles.
- [ ] Build recipe editing with ingredient/supply selection, quantity, base unit, row removal, duplicate-component handling, and inline errors. Food stock status derives from requirements; it must not pretend to be a directly stocked finished quantity.
- [ ] Attach allowed add-ons to food/product choices with explicit price and stock requirements. Block invalid nesting or recursive recipes in the initial model; defer unit conversions and substitution builders.
- [ ] Implement opening stock, stock receipt, and reason-required corrections before checkout. Keep products, ingredients, and supplies understandable in inventory filters; show low, zero, and negative balances distinctly.
- [ ] Connect real persistence one operation at a time, keeping fixture screens useful until each contract is available. Archive/deactivate referenced items rather than deleting historical sale meaning.

**Proof:** create a retail item, ingredient, packaging supply, recipe product, and add-on; receive stock; reload and see persisted values. Verify duplicate SKU, invalid decimal quantities, duplicate recipe entries, inactive items, unsaved edits, and aggregated shared-component demand. Backend tests prove store isolation and append-only movements.

## Task 4: Build the mixed retail/food cart

**Depends on:** Task 3 catalog and stock contract. **Files:** checkout layout, picker, add-on picker, cart panel, `lib/pos/cart.ts`, focused cart tests.

- [ ] Build name/SKU search and category filters with keyboard-accessible product choices. Show useful availability without turning every low ingredient into an unavailable product.
- [ ] Add retail and food products to the same cart. Configure add-ons per line; merge only identical product/modifier configurations. Support quantity edits, line removal, and explicit cart cancellation.
- [ ] Keep order totals and the payment action visible on desktop and tablet. Make search, quantity controls, dialogs, and scrolling usable with touch and on-screen keyboards.
- [ ] Show projected aggregate stock effects: direct retail shortages block; recipe ingredients/supplies warn and may become negative. Give the owner the affected item and projected balance rather than a generic warning.
- [ ] Compute immediate display totals from agreed money units, then replace them with server-authoritative quote results. Do not introduce discounts, split payments, or a new tax engine as incidental cart work.

**Proof:** a mixed basket containing two food lines with different add-ons retains the correct configuration, totals, and deductions. Focused tests cover line identity, quantity/removal, currency arithmetic, shared ingredient aggregation, and direct-stock shortage boundaries. Keyboard-only and tablet use can complete cart preparation.

## Task 5: Complete payments and recover safely

**Depends on:** Task 4 plus backend quote/completion/attempt-lookup support. **Files:** payment panel, draft hook/storage, POS page, checkout feature/integration tests.

- [ ] Persist a versioned draft keyed by authenticated user and store. Restore editable cart contents after refresh; re-fetch product data and revalidate before completion. Handle missing/disabled products and changed prices/recipes with an explanation.
- [ ] Separate drafts on store changes. Clear in-memory cart on logout and prevent another user seeing the previous owner's draft. Use a documented same-origin draft-storage policy; do not store payment credentials or sensitive customer data.
- [ ] Detect conflicting tabs editing/submitting the same draft and require an explicit recovery/reload action. The same checkout attempt must keep its operation key through refresh and uncertain responses.
- [ ] Add cash tender/change and enabled external payment methods. Digital payment confirmation means the owner has checked receipt outside the app; show that meaning clearly and capture references according to the contract.
- [ ] Disable completion for empty carts, invalid quantities, insufficient cash, direct-stock shortages, unconfirmed external receipt, or known offline state. A browser connectivity flag is only a hint; handle actual network failures too.
- [ ] Quote/revalidate before final confirmation; require acknowledgment if authoritative prices or material warnings change. Do not optimistically announce success or clear the draft while submitting.
- [ ] Implement submitting, confirmed, rejected, and unknown-result screens. For unknown results, look up the existing attempt and offer same-key retry only under the agreed backend contract; never generate a fresh attempt automatically.
- [ ] On confirmed completion, show transaction reference, line items, total, recorded payment, and cash change. Clear the completed draft and offer New Sale. Optional View/Print Receipt is secondary.

**Proof:** one submission creates one sale, one payment record, and the intended stock movements. Automated integration coverage must simulate a server commit followed by response loss, repeated requests, refresh during submission, price changes, store/user draft isolation, and competing stock purchases. A disconnected browser cannot present an unrecorded sale as complete.

## Task 6: Review and correct completed sales

**Depends on:** Task 5. **Files:** sales pages, correction dialog, receipt component, backend reversal and stock-history actions/tests.

- [ ] Build sales history with reference/date/payment/status filters and persistent pagination. Provide transaction detail with item/modifier snapshots, original totals, payment details, and linked stock movements.
- [ ] Add full-sale reversal/refund with required reason and explicit confirmation of the external money return. The app records that action; it does not transfer funds.
- [ ] Require an explicit restockable selection for returned retail items. Do not automatically restore consumed food ingredients or supplies. A physical inventory correction is a separate reasoned stock action.
- [ ] Preserve original sales, payments, prices, recipe deductions, and linked reversals. Stop repeated corrections and duplicate restocking; show original and adjusted financial status without deleting history.
- [ ] Provide the receipt on screen and optional A5 browser print/Save as PDF. Isolate print styling from the application theme/chrome; cancellation or printer failure leaves the confirmed sale intact.

**Proof:** a retail return can restore eligible stock once, while a food refund leaves used ingredients deducted. Financial totals and history remain explainable after reversal. Check original sale snapshots after catalog edits, repeated reversal submissions, optional print cancellation, and store isolation.

## Task 7: Make management screens trustworthy

**Depends on:** Tasks 3, 5, and 6. **Files:** inventory/history, dashboard, business settings, corresponding data controllers.

- [ ] Complete searchable stock history showing movement type, delta, previous/new quantity, reason, timestamp, actor, and source sale/adjustment. Corrections append records; they do not edit old movements.
- [ ] Replace hardcoded dashboard values with real sales, transactions, active catalog, and low/negative stock aggregates. Show explicit empty, unavailable, error, and populated states; never claim healthy stock when data did not load.
- [ ] Link actionable alerts to filtered inventory and recent sales to details. Define completed/reversed revenue treatment and business-day boundaries so dashboard and sales history agree.
- [ ] Add minimum business settings: store details, enabled payment methods, confirmed currency/timezone, and optional receipt header/footer. Preserve existing account, security, and appearance controls rather than duplicating them.
- [ ] Make catalog, stock management, sales review, and dashboard usable on phones with prioritized columns/cards and accessible filter dialogs. Broad report exports and analytics remain later work.

**Proof:** completing a sale, receiving stock, and reversing a sale update the relevant pages consistently. Test day-boundary transactions and unavailable dashboard data. Management tasks work at a proposed 390px phone width without page-wide horizontal overflow.

## Task 8: Verify the operating workflow and document readiness

**Depends on:** Tasks 1–7. **Deliverable:** verified initial workflow, known limitations, and an updated progress summary.

- [ ] Add the smallest test setup needed for meaningful cart/draft unit tests and browser workflow coverage; current package scripts do not establish a POS test suite. Prefer installed Vite Plus capabilities where they fit rather than assuming a runner or adding several overlapping tools.
- [ ] Automate one complete path: create stock and recipe data, sell retail plus food with an add-on, record payment, inspect sale/stock history, refresh a draft, recover an uncertain submission, and correct a completed sale.
- [ ] Run `npm run types:check`, `npm run check`, and `npm run build`; run focused frontend tests and Laravel tests for checkout, stock, reversals, and ownership. Investigate any baseline failures instead of claiming the historical summary proves current readiness.
- [ ] Inspect light/dark desktop, landscape and portrait tablet, and phone management layouts in one bounded review pass. Suggested test sizes: 1440x900, 1024x768, 768x1024, and 390x844; revise against actual target hardware before live use.
- [ ] Verify focus order, dialog focus/escape behavior, visible focus, field-error association, status announcements, contrast, touch controls, zoom, long names, large prices, and long stock histories. Test during slow requests as well as immediate success.
- [ ] Verify normal search/cart responsiveness with realistic catalog volumes. Measure a baseline on the target device; agree a budget from that evidence rather than claiming unmeasured performance. Debounce/server-filter catalog searches if volume warrants it; avoid loading the entire future suite into checkout.
- [ ] Update the progress summary with verified behavior, fixture-only areas, deferred modules, supported devices, recovery behavior, and reproduction steps. Record optional receipt printing separately from the sales completion gate.

**Release gate:** the owner can complete and review the mixed sale and correction workflow with accurate persisted stock, survive the documented interruptions without duplicate sales, and use all released links/screens on their supported devices. A successful build alone does not satisfy this gate.

## Sequence and ownership

`Contracts -> shell -> catalog/recipes + minimum stock -> mixed cart -> payment/recovery integration -> sale review/correction -> trustworthy dashboard/settings -> release verification`.

The frontend developer owns screen hierarchy, interaction states, accessible components, typed props/actions, draft UI/storage, responsive behavior, client tests, and browser verification. Backend work owns persistence, ownership/store enforcement, authoritative calculations, atomic stock/payment posting, idempotency/attempt lookup, immutable history, reversal invariants, and aggregates. Data contracts, rounding rules, fixture examples, and integration acceptance are shared responsibilities.

A workflow is complete only after backend integration and its relevant checks pass. Frontend-only completion must be labeled as such. Keep a task board with `Ready`, `Frontend in progress`, `Waiting on backend`, `Integrated`, and `Verified` states so mocked screens cannot be mistaken for released functionality.

## Later expansion

After the initial workflow is verified, prioritize from actual owner usage: purchasing/suppliers if replenishment is the bottleneck; customer records if repeat-customer tracking matters; reports/expenses if financial review is the bottleneck; promotions, staff roles, hardware, or broader checkout devices when a concrete need warrants them. Write smaller follow-up plans rather than reviving every sidebar module at once.

## First concrete work package

Start with Task 1's stock/payment/attempt contracts and a small fixture set, followed by Task 2's truthful navigation and branded shell. Then build Task 3's one retail item, one recipe product, one add-on, and their required stock records. Do not begin by drawing all 13 module pages or polishing dashboard charts.

Task 2 implementation (2026-10-30): shell delivered with local verification; see [evidence](../../verification/2026-10-30-task-2-shell.md). Deployment email and physical-device checks remain.

