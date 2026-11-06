# SDD ledger — plan: docs/superpowers/plans/2026-10-30-stockrage-task-3-execution-plan.md

Baseline: feature/layout, tracked working tree clean; only Task 3 plan/design untracked. PHP 97 tests/313 assertions passed. User explicitly selected existing checkout and preservation of Tasks 1/2.

Ruling: implement in the authorized existing checkout, leaving changes uncommitted for review; no reset/discard or unrelated staging.

Preflight interfaces: 3.1 inventory/revisions feed 3.2 movements; 3.3 catalog owns direct inventory; 3.4 recipes and 3.5 add-ons share transactional dependency validation and exact quantities. Contract amendments applied with consuming workflows.

Baseline frontend: 16 tests, TypeScript, PHPStan and build passed. Full check failed on existing Documentation, Progress Summary, parent plan and public CSS, plus the newly authored execution-plan formatting; format only owned files.

Ruling: keep input validation in focused domain actions rather than creating request classes that only forward the same rules — all public writes still validate before persistence, and foreign IDs are scoped before use. Cost: actions contain validation and persistence together.

Ruling: stock operations use the common store transaction boundary as well as item compare-and-swap. A real two-process SQLite test exposed an unhandled busy error in DB::transaction retries; bounded whole-transaction retry fixed it. Cost: stock writes within a store serialize on row-locking databases, appropriate to this owner-only release.

Ruling: browser-history protection needs an early popstate dispatcher and namespaced entry indexes, installed before Inertia initialization. Accessible discard dialogs guard history, store switching and form dismissal; native beforeunload remains for refresh/cross-document exits. Cost: small shared history integration, verified against existing shell and in a live browser.

Task 3.1: implemented; RED missing parser/routes → GREEN inventory and exact-quantity tests; editable metadata, scoped lists, optional thresholds, filters, dirty dialog guard.
Task 3.2: implemented; RED missing stock routes → GREEN receipts, negative-correction rules, stale replay, forced rollback, immutable snapshots, competing SQLite writers; per-item movement panel.
Task 3.3: implemented; RED missing catalog → GREEN automatic stock, SKU/price validation, foreign-ID rejection, categories and active-dependent protection. Price parser RED→GREEN including safe-integer boundary.
Task 3.4: implemented; RED unsupported food → GREEN exact persisted recipes, duplicate components, stale revision and dependency/reactivation tests. Browser rejected 0.5 each, retained rows, saved 1 each and reopened it as 1.000.
Task 3.5: implemented; RED unsupported add-ons/missing demand service → GREEN direct/recipe add-ons and contract shared-component basket, without writes. Query-count regression RED 127 → GREEN <=12 domain queries for 25 retail rows.
Task 3.6: complete with local verification; PHP 120 tests / 523 assertions, frontend 20 tests / 7 files, TypeScript, PHPStan, scoped Pint, scoped frontend lint/format and build passed. Full-repository formatting failures remain documented in the evidence. Independent review performed and findings addressed below. No product code committed; user review can inspect the complete working tree.

Final review fixes: active-filter transport RED→GREEN test; store-switch busy state now starts only after navigation is approved; Back/Forward dirty guard added with unit history-marker checks and live Back cancellation preserving the typed name; uncertain stock writes require reload; contract amendments and accessible field-error associations added. Activation/deactivation concurrency test passes using separate SQLite processes. No deferred reviewer findings.

Live browser: isolated SQLite preview and fake mail only. Created Packaging boxes; received 10 each and verified movement; preserved/rejected fractional recipe row; saved/reopened recipe; combined active/type/search filters; canceled browser Back and store switch preserving input; explicitly discarded and switched stores. Inspected 1440x900, 1024x768, 768x1024, 390x844, with dark recipe and light phone inventory. No page-wide horizontal overflow in measured views.

Final preservation check: branch remains feature/layout; only Task 3 additions and necessary contract/navigation/history integration changed. Existing Task 1/2 regression tests pass. Preview tabs closed and viewport override reset; isolated test artifacts retained under ignored .superpowers/task3. No working/production database migration, commit, merge, push or deployment performed.
