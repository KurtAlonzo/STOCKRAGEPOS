# STOCKRAGE Task 4 Mixed Cart Design

**Date:** 2026-10-31. **Status:** Interview decisions confirmed; design and execution plan prepared for review. No product implementation authorized by these documents.

**Source:** [Parent Task 4](../plans/2026-10-29-stockrage-frontend-action-plan.md), [POS contract](../../contracts/pos-v1.md), and the current Task 3 implementation.

## Intent and scope

Give the verified store owner a working cart for retail and recipe-based food, with per-line add-ons, immediate price estimates, and real server quotes showing aggregate stock effects. This is order preparation, not a completed sale. Task 5 owns payment input, sale/payment/stock posting, persistent drafts, conflicting tabs, and uncertain checkout recovery.

Success is the contract basket: one water, two burgers with cheese, and one burger with extra beef remain three correctly configured lines, total PHP 410.00, and project beef -100.000 g, sauce -35.000 g, seasoning 0.625 g, packaging -1.000 each, water 1.000 each, and cheese 1.000 each. Preparing or quoting the basket never changes inventory balances or movements.

## Confirmed interview decisions

1. Build the integrated cart and real quote endpoint in Task 4. Leave payments, sale posting, and persistent draft recovery to Task 5.
2. Retain excessive direct-stock quantities. Show requested/available quantities and block readiness until the owner explicitly corrects or removes the line; never silently clamp or remove an order. Recipe shortages remain warnings.
3. Desktop and landscape tablet use products and cart side by side. Portrait tablet uses a persistent total/cart button opening a cart drawer.
4. Warn before leaving a nonempty cart or switching stores. Use the browser refresh/close warning where supported. Explain that drafts are not saved yet.

## Approaches considered

- **Integrated cart and quotes — selected:** finishes Task 4 against real data while keeping financial writes in Task 5.
- **Fixture-only preview — not selected:** less work now, but cannot satisfy authoritative-quote acceptance.
- **Combined Tasks 4–5 — not selected:** provides complete checkout but substantially increases scope and recovery testing.

For portrait tablet, the selected drawer preserves touch space; a permanent narrow split was not selected. Draft saving was explicitly left to Task 5 rather than pulled forward.

## Existing foundations and gaps

- Reuse `StockDemand`, `Quantity`, `StoreCatalogTransaction`, catalog/inventory serializers, owner middleware, existing Radix components, appearance settings, and the unsaved-navigation guard.
- `PosLayout` currently contains development-preview copy; `app.tsx` uses it only for `dev/pos-shell`. Production POS, quote routes, cart logic, and quote request handling do not exist yet.
- Existing `PosPageProps` lacks resolved add-ons, stock references, and filter metadata required by a paginated picker. Extend it explicitly rather than loading the entire catalog.
- A separate [Task 3 readiness audit plan](../plans/2026-10-31-stockrage-task-3-audit-completion-plan.md) exists, with no readiness report found during this inspection. Its unchecked steps are not proof of completion. Verify prerequisites during execution; avoid duplicating that audit or its unrelated formatting cleanup.
- `EnsureTeamMembership` currently selects the route store as a side effect. Background POS requests need a narrow opt-out from that selection, while retaining all authentication/ownership checks; otherwise a late request can undo an intentional store switch.

## Proposed implementation decisions

These are builder recommendations, not additional answers attributed to the owner.

### Catalog and cart

- `GET /{current_team}/pos` renders initial business context, categories, and the first catalog page. `GET /{current_team}/pos/catalog` returns the same picker data as JSON for name/SKU search, category filtering, and pagination, 25 products per page. Only active retail/food are top-level choices; low or zero direct stock remains visible and selectable so the requested order can be represented. Inactive or structurally invalid products cannot be added.
- Return allowed add-ons and inventory records referenced by the returned products/add-ons. Bound results by the page, not the whole store. Cache selected product/add-on/inventory snapshots in component memory across filters. Search is local UI state and JSON reads, not Inertia navigation; it must not activate the discard guard or reset the cart.
- Products without add-ons add immediately. Products with add-ons open a selection dialog, including an explicit no-add-ons choice. The dialog also edits an existing line; cancel changes nothing. Each selected add-on applies once per product unit. Add-ons are never standalone product tiles.
- Line identity is product ID plus sorted, unique add-on IDs. Adding an identical configuration increments its quantity and preserves its ID. Editing modifiers into an existing configuration merges quantities into the existing target ID and removes the edited source; announce the merge. Otherwise edits keep the line ID. IDs are UUIDs.
- Quantities are whole positive integers, maximum 9007199254740. Empty/intermediate/invalid input is retained in the field with an error and prevents quoting that edited cart until corrected. Zero never silently removes a line; removal is explicit. Quantity additions and all calculations reject overflow.
- Show local estimates immediately, including aggregate projected stock, then replace them with a quote for the exact current cart. Retain requested counts when stock is short. Structural invalidity or removed/disabled selections discovered by the server preserves the cart and presents actionable line errors.

### Quotes and freshness

- `POST /{current_team}/pos/quotes` accepts the existing `CheckoutQuoteInput`, with `payment: null` for this stage. Non-null payment is rejected with a field error until Task 5. Never accept client prices, totals, stock balances, or store authority.
- Mark the catalog JSON and quote routes with the trusted server route default `preserve_current_team: true`. Extend `EnsureTeamMembership` to skip only its `switchTeam` side effect for that default, after the usual access checks. Never read this flag from input or headers. Page visits and intentional store switching keep existing behavior. All POS JSON data and cache scope come from the route store, not the selected-store session.
- Build prices, snapshots, revisions, and aggregate effects within the existing store transaction boundary. Use checked integer arithmetic, eager-load relevant records, and reuse `StockDemand` rather than inventing a second backend shortage policy. Quotes do not reserve or deduct stock.
- Shortages are valid quote results: direct shortages appear in `blockingIssues`; ingredient/supply shortages in `warnings`. Invalid counts/selections return 422 with dotted fields; foreign resource IDs return 404 without leaking data. Empty carts are not quoted. Authentication/ownership/session failures are not successful quotes.
- Add `inventoryItemName: string` to `StockEffect` so every current projection remains understandable even after a recipe changes to a previously unseen ingredient. Update existing effect fixtures and contract consumers together.
- Store the immutable quote, canonical inputs, and user/store identity in the existing Laravel cache for five minutes. Use UUID quote IDs/revisions and a user/store/quote-scoped key. No new quote migration is needed. An expired/evicted entry requires a new quote; it must never authorize a future completion. Task 5 still owns final review, lookup consumption, and durable idempotency independent of cache lifetime.
- Debounce valid cart edits by 300 ms. Mark the previous quote stale immediately, abort superseded requests, and also guard responses by request generation plus user/store and exact canonical cart identity. Abort alone is insufficient. Apply the same latest-request protection to picker searches.
- Quote states: empty, invalid, pending, current, stale, and error. A quote is current only for matching inputs before its server expiry. Requote on expiry while visible and on focus/online recovery when stale; do not poll continuously. Failed requests preserve the cart and last result labeled stale, with Retry. Reject malformed JSON, unexpected HTML, and responses that do not match the request.
- Authoritative price/recipe changes replace estimates with a visible explanation. Task 5 must obtain final acknowledgment of changed prices/material warnings before completing a sale. Task 4 never presents preparation as successful payment.

### Layout, navigation, and honest readiness

- Preserve existing breakpoints: below 768 px is the supported-screen notice; 768–1023 px is the portrait drawer; 1024 px and above is the split layout. Keep total and quote status visible; within the drawer, keep totals/action fixed while lines scroll.
- Production header identifies the store and provides management return/store-switch access. Development preview retains its sample-data labeling. Avoid nesting production POS inside the management shell.
- Keep a disabled `Payment unavailable` action with visible explanation `Payment recording will be available in the next checkout stage.` A separate quote status distinguishes valid preparation, warning-only preparation, direct-stock blocking, and unverified estimates. No enabled action appears to take payment.
- Nonempty carts use the existing navigation guard for management links, Back/Forward, logout, and store switching. Explicit Cancel cart asks for confirmation. Confirmed discard resets cart, snapshots, dialogs, and requests. A new user/store never inherits state. Browser refresh/close protection is best effort; display `Cart is not saved. Leaving or refreshing may discard it.` No localStorage, sessionStorage, or Inertia remembered cart.
- Provide keyboard product selection, dialog focus/escape/return, associated input errors, polite status announcements, at least 44 px touch controls, readable long names, light/dark appearance, and no page-wide overflow. Validate on-screen-keyboard usability on actual hardware when available; emulation is not physical-device evidence.

## Constraints and non-goals

Preserve PHP integer centavos, Asia/Manila business context, final selling prices without tax arithmetic, three-decimal stock transport with exact thousandths, integral counted stock, and maximum absolute scaled value 9007199254740991. Preserve existing branding and owner-only store isolation. Do not add discounts, split payments, staff checkout, standalone add-on sales, barcode-specific flows, new state/test frameworks, or offline completion. Tax treatment remains unconfigured and this phase is not approval for live sales.

## Verification and handoff

Use existing Vite Plus unit tests for cart/projection/request-state behavior and Laravel tests for quotes, isolation, cache expiry, and no stock mutation. Exercise the real page in a disposable SQLite preview at 1440x900, 1024x768, 768x1024, and 390x844, including slow/out-of-order quote responses and dirty navigation. Record actual checks, baseline failures, and unverified physical-device behavior in a Task 4 evidence report. Detailed files, steps, and acceptance tests are in the [execution plan](../plans/2026-10-31-stockrage-task-4-execution-plan.md).

Builder review found the selected-store side effect above; the design and plan now include its fix and cross-store regression tests. No further product decisions were requested by the reviewer.
