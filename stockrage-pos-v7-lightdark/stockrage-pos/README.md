# Stockrage POS

The complete build of the **Takoyaki POS Step-by-Step Guide** (Parts 0 to 9): inventory + point of sale + reports.
Laravel 12 · MySQL · Blade · Tailwind · Alpine.js · Chart.js · branded with the Stockrage logo (`public/images/`).

## Version 7: light / dark mode
A sun/moon button in the sidebar (and top-right on the login page, and in the phone top bar) switches between dark and light. It is remembered per browser (localStorage `stockrage.mode`), dark is the default, and charts, logo and receipts-from-screen follow it. How it works: neutrals are CSS variables in `tailwind.config.js` (`inkDark` / `inkLight`) and `text-fg` is white in dark, near-black in light.

## Version 6: theme colors
Settings > **Theme color** lets you pick the accent: Stockrage Red (default, from the logo), Takoyaki Orange, Matcha Green, Teal, Blue, Violet or Pink. It previews live and is saved for everyone (login page and browser bar color too). Low-stock and error warnings stay red in every theme, and the logo image stays as is. To add a theme: add a palette in `tailwind.config.js` (`themes`) and a line in `app/Support/Theme.php`, then `npm run build`. Tests: `tests/Feature/ThemeTest.php`.

## Version 5: humanized sidebar

* **Plain, friendly sidebar.** No glow, gradients, icon tiles, capital-letter headings, flag stripes or Tagalog labels. Just sentence-case names (Dashboard, POS, Products, Sales history, Stock history, Reports, Settings) with the current page highlighted.
* **Says things in plain words.** Under Products it tells you "3 running low" in amber. At the bottom is a small **Today so far** card (total sales and number of sales, click it to open Reports) and a greeting with your name ("Good evening, Admin").
* **Hide menu.** The clearly labeled **Hide menu** button above the footer shrinks the sidebar to an icon rail (hover an icon to see its name); the same button then shows the menu again. It remembers your choice in this browser. On phones the menu slides in.
* **Logo without a white box.** `public/images/stockrage-logo-light.png` is your logo with the black parts ("POS" and the line) turned white, so it sits directly on the dark sidebar and login card. Printed receipts still use the original logo on white paper.
* Includes everything from version 3 (receipts, cashier name, void reason, cost and profit, product art).

## What's new in version 3

* **New collapsible sidebar.** Sections (Overview, Sell, Inventory, Insights, System), an icon tile per link with a glowing marker on the current page, and a red badge on Products showing how many items are low. Close it with the round arrow button on its edge or the "Close sidebar" row at the bottom: it shrinks to an icon rail (hover an icon to see its name) and your choice is remembered. On phones it is a slide-in menu.
* **Receipts.** After every sale the POS shows a **Print receipt** link; the receipt page is also on every sale's details page. It is made for an 80 mm receipt printer. Set the footer message in Settings.
* **Cashier name.** Every sale remembers who made it (shown in Sales History, sale details and the receipt).
* **Void reason.** Voiding asks for an optional reason; it is saved with who voided it and appears in Stock History.
* **Cost price and profit.** Each product has a *Cost per piece*. Reports show Profit (also in the CSV) and the Dashboard shows this month's profit. The cost is copied into each sale, so changing a cost later never changes old profit. The 5 sample products already have costs.
* **Product pictures.** Products without a photo show a drawn takoyaki picture instead of a letter. Upload real photos any time from the product form.

**Already ran an older version?** Just run `php artisan migrate`: it adds the new columns and keeps your data. Old sales show "-" as cashier and have no cost, so their profit equals their sales.

This is a full Laravel project (not an overlay): unzip it, install, run. Kurt can push it to GitHub as the repo from Part 0.

## Run it (Windows + Laragon; the same on Mac/Linux)

You need PHP 8.2+, Composer, Node 20.19+ (or 22) and MySQL. Laragon gives you all of them.

```bash
composer install
copy .env.example .env              # Mac/Linux: cp .env.example .env
php artisan key:generate

# Create an empty MySQL database called  takoyaki_pos
# (Laragon: Menu > MySQL > Create database). If your MySQL user/password is not root/empty, edit DB_USERNAME / DB_PASSWORD in .env.

php artisan migrate --seed          # tables + admin login + settings + 5 sample products
php artisan storage:link            # needed for product images
npm install
```

If an extracted copy contains a placeholder-only `public/storage` directory, move it aside before running `php artisan storage:link`. The link must point to `storage/app/public` for uploaded product images to load.

Then keep two terminals open (the VS Code split terminal from the guide):

```bash
npm run dev
php artisan serve
```

Open <http://127.0.0.1:8000> and log in with **admin@stockrage.test / password**.
Change the password right away (sidebar, your name, Profile).

**Demo data (Part 9):** `php artisan db:seed --class=DemoSalesSeeder` adds about 75 days of fake sales (made through the real checkout code), so the charts and reports are not empty. It leaves Octobits low and Overload sold out so the warnings show. It skips itself if sales already exist.

**Tests:** `php artisan test` runs the whole suite on in-memory SQLite, so it never touches your MySQL data. (If it complains about SQLite, enable `pdo_sqlite` in `php.ini`; Laragon has it on.)

**Teammate:** `git clone`, then the same steps above. `.env` is git-ignored, so each of you has your own.

## What is built (guide part to files)

| Guide part | What you get | Main files |
|---|---|---|
| 0 Setup | Login only (registration removed), dark layout with sidebar + logo, `@stack('scripts')`, 5 tables + models + relationships, `status` accessor, `completed` scope, seeded products, all 7 URLs behind `auth` | `routes/web.php`, `database/migrations/*`, `app/Models/*`, `resources/views/layouts/app.blade.php` |
| 1 Products & inventory | List with image and badges (green OK, orange LOW STOCK, red OUT OF STOCK), add/edit form with image upload (max 2 MB), **Add Stock** and **Adjust Stock** modals, Active/Inactive toggle, starting stock logged | `ProductController`, `StockService`, `resources/views/products/*` |
| 2 POS | Product grid + cart panel, quick cash ₱50/100/200/500 and Exact, change, Confirm disabled until paid is at least the total, zero-stock products greyed out, green/red result message, mobile "View order" bar | `resources/js/pos.js`, `pos/index.blade.php`, `SaleService`, `SaleController@store` |
| 3 Sales history | Search by sale ID, From/To date filters, pagination that keeps the filters, details page | `SaleController`, `resources/views/sales/*` |
| 4 Stock history | Every change with previous and new quantity, type badges, green `+` / red `-`, filters by product, type and date | `StockMovementController`, `resources/views/stock-history/index.blade.php` |
| 5 Dashboard | 4 cards (Low Stock turns red above 0), monthly revenue bar, 14-day trend line, best sellers, low-stock list, recent transactions. Voided sales are never counted | `DashboardController`, `resources/views/dashboard.blade.php` |
| 6 Reports | Daily / Monthly / Yearly tabs, date range, table + chart, **Export CSV** | `ReportController`, `resources/views/reports/index.blade.php` |
| 7 Void / refund | Void button with confirm dialog, stock returned, a `void` row in Stock History, removed from all totals | `SaleService::void`, `sales/show.blade.php` |
| 8 Settings | Business name (shown in the sidebar) and default low-stock threshold (pre-fills new products) | `SettingController`, `Setting` model |
| 9 Testing | The guide's test list, automated | `tests/Feature/*` |

Payment methods in the POS: Cash, GCash, Card. For GCash and Card press **Exact** to fill the total; only cash gives change.

## Rules from the guide that the code enforces (each has tests)

* **Only `StockService` changes stock.** Products are created with stock 0 and the starting stock goes through the service. `StockRuleTest` searches `app/` on every run and fails if anything else writes the stock column (the guide's Part 4 check).
* **The server never trusts the browser's total.** It recalculates from database prices, in centavos so there are no rounding errors.
* **One transaction per sale.** Not enough stock, payment too small or an inactive product means nothing is saved and no stock is deducted. Rows are locked, so two cashiers cannot oversell the same item.
* **Prices are copied into `sale_items`,** so changing a price later never rewrites history.
* **Void works once.** Voiding the same sale twice is refused, so stock is never returned twice.
* **Everything needs login,** and there is no public registration.

## Going live (Part 0 "first deployment" and Part 9 "before the demo")

1. Host with PHP 8.2+ and MySQL; point the site root at the project's `public/` folder.
2. `composer install --no-dev`, then create the production `.env` with `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Manila` and your database details.
3. **Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env` before seeding.** On a live site the seeder refuses to create the admin if the password is `password` or shorter than 8 characters.
4. `php artisan key:generate`, `php artisan migrate --force --seed`, `php artisan storage:link`.
5. `npm run build` on your laptop and upload `public/build`.
6. Run the Part 9 checklist on the **live** site, make a database backup, rehearse the 5-minute demo (dashboard, add product, POS sale, stock history, void, reports).

## Good to know

* `APP_TIMEZONE=Asia/Manila` is set in `.env.example`, so "today's sales" follows the shop's day, not UTC.
* Products with sales history can be deactivated. Products without sales history can be deleted.
* Reports and the dashboard use the same date logic, so the monthly report total equals the Monthly Revenue card.
* Logo files are in `public/images/` (`stockrage-logo.png`, `stockrage-icon.png`) plus `public/favicon.ico`. The sidebar and login use the logo variant that matches the selected mode. Printed receipts use the original logo.
* Accent palettes and light/dark neutrals are defined in `tailwind.config.js`. Available accent choices are registered in `app/Support/Theme.php`.
