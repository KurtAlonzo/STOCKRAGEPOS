# Task 6 sales corrections verification

Execution date: 2026-11-09 (Asia/Singapore). Source plan/design: November 7. Baseline: `17dc41a` on `feature/layout`, clean checkout. Task 5 carryover completed in `5421451`; persistence `87629ce`; historical reads `08b472a`; atomic refund `af7cb60`; screens `762cb19`; recoverable confirmation `2fa98e0`. Final implementation commit: `ddaf54d7681eb9890950b2bcdbd0133ecd5217d4`; tree: `c2d0f44e7a5b7cf704c6a6da5c1fdc451a764aa8`. This report/documentation update follows that verified code tree.

## Delivered behavior

- Verified owners can browse route-store sales with literal reference, Manila sale dates, original payment and current status filters, deterministic 25-row pagination, and validated return context.
- Detail preserves original line/add-on/recipe snapshots, total, tender, change, actor identity and sale movements. Original total, refunded amount and net have separate labels. Stock links open the existing scoped inventory movement panel.
- One full external refund records actual cash/GCash/Maya method, optional reference and required reason/confirmation. No provider transfer occurs. All methods remain available for historical refunds even if checkout configuration disables a method.
- Only explicitly entered, original direct-stock retail/add-on returns are eligible, bounded by original snapshots and negative sale movements. Default return list is empty; ingredients/supplies remain consumed. Inactive historical direct items are eligible.
- Durable actor/store/key-bound registration precedes atomic reversal/status/stock/outcome persistence. Recorded and rejected outcomes replay before current stock validation; another key cannot repeat a sale refund. Unknown client state retains the exact request and permits only checked, explicit original-request retry.
- Separate IndexedDB `stockrage-sales` v1 claims actor/store/sale operations atomically. Corrupt uncertainty blocks replacement. Account invalidation hides sale/refund state; session identity is rechecked on restore/focus/visibility before showing restored data. Cleanup failure leaves recorded state read-only.
- Receipts show original payment snapshots and a separate recorded refund. Store name comes from current store context. Internal reason and stock audit remain on detail. User-triggered printing uses light A5 CSS and performs no write.

## Evidence ledger

| Risk / acceptance                                                                  | Tests / evidence                                                                                                                                                                               |
| ---------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| One reversal, exact selected returns, unchanged original payment/snapshots         | `ReversalTest::test_mixed_refund_restocks_selected_direct_quantities_once`: 41000 refund, water/cheese +1.000, eight total movements, untouched recipe balances and original checkout response |
| Exact money, invalid reason/reference/method/confirmation/quantity and over-return | `ReversalTest` data provider and no-stock/method cases                                                                                                                                         |
| Immutable/restrictive history and operation identity                               | `ReversalPersistenceTest`: uniqueness, model update/delete guards, legacy nullable links and disposable rollback/reapply preserving sale/payment                                               |
| Current catalog differs, inactive direct stock, missing original linkage           | `SalesReadTest` historical detail/eligibility; `ReversalTest` inactive/recipe and missing-linkage cases                                                                                        |
| Stable pagination, bounded relationship queries, literal `%`/`_`, Manila midnight  | `SalesReadTest`, including exact 15:59:59Z / 16:00:00Z boundaries, 27 tied rows and compact summaries                                                                                          |
| Store/owner/actor/key isolation                                                    | `SalesReadTest`, `ReversalTest` authorization/current/attempt cases and separate-sale key conflict                                                                                             |
| Same/different-key process races and refund versus receipt                         | `ReversalConcurrencyTest` real process barriers, unique indexes, exact balances/movement counts                                                                                                |
| Processor dies after registration                                                  | `ReversalConcurrencyTest` abandoned worker; browser pending refund after durable registration, refresh and identical retry                                                                     |
| Partial transaction interruption                                                   | `ReversalTest` interruption after first inventory update rolls back refund/status/all returns but retains pending identity                                                                     |
| Rejected key remains rejected after stock changes and older overlapping retry      | `ReversalConcurrencyTest` rejected mode: original worker registered before terminal rejection, valid inventory adjustment, same rejection on resumed worker                                    |
| Committed response lost                                                            | `sales-reversal.spec.ts`: actual handler commits via `route.fetch`, downstream response dropped, unknown refresh/recovery and no second POST                                                   |
| Real local storage conflicts/corruption/failure                                    | `sales-storage.spec.ts` simultaneous competing claims, scoped reads and corrupt replacement blocked; storage quota case asserts zero POSTs                                                     |
| Recorded result survives failed cleanup/account change                             | Browser cleanup-failure refresh and missed-broadcast account switch/recovery cases                                                                                                             |
| Original confirmation decoder remains valid after refund                           | Backend original lookup/replay equality plus final integrated browser decoder check                                                                                                            |
| Responsive browsing, retained filters, no results                                  | `sales-history.spec.ts`: 390, 768 and 1440 widths, current sale link, filter return and no-results state                                                                                       |
| Optional receipts, long reference/names/modifiers, A5 and multiple pages           | Receipt unit tests, `sales-receipt.spec.ts` original/reversed receipt, 255-character reference, 80-line real checkout, PDF page size/count, unchanged records before/after presentation        |
| Phone refund and keyboard recovery                                                 | Pending-retry browser case; phone keyboard completion/focus-return case recorded with final results below                                                                                      |

## Verification commands and results

- `php artisan test`: 178 passed, 1,295 assertions, 40.259 seconds.
- `php vendor/bin/phpstan analyse --memory-limit=512M`: zero errors.
- `php vendor/bin/pint --test`: passed.
- `npx vp test run`: 17 files / 64 tests passed.
- `npm run types:check`: passed.
- `npm run check`: 197 formatted files / 163 linted files passed.
- `npm run test:pos-browser`: production build and all 35 browser tests passed (6.7 minutes), including the final focus regression and complete sale/refund/recovery/history/receipt/inventory path.
- `php artisan wayfinder:generate --with-form`: generated from routes; helpers were not hand-edited.
- `git diff --check`: passed.

The first integrated browser run exposed a test-selector/time-budget problem (32 passed / one test timeout); the role selector was corrected and the composite workflow received its own 90-second budget. The final complete 35-test run passed. The separate review regression reproduced and fixed actual form loss; it is included in the final green suite.

## Reproduction and artifacts

Run `npm run test:pos-browser` from `example-app`. Harness database/cache/session/view paths remain confined to verified disposable testing storage; PHP tests use isolated memory/random temporary SQLite files. No development database migration was performed.

The integrated test sells the PHP 410.00 mixed basket, records a full Maya refund with water/cheese 1.000 each, drops the committed response, refreshes/reconciles, filters current refunded history by original cash/date/status, decodes the original checkout confirmation, shows the reversed receipt and follows Water into inventory movements. One sale/payment, one reversal, eight movements and water/cheese 2.000 are asserted.

Artifacts under `screenshots/2026-11-09/`: desktop/phone refunded detail, phone refund dialog, print-media reversed receipt, original A5 reversed receipt PDF, and a multi-page long receipt PDF. The PDFs are generated by headless Chromium with CSS page size; the print screenshot remains a browser-width print-media view.

## Schema, route and release handoff

Three new migrations create reversal attempts/reversals, link stock operations to reversals and index scoped sale history. Deploy/apply these migrations before enabling the new screens outside the isolated test harness. Existing migrations were not rewritten.

Inertia GET routes: `sales.index`, `sales.show`. Financial JSON routes: `sales.reversals.store`, `sales.reversals.show`, `sales.reversal-attempts.show`; every read/replay reauthorizes owner/store and JSON operations enforce actor preconditions/echoes. See the amended POS contract for canonical input, response unions, 422/409/202/503 semantics and lookup behavior.

External checks remain: physical phones/tablets/keyboards, screen readers, actual 200% browser zoom, physical networks and target-device performance; production database concurrency; native print preview/cancel, Save as PDF dialogs and physical printers. Automated beforeprint/afterprint simulation and headless PDF generation do not prove native cancellation or printer behavior. Printing remains optional and cannot change a confirmed sale/refund.

Task 7 starts with global stock history, real dashboard aggregates and business settings. Tax treatment, deployment/live-selling approval, partial refunds/exchanges and provider integration remain outside this delivery.

## Final review and execution decisions

A fresh read-only review found no critical or deferred minor issues and one important interaction defect: same-account focus revalidation unmounted the refund dialog and discarded unsaved fields. A browser regression reproduced the empty reason after returning focus. The fix holds form state above the temporary verification boundary and keys it by actor/store/sale, while the verification boundary still hides sensitive content. The regression failed with an empty reason before the fix, then passed with all fields retained. The full 35-test browser suite and 64-test frontend suite passed after the fix. No critical or important review finding remains open.

Rulings made:

1. Continue the user's existing clean `feature/layout` checkout instead of relocating the resumed implementation. All changes are local commits on that branch; cost if wrong is needing to move those commits to another branch.
2. External cash/provider transfers remain outside the app, as specified. Database uniqueness cannot stop independently repeated manual transfers; the recovery UI explicitly tells the owner not to return money again.
3. Native print cancellation, screen readers and physical devices remain external release checks. Headless output and focus assertions prove only the recorded local scope; cost if omitted before release is undiscovered device/assistive-technology behavior.
4. Eloquent immutability guards supported application writes, not privileged raw-SQL administrator tampering. There is no supported UI for rewriting these records; cost if privileged database access is misused is altered historical data.

No deferred minor findings.

Visual QA: Poppler rendered and inspection checked both refunded receipt pages, including the wrapped 255-character reference. `pdfinfo` reports A5 420 × 594.96 points; the valid 80-line checkout generates 27 pages. Desktop/phone detail and phone dialog screenshots were inspected. Native print cancellation remains unverified.
