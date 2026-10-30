# STOCKRAGE Task 2 — Owner application shell design

Status: Draft for user review. Planning only; no product implementation authorized by this document.

Source: ../plans/2026-10-29-stockrage-frontend-action-plan.md, Task 2; ../../contracts/pos-v1.md. The six interview decisions below were confirmed on 2026-10-30. Implementation details labeled proposals are recommendations, not additional user-confirmed requirements.

## Intent and boundary

Give the provisioned owner one branded, authenticated React application with truthful navigation, reliable store context, and layouts ready for the later operating workflows. Task 2 is complete when entry, ownership boundaries, store creation/switching, branding, and shell behavior are verified. It does not deliver catalog persistence, checkout, sales, real dashboard aggregates, or draft recovery.

## Confirmed decisions

1. Disable public registration. A documented administrator command provisions the initial owner and store.
2. Owners may create additional stores and switch between stores they own. Hide staff invitations and role management in this release.
3. Preserve the Blade dashboard as a clearly labeled local development reference. /dashboard enters the authenticated React flow; the reference is not public in production.
4. Hide unfinished modules. Add POS, Catalog, Inventory, Sales, and Stock History only as their workflows become usable. Categories belong inside Catalog.
5. Checkout shell supports portrait and landscape tablets from 768 CSS pixels of viewport width. Below 768, retain management access and show a larger-screen message in place of checkout. This is a viewport rule, not device identification.
6. Provisioned owners sign in and verify their email through the existing verification link before accessing a store. Working outgoing email is a deployment prerequisite.

## Existing foundations and constraints

- Keep Laravel/Inertia/React, the existing components, Team models/relationships, currentTeam and teams shared-prop names, route parameter names, and React light/dark/system appearance infrastructure.
- EnsureTeamMembership already supports a minimum role. Business routes require auth, verified, and owner of the route's store; membership or ownership of a different store is insufficient.
- CreateTeam already assigns the creator the Owner role and switches context. Registration currently creates a personal team; preserve the internal fallback invariant during provisioning.
- Login, two-factor, passkey, and verification responses share RedirectsToCurrentTeam. Handle missing or non-owned current stores consistently in all of them.
- Existing store switching returns back and rewrites the previous URL in JavaScript. Do not carry an old store's resource ID or filters into a new store.
- Existing dashboard metrics and quick actions are placeholders; absence of data must not be displayed as zero or healthy stock.
- Retain the agreed brand: Inter; 6px base radius; 8px spacing rhythm; action red #E10D18; indicator red #FF101C; dark red #8B0000; black #111111; dark surface #1C1C1C; light gray #F5F5F5. Verify text and focus contrast on rendered backgrounds. Stock/error meaning uses text as well as color.
- Do not hand-edit generated Wayfinder files. Generate from implemented routes, then fix source imports affected by removed registration routes.
- The feature/layout checkout contains pre-existing uncommitted product and documentation changes. Preserve them; execution must inventory and carry them into any isolated checkout before work. Do not reset, discard, or commit unrelated files.

## Approaches

The confirmed choices favor extending the current shell and releasing navigation incrementally. This is the recommended implementation approach: access and entry first, store context and truthful navigation next, shared branding next, then responsive shell verification.

A parallel Blade/React production implementation conflicts with the selected single React UI. Creating placeholder business pages to populate the sidebar conflicts with hiding unfinished modules. A replacement auth/tenancy framework adds work without serving a confirmed requirement. No rewrite, new onboarding portal, or general feature-flag system is proposed.

## Proposed entry and provisioning behavior

- Guest / redirects to the branded /login page; authenticated / and /dashboard resolve a usable owned store and redirect to its React dashboard. This replaces the starter welcome screen without adding a marketing page.
- Provision through an interactive stockrage:provision-owner command: owner name, normalized email, store name, hidden password and confirmation. Validate using the existing account/store rules. Never pass or print a password in command arguments or logs.
- In one database transaction create a new unverified user and one named store with is_personal=true, Owner membership, and current_team_id. Reject duplicate email without changing that account or assigning ownership. Do not silently promote existing users.
- After commit, request the standard verification notification. If sending fails, keep the created account unverified, report the send failure, and direct the administrator to fix email and the owner to use the existing resend screen. Re-running provisioning must not create duplicates. Password delivery is an administrator's separate secure handoff; no automatic external message beyond the verification requested during actual execution.
- Resolve the current store only if still owned and active; otherwise choose the first active owned store ordered by ID. If there is none, show an authenticated no-store-access page with account settings and logout. Never create an owner role automatically to repair access.
- Existing intended URLs still go through destination authorization. No-owned-store users cannot create a store to bypass controlled provisioning. An existing verified owner can create another store using the existing action.

## Proposed store and navigation behavior

- Store lists and switchers display only owned stores, using existing shared-prop shapes. Account/security/appearance remain available independently of business access.
- After successful store switching, redirect once on the server to the selected store's dashboard; discard previous resource paths and query filters. Failed switching leaves the prior context intact. Direct authorized store URLs remain authoritative.
- Changing tabs or browser history never authorizes a route using only current_team_id. Refresh context on navigation; when POS drafts exist later, Task 5 must key/reset drafts by authenticated user and route store. Task 2 does not claim draft isolation is implemented.
- Settings keeps Profile, Security, Stores, and Appearance. Store management reuses the existing teams pages, with owner-only list/create/edit/switch and Store wording. Keep current protections on deletion; do not add deletion capabilities.
- Hide staff invitation and membership controls and pending-invitation prompts in released screens. Retain existing underlying tenancy administration code/policies; hiding these controls is not a claim that all legacy administration endpoints have been removed. No role grants access to business routes except Owner.
- Task 2 navigation contains Dashboard and Settings. Settings is active for account and store-management routes. Remove starter repository/documentation links from reachable navigation and absent-route dashboard actions.
- Parent-path matching must respect segment boundaries: /products/42/edit matches /products, but /products-old does not. Future Inventory and Stock History entries use the most specific destination so both do not become active together.
- Dashboard keeps the approved visual structure with an explicit data-unavailable state. Use an em dash and 'Not connected yet' instead of numeric metrics, fabricated trends, or stock-health assertions. Real data remains Task 7.

## Proposed layout and reference behavior

- Keep the existing management sidebar/drawer and React theme hook. Brand the shared auth layout so login, verification, reset, two-factor, and password confirmation are consistent.
- Reuse the supplied logo assets for logo/icon/favicon outputs. Retain the current font pipeline and switch its font to Inter. Do not generate a new brand or install another theme package.
- Desktop checkout layout (1024px and up): picker and cart columns with independently scrollable content and a visible cart footer. Portrait tablet (768–1023px): picker with an accessible cart sheet and persistent order-summary trigger. Task 4 supplies real product/cart content; Task 5 supplies payment behavior.
- Below 768px, render 'Checkout requires a larger screen' with 'Use a tablet or desktop with a browser window at least 768 pixels wide. You can still manage your store here.' and a dashboard link. No checkout actions should mount on unsupported widths. CSS should prevent a supported-layout flash before hydration. Resizing later must not silently discard cart state; state belongs above the layout in Task 4/5.
- Verify the checkout layout in a clearly labeled local-only, authenticated owner preview using inert example content. Do not register a production POS route or add a POS navigation link in Task 2.
- Move the Blade reference to /_dev/dashboard-reference with a conspicuous 'Development reference — sample data' label. Require local environment and authentication. Its theme toggle is also local-only and reference-scoped. Keep its files and assets; production rendering uses the React theme only.
- Register local preview endpoints with a runtime local-environment guard as well as environment-specific registration so a mistakenly copied route cache cannot expose them. Never import dev-only generated helpers into production modules.

## Acceptance and handoff

Automated coverage must prove registration is unavailable, provisioning is atomic and does not promote existing users, verification gates store access, owner/admin/member/outsider boundaries hold, switching is scoped, and production denies reference/preview routes. Focused frontend tests cover navigation selection and viewport boundaries. A browser inspection covers visible links, sign-in/verification/account flows, switching, light/dark/system, keyboard focus, touch, and 1440x900 / 1024x768 / 768x1024 / 390x844 viewports.

Preserve Task 1 contract checks. Run current type, formatting/lint, build, and relevant Laravel checks at execution time; report pre-existing failures separately. A planning review is not evidence that runtime behavior passes.

Builder discretion: exact component extraction, responsive spacing within the fixed rhythm, logo asset conversion tooling, and test fixture construction. Do not expand product scope to resolve these choices. This design and its execution plan require user review before implementation.
