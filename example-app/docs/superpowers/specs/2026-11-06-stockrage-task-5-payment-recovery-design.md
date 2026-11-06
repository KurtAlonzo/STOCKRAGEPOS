# STOCKRAGE Task 5 Payment and Recovery Design

**Date:** 2026-11-06. **Status:** Proposed design for the requested execution plan; interview decisions below are confirmed. Documentation only, not product implementation or live-selling approval.

**Sources:** [Parent Task 5](../plans/2026-10-29-stockrage-frontend-action-plan.md), [POS contract](../../contracts/pos-v1.md), [Task 4 handoff](../../verification/2026-10-31-task-4-mixed-cart.md), and current application code.

## Intent and completion boundary

Let the verified store owner complete the existing mixed retail/food cart, record externally received money, and recover an interrupted checkout without duplicate sales or deductions. Success means one confirmed attempt produces one sale, one payment and one movement per affected inventory item, with durable immutable snapshots and an understandable on-screen result.

Include cash, GCash and Maya; final payment-aware quotes; local draft restoration; user/store isolation; explicit tab takeover; durable backend attempts; atomic completion; unknown-result reconciliation; confirmation and New Sale. Task 6 retains sales browsing, reversals/refunds and printable receipts. Task 7 retains business-settings UI. No provider integration, offline completion, discounts, tax engine, split payments, held-order library, staff checkout or phone checkout.

## Confirmed interview decisions

1. Logout retains the editable cart for that owner/store, clears unsubmitted payment details and external receipt confirmation, and preserves the exact original request/key of an unresolved submission.
2. Only one tab edits a draft. A second tab is read-only and offers explicit Take over, disabling the former editor. An unresolved submission prevents editing/takeover until reconciliation.
3. Unknown results trigger bounded automatic status lookups, followed by Check again and explicit same-key retry when permitted. Never automatically POST again or create a replacement key.
4. Execute backend-first: prove atomic completion and recovery before connecting drafts and the payment UI.

Existing confirmed rules remain: PHP integer centavos, Asia/Manila business time, whole sold counts, exact stock thousandths, direct shortages block, recipe shortages warn and may become negative, external receipt confirmation required, references optional, duplicate references warn within store/provider including reversed sales. Final prices have no added/extracted tax; tax remains unconfigured.

## Approaches

- **Backend-first — selected:** establish durable attempts, payment-aware review, atomic posting and concurrency tests before wiring interactive completion. This concentrates the early work on the consequences hardest to undo.
- **Cash end-to-end first — considered:** produces an earlier visible workflow, then extends payment types and recovery. Not selected because recovery invariants would still be incomplete during early UI integration.

## Current foundations and constraints

- Reuse `CartLine`, `CheckoutQuote`, `CheckoutCompletionInput`, `CheckoutAttempt`, `AttemptLookupResult`, `Sale`, `StockDemand`, `Quantity`, `usePosQuote`, the POS layout and owner middleware.
- `CreateQuote` currently rejects payment. `QuoteStore` holds an immutable actor/store-scoped envelope for 300 seconds; it is not an attempt store or stock reservation.
- Cart and quantity buffers currently exist only in memory. Paginated catalog data cannot alone restore products that are off the current page.
- There are no completion/lookup routes or sale/payment/attempt tables. Existing types describe the intended protocol, not implemented behavior.
- `stock_movements.stock_operation_id` is unique. Preserve this invariant: create one sale-linked stock operation and movement per aggregated affected inventory item, all in the sale transaction. Do not rewrite historical migrations.
- `StoreCatalogTransaction` serializes store mutations on supported databases and retries contention; SQLite remains the current example/test default. Existing multi-process stock tests provide a pattern for real competing-connection tests. A passing in-memory test does not prove concurrency.
- Preserve current uncommitted Task 4 changes. Execution must inventory its baseline, use an agreed isolated checkout when needed, and avoid staging unrelated edits.

## Proposed implementation decisions

These are builder recommendations, not additional answers attributed to the user. They are concrete defaults for the execution plan.

### Durable attempts and atomic sale posting

Use synchronous completion with database-backed recovery; no queue or cleanup job is required. An attempt has actor/store/key identity, a canonical original request and fingerprint, status `pending/confirmed/rejected`, and a persisted terminal result. Enforce a unique `(team_id, actor_id, idempotency_key)` constraint. Preserve records indefinitely for this release.

Validate authentication, ownership and request envelope first. Register a well-formed operation in a short committed transaction before business processing. Concurrent registration uses the database uniqueness constraint and rereads the existing record. An existing key with a changed canonical request returns `idempotency_conflict` without replacing the original. Canonicalization fixes field order and sorts add-on and warning IDs; preserve line order and line IDs. Include quote ID/revision and payment details in identity; changed review means a changed request.

Resolve terminal replay before consulting quote cache or catalog state. For pending processing, lock in a single order: store, attempt, inventory rows ordered by ID. Reuse the existing store serialization protocol for catalog/stock writers. A simultaneous retry waits or returns 202 pending under bounded lock contention; elapsed time never authorizes a second processor. A killed worker releases database locks; an explicit same-key POST can resume the persisted pending attempt. GET lookup only reads status.

Within one transaction, revalidate the reviewed quote and payment against current catalog/recipe/stock state, create the sale and its line/add-on/recipe snapshots, create its single payment, append sale-linked operations/movements, update balances/revisions, and mark the attempt confirmed with the result. Each inventory row is updated once for aggregate demand. Rollback leaves no partial business records. Definitive business failures persist a rejected result; database/transport uncertainty is never converted into a false rejection. Do not send a terminal response until its transaction is known committed.

Sales store immutable line snapshots as JSON and integer totals; payments are separate records unique by sale. Stock operations gain nullable `sale_id`; existing receipts/adjustments retain null. Movement serialization uses the sale ID for sale sources and existing operation IDs for receipt/adjustment sources. A reference such as `SR-<sale id padded to at least 8 digits>` provides a stable unique display identifier without a separate counter. Payment reference is never that identifier or an idempotency key.

### Final quotes and review

Extend `POST /{current_team}/pos/quotes` to accept null or valid payment input. Retain preparation quotes with null payment. A final quote includes chosen method, integer tender/change or confirmed external receipt/reference, and duplicate-reference warnings. Enable cash/GCash/Maya from server configuration with optional store overrides; do not build settings UI here. Validate the enabled set server-side on quote and completion.

Cash tender must cover the total. External references trim to null when empty and otherwise match exactly within store/provider, including reversed sales. A missing reference does not require a warning or prevent completion. Query duplicates under the same store transaction during completion so a newly recorded concurrent reference forces review.

Bind acknowledgment to the exact final quote ID/revision, canonical inputs and its warning IDs. On completion, changed monetary/line/recipe snapshots, enabled-method validity, new warnings or changed warning details require a replacement quote and renewed review. Compare semantic warning data, not freshly generated quote IDs or clock timestamps. Changes to positive stock alone do not force review if snapshots/warnings remain identical and no blocker appears. Any changed displayed shortage amounts do require review, even if improved; this conservative rule avoids ambiguity about what was acknowledged.

An expired/missing quote definitively rejects an otherwise unprocessed attempt with `quote_expired`; a confirmed attempt still replays successfully. A corrected request/new quote gets a new key only after the previous operation is definitively rejected. Stock shortages return `stock_conflict`; other material changes return `quote_changed`. Persist the rejection; include a replacement quote when it can be generated. Cache unavailability is a retryable service failure, not proof a sale failed.

Review shows named stock warnings, any duplicate-reference warning, selected payment, final total and change. A single explicit final action acknowledges the displayed warning set; changed review invalidates that acknowledgment. If an external payment was already received and the amount changes, require the owner to resolve the difference outside the app and reconfirm receipt of the new total. Do not assert that the app collected or refunded money.

### Draft storage and account lifecycle

Use an IndexedDB database `stockrage-pos`, version 1, with a `drafts` store keyed by authenticated numeric user/store IDs. One active draft per pair per browser profile; this is not cross-device draft synchronization. Each record has `schemaVersion`, `draftId`, cart lines, raw quantity buffers, minimal last-seen display snapshots, revision, editing owner/epoch and nullable original submission/terminal marker. Validate records on read; unsupported/corrupt editable records offer explicit discard, never silent success. If an unresolved envelope remains readable, preserve it for reconciliation rather than overwriting it.

Persist cart mutations before presenting them as saved. Unsubmitted tender, reference and external confirmation remain memory-only; refresh also clears these payment fields. Before network submission, atomically persist the exact original completion request/key and set submitting state. If this durable write fails, send no POST and show a storage error. Never fall back silently to an unsaved checkout. No credentials, proof images or customer information are stored.

Same-origin storage is application isolation, not encryption against someone inspecting the same browser profile. The UI only loads records for the authenticated server-provided actor/store. On logout, auth loss or actor/store change, clear in-memory cart/payment/snapshots, stop timers and invalidate request generations before another scope renders. Keep unresolved requests in their original scope. Use a logout event broadcast to clear payment state in other open POS tabs; verify the session again on focus. Never re-enable an old tab after a different account signs in.

Every POS JSON request carries `X-POS-Actor-Id`, a precondition that must equal the authenticated actor; it never grants authorization. Apply a dedicated middleware after authentication/owner checks and before parsing/processing an operation. Missing/mismatched actor returns 409 `session_changed` without registering or rejecting any attempt. Success and business-error responses echo `X-POS-Actor-Id` and `X-POS-Store-Id`; the client verifies both against the saved scope before applying a quote or terminal result. Missing/mismatched headers, including auth failures, cannot reject the old actor's attempt. A stale tab stays locked with its original recovery envelope. On focus, hide stale cart/payment data until an existing scoped catalog GET with these preconditions verifies the session; no new session endpoint is needed. Bootstrap/shared-prop actor changes also broadcast invalidation. Late valid responses from an old session may update only the original scope's durable record, never the new account's view.

Persist normal edits before allowing navigation. Store switching loads its own draft. A submitting/unknown draft can be left saved; revisiting it resumes lookup and keeps cart/payment edits locked. Explicit Cancel cart discards only an editable draft after confirmation. A failed save retains a navigation warning. Confirmed completion replaces the submitted draft with a durable terminal marker containing the original key/sale identity, clears cart/payment data, and shows the server sale. New Sale creates a fresh draft ID only after this marker is durable. On reload, a marker can look up the confirmed sale again without resubmission. Retain it until explicit New Sale; do not keep full sales indefinitely in local storage.

If a confirmation is received but the terminal marker cannot be saved, show the verified sale and storage error, disable New Sale, and keep the prior unresolved envelope for safe lookup on reload. Quarantine corrupt/unsupported records unchanged. Explicit discard is offered only when the decoder can prove the record is editable with no submitted operation; otherwise block this draft and retain diagnostic recovery instructions. Do not assume a corrupt record means no sale was submitted.

Resolve saved line IDs with a new owner-scoped JSON `POST /{current_team}/pos/draft-catalog` read-only endpoint taking `lines: CartLine[]`. Return current selected products, selected/allowed add-ons and required inventory plus per-line issues, regardless of picker pagination. Missing, disabled, foreign or no-longer-allowed selections remain visibly unresolved and require explicit correction/removal; do not silently discard or clamp. Do not reveal foreign record details. Compare current prices/recipes to saved snapshots and explain changes; quotes remain authoritative. Persist raw invalid quantity buffers too, so reload cannot replace an unfinished quantity with an old valid one.

### Tabs and submission races

Use atomic IndexedDB read/write transactions to compare draft revision and editing epoch. BroadcastChannel provides prompt invalidation; also reread on focus and before every mutation/submission, so correctness does not depend on timely messages. A missing BroadcastChannel does not permit unsafe writes.

Explicit takeover increments the editing epoch while preserving the cart. A stale tab cannot save or submit with its old epoch. The submission transaction checks ownership/revision and freezes the exact request before any network call. If takeover commits first, the old tab cannot submit; if submission commits first, takeover is refused. This prevents two tabs from creating different operation keys for the same editable draft. Closed/crashed editable tabs can be recovered by explicit takeover without a lease timeout granting authority.

An unresolved draft can be inspected and reconciled from another tab without taking edit ownership. Successful reconciliation updates the shared terminal state atomically. Explicit same-key retries may safely overlap at the server; neither may change the stored payload. After definitive rejection, an explicit takeover can reopen editing. On confirmation, all tabs stop displaying the submitted cart as editable.

### Client state and recovery

Keep client states `draft/submitting/confirmed/rejected/unknown` separate from lookup results `not_found/pending/confirmed/rejected`. Treat response loss, timeout, unexpected HTML, malformed success JSON and ambiguous authentication failures after POST as unknown. A valid 202 is pending, not failed. Only a validated response bound to the current attempt/scope can display success.

Recommended automatic lookup schedule: immediately, then at 2, 5, 10 and 20 seconds from recovery start, at most one request in flight. Apply a 10-second per-request timeout and skip elapsed slots rather than accumulating work. Pause when hidden/offline; becoming visible/online performs one reconciliation and lets the owner restart bounded checking. Stop on terminal results, auth failure, scope change or budget exhaustion. Never automatically POST. Checking may stop without declaring failure.

For `not_found`, retain the exact original request/key and offer explicit same-key retry; it may be a request that never reached the server. For `pending`, offer Check again and explicit Resume same attempt; backend serialization handles an active processor or abandoned work. A changed-payload conflict preserves the original draft and goes to lookup. Session failure asks for sign-in and keeps the unknown operation intact. Do not offer discard, editing or New Sale for the unresolved draft. A known-offline browser cannot initiate POST, but an online flag does not prove delivery.

### Screens and accessibility

Keep current desktop split layout (1024 px and above), tablet drawer (768–1023 px), and below-768 supported-screen notice. Add payment entry, final review and result components within the existing POS workflow rather than rebuilding the shell. Support long names, currency overflow limits, 44 px touch controls, keyboard focus and status announcements. On unknown state, say `Payment recording status is unknown. Do not collect payment again while checking this sale.`

Confirmed view uses the server's transaction reference, immutable lines, total, payment method/recorded amount and cash change. New Sale is explicit. No receipt link points at a missing Task 6 route; printing stays deferred and cannot be a completion gate.

## Acceptance and evidence

- Contract basket totals 41000 centavos; cash 50000 gives 9000 change. Exactly one sale/payment and six aggregate deductions; balances match the existing worked example, including negative recipe stock.
- Repeated/concurrent same-key requests, refresh during submission and commit followed by response loss all resolve one sale. Replay works after quote cache expiry and catalog changes. Changed payload conflicts without new writes.
- Kill a worker after pending registration and recover by same-key POST. Fail within posting and assert complete business rollback. Race different attempts for the last retail unit and prevent negative direct stock.
- Race identical external references and demand renewed warning review for the later sale. Preserve optional references and warning-only ingredient shortages.
- Test restore after refresh, off-page/disabled products, price/recipe changes, invalid quantity buffers, storage failure/corruption, logout/account/store separation, simultaneous takeovers and takeover-versus-submit.
- Switch from actor A to B while A's response is lost and notifications are delayed: A's lookup/retry must fail its actor precondition, must not create B's attempt, and must never apply B's rejection to A's draft. Log back in as A and reconcile its original confirmed sale.
- Verify malformed/auth/error responses never display success; known offline prevents submission; unknown state cannot clear the draft or collect again.
- Use existing Vite Plus and PHPUnit plus multi-process file-backed SQLite tests. Browser fault injection must cover a real committed response being lost and a refresh; deterministic unit tests alone do not prove this UI integration. Record actual browser capabilities and any unverified physical-device cases.
- Run focused tests and existing types/check/build, Laravel suite, PHPStan, Pint and diff checks at execution time. Historical Task 4 results are not new Task 5 verification. A release database differing from SQLite requires the same concurrency cases on that engine before claiming readiness there.

## Builder discretion

Choose small helper decompositions, exact field labels beyond the required unknown-state message, test fixtures and CSS using current patterns. No new state framework, payment SDK or browser test stack is presumed. The execution plan must pin paths/interfaces and verification cases. Any test tooling addition requires an identified gap in the installed tooling, not a parallel test suite by default.
