# Task 3 readiness audit — completed November 2, 2026

This completes the October 31 audit plan against `feature/layout`, starting at `3f98af1`. The checkout was clean on resumption. Results apply to that commit plus the accompanying working-tree changes; no commit, merge, deployment, or working-database migration was performed.

## Scope and environment

Development readiness for the mixed cart, using PHP 8.4.27, the installed Laravel/React dependencies, SQLite, PHPUnit and Vite Plus. `bootstrap/cache/config.php` is absent. PHPUnit selects `:memory:`, array cache/mail/session, and concurrency tests create separate disposable SQLite files. Browser data lives in the ignored absolute path `C:/Users/ADMIN/Documents/GitHub/STOCKRAGEPOS/example-app/.superpowers/verification-2026-11-02/preview.sqlite`; the preview binds only `127.0.0.1:8014`, with array mail and file sessions/cache.

## Requirement-to-evidence matrix

| Parent Task 3 requirement                                                              | Implementation                                                  | Evidence                                                                                                                                                                                                                                                    | Result |
| -------------------------------------------------------------------------------------- | --------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| Searchable, filtered, paginated catalog and honest availability                        | `CatalogData`, product index                                    | `ProductTest::test_catalog_page_batches_stock_and_recipe_queries`, `Pos\CatalogTest::test_paginated_picker_filters_dependencies_and_route_store_selection`; browser shows recipe warnings, dedicated direct stock, and distinct empty-store/no-match states | Pass   |
| Persisted create/edit, normalized store-unique SKU, separate ingredient/supply records | `SaveCatalogItem`, `SaveInventoryItem`, product/inventory forms | `ProductTest::test_retail_creation_and_sku_uniqueness`, `test_runtime_price_type_and_stock_precision_validation`, `InventoryItemTest`; browser creates Audit flour and reloads it                                                                           | Pass   |
| Exact recipe components, duplicates/precision/errors                                   | `SaveRecipe`, recipe editor, `Quantity`                         | `RecipeTest::test_recipe_persists_exact_components_and_protects_dependencies`, `test_duplicate_components_and_stale_recipe_save_reject`; browser rejects packaging `0.5 each`, retains rows and associated errors, then persists `1.000 each`               | Pass   |
| Allowed direct/recipe add-ons and dependency protection                                | `SaveCatalogItem`, `CheckActiveDependents`, `StockDemand`       | `AddonTest::test_direct_addons_and_active_assignment_guard`, `CatalogLifecycleTest`; demand tests reject inactive/unassigned/foreign selections; browser reloads burger, cheese and extra-beef configuration                                                | Pass   |
| Receipts, reasoned corrections, distinguish negative stock                             | `RecordStockOperation`, stock dialog/history                    | `StockOperationTest` atomic replay/rollback/immutable snapshots; `StockConcurrencyTest`; browser receipt `0 → 10.125g`, reason-required correction `10.125 → -0.875g`, persisted actor/reason/time, stale rejection and refresh                             | Pass   |
| Real persistence, isolation, retained references, shared demand without writes         | owner middleware, store transactions, catalog/inventory actions | `Task3AccessTest`, `CatalogLifecycleTest`, demand contract-basket/overflow tests; dirty Back/Keep and canceled/confirmed store switches verified; six basket balances/revisions unchanged during preparation                                                | Pass   |

## Audit findings and resolutions

The five formatting failures named in the plan were already corrected in `3f98af1`; their diff and current checks were inspected instead of making duplicate edits. The fresh baseline exposed one quote-hook lint warning: the external-store subscription method had no explicit `this: void`. The annotation resolves the warning without changing runtime behavior.

The existing inactive-add-on test marked the parent inactive too. It now separately calls the service with an active parent and an inactive allowed add-on, asserts `lines.0.addonIds`, and snapshots unchanged inventory. Invalid-line cases now assert unchanged balances/revisions and zero operations/movements after each rejection. Existing tests cover direct water/cheese boundaries, invalid/duplicate UUIDs and selections, foreign resources, inactive dependencies, and multiplication/aggregation/projection overflow. The full contract basket asserts exact quantities and no writes. No Task 3 domain implementation defect reproduced.

## Browser walkthrough

Signed in with a factory-generated disposable owner and two owned stores. The contract products were seeded through existing domain actions; ingredient balances used fixture setup. The connected UI then created Audit flour, received `10.125g`, rejected a blank correction reason, recorded `-11g`, and reloaded the movement history. The two-tab stale-write exercise left one additional receipt (`1g`), displayed “This item changed. Reload before saving.” with entered values retained, and provided the explicit discard/reload path. Final Audit flour balance was `0.125g`, revision 4, with three operations and three movements.

Opened the persisted burger recipe, rejected `0.5 each` packaging with `aria-invalid`/`aria-describedby=product-errors`, corrected to `1`, saved and reopened as `1.000`. Browser Back inside application history opened the discard prompt; Keep retained “Unsaved audit burger”. Canceling a store switch preserved the name and left the switcher usable; confirmed discard reached the second store. The second store's POS was empty; returning to the first did not save the discarded name.

Phone recipe management at 390×844 fit within the viewport. Desktop and tablet cart/management checks and screenshots are described in the [Task 4 report](2026-10-31-task-4-mixed-cart.md). [Phone recipe screenshot](screenshots/2026-11-02/recipe-phone-dark.jpg).

## Reproduction

From `example-app`, create an empty disposable SQLite file under `.superpowers/verification-2026-11-02/`. Set process-local `DB_CONNECTION=sqlite`, `DB_DATABASE` to its absolute path, `DB_URL=''`, `MAIL_MAILER=array`, `CACHE_STORE=file`, `SESSION_DRIVER=file`, `APP_ENV=local`, and `APP_URL=http://127.0.0.1:8014`. Run `php artisan migrate --force` only with those overrides. Create a verified test owner using `User::factory()->create()` in that database and a second owned store using `CreateTeam`; use the existing catalog/inventory forms to reproduce the basket documented in `docs/contracts/pos-v1.md`. The local seed script used existing `SaveCatalogItem` actions and the same data as `StockDemandTest::test_contract_basket_aggregates_without_mutating_stock`.

Build with `npm run build`. The disposable router bootstraps `bootstrap/app.php`, calls the HTTP kernel, overrides Vite's hot file to an absent file, and serves `public` using `php -S 127.0.0.1:8014 -t public .superpowers/verification-2026-11-02/router.php`. The hot-file override is needed when an unrelated Vite development server has left `public/hot` behind. Never copy the preview DB settings into the working `.env`.

## Verification and handoff

The fresh baseline passed 130 PHP tests / 685 assertions, 32 frontend tests, TypeScript, build, PHPStan and Pint; lint failed once, then passed after the annotation. The expanded domain subset passed 31 tests / 358 assertions. Final repository gate results are recorded in the [Task 4 report](2026-10-31-task-4-mixed-cart.md), shared by both completion checks.

Task 4 receives persisted store-scoped catalog/inventory types, decimal quantity transport, revision checks, recipes/allowed add-ons and `StockDemand`'s aggregate `block`/`warn` effects. Its integrated quotes are an intentional extension beyond the original Task 3 handoff; payments remain Task 5.

Readiness is for continued development. Physical tablets/software keyboards, actual offline transport and precise browser post-commit connection loss, formal assistive-technology/contrast acceptance, other database engines, target-device performance, backups/migration rollout and configured tax treatment remain deployment/release checks. Automated stock replay/rollback/concurrency tests cover integrity; they do not substitute for hardware acceptance. No new browser framework was added.
