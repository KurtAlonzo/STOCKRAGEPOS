# STOCKRAGE POS - Comprehensive Documentation

*Last Updated: October 28, 2026*  
*Project: STOCKRAGE POS*  
*Built on: Laravel 13 + Inertia.js v3 + React 19 + TypeScript + Tailwind CSS v4*

---

## Table of Contents

- [1. Overview](#1-overview)
- [2. Overall Functionality & Features](#2-overall-functionality--features)
- [3. UI/UX Design](#3-uiux-design)
- [4. Design Approach](#4-design-approach)
- [5. System Architecture](#5-system-architecture)
- [6. Frontend Approach](#6-frontend-approach)
- [7. Backend Approach](#7-backend-approach)
- [8. Tech Stack Alignment & Cohesion Plan](#8-tech-stack-alignment--cohesion-plan)
- [9. Implementation Roadmap](#9-implementation-roadmap)
- [10. File Structure & Conventions](#10-file-structure--conventions)
- [11. Branding & Assets](#11-branding--assets)

---

## 1. Overview

STOCKRAGE POS is a modern, full-featured Point of Sale system designed for retail businesses. It provides end-to-end management of sales transactions, inventory, products, customers, suppliers, and business operations. Built as an extension of the existing Laravel React Starter Kit, it leverages the pre-built authentication, team management, and UI foundation while adding comprehensive POS functionality.

### Core Principles
- **Speed-Focused**: Optimized for fast checkout operations at cashier terminals
- **Inventory-Aware**: Real-time stock tracking integrated with every sale
- **Role-Based**: Granular permissions leveraging the existing Teams/RBAC system
- **Modern UX**: Clean, intuitive interface inspired by the STOCKRAGE design system
- **Scalable**: Built to grow from single store to multi-store operations

---

## 2. Overall Functionality & Features

Based on the design mockups and chosen scope (Full Retail Suite), the system will include the following modules:

### 2.1 Point of Sale (POS) / Checkout
- **Product Search & Selection**: Quick search by name, SKU, barcode with category filtering
- **Shopping Cart**: Add/remove items, adjust quantities, apply discounts per item or cart-wide
- **Customer Management**: Optional customer assignment to sales, walk-in customer support
- **Payment Processing**: Multiple payment methods (Cash, Card, Split Payment, etc.)
- **Receipt Generation**: Print/email receipts with store details, items, taxes, totals
- **Hold/Recall Transactions**: Save incomplete transactions for later completion
- **Quick Keys**: Keyboard shortcuts for common operations

### 2.2 Product & Catalog Management
- **Product CRUD**: Create/edit products with name, SKU, barcode, price, cost, category, images
- **Categories**: Organize products into hierarchical categories
- **Product Variants**: Support for size/color/variant products (if needed)
- **Pricing**: Sale price, regular price, bulk pricing support
- **Product Status**: Active/inactive, trackable inventory flags

### 2.3 Inventory Management
- **Stock Tracking**: Real-time inventory levels per product
- **Stock Adjustments**: Manual adjustments with reasons (damaged, lost, found, corrections)
- **Low Stock Alerts**: Notifications for products below reorder threshold
- **Stock History**: Complete audit trail of all stock movements
- **Batch/Expiry Tracking**: Optional support for perishable goods
- **Reorder Points**: Configurable minimum stock levels

### 2.4 Sales & Transaction Management
- **Sales History**: Comprehensive list of all transactions with filters (date, cashier, payment method)
- **Transaction Details**: View full transaction breakdown, items, payments, customer info
- **Returns/Refunds**: Process full or partial returns with reason codes
- **Void Transactions**: Authorized voiding with audit trail
- **Sales Reports**: Daily, weekly, monthly sales summaries
- **Receipt Reprinting**: Reprint receipts for past transactions

### 2.5 Customer Management
- **Customer Database**: Store customer profiles with contact details
- **Purchase History**: Track customer purchase history and spending
- **Loyalty/Rewards**: Foundation for future loyalty program
- **Customer Groups**: Segment customers for targeted pricing/promotions

### 2.6 Suppliers & Purchases
- **Supplier Management**: Maintain supplier contact information and terms
- **Purchase Orders**: Create, track, and receive purchase orders
- **Goods Receiving**: Update inventory when receiving stock from suppliers
- **Purchase History**: Track all purchases and outstanding payables
- **Supplier Payments**: Track payments to suppliers

### 2.7 Expenses Management
- **Expense Categories**: Categorize business expenses
- **Expense Tracking**: Record expenses with receipts, dates, vendors
- **Expense Reports**: Analyze expenses over time
- **Recurring Expenses**: Support for recurring business costs

### 2.8 Promotions & Discounts
- **Discount Types**: Percentage or fixed amount discounts
- **Promo Codes**: Create and manage promotional codes
- **Time-Based Promotions**: Schedule promotions with start/end dates
- **Product/Category Promotions**: Apply discounts to specific products or categories
- **Cart-Level Discounts**: Manager/cashier discounts with permission controls

### 2.9 Users, Roles & Access Control
- **Team = Store/Branch**: Leverage existing Teams system (each team represents a store)
- **Role-Based Permissions**: Utilize existing TeamRole enum and membership system
- **User Management**: Manage staff within the store/team
- **Cashier Tracking**: Track which user processed each transaction
- **Permission Gates**: Restrict voids, discounts, refunds, reports by role

### 2.10 Reports & Analytics
- **Sales Summary**: Revenue, profit, transaction count by period
- **Product Performance**: Best/worst selling products
- **Inventory Reports**: Stock levels, low stock, stock valuation
- **Cashier Reports**: Sales performance per cashier
- **Payment Method Reports**: Breakdown by payment type
- **Tax Reports**: Tax collected summaries
- **Profit/Loss**: Cost vs revenue analysis

### 2.11 Settings & Configuration
- **Store Settings**: Store name, address, contact, logo
- **Receipt Settings**: Customize receipt header/footer, tax info
- **Tax Configuration**: Tax rates, tax-inclusive/exclusive pricing
- **Currency Settings**: Currency symbol and formatting
- **POS Settings**: Printer settings, barcode scanner config
- **User Preferences**: Leverage existing appearance/settings system

---

## 3. UI/UX Design

### 3.1 Design Philosophy
The STOCKRAGE POS UI follows a modern, professional aesthetic optimized for speed and efficiency in retail environments. The design prioritizes clarity, minimal clicks, and keyboard accessibility for fast-paced checkout operations.

### 3.2 Key Screen Layouts (From Mockups)

Based on the design files, the following screens are planned:

| Screen | Purpose | Notes |
| --- | --- | --- |
| **Login** | Authentication | Clean login with STOCKRAGE branding |
| **Dashboard (Light/Dark)** | Store overview | Sales summary, recent transactions, low stock alerts, quick actions |
| **Point of Sale (Light/Dark)** | Main checkout interface | Product grid/list, cart panel, customer selection, payment. Primary cashier screen. |
| **Stock Management** | Inventory overview | Product list with stock levels, filters, bulk actions |
| **Add Stock Item** | Product creation/editing | Comprehensive form for product details |
| **Stock History** | Inventory audit trail | Stock movements with dates, reasons, users |
| **Sales History** | Transaction list | Searchable transaction history with filters |
| **Transaction & Receipt** | Transaction details/receipt view | Full transaction breakdown and printable receipt |
| **Settings** | System configuration | Store, users, roles, taxes, receipts, etc. |
| **Tablet/Mobile Views** | Responsive layouts | Adapted for tablet POS terminals and mobile management |

### 3.3 UX Principles
- **Efficiency First**: Minimize clicks to complete a sale (target: < 5 clicks from product selection to payment)
- **Keyboard Navigation**: Full keyboard support for cashier operations
- **Visual Hierarchy**: Clear distinction between products, cart, actions, and totals
- **Error Prevention**: Confirmation modals for voids, refunds, and critical actions
- **Real-time Feedback**: Toast notifications (using existing Sonner) for all actions
- **Responsive**: Optimized for desktop POS terminals, tablets for mobility
- **Accessibility**: Follow WCAG guidelines where possible, proper ARIA labels

### 3.4 Navigation Structure
- **Sidebar Navigation**: Main app navigation (leveraging existing AppSidebar) with POS modules
- **POS-Focused Layout**: The POS checkout screen may use a specialized layout optimized for the cashier workflow
- **Breadcrumbs**: Clear navigation context using existing breadcrumb system
- **Quick Access**: Dashboard with shortcuts to common actions

---

## 4. Design Approach

### 4.1 Hybrid Design Strategy
Following the chosen approach (C - Hybrid), we'll blend the STOCKRAGE mockup designs with the existing design system:

**What we'll take from mockups:**
- POS checkout layout and workflow
- Overall aesthetic and information density
- Specific UI patterns for cart, product grid, payment
- Branding elements and visual style

**What we'll keep from existing system:**
- shadcn/ui component library and patterns
- Tailwind CSS v4 with CSS variable theming
- Layout system (AppSidebar, AppContent, etc.)
- Form components, modals, data tables patterns
- Dark/light mode infrastructure
- Toast notifications, tooltips, etc.

### 4.2 Design System

#### Typography
- **Font Family**: Instrument Sans (already configured in vite.config.ts)
- **Weights**: 400, 500, 600
- **Scale**: Following Tailwind's default type scale with adjustments for POS readability

#### Spacing & Layout
- **Grid System**: CSS Grid/Flexbox with Tailwind utilities
- **Spacing Scale**: Tailwind's default spacing scale
- **Border Radius**: Consistent rounded corners via CSS variables
- **Shadows**: Subtle shadows for depth and hierarchy

#### Color System (Brand-Adapted from PDF)
*Note: Extract exact colors from the Design System.png in the UI DESIGN folder. The implementation should use CSS variables mapped to Tailwind's color system.*

Based on STOCKRAGE branding:
- **Primary Brand Color**: Red-based (from the "Red Isometric Ribbon Cube Logo") - to be extracted from Design System.png
- **Accent Colors**: Complementary colors for CTAs and highlights
- **Semantic Colors**: Success (green), Warning (amber), Danger/Destructive (red), Info (blue)
- **Neutral Palette**: Grays for text, borders, backgrounds
- **Surface Colors**: Card backgrounds, panels, inputs

Colors will be defined as CSS custom properties in `resources/css/app.css` following the existing pattern.

#### Component Strategy
- **Reuse Existing**: Leverage shadcn/ui components from `resources/js/components/ui/`
- **Extend When Needed**: Create POS-specific components in `resources/js/components/pos/`
- **Compose**: Build complex POS interfaces by composing smaller components
- **Consistency**: Follow existing naming conventions and patterns

---

## 5. System Architecture

### 5.1 High-Level Architecture
STOCKRAGE POS follows a monolithic architecture with Inertia.js bridging Laravel and React:

```
┌─────────────────────────────────────────────────────────┐
│                        Client                          │
│  React 19 + TypeScript + Tailwind CSS + shadcn/ui     │
└────────────────────────┬────────────────────────────────┘
                         │ Inertia.js (JSON + Components)
┌────────────────────────▼────────────────────────────────┐
│                      Laravel 13                         │
│  Routes, Controllers, Middleware, Validation, Models   │
└────────────────────────┬────────────────────────────────┘
                         │ Eloquent ORM
┌────────────────────────▼────────────────────────────────┐
│                      Database                          │
│                    SQLite/MySQL/Postgres                │
└─────────────────────────────────────────────────────────┘
```

### 5.2 Multi-Tenancy Architecture (Team = Store)
Following Answer A from Question 3:

- **Tenant Model**: `Team` represents a Store/Branch
- **User Membership**: Users belong to teams via `team_members` pivot with roles
- **Route Scoping**: POS routes scoped under `/{current_team}/pos/...`
- **Middleware**: `EnsureTeamMembership` ensures users only access their team's data
- **Data Isolation**: All POS models (Products, Sales, etc.) will be scoped to `team_id`
- **Team Switching**: Leverage existing team switcher for multi-store access (if users belong to multiple stores)

### 5.3 Request Flow
1. **Authentication**: Handled by Laravel Fortify (sessions, 2FA, passkeys)
2. **Authorization**: Team membership checked via middleware, role-based checks in policies/controllers
3. **Routing**: Web routes with Inertia responses
4. **Data Loading**: Controllers fetch team-scoped data, pass to Inertia
5. **Rendering**: React components render with props from Inertia
6. **Actions**: Form submissions/requests go to Laravel controllers, redirect with Inertia or return data
7. **Validation**: Server-side validation with Form Requests

### 5.4 Security Considerations
- **CSRF Protection**: Laravel's built-in CSRF protection
- **Authentication**: Fortify with email verification
- **Authorization**: Policies for each resource, role checks
- **Team Isolation**: All queries scoped by current team
- **Input Validation**: Form Requests for all write operations
- **XSS Protection**: React's built-in escaping
- **SQL Injection**: Eloquent ORM parameterized queries

---

## 6. Frontend Approach

### 6.1 Technology Stack
| Technology | Version | Purpose |
| --- | --- | --- |
| **React** | 19.2.0 | UI framework |
| **TypeScript** | 5.7.2 | Type safety |
| **Inertia.js React** | 3.0.0 | SPA without API complexity |
| **Tailwind CSS** | 4.0.0 | Utility-first styling |
| **shadcn/ui** | Latest | Component library (Radix UI primitives) |
| **Vite Plus** | 1.4.0 | Build tool & dev server |
| **Lucide React** | 0.475.0 | Icon library |
| **Sonner** | 2.0.0 | Toast notifications |
| **Wayfinder** | 0.1.3 | Type-safe Laravel route helpers |

### 6.2 Project Structure
```
example-app/resources/js/
├── app.tsx                 # Inertia app setup, layouts
├── components/
│   ├── ui/                 # shadcn/ui components (existing)
│   ├── pos/                # POS-specific components (new)
│   ├── app-sidebar.tsx
│   └── ...
├── layouts/
│   ├── app/                # App layouts (sidebar/header)
│   ├── auth/               # Auth layouts
│   └── pos/                # POS-specific layout (new, minimal for checkout)
├── pages/
│   ├── dashboard.tsx
│   ├── pos/                # POS pages (new)
│   ├── products/           # Product management (new)
│   ├── sales/              # Sales history (new)
│   ├── inventory/          # Inventory management (new)
│   ├── customers/          # Customer management (new)
│   ├── suppliers/          # Supplier management (new)
│   ├── purchases/          # Purchase orders (new)
│   ├── expenses/           # Expenses (new)
│   ├── reports/            # Reports (new)
│   └── settings/           # Extended settings
├── hooks/                  # Custom React hooks
├── lib/                    # Utilities (cn, etc.)
├── types/                  # TypeScript type definitions
└── routes/                 # Wayfinder generated routes
```

### 6.3 Component Architecture
- **Atomic Design**: Atoms (ui/), Molecules (composed components), Organisms (complex sections), Pages
- **Server Props**: All data comes from Inertia props (no client-side fetching needed for most cases)
- **Forms**: Use Inertia's `useForm` hook for form handling
- **State Management**: Local state with `useState`/`useReducer`, Inertia for server state
- **Type Safety**: Shared types between backend and frontend where possible

### 6.4 POS-Specific Frontend Patterns
- **POS Layout**: Full-screen or optimized layout for checkout (minimal chrome, focus on cart/products)
- **Product Grid**: Responsive grid with product cards, search, category filters
- **Cart Component**: Sticky cart panel with line items, totals, actions
- **Payment Modal**: Full-screen or modal for payment processing
- **Barcode Scanner Support**: Input handling for barcode scanners
- **Keyboard Shortcuts**: Global shortcuts for POS operations
- **Real-time Calculations**: Client-side price/total calculations for instant feedback

### 6.5 Routing
Using Wayfinder for type-safe routes:
```typescript
import { pos } from '@/routes';
// Usage: pos.checkout(currentTeam.slug)
```

Routes will follow the team-scoped pattern: `/{current_team}/pos/*`

---

## 7. Backend Approach

### 7.1 Technology Stack
| Technology | Version | Purpose |
| --- | --- | --- |
| **PHP** | ^8.3 | Backend language |
| **Laravel** | ^13.17 | Framework |
| **Inertia Laravel** | ^3.0 | Inertia server adapter |
| **Laravel Fortify** | ^1.37.2 | Authentication |
| **Laravel Wayfinder** | ^0.1.14 | Route generation for frontend |
| **Laravel Chisel** | ^0.1.0 | Development tooling |

### 7.2 Database Schema Design

All POS models will be scoped to `teams` (stores). Key tables:

#### Core POS Tables
| Table | Purpose | Key Relationships |
| --- | --- | --- |
| `products` | Product catalog | belongsTo Team, belongsTo Category, hasMany SaleItems |
| `categories` | Product categories | belongsTo Team, hasMany Products |
| `customers` | Customer database | belongsTo Team, hasMany Sales |
| `sales` | Sales transactions | belongsTo Team, belongsTo User (cashier), belongsTo Customer, hasMany SaleItems, hasMany Payments |
| `sale_items` | Line items in a sale | belongsTo Sale, belongsTo Product |
| `payments` | Payment records (supports split payments) | belongsTo Sale |
| `stock_adjustments` | Inventory adjustments | belongsTo Team, belongsTo Product, belongsTo User |
| `stock_movements` | Audit trail of all stock changes | belongsTo Team, belongsTo Product, morphable to source (Sale, Purchase, Adjustment) |

#### Suppliers & Purchases
| Table | Purpose | Key Relationships |
| --- | --- | --- |
| `suppliers` | Supplier information | belongsTo Team, hasMany Purchases |
| `purchases` | Purchase orders | belongsTo Team, belongsTo Supplier, belongsTo User, hasMany PurchaseItems |
| `purchase_items` | PO line items | belongsTo Purchase, belongsTo Product |

#### Expenses & Returns
| Table | Purpose | Key Relationships |
| --- | --- | --- |
| `expenses` | Business expenses | belongsTo Team, belongsTo User, belongsTo ExpenseCategory |
| `expense_categories` | Expense categorization | belongsTo Team, hasMany Expenses |
| `returns` | Returns/refunds | belongsTo Team, belongsTo Sale, belongsTo User, hasMany ReturnItems |
| `return_items` | Return line items | belongsTo Return, belongsTo SaleItem |

#### Promotions
| Table | Purpose | Key Relationships |
| --- | --- | --- |
| `promotions` | Discount/promo definitions | belongsTo Team |
| `promotion_product` | Pivot for product-specific promos | belongsTo Promotion, belongsTo Product |
| `promotion_category` | Pivot for category-specific promos | belongsTo Promotion, belongsTo Category |

### 7.3 Models & Relationships
All POS models will follow Laravel conventions:

```php
// Example structure
class Product extends Model
{
    use HasFactory;
    
    protected $fillable = ['team_id', 'category_id', 'name', 'sku', 'barcode', ...];
    
    // Team scoping
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function saleItems(): HasMany { return $this->hasMany(SaleItem::class); }
    
    // Global scope for team isolation
    protected static function booted(): void {
        static::addGlobalScope('team', function (Builder $builder) {
            if (auth()->check() && session('current_team_id')) {
                $builder->where('team_id', session('current_team_id'));
            }
        });
    }
}
```

### 7.4 Controllers
Controllers will be organized by module:

```
app/Http/Controllers/
├── Pos/
│   ├── CheckoutController.php    # POS checkout operations
│   └── CartController.php        # Cart management
├── Products/
│   ├── ProductController.php
│   └── CategoryController.php
├── Sales/
│   ├── SaleController.php
│   └── ReturnController.php
├── Inventory/
│   ├── StockAdjustmentController.php
│   └── StockHistoryController.php
├── Customers/
│   └── CustomerController.php
├── Suppliers/
│   └── SupplierController.php
├── Purchases/
│   └── PurchaseController.php
├── Expenses/
│   └── ExpenseController.php
├── Promotions/
│   └── PromotionController.php
└── Reports/
    └── ReportController.php
```

### 7.5 Routes
Routes will be team-scoped under `/{current_team}` prefix (consistent with existing pattern):

```php
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        // POS
        Route::get('pos', [CheckoutController::class, 'index'])->name('pos.index');
        Route::post('pos/checkout', [CheckoutController::class, 'store'])->name('pos.checkout');
        
        // Products
        Route::resource('products', ProductController::class);
        Route::resource('categories', CategoryController::class);
        
        // Sales & Returns
        Route::resource('sales', SaleController::class)->only(['index', 'show']);
        Route::post('sales/{sale}/return', [ReturnController::class, 'store'])->name('sales.return');
        
        // Inventory
        Route::resource('inventory/stock-adjustments', StockAdjustmentController::class);
        Route::get('inventory/history', [StockHistoryController::class, 'index'])->name('inventory.history');
        
        // Customers, Suppliers, Purchases, Expenses, Promotions, Reports
        // ... additional routes
    });
```

### 7.6 Validation & Authorization
- **Form Requests**: Create dedicated Form Request classes for validation
- **Policies**: Implement Policies for all resources (e.g., `SalePolicy` for void/return permissions)
- **Gates**: Use Laravel Gates for role-based permissions
- **Team Scoping**: Enforce team ownership in all queries and policies

### 7.7 Business Logic Considerations
- **Inventory Updates**: Automatically deduct stock on sale, add stock on purchase/return
- **Transaction Atomicity**: Use database transactions for checkout (sale + items + payments + inventory)
- **Stock Validation**: Prevent selling more than available stock
- **Receipt Numbers**: Auto-generate sequential receipt/invoice numbers per team
- **Pricing**: Handle tax calculations (configurable tax rates)
- **Audit Trail**: Log all critical actions (voids, refunds, stock adjustments)

---

## 8. Tech Stack Alignment & Cohesion Plan

### 8.1 Leveraging Existing Infrastructure
| Existing Feature | How We'll Use It | Notes |
| --- | --- | --- |
| **Teams System** | Repurpose as Store/Branch | Minimal changes needed, just UI terminology |
| **User Authentication (Fortify)** | Keep as-is | Already has login, 2FA, passkeys, email verification |
| **Team Membership & Roles** | Extend for POS permissions | Use existing TeamRole enum, add POS-specific permissions if needed |
| **Middleware (EnsureTeamMembership)** | Apply to all POS routes | Already working perfectly |
| **AppLayout/Sidebar** | Extend sidebar with POS modules | Add POS menu items dynamically based on permissions |
| **Settings System** | Extend for store/POS settings | Add new settings pages under settings |
| **UI Components (shadcn/ui)** | Reuse extensively | Consistent look and feel |
| **Inertia.js Setup** | Keep as-is | Already configured |
| **Wayfinder** | Use for all routes | Type-safe routing |
| **Theme System** | Brand-adapt with STOCKRAGE colors | Update CSS variables |

### 8.2 Making Everything Work Cohesively

#### Naming & Terminology
- **Team → Store/Branch**: Update UI labels ("Create Store", "Store Settings") while keeping database table names as `teams` to avoid migrations
- **Consistent Terminology**: Use "Sale" instead of "Order" to match POS terminology
- **Route Naming**: Follow existing patterns (`pos.index`, `products.create`)

#### Data Architecture
- **Team Scoping**: Add `team_id` foreign keys to all POS tables
- **Shared User Model**: Use existing User model, no duplication
- **Role Integration**: Leverage team memberships for cashier/admin/manager roles
- **Settings Per Team**: Store POS settings in team-related config or dedicated table

#### UI/UX Cohesion
- **Consistent Layouts**: Use AppLayout for management screens, create optimized POS layout for checkout
- **Shared Components**: Build POS components that follow existing patterns
- **Form Patterns**: Use same form validation and error display patterns
- **Modal Patterns**: Reuse existing modal components
- **Empty States**: Follow existing empty state patterns

#### Code Organization
- **Follow Existing Conventions**: PSR-4 autoloading, Laravel conventions, TypeScript strict mode
- **Type Sharing**: Define shared types in `resources/js/types/` mirroring backend structures
- **Service Classes**: Extract business logic to Services/Actions (following Laravel best practices)
- **Use Existing Helpers**: Leverage existing utilities and patterns

### 8.3 Minimal Disruption Strategy
1. **Non-Breaking**: All new features added, existing functionality preserved
2. **Incremental**: Build module by module, test as we go
3. **Database Migrations**: Additive migrations only (no changes to existing tables except maybe adding indexes)
4. **Route Isolation**: POS routes under team prefix, don't interfere with existing routes
5. **Component Isolation**: New POS components in separate directory

---

## 9. Implementation Roadmap

### Phase 1: Foundation & Setup (Week 1)
- [ ] **Branding**: Extract colors from Design System.png and update `resources/css/app.css`
- [ ] **Assets**: Copy STOCKRAGE logos to appropriate locations
- [ ] **Database**: Create migrations for all POS tables (products, categories, sales, etc.)
- [ ] **Models**: Create Eloquent models with relationships and team scoping
- [ ] **Seeders**: Create sample data for testing (optional but helpful)
- [ ] **Routes**: Set up POS route structure
- [ ] **Sidebar**: Update AppSidebar to include POS navigation

### Phase 2: Core POS & Products (Week 2)
- [ ] **Categories**: CRUD for product categories
- [ ] **Products**: CRUD for products with inventory tracking
- [ ] **POS Interface**: Build checkout page (product grid, cart, customer selection)
- [ ] **Cart Logic**: Add to cart, update quantities, remove items
- [ ] **Checkout Flow**: Basic checkout without payments first, then add payments
- [ ] **Receipt Display**: Transaction confirmation/receipt view

### Phase 3: Sales, Payments & Returns (Week 3)
- [ ] **Payment Processing**: Multiple payment methods, split payments
- [ ] **Sales History**: Transaction listing with filters
- [ ] **Transaction Details**: Full transaction view with receipt
- [ ] **Returns/Refunds**: Return processing with inventory restoration
- [ ] **Void Transactions**: With permission checks
- [ ] **Inventory Integration**: Auto-deduct stock on sale, restore on return

### Phase 4: Inventory & Suppliers (Week 4)
- [ ] **Stock Management**: Inventory overview page
- [ ] **Stock Adjustments**: Manual adjustments with reasons
- [ ] **Stock History**: Audit trail
- [ ] **Suppliers**: Supplier CRUD
- [ ] **Purchases**: Purchase order creation and receiving
- [ ] **Low Stock Alerts**: Dashboard alerts

### Phase 5: Customers, Expenses & Promotions (Week 5)
- [ ] **Customers**: Customer CRUD and purchase history
- [ ] **Expenses**: Expense tracking with categories
- [ ] **Promotions**: Discount and promo code management
- [ ] **Cart Discounts**: Apply discounts in POS
- [ ] **User Permissions**: Fine-tune role-based access

### Phase 6: Reports, Settings & Polish (Week 6)
- [ ] **Reports**: Sales, inventory, cashier, payment reports
- [ ] **Store Settings**: Configure store details, receipt, taxes
- [ ] **POS Settings**: Printer, scanner configuration
- [ ] **UI Polish**: Match mockup designs, responsive adjustments
- [ ] **Testing**: Test all critical flows
- [ ] **Documentation**: Update with implementation details

---

## 10. File Structure & Conventions

### 10.1 Backend Conventions
```php
// Model example
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = ['team_id', 'category_id', 'name', 'sku', 'price', 'cost', 'stock', ...];
    
    protected function casts(): array {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
    
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
}
```

### 10.2 Frontend Conventions
```typescript
// Page component example
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Products', href: '/products' },
];

export default function ProductsIndex({ products }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Products" />
            {/* Content */}
        </AppLayout>
    );
}
```

### 10.3 Naming Conventions
| Type | Convention | Example |
| --- | --- | --- |
| **Controllers** | PascalCase, plural/singular as appropriate | `ProductController`, `CheckoutController` |
| **Models** | PascalCase, singular | `Product`, `Sale`, `SaleItem` |
| **Migrations** | snake_case with timestamp | `2026_10_28_000001_create_products_table.php` |
| **React Components** | PascalCase | `ProductCard.tsx`, `CartPanel.tsx` |
| **Pages** | kebab-case or PascalCase matching route | `pages/pos/index.tsx` |
| **Routes** | kebab-case, RESTful | `products.index`, `pos.checkout` |
| **Database Tables** | snake_case, plural | `products`, `sale_items` |

### 10.4 Code Quality
- **TypeScript**: Strict mode enabled, no `any` where possible
- **PHP**: Follow PSR-12, use Laravel Pint for formatting
- **Validation**: Always validate on server side
- **Error Handling**: Use Inertia error handling, display validation errors
- **Comments**: Document complex business logic
- **Testing**: Consider feature tests for critical POS flows

---

## 11. Branding & Assets

### 11.1 Brand Assets Location
All branding assets are located in:
```
context and md files/
├── STOCKRAGE.png
├── Red Isometric Ribbon Cube Logo.png
└── UI DESIGN/
    ├── STOCKRAGE POS — UI Design-png/
    │   ├── Design System.png    # Color palette & design tokens
    │   ├── Dashboard (Light).png
    │   ├── Point of Sale (Light).png
    │   └── ... (other mockups)
    └── STOCKRAGE POS — UI Design.pdf
```

### 11.2 Logo Usage
- **Primary Logo**: `Red Isometric Ribbon Cube Logo.png` - Use in sidebar, login, receipts
- **Secondary Logo**: `STOCKRAGE.png` - Alternative branding
- **App Logo Component**: Update `resources/js/components/app-logo.tsx` to use STOCKRAGE branding

### 11.3 Color Palette
*Extract the exact color values from `Design System.png`. The implementation should:*

1. Open `context and md files/UI DESIGN/STOCKRAGE POS — UI Design-png/Design System.png`
2. Extract all color codes (hex values)
3. Map to CSS variables in `resources/css/app.css` following the existing pattern:
```css
:root {
  --primary: oklch(...); /* STOCKRAGE brand red */
  --primary-foreground: oklch(...);
  /* ... other colors */
}
```

4. Ensure colors work in both light and dark modes
5. Maintain sufficient contrast ratios for accessibility

### 11.4 Implementation Notes for Branding
- Copy logos to `example-app/public/images/` or `example-app/resources/images/`
- Update `app.tsx` app name if needed
- Update meta tags and favicons
- Customize receipt branding with store logo

---

## Next Steps

With all answers gathered, this document is ready. To begin implementation:

1. **Extract Colors**: Review Design System.png to get exact color values
2. **Set Up Database**: Create and run migrations for POS schema
3. **Build Incrementally**: Follow the roadmap phase by phase
4. **Maintain Cohesion**: Keep using existing patterns and components where possible
5. **Test Continuously**: Test in the context of the existing app

This documentation will serve as the blueprint for building STOCKRAGE POS in a way that feels native to the existing codebase while delivering all the features shown in the designs.
