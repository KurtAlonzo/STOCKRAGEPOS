# STOCKRAGE Task 1: Operating Rules and Contracts Design

**Date:** 2026-10-29  
**Status:** Interview decisions confirmed; technical proposals below await document review. Planning only.  
**Scope:** Task 1 of [the frontend action plan](../plans/2026-10-29-stockrage-frontend-action-plan.md).

## Intent and boundary

Give frontend and backend implementers one agreed vocabulary, payload shape, recovery protocol, and worked sale before persistent business screens are built. Task 1 produces a contract document, TypeScript definitions, realistic fixtures, and checks of those artifacts. It does not build screens, database persistence, controllers, live routes, payment processing, or a production checkout calculator.

Success means both implementers can independently explain the same total, aggregate stock deductions, and uncertain-submission outcome from the contract. Passing fixture checks is not proof that a backend enforces the contract.

## Approaches

- **Selected by the user: document + TypeScript + checked fixtures.** Fits the existing Laravel/Inertia architecture and adds no dependency. Changes must update the document, types, examples, and checks together.
- **Considered: machine-readable schemas with generated types.** Automates more consistency checking, but adds schema tooling and a generation workflow before the business API exists. Deferred.

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

## Verification and handoff

Reuse installed Vite Plus 1.4.0 and its `vite-plus/test` APIs. Its CLI and bundled test documentation were inspected; `vp test --help` succeeded on Node 24.21.0. No test dependency is needed. Fixture tests check data consistency and independent arithmetic, not a pretend backend state machine.

The execution plan must map every parent Task 1 checkbox to an artifact and proof. Examples cover stock boundaries, decimal precision, changed prices/recipes, invalid/unavailable items, optional/duplicate references, validation rejection, conflicting operation payloads, a response lost after commit, and forbidden store access. The backend's eventual integration suite must enforce the same scenarios.

Task 1 can become **contract-ready for development** while tax remains an explicit live-use blocker. It cannot be called integrated or production-ready. Authoring this design and its plan does not authorize product implementation.
