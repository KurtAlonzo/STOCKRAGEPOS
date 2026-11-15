# Stockrage POS

The complete build of the **Takoyaki POS Step-by-Step Guide** (Parts 0 to 9): inventory + point of sale + reports.
Laravel 12 · MySQL · Blade · Tailwind · Alpine.js · Chart.js · branded with the Stockrage logo (`public/images/`).

## Version 18: sizes only, correct prices
- The POS shows **only the four size cards**: Solo, Barkada, Family Tray, Party Tray. The "Other flavors" section is gone.
- Tap a size and the flavors come from **Products** (the "Flavor - Size (n pcs)" products plus single-size flavors that have *Pieces per order*). **Solo = one flavor.** Barkada / Family Tray / Party Tray = one flavor or a mix of 2+.
- **Prices fixed**
  - "from ₱..." on a size card is now the cheapest price of a *whole* tray/Solo (before, it used the Classic's ₱50 per 6 pcs, so every card said ~₱50).
  - Each flavor row shows the price of the whole tray ("₱240.00 per tray").
  - One flavor in a tray is charged the **tray price**. Before, it was added as a plain item at the flavor's own price (Classic in a Family Tray cost ₱50).
  - A single-size flavor's tray price = its price per order spread over the tray's pieces, in whole pesos (Classic ₱50 per 6 pcs: Family Tray 25 pcs = ₱208).
  - Mixed trays are priced as before (each flavor's share of its own price).
- Every POS sale is now saved with a note ("Family Tray (25 pcs)") on receipts and Sale details.
- Check **Products**: a product with a wrong *Pieces per order* or price (e.g. ₱11,000 per 1 piece) will give a huge tray price here.
- **Mix pieces:** the cashier types how many pieces of each flavor (it starts as an even split, with a "Split evenly" button). The pieces must add up to the size, and each flavor needs a minimum: **Barkada 2, Family Tray 5, Party Tray 10**.
- **Discount:** Barkada **5%**, Family Tray **8%**, Party Tray **10%** (Solo none), so a bigger size is cheaper per piece. The size card says "save N%" and the flavor rows show the old price crossed out. Mixed trays are priced in whole pesos.
- Change the discounts and minimums in `config/pos.php` (0 = no discount).
- Run `php artisan test` and `npm run build` on your machine (not run in the build sandbox).

## Version 17: choose a size, tick the flavors
The POS now shows four size cards: **Solo, Barkada, Family Tray, Party Tray** (plus "Other flavors" for products with no size). Tap a size and tick the flavors. Nothing to type except how many.
- **Solo**: one flavor only (ticking another replaces it). Quantity = how many Solos.
- **Barkada / Family Tray / Party Tray**: tick one flavor for a normal tray, or tick **2 or more to mix**. The tray's pieces are split evenly and the leftover goes to the first flavors (25 pcs with 2 flavors = 13 + 12). Quantity = how many such trays.
- Tap a ticked flavor again to remove it. Change your mind about the amount with the quantity stepper (also in the order panel).
- **Mix** is already a mix, so it is a ready-made choice: ticking it clears the other flavors, and ticking another flavor clears it.
- Mixed price = each flavor's price for that size divided by its pieces, times the pieces it got (so it is not the Mix tray price). Stock: every tray used takes ONE set of each ticked flavor's own product.
- Single flavors with "Pieces per order" set (Classic, Cheese, Crab Stick, Octobits) can be ticked into trays too.
- Not verified here: `composer install` and the PHP test suite could not be run in the build sandbox (Packagist blocked); run `php artisan test` on your machine first.

## Version 15: all flavors in bundles
Single-size flavors (Classic, Cheese, Crab Stick, Octobits) can now be mixed into bundles. They need **Pieces per order** (new field in Products > Add/Edit): the seeder sets it to **6**, which is only a guess, so change it to the real number. The price per piece is the flavor's price divided by that number. Upgrade: `php artisan migrate`, then `php artisan db:seed --class=ProductSeeder` (fills the pieces for flavors that don't have it yet and changes nothing else).

## Version 14: bundles
POS > **Build a bundle**: pick a tray size (Barkada 12, Family Tray 25, Party Tray 50), then choose how many pieces of each flavor; the pieces must add up to the tray. Then **Add bundle to order**.
- **Price**: each flavor's price for that size divided by its pieces, times the pieces chosen (e.g. Family Tray: Bacon 240/25 = 9.60 per piece, Mix 13.00, Overload 11.60). Computed on the server, never trusted from the browser.
- **Stock**: each flavor used gives up one set of that size (its own "Flavor - Family Tray (25 pcs)" product). Voiding the sale puts them back.
- **Which sizes**: worked out from the product names. A size is offered when at least two flavors have it with the same number of pieces, so Solo (5 vs 6 pcs) is not offered and a new size shows up by itself.
- Receipts and Sale details show the bundle note under each flavor ("Bundle · Family Tray (25 pcs): 10 pcs"); profit uses the matching share of each flavor's cost.
- **Upgrading**: run `php artisan migrate` (adds `sale_items.note`).

## Version 13: more flavors
`ProductSeeder` now also adds the single-size flavors from the original guide: Classic Takoyaki (50), Cheese Takoyaki (60), Crab Stick Takoyaki (60) and Octobits (70), next to the 12 sized items. On the POS they appear under "Other flavors" and on Products as normal rows, each with its own picture. Already have a database? `php artisan db:seed --class=ProductSeeder` only adds what is missing. To add any other flavor yourself: Products > Add product (name it `Flavor - Size (N pcs)` to get a size row inside that flavor's section on the POS).

## Version 11: better pictures, controls under the logo
- Hide menu and the light/dark button now sit right under the logo.
- The menu pictures are redrawn to look like real takoyaki in a paper boat (bacon strips, mixed toppings, heaps of toppings). For **real photos**, drop `bacon.jpg`, `mix.jpg`, `overload.jpg` (and `classic.jpg` for other items) into `public/images/menu/` and they are used on the POS and Products pages automatically. A photo uploaded on a product (Products > Edit) always wins. See `public/images/menu/README.txt`.

## Version 10: bulk stock on Products, full menu on POS
- **Products**: every row has a tick box (and one at the top ticks the whole page). Type the **Quantity** once, add an optional note, press **Add stock to N**. Each product gets that quantity added and each one is still written to Stock History as its own "in" movement. Selecting works per page (20 products per page).
- **POS**: back to showing the whole menu at once, grouped by flavor, each item a picture card. Tap to add one; type the quantity right in the Current order panel (it is capped at the stock left).

## Version 9 (replaced by 10): tick-box POS
The POS lists the whole menu (every active product) as tick boxes under each flavor's picture. Tick what the customer wants, type the quantity **once** at the top ("Quantity for each ticked item"), then press **Add selected to cart**. "Tick all" selects every size of a flavor. Out-of-stock items can't be ticked, and if there isn't enough stock the cart gets what is left and tells you. You can still change each line's quantity in the Current order panel.

## Version 8: new menu, picture POS, sidebar fixes
- **POS**: one picture card per flavor (Bacon N' Cheese, Mix, Overload) with a Size dropdown, Quantity and Add to cart. Products named `<flavor> - <size>` are grouped automatically; any other name is its own card. Cards show an uploaded photo (Products > Edit) or a drawn placeholder per flavor.
- **Menu**: `ProductSeeder` now adds the 12 items (3 flavors x 4 sizes) with your prices, 100 opening stock and alert at 10. The cost per set is only a guess (45% of the price): set the real one in Products > Edit so Profit is right.
- **Sidebar**: Hide menu and the light/dark button are on one row above "Today so far". Settings link and the shop name under the logo are gone (the page still exists at `/settings`). Hidden/shown state is set on `<html data-sidebar>` before the page paints, so it no longer jumps when you click another page.

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
* Products are never deleted, only deactivated, because they have sales and stock history attached.
* Reports and the dashboard use the same date logic, so the monthly report total equals the Monthly Revenue card.
* Logo files are in `public/images/` (`stockrage-logo.png`, `stockrage-icon.png`) plus `public/favicon.ico`. The sidebar shows the logo on a white panel because the logo has black parts.
* The brand colors (logo red as a `brand` scale, near-black `ink` neutrals) are defined once in `tailwind.config.js`, so changing the theme is a one-file edit.
