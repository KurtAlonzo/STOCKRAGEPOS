# STOCKRAGE Task 6 sales review and corrections design

**Date:** 2026-11-07
**Scope:** Planning only. Implements parent Task 6 after closing the outstanding Task 5 work. No application implementation is authorized by this document.
**Sources:** [Parent action plan](../plans/2026-10-29-stockrage-frontend-action-plan.md), [POS contract](../../contracts/pos-v1.md), [Task 5 plan](../plans/2026-11-06-stockrage-task-5-execution-plan.md), and current source/tests.

## Confirmed interview decisions

1. Close Task 5 gaps before starting Task 6 implementation.
2. Permit explicit returned quantities of retail items and directly stocked add-ons. Nothing is selected for restocking by default. Never automatically restore food ingredients or supplies.
3. Permit cash, GCash or Maya refunds regardless of the original payment method; record the actual refund method and optional reference. Default the form to the original method. The full amount is returned outside the app.
4. Execute Task 6 backend-first: refund/history tests and integrity gate, then screens and receipts.

The details below are proposed implementation defaults, not additional interview answers.

## Intent and boundaries

An authenticated, verified store owner can find a completed sale, inspect what was sold and deducted, record one full external refund, explicitly restore eligible returned stock, and view or optionally print its receipt. A network interruption must never invite returning money again or duplicate a financial/stock record.

Preserve PHP integer centavos, Asia/Manila business dates, one original payment per sale, immutable sale/payment/recipe snapshots, owner/store isolation, and append-only movements. Financial correction is the original sale total, not cash tender; PHP 410.00 paid with PHP 500.00 and PHP 90.00 change refunds PHP 410.00.

No partial monetary refunds, exchanges, repeated refunds, provider transfers, receipt numbering/tax engine, staff permissions, customer data, receipt settings, thermal-printer integration, global stock-history screen, dashboard aggregates, or business settings. These remain outside Task 6. Tax treatment remains unconfigured; this work does not establish live-selling readiness.

New sales management pages support phone, tablet and desktop; the existing checkout restriction below 768 px remains. This includes the new refund dialog on phones, without expanding checkout support.

## Approaches

All options first close Task 5. The user selected **backend-first**, proving persistence, queries, replay, concurrency and stock bounds before connecting refund controls. This gives the highest-risk behavior an explicit gate.

The alternatives were read-only history first (earlier visible value, refund integration later) and end-to-end slices (earlier complete examples, more coordination across changing interfaces). Neither is the selected sequence.

## Task 5 carryover and evidence

Current code already implements payment, durable checkout attempts, IndexedDB drafts and recovery. Do not rebuild those features because the old plan's boxes are unchecked.

Confirmed documentation gaps: the planned `docs/verification/2026-11-06-task-5-payment-recovery.md` is absent; the parent Task 5 checklist is unchecked; the current progress summary still says payments are unavailable. Write fresh evidence dated 2026-11-07 and reconcile these descriptions only after verification.

Browser coverage lacks direct workflow cases for refresh while a real submission is pending, explicit same-key retry, duplicate-reference review, changed recipe/warning review, and restoration of an off-page selection. Existing lower-level tests cover portions of these behaviors; carry over the missing integrated evidence, not assumed product defects. Also audit same-account store switching and logout during editable work, because the existing account-change test focuses on an uncertain submission. Preserve the already implemented crash-after-registration, rollback, contention, committed-response-loss, storage-failure and corrupt-draft tests.

Accessibility evidence currently opens/closes review with the keyboard but does not demonstrate keyboard-only completion/recovery. Its zoom screenshot uses CSS `zoom`, not actual browser zoom. Add the missing checks or record the exact external release limitation; do not rename CSS scaling as browser zoom or screenshots as physical-device testing.

Planning baseline run on 2026-11-07: `php artisan test tests/Feature/Pos` passed 21 tests / 272 assertions; `npm run types:check` passed; `npm run check` passed formatting for 177 files and lint for 148 files. Browser tests, frontend unit tests, production build, full PHP suite, PHPStan and Pint were not run in this planning session. Execution must establish those results.

The starting tree has user changes in `composer.json`, `vite.config.ts`, `resources/js/lib/pos/checkout-requests.test.ts`, `tests/Browser/pos-recovery.spec.ts`, `tests/Support/pos-browser-fixture.php`, and untracked root `.vscode/`. Preserve them; inspect and attribute any failures before changing them. No broad staging/reset or migration of the user's development database.

Task 5 gate means relevant automated checks pass, missing integration cases are resolved, and evidence is honest. Physical devices, actual printer behavior, target-device performance and tax/deployment decisions remain separately tracked release checks, not prerequisites to planning or local Task 6 development.

## Read model and financial presentation

Add owner/store-scoped Inertia sales index and detail routes. Index filters are reference substring, sale business-date from/to, original payment method and completed/reversed status. Defaults: all dates/statuses/methods, newest first by `occurred_at DESC, id DESC`, 25 rows. Filters/page survive reload, navigation and detail-to-list return. A filter change resets page to 1. Invalid dates/ranges produce field errors. Convert Asia/Manila date boundaries to UTC with inclusive start/exclusive next-day end.

Index uses a compact summary payload, not full lines/movements per row. Detail shows immutable product/add-on names, sold quantities, prices, original recipe deductions, original payment/tender/change, actor and timestamp, linked stock movements and the correction. Use separate labels for original total, refunded amount and net amount. Reversed PHP 410.00 means original 41000, refunded 41000, net 0; original payment remains unchanged. Original-payment filtering does not silently become refund-method filtering.

Show sale and reversal movement groups on detail. Link each inventory item to its existing store-scoped movement panel. Do not create the Task 7 global history module. `sourceType: reversal` points to the reversal ID; keep a parent sale link in the sale detail context. Avoid one payment/movement query per list row.

## Refund and stock contract

Extend `ReversalInput` with `refundMethod: 'cash'|'gcash'|'maya'` and `refundReference: string|null`. Preserve `idempotencyKey`, required reason, `refundConfirmed: true` and `restock`. Trim reason/reference; reason length 1–1000, reference maximum 255; blank reference becomes null. A reference is neither proof of transfer nor a unique key. All three methods remain available for historical refunds even if disabled for new checkout.

One full reversal per sale. An empty restock array is valid. Each selected inventory ID appears once with a positive integral each quantity transported as a canonical three-place string. Aggregate eligibility from the original sale's direct-stock snapshots and original negative sale movements, including direct add-ons once per product quantity. Never calculate eligibility from current recipes, prices or currently allowed add-ons. Bound each return by its original direct deduction; reject unknown/foreign/ingredient/supply IDs, duplicates, fractions, zero/negative values, excess quantities and safe-integer overflow. Inactive original inventory is eligible; missing historical data fails safely without guessing. Aggregate one positive movement per selected inventory item and increment that stock revision once.

Full refund and selected restock commit atomically. A reversal with no restock still records its full financial correction. Later discoveries use the existing reason-required inventory adjustment, not editing/reopening the reversal. Original sale/payment/sale movements never change; only sale status becomes reversed and new linked records are appended.

## Persistence, replay and interruption

Use durable `reversal_attempts` with sale/actor/store identity, unique `(team_id, actor_id, idempotency_key)`, immutable canonical request/hash (including sale ID), status pending/recorded/rejected and terminal result JSON. Use a `sale_reversals` table with unique `sale_id`, unique `reversal_attempt_id`, immutable canonical request/hash, total, refund method/reference, reason/confirmation, actor/name and timestamp. Add nullable `sale_reversal_id` on stock operations while retaining `sale_id`. Restrict deletion of historical references and retain attempts for this release.

Authenticate, validate the request envelope and register its canonical identity in a short durable transaction. Use the business transaction lock order store -> attempt -> sale -> selected inventory rows ordered by ID. Under those locks, resolve terminal replay before validating current inventory, reject a changed-payload key or an already reversed sale, validate historical eligibility, then write reversal, stock operations/movements, balances/revisions, sale status and recorded attempt result together. Persist a definitive business rejection under the same locks before allowing edits; return the rejection without rolling back its terminal record. Never return current inventory balances as part of an idempotent replay.

Durable rejected attempts are necessary too: a stock-overflow rejection must remain rejected even if stock later changes and an older overlapping request resumes. A worker killed after registration leaves pending state recoverable with the original request/key. Transient database errors preserve pending; no time-based expiry creates permission for a new identity. Attempt lookup returns not_found/pending/recorded/rejected for the authenticated actor/store/sale/key. A separate current-reversal lookup returns `{status:'not_recorded'}` or `{status:'recorded', reversal: SaleReversal}` for the sale, including a different recorded operation. not_found/pending can race an in-flight POST, so they permit only explicit identical-key retry, not a new operation.

Use JSON POST `/sales/{sale}/reversals`, GET `/sales/{sale}/reversal` and GET `/sales/{sale}/reversal-attempts/{key}`, with existing actor precondition/echo headers and `preserve_current_team`. Inertia owns index/detail. Document this financial-write exception to ordinary management form redirects. Validation is structured 422; changed-payload or already-reversed conflicts 409; a registered unresolved attempt returns 202 pending. If durable state cannot be read, 503 reports unknown recording status without claiming failure. Auth/CSRF/scope errors stop client writes and preserve the original operation. Reauthorize every read/replay; another store's sale returns 404.

Persist the exact reversal request before POST in a separate IndexedDB database `stockrage-sales`, version 1, keyed by actor/store/sale. An atomic insert claims an unresolved operation for that sale; competing tabs observe it and cannot replace it. Do not upgrade/change the existing POS draft database for this feature. Unsaved form edits remain in memory. Restore and reconcile unresolved records on refresh; keep the original request/key through logout/account changes but clear visible sensitive state and revalidate the session before rendering it. Storage failure blocks POST; malformed uncertain records block replacement.

Client states: editing, submitting, unknown, recorded, rejected. No automatic POST retry. Unknown copy: `Refund recording status is unknown. Do not return money again while checking this sale.` Offer Check again and explicit same-request retry after a valid attempt lookup says not_found/pending and current-reversal lookup finds none. Only a durable rejected result permits correcting a registered request and generating a new key, after rereading the sale; a competing recorded reversal resolves to read-only details. Malformed-envelope validation before registration can be corrected after confirming no original attempt exists; unrecognized/ambiguous errors preserve uncertainty. A valid recorded outcome remains visible if local cleanup fails. There is never a second refund action after confirmation.

The app records the owner's external-money-return confirmation; it cannot make external cash/provider transfers atomic with a database commit or verify that they happened. UI refreshes current sale status before confirmation, displays the full amount/method, and never instructs returning money again following a recording failure. Database uniqueness prevents duplicate records/restocking, not independent manual transfers outside the app.

Checkout attempt `result_json` stays immutable. Existing checkout decoders accept only original sale movements and `reversal:null`; do not feed current sales detail into those decoders or rewrite prior confirmed responses. Regression-test a refund followed by old checkout lookup/replay: original confirmation still decodes, current sales detail is reversed, and no new deduction occurs. Add View sale from confirmed POS to the current detail route.

## Receipt and interaction defaults

Use existing application shell/components and focus/error conventions. Sales list has loading/error/empty/no-results states; detail has an explicit Refund full sale action, then a dialog showing the full amount, reason, method/reference, stock selections and external-return confirmation. Reset that confirmation when method or return selections change. No selection is inferred from refunding the money. Keep unresolved state visible after closing/reopening the dialog; disable destructive escape paths without blocking normal read-only navigation.

Render receipt content from historical sale/payment snapshots with a clearly separated refund section when reversed. Store display name comes from the current store context, labeled as such in the contract; do not fabricate historical store details that Task 5 never saved. Include reference, Asia/Manila time, item/modifier quantities/prices, original total, recorded payment/tender/change, and recorded refund method/amount/date. Keep the detailed reason and inventory audit on sale detail rather than require them on the customer printout.

Optional browser print/Save as PDF uses A5 print CSS, light paper colors, no application chrome, sensible page breaks and long-content wrapping. Printing is invoked only by the user and performs no writes. Cancel, print failure or PDF-save cancellation leaves the sale/refund unchanged. No new PDF service or library, business settings, invented tax fields, or compliance claims.

## Acceptance and release limits

For the existing PHP 410.00 mixed basket, refund 41000 and explicitly return water 1.000 and cheese 1.000: water becomes 2.000, cheese 2.000; beef -100.000, sauce -35.000, seasoning 0.625 and packaging -1.000 stay unchanged. A retry produces one reversal and two positive movements total. Selecting no stock produces zero positive movements. Returning cheese 3.000 rejects because only 2.000 were originally sold.

Prove concurrent identical/different reversal requests, durable rejection despite later stock changes/overlapping retries, worker death after registration, rollback after the first restock, committed response loss/refresh/retry, original snapshots after catalog edits, inactive inventory, overflow, actor/store isolation, Manila midnight filters, preserved list state, old checkout replay after refund, and printable reversed history. Verify desktop/tablet/phone and keyboard recovery. Record actual browser zoom, screen-reader, printer and physical-device limits separately.

## Design review

A builder check on 2026-11-07 identified the need for durable rejected-operation identity. The design now persists pending/recorded/rejected attempts and explicitly tests rejection stability when inventory changes. The reviewer found no other consequential unanswered design decisions.
