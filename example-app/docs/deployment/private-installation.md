# Private STOCKRAGE installation

Configure APP_URL and working outgoing email before launch. Use the existing Laravel deployment procedure, migrate the database, build assets with npm run build, and generate the production route cache in the production environment. Never copy a local route cache into production.

Run php artisan stockrage:provision-owner interactively. Supply the owner name, email, store name, and hidden password/confirmation. Share credentials through your established secure channel. Passwords have no CLI argument and are never printed.

The owner signs in and follows the verification email before store access. If delivery fails, the account remains created and unverified: repair mail configuration, sign in, and use Resend verification. Do not rerun provisioning. Duplicate normalized email is rejected and never promotes an existing user.

Only owners can open business dashboards and manage/switch their owned stores. Existing owners can create another store. Account settings remain available without store access; an administrator must repair lost ownership deliberately.

Local authenticated references: /_dev/dashboard-reference and /_dev/{owned-store-slug}/pos-shell. They contain sample data and are unavailable outside local. The latter tests layout only; no checkout submission exists.

Before deployment, verify actual email delivery and account recovery using a deployment test account, HTTPS, production route isolation, and supported hardware. Local tests use fake mail and a disposable database; no real owner was provisioned during implementation.
