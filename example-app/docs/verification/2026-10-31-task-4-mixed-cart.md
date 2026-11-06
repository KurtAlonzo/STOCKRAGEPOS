# Task 4 mixed cart verification and Task 5 handoff

Verification resumed November 2, 2026 on `feature/layout` at `3f98af1`, plus the accompanying fixes. This completes the October 31 preparation implementation, not payment or sale completion. The [Task 3 audit](2026-10-31-task-3-readiness-audit.md) records the isolated environment and prerequisites.

## Delivered interface

- `GET /{current_team}/pos`, `GET /{current_team}/pos/catalog`, and `POST /{current_team}/pos/quotes` require authenticated, verified store owners. Background catalog/quote requests preserve selected-store state using trusted route defaults.
- Pure cart operations preserve UUID line identity, merge only identical product/add-on combinations, retain excessive counts, and calculate exact integer-centavo/thousandth estimates with checked arithmetic.
- Quotes resolve current prices, recipe snapshots/revisions, named aggregate stock effects, direct-stock blockers and nonblocking ingredient/supply warnings. Payment/change are null. Quoting changes cache only, never business stock or financial records.
- `usePosQuote` supplies empty/invalid/pending/current/stale/error states; debounce, canonical cart correspondence, generation/scope guards, expiry and explicit recovery prevent stale results appearing current.
- Desktop/landscape split layout, portrait cart drawer and persistent total, supported-screen notice below 768px, explicit cancel/discard navigation protection. Cart contents are memory-only.

## Fixes from independent review

1. Replaced floating division money displays with existing `formatPriceMinor`. Render regressions reproduce `.90` becoming `.91` and `.87` becoming `.88` near the supported safe-integer limit, then pass for picker prices, totals, tablet summary and add-on price.
2. Modifier editing renders the union of allowed and previously selected add-ons. Removed assignments remain visible/uncheckable with an unavailable explanation; Edit remains available when a line still has selected modifiers. Both hidden-selection regressions were observed failing and then passing.
3. An invalid source quantity could disappear when modifier editing merged that source into another line. Browser reproduction used `0` on the extra-beef burger, changed it to cheese, and incorrectly produced a current merged quantity of 3. The page now requires correcting that source quantity before modifier editing; unrelated invalid fields remain retained.
4. Portrait drawer Close measured 16px. POS-specific styling now gives it a 44×44px target; the add-on dialog close target is scoped similarly.

No reviewer minors were deferred. No backend authorization or integrity defect was identified. The reviewer deliberately excluded physical hardware, non-SQLite deployment, tax setup and Task 5 payment/recovery; those remain explicit release boundaries.

## Automated evidence

Final gates on November 2 all exited 0:

| Command                                              | Result                                                                                                      |
| ---------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `npx vp test run`                                    | 35 tests / 10 files passed                                                                                  |
| `npm run types:check`                                | Passed                                                                                                      |
| `npm run check`                                      | 150 files formatted; 123 source files, zero lint warnings/errors                                            |
| `npm run build`                                      | Passed, production assets built                                                                             |
| `php artisan test`                                   | 130 tests / 709 assertions passed                                                                           |
| `php vendor/bin/phpstan analyse --memory-limit=512M` | Zero errors                                                                                                 |
| `php vendor/bin/pint --test`                         | Passed                                                                                                      |
| `php artisan route:list --path=pos -v`               | Three production owner-protected POS routes plus the local-only shell preview; no completion/attempt routes |
| `git diff --check`                                   | Passed                                                                                                      |

After evidence-only edits, the documentation formatter/check is rerun separately. Full logs are retained in ignored `.superpowers/verification-2026-11-02/final-*.log` for this local checkout.

Tests cover exact worked-basket estimates/quotes, direct shortage boundaries, identity/merge/removal and overflow, malformed/HTML/authentication errors, CSRF headers, input correspondence, reversed responses, expiry, failure without polling loops, retry using current inputs, store/user scope invalidation, immutable subscriber snapshots, route-store selection, owner access, cache failure/expiry/eviction and unchanged inventory. The quote integration test now also checks burger price `11000` yields `44000` and recipe beef `110g` yields demand `380g` / balance `-130g`.

## Browser evidence

The disposable database and loopback-only preview are described in the [audit](2026-10-31-task-3-readiness-audit.md). Keyboard Enter/Space prepared water plus two cheese burgers and one extra-beef burger as three distinct lines. Current server total was **PHP 410.00**. Effects were water/cheese `1.000 each`, beef `-100.000g`, sauce `-35.000g`, seasoning `0.625g`, packaging `-1.000 each`. Three recipe warnings and zero direct blockers appeared; Payment unavailable stayed disabled.

Changing water to 3 retained 3, showed available 2 / projected -1, and changed authoritative status to blocked. Empty quantity suppressed totals/quotes and remained empty while editing another line's modifiers. Correcting it recovered the quote. Search returning no matches retained the three cart lines and total without opening a discard prompt. A separately empty store showed “No active products”, not “No products match these filters”. Cancel cart / Keep retained the cart; explicit Discard cleared it and reset quote state. Management exit showed the discard prompt; Keep retained the cart.

Dark desktop at 1440×900, landscape 1024×768, portrait 768×1024 and phone 390×844 were inspected. Measured page width did not exceed each viewport. Portrait drawer kept total/payment explanation/cancel fixed while its lines scrolled. Phone checkout showed the larger-screen notice and management return. Keyboard product/dialog operation, quantity errors, visible focus and status announcements were inspected in the DOM. Physical touch/software keyboard and screen-reader execution are not claimed.

Post-fix browser checks confirmed the invalid-source merge is refused with quantity `0` and its error retained; correcting to `1` permits the merge. Removing all server-side add-on assignments, refreshing picker data, and reopening the existing cheese line leaves Cheese visibly selected and marked unavailable. Unchecking it repairs the line in place. Reversed-response behavior remains covered by deterministic request tests rather than a browser network interception API.

The ignored preview router replaced responses _after_ the real HTTP kernel handled them. A matching `/pos/quotes` HTML response produced “Quote failed · estimates only” with quantity 2 retained. A structured 422 displayed `lines.0.quantity` and retained the basket. Removing the override recovered current quotes. A two-second `expiresAt` override followed by restoring normal responses recovered a fresh current quote; changing burger price to 11000 showed authoritative total 27000 for water plus two plain burgers and the review explanation. Deactivating the burger preserved its line and showed `lines.1.productId`, never verified readiness. Fixtures were restored afterward. These are server response simulations, not claims of real browser offline transport.

Added 26 disposable retail items, navigated to page 2, and edited the off-page burger's modifiers using retained snapshots. Filtering back to Burger preserved the cart; restoring the contract basket again produced 41000. At 768×500 reduced height, Cancel cart's bottom was 476px within the 500px viewport; the portrait Close target measured 44×44px. Drawer close restored focus to its trigger.

The preparation checks left the six contract inventory balances/revisions unchanged, with three operations/movements belonging only to the separate Audit flour walkthrough. Automated quote tests independently assert the same no-write invariant. Light-mode desktop preparation was also inspected at 1440×900: current PHP 100.00 plain-burger quote, no page-wide overflow and no browser console errors. [Light screenshot](screenshots/2026-11-02/pos-desktop-light.jpg). Screenshots: [desktop](screenshots/2026-11-02/pos-desktop-dark.jpg), [landscape](screenshots/2026-11-02/pos-landscape-dark.jpg), [portrait](screenshots/2026-11-02/pos-portrait-dark.jpg), [retained inactive-line error](screenshots/2026-11-02/pos-portrait-validation.jpg).

### Verification limits and rulings

Ruling: reuse the user-approved current checkout and preserve its committed implementation, rather than reconstructing the old execution sequence. Cost if wrong: less isolation from concurrent edits; status was clean at entry and changes were reviewed.

Ruling: use deterministic request/backend tests for reversed network responses, auth errors, overflow, cache expiry/eviction and stock replay, plus the live response-replacement walkthrough above. The browser API lacks network throttling/offline/touch controls. Cost if wrong: physical touch, genuine connection-loss timing and transport-specific behavior remain release acceptance, not proven by this simulation.

Actual physical devices/software keyboards, 200% browser zoom, full light/dark combinations at every viewport, screen-reader execution, formal contrast and target-device performance were not exhaustively verified. These supplementary acceptance checks do not change the tested preparation/stock contracts. No real payment or sale can be recorded here. No deferred code-review minors remain.

## Task 5 handoff

Use `CartLine[]`, `usePosQuote`, `CheckoutQuote`, `StockEffect.inventoryItemName` and `QuoteStore`'s immutable actor/store envelope. Quotes live for 300 seconds under `pos:quote:{storeId}:{userId}:{quoteId}`. They are neither reservations nor durable checkout attempts. Expired/evicted quotes require fresh review, never a new operation key for an uncertain sale.

Task 5 must implement versioned user/store-scoped durable drafts; tab conflicts; cash/external-payment input; acknowledged final price/warning review; atomic sale/payment/stock posting; durable idempotency and attempt lookup; confirmed/rejected/unknown result recovery; and confirmed-sale receipt/new-sale handling. Resolve an existing confirmed attempt before checking cache/quote expiry. Revalidate authoritative state at completion; do not trust display totals or a client readiness flag. Preserve explicit user correction of direct shortages and allow warning-only recipe shortages under the accepted contract.

No completion/attempt routes, sales/payments or persistent drafts exist in this phase. Tax remains unconfigured. This handoff authorizes continued implementation, not live selling or deployment.
