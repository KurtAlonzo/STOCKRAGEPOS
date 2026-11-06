# STOCKRAGE Task 2 — Application Shell Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Use superpowers:subagent-driven-development only when delegation is authorized. Steps use checkbox (- [ ]) syntax for tracking. This is a planning deliverable, not authorization to implement.

**Goal:** Deliver one honest, branded React shell with controlled owner setup, owner-scoped store access, usable navigation, and desktop/tablet/phone layout boundaries.

**Architecture:** Extend existing Laravel/Fortify/Inertia/React code. Reuse Team ownership and shared-prop shapes, enforce access on the server, and use generated Wayfinder helpers. Keep both legacy Blade reference and inert checkout-shell previews local-only; business workflows are later tasks.

**Tech Stack:** Existing Laravel 13, Fortify, Inertia 3, React 19, TypeScript, Tailwind 4, Radix/shadcn, Vite Plus, Wayfinder, PHPUnit. No new runtime framework or theme store.

**Spec:** [Task 2 design](../specs/2026-10-30-stockrage-task-2-shell-design.md); [parent plan, Task 2](2026-10-29-stockrage-frontend-action-plan.md); [accepted Task 1 contract](../../contracts/pos-v1.md).

**Status:** Implemented with local verification, 2026-10-30. See ../../verification/2026-10-30-task-2-shell.md for evidence and deployment/hardware checks remaining. Execution was authorized in chat.

## Global constraints

- Disable public registration. A documented administrator command provisions the initial owner and store.
- Owners may create additional stores and switch between stores they own. Hide staff invitations and role management in this release.
- Preserve the Blade dashboard as a clearly labeled local development reference. /dashboard enters the authenticated React flow; the reference is not public in production.
- Hide unfinished modules. Add POS, Catalog, Inventory, Sales, and Stock History only as their workflows become usable. Categories belong inside Catalog.
- Checkout shell supports portrait and landscape tablets from 768 CSS pixels of viewport width. Below 768, retain management access and show a larger-screen message in place of checkout. This is a viewport rule, not device identification.
- Provisioned owners sign in and verify their email through the existing verification link before accessing a store. Working outgoing email is a deployment prerequisite.
- Use Inter; 6px base radius; 8px spacing rhythm; action red #E10D18; indicator red #FF101C; dark red #8B0000; black #111111; dark surface #1C1C1C; light gray #F5F5F5. Keep existing light/dark/system support and verify contrast.
- Keep tenancy identifiers and shared props unchanged internally. Use Store wording in the business interface. Frontend visibility is never the authorization boundary.
- Generate Wayfinder files from real routes; never hand-edit generated output. No production POS/catalog/inventory/sales placeholder routes.
- Preserve pre-existing edits. All paths below are relative to example-app/; run application commands there. Use explicit paths when staging, never git add . in this dirty checkout.

## Review focus

1. Registration disabled but stale imports or direct POST still work: test both registration URLs and build after regeneration (2.1).
2. Owner loses access to the current store, or owns A but is a member of B: reject B and choose only an owned fallback; never grant a role to recover access (2.2).
3. Switching from a resource/detail URL or a failed switch reuses old context: one server redirect to the new dashboard, no old path/query, failed requests leave context intact (2.2–2.3).
4. Preview routes or theme endpoint survive production route caching: runtime local-environment check plus production-denial coverage (2.2, 2.5).
5. Viewport crosses 767/768 or 1023/1024, or system theme changes: correct shell, accessible cart controls, no hydration flash or data-loss ownership in the layout (2.4–2.5).

## Before execution

- [ ] Inspect git status/diffs and applicable AGENTS.md files. Existing feature/layout changes include shell, branding, dashboard, routes, and Task 1 documents. Record the baseline; do not overwrite it or assume it is committed.
- [ ] Follow the execution-time worktree skill if isolation is needed. An isolated checkout must include the required uncommitted baseline before implementation. Otherwise work surgically in the existing checkout under the existing session authorization. Do not silently choose a clean HEAD that loses the approved visuals.
- [ ] Run npm run test:contracts, npm run types:check, npm run check, and php artisan test; record existing failures. The Task 1 ledger records historical formatting failures, not current test results. Confirm the SQLite in-memory test environment in phpunit.xml before database tests.
- [ ] Inspect the original Login, Design System, Dashboard Light/Dark, Tablet, Mobile, and POS Light/Dark PNGs under ../context and md files/UI DESIGN/STOCKRAGE POS — UI Design-png/. Do not infer exact layouts solely from prose.

## File responsibilities

| Area                | Files to create or modify                                                                                                                                                                                                                                                                                                             |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Controlled setup    | Create app/Console/Commands/ProvisionOwner.php and tests/Feature/Console/ProvisionOwnerTest.php; modify config/fortify.php and tests/Feature/Auth/RegistrationTest.php; create docs/deployment/private-installation.md                                                                                                                |
| Entry and access    | Create app/Http/Controllers/EntryController.php, app/Actions/Teams/ResolveOwnedTeam.php, resources/js/pages/no-store-access.tsx, tests/Feature/ShellAccessTest.php; modify routes/web.php, routes/settings.php, app/Http/Responses/Concerns/RedirectsToCurrentTeam.php, app/Http/Middleware/SetTeamUrlDefaults.php                    |
| Store context       | Modify app/Http/Controllers/Teams/TeamController.php, app/Http/Middleware/HandleInertiaRequests.php, resources/js/components/team-switcher.tsx, pages/teams/index.tsx, pages/teams/edit.tsx, components/create-team-modal.tsx, components/delete-team-modal.tsx, layouts/settings/layout.tsx; extend tests/Feature/Teams/TeamTest.php |
| Truthful shell      | Create resources/js/lib/shell-navigation.ts and shell-navigation.test.ts, resources/js/lib/navigation-path.ts and navigation-path.test.ts; modify components/app-sidebar.tsx, app-header.tsx, nav-main.tsx, hooks/use-current-url.ts, types/navigation.ts, pages/dashboard.tsx and app/Http/Controllers/DashboardController.php       |
| Entry/auth sources  | Modify resources/js/pages/auth/login.tsx, app/Providers/FortifyServiceProvider.php and resources/js/app.tsx; retire resources/js/pages/welcome.tsx and pages/auth/register.tsx once their routes are removed; retain unrelated auth functionality                                                                                     |
| Brand               | Modify resources/css/app.css, vite.config.ts, resources/js/components/app-logo.tsx, app-logo-icon.tsx, layouts/auth/auth-simple-layout.tsx, resources/views/app.blade.php, public/favicon.svg, favicon.ico, apple-touch-icon.png; reuse public/images/stockrage-logo.png and stockrage-logo-text.png                                  |
| Local references    | Modify resources/views/dashboard/index.blade.php and resources/views/layouts/main.blade.php; use routes/web.php for reference routes; create app/Http/Middleware/EnsureLocalEnvironment.php                                                                                                                                           |
| Checkout shell      | Create resources/js/layouts/pos-layout.tsx, components/pos/checkout-screen-notice.tsx, hooks/use-checkout-viewport.ts, lib/pos/viewport.ts, lib/pos/viewport.test.ts, pages/dev/pos-shell.tsx, tests/Feature/DevelopmentPreviewTest.php                                                                                               |
| Verification record | Create docs/verification/2026-10-30-task-2-shell.md; update docs/STOCKRAGE-POS-PROGRESS-SUMMARY.md and the parent Task 2 checkboxes only after verified implementation                                                                                                                                                                |

Do not modify every listed file mechanically. Each task identifies the behavior it owns. Retire unused source files only after checking imports; assets shared with the Blade reference must remain.

## Task 2.1: Controlled provisioning and closed registration

**Deliverable:** A new owner can be provisioned deliberately, receive verification, and sign in; public requests cannot create accounts.

**Files:** Controlled setup and entry/auth sources from the table; existing app/Actions/Teams/CreateTeam.php and validation concerns are dependencies, not rewrite targets.

**Interfaces:** ProvisionOwner::handle(): int exposes the interactive command stockrage:provision-owner. It consumes CreateTeam::handle(User $user, string $name, bool $isPersonal = false): Team. Success creates one unverified user, one named personal store, Owner membership, and selected store. No schema change.

- [ ] Write ProvisionOwnerTest assertions: valid inputs create exactly one user/store/membership; password is hashed and never in console output; email_verified_at is null; is_personal is true; selected store matches; standard verification notification is dispatched after persistence.
- [ ] Add duplicate normalized email, invalid email/store name, weak password, and mismatched confirmation cases: command fails with no new records and no change to existing roles. Force store-creation failure and assert the user transaction rolls back. Simulate notification transport failure: persisted account remains unverified, command reports the failure and recovery path, and repeat provisioning cannot duplicate it.
- [ ] Replace obsolete RegistrationTest expectations with GET /register and POST /register denial (404), including requests containing invitation query parameters. Assert no user/store count change. Use literal URLs because removed route names must not be invoked in tests.

    // RegistrationTest::test_public_registration_is_unavailable
    $this->get('/register')->assertNotFound();
    $this->post('/register', $validRegistration)->assertNotFound();
    $this->assertDatabaseCount('users', 0);

- [ ] Run php artisan test --filter='ProvisionOwnerTest|RegistrationTest'; observe failures for missing command/open registration before implementing.
- [ ] Implement the command with prompted name/email/store name and hidden password/confirmation. Reuse ProfileValidationRules, PasswordValidationRules, and TeamName validation. Normalize email before unique validation. Wrap user/store creation in DB::transaction; dispatch verification after commit and handle email-send failure separately. No password argument, automatic promotion, or verification bypass option.
- [ ] Remove Features::registration() and obsolete registration view/bindings as appropriate. Remove signup and invitation prompts from login; retire the unreachable auth/register source. Remove the registration import/link from welcome but keep that page working until Task 2.2 replaces its route. Keep reset, verification/resend, two-factor, passkeys, throttling, and password-confirmation behavior; passkey registration is unrelated to public account registration.
- [ ] Run php artisan wayfinder:generate --with-form, then npm run types:check; resolve stale source imports rather than restoring public routes. Run the focused PHP tests again; expect all pass.
- [ ] Write private-installation.md with exact interactive command, outgoing-email/APP_URL prerequisites, secure credential handoff, verification and resend, duplicate-account refusal, mail-failure recovery, and production route-cache/build steps. Do not send real email or provision production users as part of automated verification.
- [ ] Commit only this task's reviewed files and required generated artifacts under the repository's existing generated-file tracking policy. Suggested message: feat: add controlled owner provisioning.

## Task 2.2: One React entry flow and owner-scoped store context

**Deliverable:** Entry, dashboard, store creation/management, and switching enforce the agreed access rules; the Blade preview is local-only.

**Files:** Entry/access, store-context backend, local reference files, tests/Feature/DashboardTest.php, and affected existing authentication/Teams tests.

**Interfaces:** ResolveOwnedTeam::handle(User $user): ?Team returns the active owned current store, else the active owned store with smallest ID, else null. It never creates membership. EntryController::__invoke(Request $request): RedirectResponse sends guests to login and verified owners to dashboard; authenticated no-owner users reach no-store-access. Existing route name dashboard remains /{current_team}/dashboard; add entry for /dashboard and no-store-access for /no-store-access. Keep home for /.

- [ ] Write ShellAccessTest using explicit store route parameters: guests redirect to login; unverified owners redirect to verification; verified owner succeeds; admin/member/outsider receive 403 for a known inaccessible store. Include a user owning A but only belonging to B. Denied requests must not switch current_team_id. Missing store slug may return 404; never reveal another store's data.

    // ShellAccessTest::test_owning_a_does_not_authorize_member_store_b
    $this->actingAs($ownerOfA)->get(route('dashboard', ['current_team' => $storeB->slug]))->assertForbidden();
    $this->assertEquals($storeA->id, $ownerOfA->fresh()->current_team_id);

- [ ] Add entry tests for guest /, authenticated / and /dashboard, null/deleted/non-owned current store with owned fallback, and no owned store. Assert no role creation; no-access page exposes account settings/logout, no business links. Exercise common redirect resolution through password login, two-factor, passkey, and email-verification response tests using existing test patterns.
- [ ] Extend TeamTest: only verified users already owning an active store may create another; it assigns Owner to the creator and selects the new store. Store list/edit/update/switch must reject nonowners, including an Admin. Switching from an old detail URL plus filters lands at the target dashboard without old query/path; failed switch preserves current store. Owned store rename refreshes the slug and context.
- [ ] Run php artisan test --filter='ShellAccessTest|DashboardTest|TeamTest|AuthenticationTest|TwoFactorChallengeTest|EmailVerificationTest'; record expected behavior failures.
- [ ] Implement ResolveOwnedTeam and entry/no-access routes. Guest home redirects to login; authenticated entry requires verification before resolving the dashboard. Once home no longer renders welcome, retire welcome.tsx and its layout special case. Reuse EnsureTeamMembership with the owner minimum for dashboard and scoped store-management routes; authorize store creation from an existing owned-store relationship. Keep account profile/security/appearance routes reachable under their existing auth requirements.
- [ ] Wire shared redirect resolution into RedirectsToCurrentTeam without breaking the existing /dashboard Fortify home suffix. Apply owned-store checks to URL defaults/shared props so revoked or unrelated store context cannot leak into navigation. Serialize teams from ownedTeams and currentTeam only when owned, retaining existing names and DTO shapes.
- [ ] Change successful TeamController::switch to return the target dashboard redirect. In the switcher, send one generated switch request with no URL string replacement, no follow-up reload, and no preserved stale page state; disable repeated selection while pending. Context comes from the server response. For create/edit store responses keep existing route names and correct updated slugs.
- [ ] Add EnsureLocalEnvironment::handle(Request $request, Closure $next): Response with non-local 404. Register /_dev/dashboard-reference and /_dev/dashboard-reference/theme only locally, behind auth/verified and the runtime guard. Preserve route names dashboard.preview and theme.toggle where needed by reference files. Label reference sample data; remove old public /theme/toggle and repurpose /dashboard as entry.
- [ ] Update obsolete tests to match owner-only business access and hidden invitation prompts; retain tests for the existing underlying tenancy policies. Staff administration endpoints are not redesigned or claimed removed in Task 2. Generate helpers and run all focused tests again; expect pass and no auth regressions.
- [ ] Commit the reviewed task files. Suggested message: feat: enforce owner store access and React entry.

## Task 2.3: Truthful navigation and Store terminology

**Deliverable:** Only usable links are visible, parent selection is correct, and store management has owner-facing wording.

**Files:** Truthful shell and store-context frontend files from the table. Modify DashboardController/DashboardTest to remove pending-invitation props from the released dashboard. Use existing user menu and settings routes.

**Interfaces:** getShellNavItems(currentTeam: Team | null): NavItem[] returns owner Dashboard plus Settings, or only Settings without a store. Extend NavItem with matchPaths?: string[]. matchesNavigationPath(currentUrl: string, parentUrl: string): boolean normalizes URL paths and checks equality or slash-delimited descendants. getActiveNavIndex(currentUrl: string, items: NavItem[]): number selects the longest matching path; -1 when none. Export the matching functions from navigation-path.ts and delegate use-current-url's parent matching to them.

- [ ] Write navigation tests with exact expected titles ['Dashboard', 'Settings'] for an owner and ['Settings'] for null/nonowner input. No POS, Products, Categories, repository, or documentation entries. Dashboard href includes the selected store slug; Settings uses the generated profile route.
- [ ] Test matching /store/products/42/edit against /store/products, reject /store/products-old, ignore query/hash/trailing slash, handle generated route URL forms through toUrl, and select Stock History over Inventory for nested /inventory/history. Settings must select for /settings/profile, /settings/security, /settings/appearance, and /settings/teams/<slug>.

    // navigation-path.test.ts: 'matches segments, not prefixes'
    expect(matchesNavigationPath('/s/products/42/edit', '/s/products')).toBe(true);
    expect(matchesNavigationPath('/s/products-old', '/s/products')).toBe(false);

- [ ] Run npm exec -- vp test run resources/js/lib/shell-navigation.test.ts resources/js/lib/navigation-path.test.ts; observe missing-export/behavior failures.
- [ ] Implement the small helpers and reuse them in sidebar/header/nav selection. Omit future destinations entirely instead of fake routes or a capability framework. Remove starter footer/header links and absent-route dashboard quick actions. Use generated helpers for working routes.
- [ ] Update switcher, creation modal, Stores list/edit, settings navigation, headings, toasts, accessible labels, and delete confirmation copy to Store wording. Remove invitations, role controls, leave-team controls, and pending-invitation UI from released surfaces. Keep internal models/types/routes named Team. Retain existing protections on deletion and existing account/security controls.
- [ ] Replace hardcoded dashboard zeroes, dollar amounts, trends, and healthy-stock claims with '—' and 'Not connected yet'. Preserve the visual layout; do not add aggregate queries or sales fixtures to production. Remove pending-invitation props/query from DashboardController and revise its corresponding tests; unchanged tenancy invitation controller tests remain relevant.
- [ ] Rerun focused frontend tests, php artisan test --filter='DashboardTest|TeamTest|ShellAccessTest', and npm run types:check. In the browser inspect every visible navigation/action link from dashboard, Settings, and Stores; each reaches a working route appropriate to the user.
- [ ] Commit only reviewed task files. Suggested message: feat: make shell navigation truthful and store-focused.

## Task 2.4: Shared STOCKRAGE branding and auth experience

**Deliverable:** Entry, account pages, and management shell use the supplied identity with consistent light/dark/system appearance.

**Files:** Brand group from the table plus resources/js/app.tsx and login copy. Keep existing use-appearance.ts as the appearance authority.

**Interfaces:** AppLogo and AppLogoIcon retain their existing call signatures. Shared auth layout continues accepting title, description, and children. No new persisted theme state.

- [ ] Inspect current logo assets and mockups. Use existing icon/wordmark assets with legible sizing and a single accessible name; produce favicon/icon sizes from those assets. Preserve assets referenced by the local Blade preview.
- [ ] Switch the existing Vite font provider from Instrument Sans to Inter with 400/500/600/700 weights and update the CSS fallback stack. Set the base radius to 0.375rem. Apply the exact palette from Global constraints with semantic surface/text/action/error tokens; do not globally round every element identically or use red alone to distinguish destructive actions.
- [ ] Brand shared auth layout, login copy ('Sign in to STOCKRAGE'), app title fallback, favicon links, and loading progress. Guest / leads to login under Task 2.2. Keep forgotten-password, validation, verification/resend, two-factor, and passkey controls functional. Remove fixed positive tabIndex values in touched login markup in favor of DOM order.
- [ ] Align the initial Blade app document background with the React theme tokens to avoid a mismatched first paint. Keep the existing cookie/system initialization path. Local reference styling remains isolated.
- [ ] Run npm run types:check and npm run build. Inspect light, dark, and system modes on login, verification, dashboard, and Settings; change the OS/browser preference while system mode is active. Check contrast, focus, error labels, logo visibility, and a reload without theme flash. Document observed results, not assumed accessibility.
- [ ] Commit the reviewed task files. Suggested message: style: align application and auth branding.

## Task 2.5: Responsive management and reusable checkout shell

**Deliverable:** Management works on phones; a local-only preview proves desktop and portrait-tablet checkout layout slots without a working sale flow.

**Files:** Checkout-shell group, routes/web.php, resources/js/app.tsx; adjust app-sidebar-layout.tsx, app-sidebar-header.tsx and existing sidebar/drawer styles only where viewport inspection requires it.

**Interfaces:** getCheckoutViewport(width: number): 'unsupported' | 'portrait' | 'wide' uses <768, 768–1023, >=1024. useCheckoutViewport(): that union | 'pending' subscribes to media queries, using pending for SSR/hydration. PosLayout({picker, cart, summary, footer}: {picker: ReactNode; cart: ReactNode; summary: ReactNode; footer: ReactNode}) owns layout and cart-sheet disclosure only, not cart contents, totals, payment, storage, or recovery. CheckoutScreenNotice({dashboardHref: string}) supplies the agreed phone message/link.

- [ ] Write viewport.test.ts cases at 390, 767, 768, 1023, 1024, and 1440 pixels. Write DevelopmentPreviewTest asserting production/staging/testing deny dev routes, local guest requires login, local nonowner cannot access another store's shell, and authorized local owner sees an explicit preview label. Include a direct middleware test of non-local denial for a route registered before environment change, modeling a stale route cache.

    // viewport.test.ts: 'enforces the confirmed viewport boundaries'
    expect(getCheckoutViewport(767)).toBe('unsupported');
    expect(getCheckoutViewport(768)).toBe('portrait');
    expect(getCheckoutViewport(1023)).toBe('portrait');
    expect(getCheckoutViewport(1024)).toBe('wide');

- [ ] Run npm exec -- vp test run resources/js/lib/pos/viewport.test.ts and php artisan test --filter=DevelopmentPreviewTest; observe expected missing-route/helper failures.
- [ ] Implement viewport helper/hook with cleanup and the pending hydration state. While pending, render an inert shell, not actionable checkout controls. At unsupported width render only the screen notice and management return link. CSS protects first paint; do not infer device type from user-agent.
- [ ] Implement desktop picker/cart columns with independent scroll areas and visible cart footer. For 768–1023px, use the existing accessible Sheet for cart content and a persistent summary trigger; retain visible footer inside the sheet. Restore focus to the trigger on close; support Escape, touch, and keyboard. Cart state must live above PosLayout in later tasks so switching layout cannot lose it.
- [ ] Register /_dev/{current_team}/pos-shell locally with EnsureLocalEnvironment, auth, verified, and owner membership. Create dev/pos-shell with visibly labeled inert sample content, long product names and enough rows to test scrolling; no completion/payment request. Route this page to the dedicated layout in app.tsx without nesting the management shell. Do not add a production /pos route or POS sidebar item.
- [ ] Verify the phone management drawer, Stores form/list, and Settings at 390x844 with no page-wide horizontal overflow. Inspect checkout preview at 1440x900, 1024x768, and 768x1024; cross both breakpoints, test browser zoom/on-screen keyboard, sheet focus, long text and scrolling. Ensure preview URLs are inaccessible outside local even after cached-route simulation.
- [ ] Rerun focused tests and npm run types:check. Commit reviewed files. Suggested message: feat: add responsive management and checkout shell.

## Task 2.6: Integration verification and honest handoff

**Deliverable:** Repeatable evidence that Task 2 works; later workflows remain explicitly incomplete.

**Files:** Verification record, private-installation.md, progress summary, and parent Task 2 checkbox section. Fix only failures attributable to this scope unless separately authorized.

- [ ] Run php artisan route:list --except-vendor and inspect registration removal, owner middleware, entry, and local previews. Generate helpers with php artisan wayfinder:generate --with-form. Test the production route-cache/build process in a disposable environment using production configuration and a test database; never point it at live data. No production import may depend on a dev-only generated helper.
- [ ] Run php artisan test, composer lint:check, composer types:check, npm run test:contracts, npm exec -- vp test run resources/js/lib/shell-navigation.test.ts resources/js/lib/navigation-path.test.ts resources/js/lib/pos/viewport.test.ts, npm run types:check, npm run check, and npm run build. Passing means exit 0 with all selected tests successful; record actual counts. Classify baseline failures precisely and fix new regressions. Do not blanket-format user-owned changes.
- [ ] Complete one local browser journey with non-production users: provision with fake/local mail transport, sign in, hit verification gate, follow verification link, open dashboard, create a second store, switch stores, refresh, and open a direct forbidden store URL. Verify account reset/security/appearance links and logout. Use a real outgoing-email delivery check only during deployment with explicit operational authorization.
- [ ] Inspect the four specified viewport sizes in light/dark and one system-theme change. Check navigation destinations, visible active state, no fake metrics, no starter links, no staff controls, no checkout controls below 768px, keyboard/focus order, form error association, at least 44px primary touch targets, long store names, and slow-switch/loading/failure states. Record screenshots or concrete observations in the verification document. Do not add a second browser-test framework solely for this shell pass.
- [ ] Record the remaining integration obligations: Task 3 owner-scoped resource policies and usable Catalog/Inventory routes; Task 4 cart logic/content; Task 5 owner/store draft keys, logout/store-switch reset, conflicting tabs and attempt recovery; Task 7 real metrics. Task 2 verification cannot claim those absent workflows are secure or complete.
- [ ] Update parent Task 2 checkboxes only where evidence exists. Link the verification record and list any blocked checks. Update the progress summary to distinguish implemented shell, local preview, and absent business workflows. Commit only reviewed task-owned changes.

## Completion criteria and execution recommendation

Task 2 is complete when registration/provisioning, email verification, owned-store access and switching, truthful navigation, branding/theme, local-reference isolation, and the four viewport checks all meet the spec. A successful build alone is insufficient. No public business module should be enabled merely to demonstrate layout.

Recommended execution: sequential work in dependency order 2.1 → 2.2 → 2.3 → 2.4 → 2.5 → 2.6, because routes, generated helpers, and shared shell files overlap. Use one final independent review if the chosen execution workflow requires it. Delegated implementation is an alternative only if the user chooses it; do not parallelize edits to shared routes/shell files.

Planning validation: checked against all seven parent Task 2 bullets and its proof conditions. Owner access for future business endpoints and draft safety are explicitly carried forward, not marked implemented. The design received a read-only builder check with no important unanswered questions. No application test/build command was run as part of authoring this plan.
