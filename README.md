# Hawalaty

> A multi-tenant SaaS platform where each licensed business gets a fully isolated workspace - its own MySQL database - to run its trade: orders, a catalog, stock, purchasing, customers, accounts, expenses, a role-based team, e-invoicing and reports. One codebase serves **twelve business verticals** - from a restaurant to a pharmacy to a fuel station - by switching **capabilities** on per license rather than by shipping twelve systems. It is an Arabic-first (RTL), bilingual server-rendered web app **and** a versioned REST API with offline sync for mobile/SPA clients.

<p align="center">
<img src="https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php&logoColor=white" alt="PHP 8.3+">
<img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
<img src="https://img.shields.io/badge/Tailwind_CSS-4-38BDF8?logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4">
<img src="https://img.shields.io/badge/Sanctum-API_tokens-FF2D20?logo=laravel&logoColor=white" alt="Laravel Sanctum">
<img src="https://img.shields.io/badge/License-MIT-green" alt="License MIT">
</p>

---

## Table of contents

- [Overview](#overview)
- [Architecture](#architecture)
  - [The two planes](#the-two-planes)
  - [Multi-tenancy - database per tenant](#multi-tenancy---database-per-tenant)
  - [Request lifecycle](#request-lifecycle)
  - [Authentication guards](#authentication-guards)
  - [Three gates, and they answer different questions](#three-gates-and-they-answer-different-questions)
  - [Roles & permissions](#roles--permissions)
  - [Tenant provisioning](#tenant-provisioning)
- [Business verticals](#business-verticals)
  - [The twelve verticals](#the-twelve-verticals)
  - [The sixteen capabilities](#the-sixteen-capabilities)
  - [What a vertical decides](#what-a-vertical-decides)
- [Features](#features)
  - [The core workspace](#the-core-workspace)
  - [Accounts, catalogs and the ledger](#accounts-catalogs-and-the-ledger)
  - [Catalog naming](#catalog-naming)
  - [The vertical modules](#the-vertical-modules)
  - [Inventory and costing](#inventory-and-costing)
  - [Returns](#returns)
  - [E-invoicing (EN 16931)](#e-invoicing-en-16931)
  - [Margin and pricing](#margin-and-pricing)
  - [The public site](#the-public-site)
  - [Super Admin platform](#super-admin-platform)
  - [A box can never go negative](#a-box-can-never-go-negative)
  - [Partner clearing (المقاصة)](#partner-clearing-المقاصة)
- [Domain model](#domain-model)
- [Tech stack](#tech-stack)
- [Requirements](#requirements)
- [Getting started](#getting-started)
- [Configuration](#configuration)
- [Default credentials](#default-credentials)
- [Artisan commands](#artisan-commands)
- [Scheduled tasks](#scheduled-tasks)
- [REST API](#rest-api)
  - [Search terms](#search-terms)
- [Offline sync](#offline-sync)
  - [The browser also works offline](#the-browser-also-works-offline)
  - [Installable to a home screen](#installable-to-a-home-screen)
  - [Addressing a record the server has never seen](#addressing-a-record-the-server-has-never-seen)
- [WhatsApp order notifications](#whatsapp-order-notifications)
- [Localization](#localization)
  - [RTL notes](#rtl-notes)
- [Design system](#design-system)
  - [Fonts are vendored, not linked](#fonts-are-vendored-not-linked)
- [Project structure](#project-structure)
  - [Shared table conventions](#shared-table-conventions)
  - [Accessible names are not optional](#accessible-names-are-not-optional)
- [Testing](#testing)
- [Continuous integration & deployment](#continuous-integration--deployment)
- [Code style](#code-style)
- [License](#license)

---

## Overview

**Hawalaty** is a **database-per-tenant** SaaS application. A platform operator sells **licenses** (bundled into **packages**) to businesses. Each license is backed by its own dedicated MySQL database that is created, migrated and seeded automatically the moment a business signs up - so tenants are physically isolated from one another and from the platform.

Inside their workspace, a tenant's **team members** record day-to-day orders, manage a catalog, stock and suppliers, customers, accounts (cash boxes / banks / wallets), expenses and their own staff with fine-grained permissions, issue invoices and receipts, and read margin and revenue reports.

Two things make it more than a single-trade point of sale:

- **A license carries a business vertical.** Twelve are supported, and the choice switches whole modules - batch tracking, serial numbers, recipes, appointments, fuel shifts - on or off. See [Business verticals](#business-verticals).
- **Everything is exposed through two delivery surfaces**: a **server-rendered Blade web app** (Arabic-first, right-to-left, bilingual AR/EN) and a **versioned JSON REST API** (`/api/v1`) for mobile / SPA clients, complete with an offline **push/pull sync** channel.

## Architecture

### The two planes

The codebase is deliberately split into two independent planes that never share data:

| Plane | Database connection | Who uses it | Namespaces |
|-------|---------------------|-------------|------------|
| **Platform (Super Admin)** | `mysql` (the platform's own DB) | Platform operators | `App\Models\*`, `App\Http\Controllers\*\Admin\*`, `App\Services\Admin\*` |
| **Tenant** | `tenant` (dynamic, per request) | A license's team members | `App\Models\Tenant\*`, `App\Services\Tenant\*` |

The Super Admin plane manages the product catalog and every tenant's lifecycle. The tenant plane is where each business actually works. A route is always in exactly one plane, and platform routes never pass through the tenant-resolution middleware.

### Multi-tenancy - database per tenant

Each tenant has its **own physical MySQL database**. There is a single `tenant` connection in [`config/database.php`](config/database.php) whose settings are **rewritten at runtime, per request**, to point at the correct tenant database. The rewrite is done by [`App\Services\TenantConnectionResolver`](app/Services/TenantConnectionResolver.php):

1. Look up the `License` by its `license_code`.
2. Reject it if it's **suspended** or **expired**.
3. Read the connection details from the license's related `DatabaseConnection` row.
4. `config(['database.connections.tenant' => ...])` and `DB::purge('tenant')` so the next tenant query hits the right database.
5. Bind the resolved license into the container as `currentLicense`, which is what the package-feature and business-capability gates read.

Every tenant model (e.g. [`Transfer`](app/Models/Tenant/Transfer.php)) hard-codes `getConnectionName(): 'tenant'`, so tenant Eloquent queries automatically follow whichever database the resolver most recently selected - they can never accidentally read platform data.

### Request lifecycle

The license is identified differently on each surface, but both resolve **before** authentication runs:

**Web (session cookie):**
```
Request → SetLocale → tenant.session → auth:tenant → [package] → [capability] → controller
                      │                 │
                      │                 └─ authenticates the TeamMember against the tenant DB
                      └─ reads session('license_code'), points `tenant` connection at the right DB
```

**API (bearer token):**
```
Request → throttle:api → tenant.header → auth:sanctum → [capability] → controller
                          │              │
                          │              └─ validates the Sanctum token against the tenant DB
                          └─ reads X-License-Code header, points `tenant` connection at the right DB
```

**Admin web (session cookie):**
```
Request → SetLocale → auth:admin → controller
                      │
                      └─ authenticates the Admin against the platform `mysql` DB
                         (no tenant resolution - /admin/* never touches a tenant connection)
```

Ordering is critical: if the auth guard ran first it would query the wrong database. Laravel's `SortedMiddleware` reorders middleware by a priority list, so both resolvers are explicitly pinned **ahead of** the auth middleware in [`bootstrap/app.php`](bootstrap/app.php) via `prependToPriorityList(...)`. The same file also makes the "redirect unauthenticated users" behavior **guard-aware** - API requests get a plain JSON `401`, `/admin/*` redirects to the admin login, everything else to the tenant login.

If a license becomes invalid **between** page loads (an admin suspends it, or it expires), the web resolver tears down the session and redirects to a dedicated `license.inactive` page instead of letting a stale connection blow up the next query.

### Authentication guards

**Three** guards are declared in [`config/auth.php`](config/auth.php); a fourth, `sanctum`, is registered at runtime by Sanctum's service provider rather than appearing in the config file:

| Guard | Driver | Provider | Used for |
|-------|--------|----------|----------|
| `admin` | session | `users` (→ `Admin`) | Super Admin portal (web) |
| `tenant` | session | `team_members` (→ `TeamMember`, tenant DB) | Tenant team members (web) |
| `sanctum` | token *(registered by Sanctum, not in `config/auth.php`)* | - | REST API (both tenant and admin routes) |
| `web` | session | `users` (→ `Admin`, same provider as `admin`) | Laravel framework default (kept for compatibility) |

The `admin` and `tenant` web guards are declared explicitly (rather than relying on Laravel's implicit default guard) so route middleware can say `auth:admin` / `auth:tenant` unambiguously.

### Three gates, and they answer different questions

Three independent middleware aliases guard tenant routes, and conflating any two of them would be a bug:

| Gate | Alias | Asks | Source of truth |
|------|-------|------|-----------------|
| **Permission** | *(policy / explicit check)* | *Who is this member?* | `PermissionKey` on the member's role |
| **Package feature** | `package:<feature>` | *What did the operator sell?* | [`PackageFeature`](app/Enums/PackageFeature.php) on the license's package |
| **Business capability** | `capability:<capability>` | *What does the trade require?* | [`BusinessCapability`](app/Enums/BusinessCapability.php) derived from the license's `BusinessType` |

[`EnsurePackageFeature`](app/Http/Middleware/EnsurePackageFeature.php) and [`EnsureBusinessCapability`](app/Http/Middleware/EnsureBusinessCapability.php) are siblings and deliberately separate. A pharmacy without batch tracking is not a cheaper plan - it is the wrong system, and no upsell fixes it. `capability:` accepts several capabilities and passes when **any** of them is on; an unknown capability or feature string raises an `InvalidArgumentException` rather than silently letting the route through, so a typo in a route definition fails loudly the first time it is hit.

The sidebar in [`layouts/app.blade.php`](resources/views/layouts/app.blade.php) filters every nav item through the same three gates, so the navigation cannot offer a screen the request would then refuse.

### Roles & permissions

Every tenant database is seeded with **three system roles** over a fixed set of **nine permission keys** ([`App\Enums\PermissionKey`](app/Enums/PermissionKey.php)):

| Role | Permissions |
|------|-------------|
| **Owner** | All 9 permissions |
| **Manager** | All except `team.manage` |
| **Staff** | `transfers.create` only |

The nine keys are `transfers.create`, `menu.manage`, `banks.manage`, `expenses.manage`, `constants.manage`, `devices.manage`, `team.manage`, `inventory.manage`, `purchases.manage`. Each carries its own Arabic label, description and icon, so the roles screen describes a permission in plain language rather than printing its key. Owners can define additional custom roles on top of the system ones.

Permissions are enforced at the request boundary by per-model policies in [`app/Policies/Tenant`](app/Policies/Tenant) - `TransferPolicy`, `MenuItemPolicy`, `MenuCategoryPolicy`, `ExpensePolicy`, `ConstantListPolicy`, `DevicePolicy`, `TeamMemberPolicy`, `RolePolicy` - each mapping its actions onto a `PermissionKey`. Screens without a model of their own (accounts, catalogs, inventory, purchasing, the vertical modules) check their `PermissionKey` explicitly in the controller. FormRequests deliberately return `true` from `authorize()` and leave the decision to the policy, so the web and API surfaces share one authorization path.

> [`RouteAuthorizationTest`](tests/Feature/Tenant/RouteAuthorizationTest.php) asserts that **every tenant route that writes checks a permission**. `auth:tenant` only proves someone is signed in, and a hidden nav item is not access control.

### Tenant provisioning

New tenants are created end-to-end by [`App\Services\TenantProvisioner`](app/Services/TenantProvisioner.php) - the **same** service used by the public signup flow, the Super Admin "issue a license" form and the seeder. In one operation it:

1. Creates the `License` row (status **Trial**, expiring after the operator-configured `trial_days`, default 14) linked to the chosen package and **business type**.
2. Provisions storage, in one of two modes:
   - **Create** (default): a physical MySQL database **and** a dedicated MySQL user with privileges scoped to that database. Identifiers are derived from the random license code and whitelist-validated before being interpolated into DDL.
   - **Link**: when `dbName` is supplied, an existing database is adopted instead - the cPanel case, where the hosting panel created the database and user. The connection is **probed with `SELECT 1`** before anything else runs, so a wrong password fails with a readable Arabic message rather than half a provisioning run.
3. Records the connection details in a `DatabaseConnection` row.
4. Runs the tenant migrations against that database (`php artisan tenants:migrate`).
5. Seeds the 9 permissions, the 3 system roles and the **owner** team member.
6. Seeds the vertical's defaults: catalog terminology, catalog icon, and the constant lists its trade actually uses.
7. Writes a platform `ActivityLog` entry.

If any step fails, it performs best-effort cleanup - dropping the database and user and deleting the platform rows already written - so a failed signup never leaks orphaned databases.

## Business verticals

A vertical is **not a label**. It is a set of capabilities, and the twelve supported trades are built out of sixteen shared axes rather than as twelve separate systems - so a capability is implemented once and reused by every vertical that declares it.

The choice is **platform-side data**: an operator picks it when issuing the license (and can change it later from `/admin/licenses/{id}`). Tenants cannot change their own.

### The twelve verticals

Defined in [`App\Enums\BusinessType`](app/Enums/BusinessType.php). Each carries a label, an icon, catalog terminology, its constant lists, a default unit of measure, a return policy, and the headline standards it is designed against.

| Vertical | Capabilities it switches on | Standards it targets |
|----------|-----------------------------|----------------------|
| **المطاعم والمقاهي** (Restaurant) | inventory, purchasing, recipes, modifiers, waste, allergens | HACCP / Codex, ISO 22000, EU 1169/2011 |
| **المخبوزات** (Bakery) | inventory, purchasing, recipes, batches, weighed items, waste, allergens | HACCP / Codex, OIML R76, OIML R87 |
| **المحروقات** (Fuel) | inventory, purchasing, weighed items, fuel station | OIML R117, OIML R118, ASTM D1250, UN 1203 / ADR |
| **الخضار والفواكه** (Produce) | inventory, purchasing, batches, weighed items, waste | IFPS PLU, FSMA 204, UNECE marketing standards |
| **البقالة والسوبرماركت** (Grocery) | inventory, purchasing, batches, weighed items, waste | GS1 GTIN-13, GS1 GPC, EU 98/6/EC |
| **الكتب والقرطاسية** (Bookstore) | inventory, purchasing | ISBN-13 / ISO 2108, ONIX 3.0, Thema |
| **السجائر والتبغ** (Tobacco) | inventory, purchasing, batches, age restriction | EU TPD Art. 15-16, WHO FCTC Protocol |
| **الملابس** (Clothing) | inventory, purchasing, variants | EN 13402, ISO 8559, ISO 3758, EU 1007/2011 |
| **الإلكترونيات** (Electronics) | inventory, purchasing, serials, variants, warranty | IMEI / 3GPP TS 23.003, RoHS, WEEE, CE |
| **الصيدليات** (Pharmacy) | inventory, purchasing, batches, serials, prescription, waste | WHO ATC, GS1 DataMatrix, EU FMD 2016/161, US DSCSA, GDP |
| **قطع الغيار** (Auto parts) | inventory, purchasing, serials, fitment, warranty, scheduling | TecDoc, ACES / PIES, ISO 3779 (VIN), UNECE E-mark |
| **الخدمات** (Services) | scheduling, warranty | ISO 9001, ISO/IEC 20000, IFRS 15 |

`Grocery` is the default for a license whose type is unset, which is the least surprising fallback: a general shop with stock and no exotic rules.

### The sixteen capabilities

Defined in [`App\Enums\BusinessCapability`](app/Enums/BusinessCapability.php).

| Capability | What it turns on |
|------------|------------------|
| `inventory` | Append-only stock ledger, locations, on-hand balances, stocktakes |
| `purchasing` | Suppliers, purchase orders, goods receipt - the only legitimate stock inflow |
| `batches` | Lot tracking with expiry dates and FEFO issuing |
| `serials` | Per-unit serial numbers (IMEI, chassis, device serial) |
| `variants` | Size × colour matrices - one style, many stock-keeping variants |
| `recipes` | Bills of materials, plus production runs where the finished item is stocked |
| `modifiers` | Sale-time options and add-ons (no onion, extra cheese, large) |
| `weighed_items` | Fractional quantities by weight or volume, PLU codes, scale labels |
| `waste` | Spoilage, breakage and end-of-day write-offs as a first-class movement |
| `warranty` | Warranty terms, claims and the RMA flow |
| `fitment` | Vehicle compatibility, OE cross-references, superseded parts |
| `scheduling` | Appointments, resources and work orders - time is the stock |
| `fuel_station` | Tanks, pumps, meter readings and wetstock reconciliation |
| `age_restriction` | Minimum legal age prompt recorded against the sale |
| `prescription` | Rx vs OTC, prescriber details, controlled-substance register |
| `allergens` | The fourteen declarable allergens on every sellable item |

Some capabilities cannot function alone - `batches`, `serials`, `variants`, `recipes`, `waste` and `purchasing` all declare `requires() → inventory`, which is what keeps a vertical definition from switching on a module with nothing underneath it.

### What a vertical decides

Beyond which screens exist, `BusinessType` answers four questions the rest of the app asks it:

- **Catalog terminology and icon.** A bakery opens on «المخبوزات / نوع / منتج»; a pharmacy on «الأدوية / فئة / دواء». Seeded at provisioning, editable afterwards.
- **Which constant lists to seed**, so a workspace opens with the vocabulary its trade already uses - dosage forms and prescribers for a pharmacy, fuel grades and shifts for a station, sizes and seasons for a clothes shop.
- **Whether returned goods may go back on the shelf.** Restaurants, bakeries, produce and pharmacies **destroy** returns rather than restocking them: once food or medicine has left the premises its chain of custody is broken. The module does not offer the choice - unlike the return window in days, which is only a starting default the shop can override.
- **The default unit of measure**, as a UN/CEFACT Rec 20 code: `LTR` for fuel, `KGM` for produce, `HUR` for services, `H87` (piece) otherwise.

[`BusinessProfile`](app/Services/Tenant/BusinessProfile.php) is the single point of truth for "does this tenant have module X". Controllers, middleware, navigation and Blade all ask it rather than reading the license directly, so the fallback for an unresolved license is decided in one place. It also derives two cross-cutting answers: `tracksStock()` and `allowsFractionalQuantity()` - the latter drives input `step`, rounding and the decimal places shown on quantities across the UI.

> [`docs/verticals-analysis.md`](docs/verticals-analysis.md) is the design reference the whole vertical layer was built from - requirements, shared factors and the international standards per trade.

## Features

### The core workspace

Available to every vertical, subject to permissions and the package.

| Area | What it does |
|------|--------------|
| **Dashboard** | Revenue tile, weekly-activity chart, recent transactions |
| **Orders** (`/transfers`) | Record a sale - cash or an account - with line items, modifiers, discount, notes, change handling, a reference number and a status (completed / pending / failed) |
| **History & receipts** | Searchable order history and a printable receipt (HTML print view on the web, **PDF** over the API) |
| **Customers** | Customer directory. Identity is the **phone number**, not the name - two walk-ins called «أحمد» are two customers, and the same phone is always the same person however the name is spelled that day |
| **Accounts** (`/accounts`) | Cash boxes, bank accounts and e-wallets, each in a currency, with a balance and a ledger. Replaces the old single "banks" table - see [Accounts, catalogs and the ledger](#accounts-catalogs-and-the-ledger) |
| **Catalogs** (`/catalogs`) | The lookup data accounts are built from: currencies, bank names, wallet names, account types, wallet types, cash boxes |
| **Catalog / menu** (`/menu`) | Categories + items with prices, cost, tax, barcode/SKU/PLU, unit of measure, per-item icons, and CSV import/export. Wording is the tenant's - see [Catalog naming](#catalog-naming) |
| **Returns** (`/returns`) | Goods coming back, as their own document - see [Returns](#returns) |
| **E-invoicing** (`/invoices`) | EN 16931 invoices and credit notes, UBL 2.1 XML, TLV QR - see [E-invoicing](#e-invoicing-en-16931) |
| **Margin & pricing** (`/margins`) | What the shop actually earns, ABC analysis, price history, stock valuation - see [Margin and pricing](#margin-and-pricing) |
| **Expenses** | Track outgoings per account/date |
| **Balance** (`/balance`) | Money in (completed orders) vs money out (expenses, with salaries as a subtotal), per account and in total |
| **Reports** | Aggregated reporting with **PDF** export and a year-long activity heatmap |
| **Constants** | Custom, user-defined lookup lists and items, plus the catalog wording |
| **Team & payroll** | Team members with a daily or monthly **salary**. Paying one writes an expense **and** a salary record in a single transaction, so it lands in Expenses, Balance and Reports at once, against the account it actually came out of |
| **Roles** (`/roles`) | Create roles and tick the permissions they hold, described in plain language rather than by key |
| **Devices** | Registered client devices (for API/sync), with a per-package limit and revocation. A web session can register as a device too |
| **Activity log** | Read-only audit trail of tenant actions, filterable by category, action and date |
| **Settings** | Self-service profile & password, business logo upload, and the WhatsApp connection |
| **Notifications & Search** | In-app notification center, and a global search with keyboard navigation (`Ctrl`/`⌘`+`K`, arrows, `Enter`) |
| **Sync** | Offline `push` / `pull` endpoints for mobile clients, plus a browser-side queue |
| **Help** | In-app help / usage guide page (`/help`) |

Every list backed by a table is paginated, with a per-page control whose choice is remembered.

### Accounts, catalogs and the ledger

The old design had one `banks` table holding a name, a type and a phone. That could not express "the same bank in two currencies", "three cash drawers", or "what is this account's balance and how did it get there" - so it was replaced.

An [`Account`](app/Models/Tenant/Account.php) is one of three **kinds** ([`AccountKind`](app/Enums/AccountKind.php)): `cash`, `bank`, `wallet`. It points at rows in the shared catalogs - a `Currency`, and then a `BankName` + `AccountType` + IBAN, or a `WalletName` + `WalletType` + wallet phone, or a `CashBox`. It carries a holder name, a balance, an active flag, a contact phone and a **WhatsApp number**.

Balances move through [`LedgerEntry`](app/Models/Tenant/LedgerEntry.php) rows rather than by writing to a column, so an account can always show *how* it reached the figure it displays. Orders link to the account they were paid into and, separately, to the account change came out of.

The six catalogs are declared once in [`CatalogRegistry`](app/Services/Tenant/CatalogRegistry.php) - model, singular/plural label, icon, whether it has a symbol, whether it is currency-scoped, and the foreign key that points at it - and a single controller and screen serve all six. Adding a seventh is a registry entry, not a new CRUD stack.

Existing installations are carried across by [`LegacyBankMigrator`](app/Services/Tenant/LegacyBankMigrator.php), invoked from a data migration that rewrites `transfers` and `expenses` onto accounts and then **drops the `banks` table**. The migration is intentionally irreversible.

### Catalog naming

The catalog ships worded for the license's vertical - a restaurant gets «قائمة الطعام / قسم / طبق», a hardware-adjacent grocery gets «البقالة / فئة / صنف» - because the app makes no assumption about what is being sold.

Four values are the tenant's own, seeded at provisioning from `BusinessType::naming()` and editable from Constants. They live in `tenant_settings`:

| Key | Means |
|-----|-------|
| `catalog_label` | the section itself |
| `catalog_category_label` | one grouping, singular |
| `catalog_item_label` | one entry, singular |
| `catalog_icon` | the icon the section shows in the sidebar |

Resolved once per request by [`CatalogNaming`](app/Services/Tenant/CatalogNaming.php), which falls back to the defaults if the table is unreachable so a missing label can never take a page down. Each category and entry also carries an **icon** chosen from a trade-grouped whitelist ([`CatalogIcons`](app/Services/Tenant/CatalogIcons.php)); an entry with no icon falls back to its category's, then to a neutral glyph.

> When a label is composed from a **tenant-supplied word**, use a placeholder - `__('إضافة :label', ['label' => $naming->item()])` - never string concatenation. Arabic adjectives agree in gender, so `":label جديدة"` is broken for every masculine noun a tenant might choose.

### The vertical modules

Each is gated on a capability, and each has both a Blade screen and a read-only API twin.

| Module | Route | Capability | What it does |
|--------|-------|------------|--------------|
| **Inventory** | `/inventory` | `inventory` | Per-item stock cards and manual movements (adjustment, waste, transfer) |
| **Stocktakes** | `/stock-counts` | `inventory` | Open a sheet, count, post the variance. Opening snapshots what the ledger believes, so the variance stays meaningful while the shop keeps trading |
| **Purchasing** | `/purchases`, `/suppliers` | `purchasing` | Suppliers, purchase orders, goods receipt. A purchase is a draft until it is received; receiving is the moment stock and cost change, and it happens once |
| **Batches** | `/batches` | `batches` | Lot numbers, expiry, FEFO issuing, write-offs, and the recall question: how much is still here, and who did we sell the rest to |
| **Serials** | `/serials` | `serials` | Per-unit identity, captured on receipt and on sale. Serials are never auto-invented |
| **Warranty** | `/warranty` | `warranty` | Claims and RMA. Cover is computed from the serial and then **frozen onto the claim**, because six months later the cover will have lapsed and the record would start lying about what was decided at the time |
| **Variants** | `/variants` | `variants` | Size × colour generation. A variant is a `menu_item` with a parent, so stock, purchases, sales and batches work on it unchanged. Generating is additive; removing one is refused once it has moved stock |
| **Recipes** | `/recipes` | `recipes` | Bills of materials. The explosion stops at whatever is actually stocked, because a bakery holding finished dough depletes the dough while a kitchen mixing it per order depletes the flour |
| **Production** | `/production` | `recipes` + `inventory` | Batch manufacturing: one event that both consumes components and creates finished goods. Posting is one-way |
| **Modifiers** | `/modifiers` | `modifiers` | Option groups and options, attached to items and priced at the till |
| **Allergens** | `/allergens` | `allergens` | The fourteen EU 1169/2011 allergens per item, as *contains* or *may contain*, with a reviewed-at stamp |
| **Fitment** | `/fitment` | `fitment` | "Does this fit?" - by vehicle (make/model/year, or a VIN) or by a part number off the old box, with OE references and supersessions |
| **Scheduling** | `/scheduling` | `scheduling` | Appointments and service resources. One rule carries the module: a resource cannot be in two places at once |
| **Work orders** | `/work-orders` | `scheduling` | Jobs on the floor - parts and hours - handed to the ordinary sale path to be billed, rather than invoicing themselves |
| **Fuel** | `/fuel` | `fuel_station` | Tanks, pumps, price history, shifts, meter readings and dips. Two independent measurements of the same thing; the size and direction of their disagreement is the finding |
| **Pharmacy** | `/pharmacy` | `prescription` | Rx vs OTC, ATC codes, control schedules and the dispensing register. The module **refuses** rather than warns |

### Inventory and costing

[`StockService`](app/Services/Tenant/StockService.php) is the **only** writer of the stock ledger, and two rules hold throughout:

1. **Movements are appended, never edited.** A wrong movement is answered with an opposite one, so the history stays readable and the sync channel stays conflict-free.
2. **On-hand is derived.** There is no cached balance to drift out of agreement with the rows that produced it.

Costing is **weighted average**, which IAS 2 permits alongside FIFO and which is the sane default for a shop: it needs no layer tracking and survives partial receipts. Each inflow recomputes the item's average; each outflow is **stamped** with the average as it stood, so cost of goods sold is recorded rather than recalculated later against a number that has since moved.

[`StockMovementType`](app/Enums/StockMovementType.php) says *why* stock moved - purchase, sale, return in/out, adjustment, transfer, waste, count, production, consumption - while the sign of the quantity says which way. Only `purchase`, `return_in` and `production` are inflows, and only inflows recompute the average.

### Returns

Every trade needs this and no two agree on the rules, so [`ReturnService`](app/Services/Tenant/ReturnService.php) separates the part that is fixed from the part that is not.

**Fixed:** a sale is never edited or deleted. The return is its own document pointing at the original, and both stay in the ledger - reversing by deletion would make yesterday's takings depend on when you looked at them.

**Not fixed:** whether the goods can be sold again. That is decided by the vertical, not by the shop.

Returns are **not** capability-gated: every trade takes goods back. What differs is the policy, and that is applied inside the service rather than by hiding the screen.

### E-invoicing (EN 16931)

[`InvoiceService`](app/Services/Tenant/InvoiceService.php) issues invoices from a sale and credit notes from a return. Three things it gets right because they are the three that get audited:

- **Numbering has no gaps.** A counter row is locked while a number is taken, rather than reading `max + 1` - which under two concurrent tills produces either a duplicate or a hole, and both are findings.
- **Tax is computed per line and reported per category.** Shelf prices here include tax and the standard wants line amounts net of it, so the net is derived rather than stored twice and allowed to disagree. [`TaxCategory`](app/Enums/TaxCategory.php) follows UN/EDIFACT 5305, and keeps zero-rated apart from exempt - both charge nothing and are legally different, and the rate alone cannot tell them apart.
- **Each document hashes the one before it.** Editing an old invoice breaks every hash after it, which is why nothing in the application offers to.

[`UblRenderer`](app/Services/Tenant/UblRenderer.php) renders EN 16931 as **UBL 2.1** (`GET /invoices/{id}/xml`) - a pure transformation, built with DOM rather than string concatenation so an ampersand in a customer's name or Arabic in a seller's comes out well-formed every time. [`InvoiceQr`](app/Support/InvoiceQr.php) produces the base64 **TLV** payload (seller, tax number, timestamp, total, tax) that the regional regimes specify.

> **What it does not do:** sign with a tax-authority certificate, or submit for clearance. Those need credentials and a per-country integration, and claiming them without doing them would be worse than leaving them out.

Invoicing is not capability-gated either - no tax authority cares what trade issued a document.

### Margin and pricing

Turnover is the number every till reports and the least useful one in the building. [`MarginService`](app/Services/Tenant/MarginService.php) answers the harder question: after what the goods cost, what is left - per item, per category, and where it is leaking. It backs `/margins`, `/margins/pricing`, `/margins/valuation` and `/margins/history/{item}`, plus an ABC breakdown over the API.

Three things it is careful about, because they are where margin reports usually go quietly wrong:

- **Cost comes from the ledger, not from the item.** Every outflow was stamped with the weighted average as it stood at that moment. Multiplying today's cost by last month's quantity would revalue history every time a supplier changes a price, and last month's margin would move while you watched it.
- **Revenue is net of the order discount and net of tax.** A discount reduces what was earned; tax was never the shop's. Reporting either gross inflates the margin, and the inflation is largest exactly where discounts are heaviest - which is where the report is most likely to be read.
- **An assembled item's cost is theoretical, and says so.** What leaves the shelf for a sandwich is bread and cheese; with four sandwiches on an order there is no way back from a flour movement to the dish that caused it. The per-dish figure is the recipe rolled up at current component costs, and the real number lives in the **consumption variance**, which is where over-portioning and theft actually show up.

Price changes are recorded in `price_changes`, so `/margins/history/{item}` can show what a price was and when it moved.

These screens are gated on the **catalog** permission rather than a new one: whoever sets a price is who the reports are written for, and the figures are meaningless to anyone who cannot act on them.

### The public site

The same application serves the marketing front door: `/` (landing), `/privacy`, `/terms`, `/security`, `/help`, plus `/sitemap.xml` and `/robots.txt` generated from the route table rather than kept as static files.

Two details are load-bearing:

- **The landing page prices what is actually sold.** Its plan table used to be written by hand, so it drifted the moment a package changed - advertising 50 devices on a tier that grants 10. A view composer in [`AppServiceProvider`](app/Providers/AppServiceProvider.php) reads the real `Package` rows and the operator's `trial_days` setting, so the page cannot promise something the app will not honour. It **degrades rather than throwing**: this is the public front door, so an unreachable or unmigrated database renders the "talk to us" card instead of a 500.
- **`/help` runs `tenant.session` but not `auth:tenant`.** It renders the tenant shell, and a tenant-shell view without a resolved connection is how that page used to 500. [`PublicPagesTest`](tests/Feature/PublicPagesTest.php) asserts structurally that no public route renders a tenant-guard view without the resolver.

The landing page's contact form (throttled 6/hour, with a honeypot) writes a `ContactMessage` and emails the configured support address, failing silently to the log if mail is not set up - the message is already stored, and the operator reads it at `/admin/contact-messages`.

### Super Admin platform

The Super Admin portal is a **full server-rendered Blade UI** under `/admin/*` (see [`routes/web.php`](routes/web.php) and [`resources/views/admin`](resources/views/admin)) - not just an API surface. The `/api/v1/admin/*` endpoints in [REST API](#rest-api) mirror most of the same capabilities for programmatic clients.

| Page | URL | What it does |
|------|-----|--------------|
| **Dashboard** | `/admin/dashboard` | License KPIs (total / active / trial / suspended / expired) plus a finance snapshot and recent activity |
| **Licenses** | `/admin/licenses`, `/admin/licenses/{id}` | Issue a license (provisions or links a real database), inspect one - including a live read-only peek at its tenant's team roster - **suspend**, **activate**, re-run its migrations, change package, **change business type** |
| **Packages** | `/admin/packages` | Create / edit / delete the sellable tiers, their device limit and their `PackageFeature` set |
| **Boxes** | `/admin/boxes` | Platform cash boxes (CRUD) |
| **Partners** | `/admin/partners` | Business partners and their profit-share percentage (CRUD) |
| **Payments** | `/admin/payments` | Record a customer payment - credits the box **and** renews the license atomically |
| **Finance** | `/admin/finance` | Consolidated summary (box balances, partner shares), plus the withdrawal and platform-expense forms |
| **Settlements** | `/admin/settlements`, `/admin/settlements/{id}` | Partner clearing (**المقاصة**) - see below |
| **Backups** | `/admin/backups` | On-demand platform/tenant backups (download / delete) |
| **Contact messages** | `/admin/contact-messages` | The landing page's contact form inbox (read / delete) |
| **Activity log** | `/admin/logs` | Platform-wide read-only audit trail |
| **Settings** | `/admin/settings` | Typed platform settings, declared in [`SettingRegistry`](app/Settings/SettingRegistry.php) |
| **Profile** | `/admin/profile` | The operator's own name, email and password |

> Withdrawals and platform expenses have no page of their own - both are forms on `/admin/finance`, since both exist to move money out of a box.

Platform settings are **declared, not free-form**: [`SettingRegistry`](app/Settings/SettingRegistry.php) groups them, gives each a label, description, `SettingType` (text / email / number / boolean / textarea), default and validation rules, and [`PlatformSettings`](app/Settings/PlatformSettings.php) reads them. `trial_days` and `support_email` are read straight from there by the provisioner and the public pages, so a value the operator changed cannot be contradicted by a constant hard-coded somewhere else.

### A box can never go negative

Both money-out paths — a partner withdrawal and a platform expense — are guarded **twice**, and the pairing is the point:

| Where | What it catches |
|-------|-----------------|
| [`ChecksBoxBalance`](app/Http/Requests/Admin/Concerns/ChecksBoxBalance.php) on the FormRequest | the ordinary case, returned as an `amount` field error with the shortfall spelled out |
| Inside the services' **locked** transaction | two submissions racing each other, which both pass a request-level check read against the same starting balance |

Only the second is authoritative. `lockForUpdate()` alone prevents a lost update, not an overdraw, and there is no `CHECK` constraint on `boxes.box_balance` to catch one either — so before this, withdrawing 10 000 from a box holding 850 simply left it at −9 150, and every figure built on it (the finance summary, partner shares, the dashboard tile) reported a number that cannot exist. Comparison is `bccomp` on the decimal strings, never float arithmetic, because these are `decimal(12,2)` money columns.

### Partner clearing (المقاصة)

A settlement closes a period and states what each partner is owed. The arithmetic lives in one place, [`SettlementService`](app/Services/Admin/SettlementService.php):

```
distributable   = payments − expenses
entitlement(i)  = distributable × percentage(i)
net(i)          = entitlement(i) − drawn(i)
retained        = distributable − Σ entitlement        (the residual)
```

Three decisions worth knowing:

- **Withdrawals are not subtracted from `distributable`.** They are distributions *of* profit, not a cost of earning it; subtracting them would pay partners twice for the same money. They are netted per partner as `drawn` instead.
- **`retained` is computed as the residual**, not as `distributable × (1 − Σ pct)`. That makes `Σ entitlement + retained == distributable` exact, so a rounding remainder can never go missing.
- **Period windows are half-open, `[start, end)`.** `expenses.date` is a date cast stored as a datetime string; an inclusive `BETWEEN` upper bound silently dropped every row on the final day of the period.

A run is created as a **draft** (one open draft at a time), reviewed, then **confirmed**. Confirmed runs are immutable, and each line snapshots the partner's name and percentage so a later edit to the partner never rewrites a statement that has already been agreed.

**Percentages summing over 100% are refused**, not merely flagged. They would hand out more than the period earned and drive `retained` negative — recording the platform as owing money it never made. A warning could not be the guard here, because a run can be confirmed immediately and a confirmed settlement cannot be edited or deleted afterwards. The message names the actual total, since this is nearly always a slip in the partner list, and the clearing page disables the run button for the same condition and links to the percentages. Exactly 100% and under 100% are both normal and unaffected.

> **Partner shares appear on two screens and legitimately differ.** The dashboard and finance pages show `box balance × percentage` — a balance-sheet view of who owns the cash on hand. A settlement distributes `(payments − expenses) × percentage` — an income view of what was earned this period. Box balance also holds opening capital that was never profit, so the same partner can show a much larger number on the dashboard. Both are correct about different questions; the cards say so explicitly and link to the settlement rather than being reconciled into one figure.

## Domain model

Primary keys are **ULIDs** across the app. Financial tenant models use **soft deletes** so historical records (and the names they reference) survive deletion. The platform schema is **23 tables**; a tenant database is **68**.

**Platform models** ([`app/Models`](app/Models)): `Admin`, `Package`, `License`, `DatabaseConnection`, `Box`, `Partner`, `Withdrawal`, `Payment`, `Expense`, `Setting`, `Settlement`, `SettlementLine`, `ActivityLog`, `Backup`, `ContactMessage`, and `TenantPersonalAccessToken` - Sanctum's token model made connection-aware, so one registered class serves both the platform `mysql` connection (for `Admin` tokens) and the per-request `tenant` connection (for team-member tokens).

**Tenant models** ([`app/Models/Tenant`](app/Models/Tenant)), grouped by what they are for:

| Group | Models |
|-------|--------|
| Access | `TeamMember`, `Role`, `Permission`, `Device`, `AuditLog` |
| Selling | `Transfer`, `TransferLineItem`, `TransferLineItemModifier`, `Customer`, `SaleReturn`, `SaleReturnLine`, `Invoice`, `InvoiceLine` |
| Money | `Account`, `AccountType`, `BankName`, `WalletName`, `WalletType`, `CashBox`, `Currency`, `LedgerEntry`, `Expense`, `SalaryPayment` |
| Catalog | `MenuCategory`, `MenuItem`, `Modifier`, `ModifierGroup`, `MenuItemAllergen`, `MenuItemVariantValue`, `RecipeComponent`, `PriceChange` |
| Stock | `StockMovement`, `Location`, `Batch`, `SerialNumber`, `StockCount`, `StockCountLine`, `ProductionRun`, `Supplier`, `Purchase`, `PurchaseLineItem` |
| Vertical | `WarrantyClaim`, `ItemFitment`, `ItemReference`, `VehicleMake`, `VehicleModel`, `Appointment`, `ServiceResource`, `WorkOrder`, `WorkOrderLine`, `DispenseRecord`, `FuelTank`, `FuelPump`, `FuelShift`, `FuelPrice`, `FuelTankDip`, `FuelMeterReading` |
| Config & sync | `ConstantList`, `ConstantListItem`, `TenantSetting`, `SyncEvent` |

**Append-only logs are indexed on `created_at`.** Both `activity_logs` (platform) and `audit_log` (tenant) grow for the life of the account and are read exclusively in `created_at DESC` order — the log pages, their API twins, and the two dashboards' "latest N". Without an index that is a full scan plus a filesort on every page load: free on demo data, unbounded on a real account.

`activity_logs` carries `created_at` plus a composite `(admin_id, created_at)`, since its filter narrows by admin and still sorts by date. `audit_log` carries `created_at` plus `(category, created_at)` and `(action, created_at)` - **MySQL uses one index per table reference**, so a single-column `category` index let it filter *or* sort, never both, and the rest was a filesort. Leading with the filtered column and trailing with the sort column lets one index serve the whole query; the standalone `created_at` index stays because it is what the unfiltered page uses.

**Key enums** ([`app/Enums`](app/Enums), 28 of them):

| Enum | Values |
|------|--------|
| `BusinessType` | the 12 verticals |
| `BusinessCapability` | the 16 capability axes |
| `PackageFeature` | `transfers`, `menu`, `reports`, `banks`, `expenses`, `team`, `devices`, `constants`, `backups`, `whatsapp` |
| `PermissionKey` | the 9 keys listed above |
| `LicenseStatus` | `trial`, `active`, `suspended`, `expired` |
| `RoleKey` | `owner`, `manager`, `staff` |
| `TransferStatus` | `completed`, `pending`, `failed` |
| `PaymentMethod` / `ChangeMethod` | `cash`, `bank` |
| `AccountKind` | `cash`, `bank`, `wallet` |
| `StockMovementType` | `purchase`, `sale`, `return_in`, `return_out`, `adjustment`, `transfer`, `waste`, `count`, `production`, `consumption` |
| `UnitOfMeasure` | UN/CEFACT Rec 20 codes (`H87`, `KGM`, `LTR`, `HUR`, …) |
| `InvoiceType` | `standard`, `simplified`, `credit_note` (UNCL 1001: 388 / 381) |
| `TaxCategory` | `S`, `Z`, `E`, `O`, `AE` (UN/EDIFACT 5305) |
| `Allergen` / `AllergenPresence` | the 14 EU allergens; `contains`, `may_contain` |
| `SerialStatus` | `in_stock`, `sold`, `returned`, `scrapped` |
| `WarrantyClaimStatus` | `open`, `in_repair`, `resolved`, `rejected`, `replaced` |
| `WorkOrderStatus` | `received`, `diagnosing`, `quoted`, `approved`, `in_progress`, `done`, `delivered`, `cancelled` |
| `AppointmentStatus` | `booked`, `confirmed`, `in_progress`, `done`, `cancelled`, `no_show` |
| `SalaryPeriod` | `daily`, `monthly` |
| `SettlementStatus` | `draft`, `confirmed` |
| `SyncOperation` | `create`, `update`, `delete` |
| `Backup*` / `Audit*` | backup status/trigger and audit action/category/entity types |

## Tech stack

- **PHP 8.3**, **Laravel 13** (including the `#[Fillable]` model attribute)
- **Laravel Sanctum** - API token authentication
- **WhatsApp Cloud API** (Meta Graph API, called through Laravel's HTTP client - no SDK) - new-order notifications and order-confirmation links
- **barryvdh/laravel-dompdf** - PDF receipts (API) and report exports
- **Laravel Reverb** - installed for future real-time work; **no broadcasting is wired up yet** (nothing in `app/` broadcasts, and there is no `config/reverb.php`)
- **Laravel Tinker** (REPL), **Pint** (code style), **Pail** (live log viewer - ⚠️ *Linux/macOS only, requires the `pcntl` extension*)
- **Frontend:** Blade + **Tailwind CSS 4** + **Vite 8** + **Alpine.js**, with self-hosted fonts and a service worker
- **Database:** MySQL / MariaDB - one platform database plus one database per tenant
- **Testing:** PHPUnit 12, Mockery, FakerPHP

## Requirements

- PHP **8.3+**, with **`bcmath`** - money is compared as decimal strings, never floats, and the box-balance guard and settlement reconciliation both depend on it
- Composer
- **MySQL 8+** (or MariaDB) - the provisioner creates a real database and user per tenant, so the configured DB user needs `CREATE DATABASE`, `CREATE USER`, and `GRANT` privileges (Laragon's `root` account has these out of the box). On shared hosting, use the provisioner's **link** mode with a database cPanel already created.
- Node.js **20+** and npm
- **`mysqldump`** on disk - the backup feature shells out to it. Its path is set by `MYSQLDUMP_BINARY` in [`config/backup.php`](config/backup.php), which defaults to a Laragon-specific Windows path; override it on any other machine.

## Getting started

```bash
# 1. Install PHP & JS dependencies
composer install
npm install

# 2. Set up the environment
cp .env.example .env
php artisan key:generate
```

Configure the platform database in `.env` (MySQL is required - see [Configuration](#configuration)):

```dotenv
APP_NAME=Hawalaty

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hawalaty
DB_USERNAME=root
DB_PASSWORD=
```

```bash
# 3. Create the platform schema, then seed the admin, the packages and a real tenant
php artisan migrate
php artisan db:seed

# 4. Optional: fill that tenant with realistic Arabic sample data
php artisan db:seed --class=DemoTenantSeeder

# 5. Optional: fill the platform side (boxes, partners, payments, a settlement)
php artisan db:seed --class=PlatformDemoSeeder

# 6. Build front-end assets
npm run build
```

Run the whole stack (PHP server + queue worker + log tail + Vite) with one command:

```bash
composer dev
```

> ### ⚠️ `composer dev` does not work on Windows
>
> The script runs four processes under `concurrently ... --kill-others`, and one of them is
> `php artisan pail`. **Pail requires the `pcntl` extension, which does not exist on Windows** - so
> it exits immediately, and `--kill-others` then tears down the server, queue worker and Vite with
> it. Run the pieces separately instead:
>
> ```bash
> php artisan serve                                   # http://localhost:8000
> php artisan queue:listen --tries=1 --timeout=0      # separate terminal
> npm run dev                                         # separate terminal
> ```
>
> And to follow the log (Pail's job) use PowerShell:
>
> ```powershell
> Get-Content storage\logs\laravel.log -Wait -Tail 30
> ```
>
> Removing `"php artisan pail --timeout=0"` (and `logs` from `--names`) in `composer.json` makes
> `composer dev` cross-platform.

The app is now available at `http://localhost:8000` - tenant login at `/login`, Super Admin at `/admin/login`.

> **Platform vs. tenant migrations:** `php artisan migrate` applies only the **platform** schema in [`database/migrations`](database/migrations). The **tenant** schema in [`database/migrations/tenant`](database/migrations/tenant) is *not* run by the plain command - it is applied per-tenant automatically during provisioning, or manually with `php artisan tenants:migrate` (see [Artisan commands](#artisan-commands)).

## Configuration

- **Why MySQL is required.** Although `.env.example` defaults `DB_CONNECTION` to `sqlite`, the tenant provisioner issues `CREATE DATABASE` / `CREATE USER` / `GRANT` statements against the **`mysql`** connection to build each tenant's isolated database, and the tenant schema uses MySQL-only DDL. Point both `DB_CONNECTION=mysql` and the `mysql` connection at the same server so platform data and tenant provisioning share one MySQL instance.
- **Sessions, cache & queue** default to the `database` driver (`SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`), so the `composer dev` script also runs a queue worker.
- **Broadcasting** ships as Reverb in `composer.json` but is not wired up; `BROADCAST_CONNECTION` defaults to `log`.
- **Backups** are configured in [`config/backup.php`](config/backup.php) and run via the scheduler (see [Scheduled tasks](#scheduled-tasks)).
- **WhatsApp** is configured in [`config/whatsapp.php`](config/whatsapp.php) but every value is **overridable per tenant** from `/settings/whatsapp` - see [WhatsApp order notifications](#whatsapp-order-notifications). `WHATSAPP_DRIVER` defaults to `log`, which renders the message into the log instead of delivering it, so the feature works on a fresh clone with no credentials. Duplicate suppression (`WHATSAPP_DEDUPE_TTL`, default 3600s) needs a **shared** cache store - the default `CACHE_STORE=database` qualifies; under the `array` store it degrades to a no-op (risking a duplicate message, never a lost order).

## Default credentials

After running `php artisan db:seed`:

| Account | Login page | Email / code | Password |
|---------|-----------|--------------|----------|
| Platform Admin | `/admin/login` | `admin@hawalaty.test` | `password` |
| Tenant owner | `/login` | `owner@hawalaty.test` + the printed license code | `password` |

[`DatabaseSeeder`](database/seeders/DatabaseSeeder.php) creates the platform admin, then calls [`PackageSeeder`](database/seeders/PackageSeeder.php) (Basic / Pro / Enterprise) and [`TenantSeeder`](database/seeders/TenantSeeder.php), which provisions a real tenant («بيت التميس») on the Enterprise package and **prints its generated license code and login credentials** at the end of the seed run.

Two optional seeders fill in sample data and are **not** part of `db:seed`:

- [`DemoTenantSeeder`](database/seeders/DemoTenantSeeder.php) - fills that tenant with realistic Arabic data: currencies, banks and wallets, accounts, a catalog, staff and salaries, customers, orders and expenses.
- [`PlatformDemoSeeder`](database/seeders/PlatformDemoSeeder.php) - fills the platform side: a cash box, partners with percentages, payments, withdrawals, expenses, contact messages and a settlement.

The whole seed is idempotent - re-running it never leaks orphan databases.

## Artisan commands

| Command | Description |
|---------|-------------|
| `php artisan tenants:migrate {code}` | Run the tenant migrations against a single tenant's database |
| `php artisan tenants:migrate --all` | Migrate every active/trial license that has a database connection |
| `php artisan licenses:expire-overdue` | Mark overdue licenses as expired |
| `php artisan backups:scheduled` | Run the scheduled backup of the **platform** database |
| `php artisan db:seed` | Seed the platform admin, the packages and the tenant |
| `php artisan db:seed --class=DemoTenantSeeder` | Fill the tenant workspace with sample data |
| `php artisan db:seed --class=PlatformDemoSeeder` | Fill the platform side with sample data |
| `composer dev` | Start server + queue + logs + Vite concurrently - ⚠️ **broken on Windows**, see [Getting started](#getting-started) |
| `composer test` | Clear config and run the test suite |
| `composer setup` | Install, key, migrate, npm install and build in one pass |

## Scheduled tasks

Defined in [`routes/console.php`](routes/console.php):

- `licenses:expire-overdue` - **daily**. Flips trial/active licences past their date to `expired` and writes one `activity_logs` row each. A licence is valid **through** its expiry date, so the comparison is strictly `<`.
- `backups:scheduled` - **daily at 02:00**

In production, ensure the Laravel scheduler runs - add a single cron entry:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

These two are the only code here that runs with nobody watching, which is why [`ScheduledCommandsTest`](tests/Feature/Console/ScheduledCommandsTest.php) also asserts they are still **registered** and still resolve to real commands. `Schedule::command()` takes a string, so a typo does not fail at boot - it fails silently at 02:00, and the first symptom is a customer whose licence never expired or a backup nobody took.

> **Known gap: tenant databases have no scheduled backup.** `backups:scheduled` dumps the *platform* database only - the licence registry, not customer data. Looping every licence is a one-line change but needs staggering or a queue so it does not fire N `mysqldump`s at once, plus a retention policy; see the docblock on [`RunScheduledBackup`](app/Console/Commands/RunScheduledBackup.php). Until that lands, tenant data relies on whatever backs up the MySQL server itself.

## REST API

All endpoints live under the `/api/v1` prefix (see [`routes/api.php`](routes/api.php)). Responses are JSON; unauthenticated API requests receive a plain `401`.

**Authentication**

| Method & path | Notes |
|---------------|-------|
| `POST /api/v1/auth/login` | Tenant login → Sanctum token (rate-limited 6/min) |
| `POST /api/v1/auth/signup` | Provisions a brand-new tenant |
| `POST /api/v1/auth/logout` | Requires `X-License-Code` + bearer token; **revokes** the token |
| `POST /api/v1/admin/auth/login` | Super Admin login → Sanctum token |

**Tenant routes** - require **both** the `X-License-Code` header and a Sanctum bearer token:

```
GET/POST/PUT/DELETE  /api/v1/transfers          GET /api/v1/history
GET                  /api/v1/transfers/{id}/receipt      → application/pdf
GET                  /api/v1/dashboard/stats, /api/v1/dashboard/weekly-activity
GET                  /api/v1/customers, /api/v1/customers/{id}
GET                  /api/v1/notifications
POST                 /api/v1/notifications/{id}/read, /api/v1/notifications/read-all
GET                  /api/v1/search?q=
… /menu-items  /menu-categories  /expenses  /constant-lists
… /team        /roles            /devices   /activity-log  /reports
POST /api/v1/sync/push        GET /api/v1/sync/pull
```

**The vertical modules**, each behind the *same* capability gate as its screen:

```
capability:inventory   GET /inventory, /inventory/{item}
  capability:batches   GET /batches, /batches/expiring, /batches/{id}/sold-on
  capability:serials   GET /serials, /serials/{id}
  capability:warranty  GET /warranty, /warranty/lookup        POST /warranty
capability:variants    GET /variants, /variants/{item}
capability:fitment     GET /fitment/parts, /fitment/reference, /fitment/vin,
                           /fitment/vehicles, /fitment/{item}/supersessions
capability:scheduling  GET /appointments, /appointments/slots, /service-resources,
                           /work-orders, /work-orders/{id}
```

A client that queries a module the trade does not have gets a **403 saying so**, rather than an empty list it would mistake for "nothing to show".

These endpoints are **read-heavy by design**. Writes that move stock, money or goods stay on the paths that already own their transaction - a second entry point into a return or a stock adjustment would be a second place for the rules to be got wrong. The exceptions are deliberate: raising a warranty claim, and issuing an invoice.

**Returns, invoicing and margins** are not capability-gated, for the same reasons their screens are not:

```
GET  /api/v1/returns, /returns/{id}, /transfers/{id}/return-policy
GET  /api/v1/invoices, /invoices/verify, /invoices/{id}, /invoices/{id}/xml
POST /api/v1/invoices/sales/{transfer}
GET  /api/v1/margins, /margins/abc, /margins/pricing, /margins/valuation
```

`/search` returns the same groups as the header search and honours the **same permission rules** - accounts and catalog entries only appear for a member who may manage them. Both surfaces call one [`SearchService`](app/Services/Tenant/SearchService.php); they used to be separate copies, which is how they drifted.

**Admin / platform routes** - require an admin Sanctum token and the `admin` middleware, and **never** the `X-License-Code` header:

```
GET/POST/PUT/DELETE  /api/v1/admin/packages
GET/POST … suspend|activate|change-package  /api/v1/admin/licenses
… /boxes  /partners  /withdrawals  /payments  /expenses  /settings
GET /api/v1/admin/activity-log
GET /api/v1/admin/finance/summary
GET/POST/DELETE + preview|confirm  /api/v1/admin/settlements
GET/POST/DELETE + download  /api/v1/admin/backups
```

`GET /api/v1/admin/settlements/preview` returns the live, uncommitted position for the open period without writing anything. A run refused by the *state* rather than the payload - a draft already exists, or the period ends before the open period starts - returns **422 with a message**, so a client can tell it apart from a validation failure.

### Search terms

Every search box on every list page goes through the `whereSearch` macro registered in [`AppServiceProvider`](app/Providers/AppServiceProvider.php), backed by [`SearchTerm`](app/Support/SearchTerm.php).

The point is that a search box should answer the question that was typed into it. Each page used to build its own `"%{$search}%"`. Bindings kept that injection-safe, which is why it never looked wrong - but `%` and `_` remained **wildcards** inside the pattern, so the query quietly widened: `abu_ali@x.com` also matched `abuXali@x.com`, and a lone `%` returned the whole table through a box that is supposed to require two real characters.

Two details are load-bearing:

- **The escape character is `!`, not backslash.** A backslash has to be written `'\\'` in MySQL and `'\'` in SQLite, so the SQL text would differ per driver - on a codebase that runs MySQL for tenants and SQLite for the platform suite. `!` is literal in both.
- **The macro is not called `whereLike`.** Laravel 13 ships a native `whereLike()`, which compiles to a bare `LIKE ?` with no ESCAPE clause and passes the value through untouched - so it has the same hole, *and* a macro of that name would be shadowed by the real method and silently never run.

The OR-group is always parenthesised, so a term spread across three columns cannot escape its own condition and swallow the status and date filters beside it.

## Offline sync

Mobile/SPA clients work offline and reconcile through two endpoints, both handled by [`SyncService`](app/Services/Tenant/SyncService.php):

- `POST /api/v1/sync/push` - the client uploads queued create / update / delete operations ([`SyncOperation`](app/Enums/SyncOperation.php)).
- `GET /api/v1/sync/pull?since=N` - the client downloads every event after cursor `N`, in order.

**Scope is `transfer` and `expense`.** Those are what staff record with no signal. Accounts, catalog entries and team members are configuration, changed deliberately at a desk, so giving them an offline write path would buy conflict handling nobody needs.

**Ordering and idempotency.** `sync_events.server_sequence` is an auto-incrementing integer that doubles as the pull cursor - which is why that table alone does not use ULIDs. `client_uuid` is unique, so a resend after a dropped connection comes back in `alreadyApplied[]` rather than applying twice.

**Every event gets its own transaction**, not one for the batch, so a single bad event lands in `failed[]` while the rest of the batch still applies.

### The browser also works offline

The web app takes orders with no connection, and pages already visited stay readable. Scope stops there on purpose: a queued *edit* needs conflict resolution that a shop counter gains nothing from, so editing and the configuration screens still require the network.

| Piece | Role |
|-------|------|
| [`resources/js/offline.js`](resources/js/offline.js) | The IndexedDB queue, the flush, and the sign-out wipe |
| [`resources/sw.js`](resources/sw.js) | Service worker: network-first for pages, cache-first for hashed build assets |
| [`Web\ServiceWorkerController`](app/Http/Controllers/Web/ServiceWorkerController.php) | Serves `/sw.js` with the current build id stamped into its cache name |
| [`Web\SyncController`](app/Http/Controllers/Web/SyncController.php) | `POST /sync/push` - the drain the page posts to |

Five decisions behind it:

- **`POST /sync/push` is a separate, session-authed route** rather than the API one. The page is authenticated by a cookie; calling `/api/v1/sync/push` would mean keeping a Sanctum token in the browser, an XSS-readable credential worth far more than the queue it would serve. Both controllers call the same `SyncService`, so they cannot diverge.
- **The permission is checked on arrival.** Having been offline when an order was written is not a way around a permission the member does not hold.
- **IndexedDB, not `localStorage`.** A queued order has to survive a tab crash and a phone dying mid-shift; `localStorage` is synchronous (it would block the order screen on every write) and shares a small origin-wide cap.
- **`sw.js` is generated, not static.** The worker used a fixed `hawalaty-v1`, so its cache outlived every deploy: it kept a page along with the hashed assets that page referenced, and Vite deletes those on the next build - leaving a cached page asking for JavaScript that no longer exists. The controller stamps a **build id derived from `public/build/manifest.json`**, which changes whenever any hashed asset does and never when nothing has. That changes the *file* (so the browser installs a new worker at all) and the *cache name* (so `activate` drops what the previous build left). The response is `no-cache`, or the update would be delayed by exactly as long as the HTTP cache holds it.
- **The URL stays `/sw.js`.** A worker registers from a URL, and a hashed filename would register as a *new* worker on every deploy while the old one kept serving.

The service worker never answers a POST from cache: an order must reach the server or fail loudly enough for the page to queue it. `/login`, `/logout`, `/sync` and `/api` are never cached, so a cached shell can never show a signed-out page to someone signed in, or a stale CSRF token that fails every later post. Signing out clears both the queue and the cache.

### Installable to a home screen

[`public/manifest.webmanifest`](public/manifest.webmanifest) puts the workspace on a shop tablet's home screen in `standalone` display, which is where the offline queue already earns its keep — the browser chrome has no use at a counter.

- **Declared on the tenant shells including the login page**, because a browser only offers to install from a page that carries a manifest, and the login page is the first thing anyone opens.
- **Not declared on `/admin/*`**, which shares the guest shell for its own login. `start_url` is the tenant dashboard, so an install from the platform console would put the wrong app on an operator's home screen.
- **`apple-touch-icon` is a PNG.** iOS ignores SVG for the home screen and falls back to a screenshot of the page.

The icons are redrawn from `favicon.svg`'s own geometry with GD rather than rasterised, since Imagick with librsvg is not available here.

> `public/favicon.ico` was a **zero-byte file** that three layouts linked with `rel="icon"`. A browser honouring that link gets an empty `200`, draws a blank icon, and can beat the SVG that was working — which is how it survived an earlier favicon fix. `ViewMarkupTest` now fails on any linked icon that is missing or empty.

**A session that dies mid-shift must not look like a successful push.** The flush posts with `redirect: 'manual'`, because a timed-out session redirects `/sync/push` to `/login` and the default `follow` turns that into a `200` HTML response — `response.ok` would be true, and the queue would survive only because `JSON.parse` chokes on markup. Orders kept by an exception rather than by design is one login route away from a flush that reports success with nothing sent, hiding a dead session while orders pile up. With `manual`, a redirect arrives opaque (status `0`, not ok) and is refused outright; the response envelope is then checked to actually be the sync engine's before any order is treated as delivered.

### Addressing a record the server has never seen

An update or delete must say which record it means, and a record created offline has no server id yet. So a client may name either:

| Field | Use |
|-------|-----|
| `payload.id` | a server id, for a record the client pulled |
| `payload.clientUuid` | the uuid of the **create event** that made it |

The second resolves through the event log's own `entity_id`, and is what lets a client create, edit and delete the same record while offline and push all three in one batch.

Two behaviours worth knowing:

- **An order update can only change its `status`.** The amount and line items are computed server-side from the catalog; the payload is validated with [`UpdateTransferRequest`](app/Http/Requests/Tenant/Transfer/UpdateTransferRequest.php)'s rules, so a client cannot rewrite what an order was worth.
- **Deleting something already deleted succeeds.** Sync converges on end states - the client asked for a state that already holds, and failing would leave it retrying forever over a record two devices both removed.

## WhatsApp order notifications

When an order is created with `payment_method = bank`, Hawalaty sends a formatted Arabic "new order" message to the WhatsApp number stored on the **account** the operator selected, carrying a link the recipient can open to **confirm or reject** the order.

**`accounts.whatsapp_phone` is the number.** It is stored in **E.164** (`+972595817016`) and normalised on write by [`PhoneNumber::toE164()`](app/Services/WhatsApp/PhoneNumber.php), called from `prepareForValidation()` on both the store and update account requests, so the web form and the REST API normalise identically. The normaliser converts Arabic-Indic digits, strips separators, applies the default country code to bare local numbers only, and collapses a leftover trunk zero (`+9720595817016` → `+972595817016`). Anything it cannot parse is left intact and rejected visibly by the `regex:/^\+[1-9]\d{7,14}$/` rule, rather than being silently nulled.

### Settings are per tenant, with config as the fallback

[`config/whatsapp.php`](config/whatsapp.php) supplies the defaults; [`WhatsAppSettings`](app/Services/WhatsApp/WhatsAppSettings.php) reads `tenant_settings` first and falls back to config per key, so a tenant that has configured nothing still behaves exactly as `.env` says.

A tenant connects its own WhatsApp Business account from **`/settings/whatsapp`** (gated on `package:whatsapp` and `constants.manage`): enabled, driver, access token, phone number id, WABA id, app secret, webhook verify token, and the template's name and language. The page also shows the **webhook URL** to paste into Meta and the **effective driver** actually in force.

The access token and app secret are treated as **secrets**. They come back from the server masked as bullets, never as their value, and are written **only when the field is non-blank** - so saving the form without retyping them keeps what is stored rather than blanking a working connection. A **test send** button on the same page proves the connection without creating an order.

### Drivers

`WHATSAPP_DRIVER` (or the tenant's override) selects the transport. An **unrecognised value falls back to `log`** rather than throwing - a typo must never break the order screen.

| Driver | Behaviour |
|--------|-----------|
| `log` *(default)* | **Renders the full message to `storage/logs/laravel.log` and delivers nothing.** A fresh clone therefore demonstrates the feature with zero credentials, and nothing leaves the machine. |
| `cloud` | Real delivery via the **Meta WhatsApp Cloud API**. If the token or phone number id is blank it logs a warning and falls back to `log`. |
| `null` | Sends nothing and reports success. For tests. |

> **The default driver does not deliver anything.** Real delivery requires the `cloud` driver *and* provider credentials.

### The Cloud API requires an approved template

The `cloud` driver **always** sends an approved WhatsApp *template*, never free-form text. Meta rejects free-form messages sent outside a 24-hour customer-initiated window with **error `131047`**, and a bank's number never initiates a conversation - so free-form would fail every time.

The template is named by `WHATSAPP_TEMPLATE_NEW_ORDER` (default `new_order`) in the language given by `WHATSAPP_TEMPLATE_LANGUAGE` (default `ar`). Its body must have **exactly as many positional parameters as `WHATSAPP_TEMPLATE_VARIABLES` says** - default **8**, in this order:

| # | Value |
|---|-------|
| 1 | Order reference (`#` + last 8 of the id) |
| 2 | Date and time, Arabic-formatted |
| 3 | Customer name |
| 4 | Customer phone |
| 5 | Items summary |
| 6 | Total |
| 7 | Destination account name |
| 8 | Confirmation link |

Setting the count to `1` collapses all of it into a single rendered line for a one-parameter template. A mismatch between the template and the count returns **error `132000`**.

Every parameter that could contain a phone number, a reference or a URL is wrapped in Unicode isolates, so an LTR value cannot reorder the Arabic sentence around it.

### Order confirmation links

A transfer carries a `confirmation_token`. [`OrderConfirmationLink`](app/Services/WhatsApp/OrderConfirmationLink.php) turns it into a public URL - `/order/confirm/{license}/{token}` - that resolves the tenant from the license code in the path, so the recipient needs no account. Acting on it (throttled to 10/min) marks the order confirmed or rejected and **notifies the member who created it** in the in-app notification center.

### The inbound webhook

`GET|POST /webhooks/whatsapp` is exempt from CSRF and handled by [`WhatsAppWebhookController`](app/Http/Controllers/Web/WhatsAppWebhookController.php):

- **`GET`** answers Meta's subscription handshake, comparing `hub_verify_token` with `hash_equals` and echoing `hub_challenge` only on a match.
- **`POST`** verifies `X-Hub-Signature-256` as an HMAC-SHA256 of the **raw body** against the app secret and refuses anything that does not match. With no app secret configured the check is skipped rather than failing closed, so a local clone can receive test events.

Both read the verify token and app secret from **config, not tenant settings** - and necessarily so: an inbound webhook carries no `X-License-Code` and no session, so there is no tenant resolved to read settings from. Per-tenant values still exist for the outbound side.

### Sending is synchronous, never queued

[`WhatsAppSender`](app/Services/WhatsApp/WhatsAppSender.php) is called inline from `TransferService::create()`, inside the request. This is deliberate:

- `QUEUE_CONNECTION` is `database` and **no worker is assumed to be running**, so dispatching a job would write a `jobs` row that is never processed while reporting success to the operator.
- More seriously, [`TenantConnectionResolver`](app/Services/TenantConnectionResolver.php) **mutates process-global config** to point the `tenant` connection at the current license's database. A long-lived queue worker would leak one tenant's connection into another tenant's job.

Timeouts are correspondingly short (`WHATSAPP_CONNECT_TIMEOUT=3`, `WHATSAPP_TIMEOUT=5`) because the operator pays for them while waiting on the order screen. `send()` takes primitives only, so the feature can move behind a queued job the day a dedicated per-tenant worker exists.

### A WhatsApp failure can never fail or roll back an order

The order is already committed before the sender runs. Every failure path returns a [`WhatsAppResult`](app/Services/WhatsApp/WhatsAppResult.php) and logs instead of throwing, and the call site wraps it in `try/catch (Throwable)` anyway. `WhatsAppResult` distinguishes three outcomes - `sent`, `failed` (carrying the HTTP status and Meta's error code, since `131047` / `132000` / `190` each demand a different operator action), and `skipped` (nothing was wrong; the message simply did not apply: cash order, no account, deleted account, no number, feature disabled, or a de-duplicated double-submit). A failed send **releases the dedupe key**, so a retry is possible.

### Environment variables

All read by [`config/whatsapp.php`](config/whatsapp.php).

**Overridable per tenant** (twelve keys, mirrored into `tenant_settings`): `WHATSAPP_ENABLED`, `WHATSAPP_DRIVER`, `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_WABA_ID`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`, `WHATSAPP_TEMPLATE_NEW_ORDER`, `WHATSAPP_TEMPLATE_LANGUAGE`, `WHATSAPP_TEMPLATE_VARIABLES`, `WHATSAPP_TEST_RECIPIENTS`, `WHATSAPP_DEFAULT_COUNTRY_CODE`.

**Deployment-wide only**: `WHATSAPP_BASE_URL`, `WHATSAPP_API_VERSION`, `WHATSAPP_CONNECT_TIMEOUT`, `WHATSAPP_TIMEOUT`, `WHATSAPP_DEDUPE_TTL`.

## Localization

The UI is **Arabic-first (RTL)** and bilingual (Arabic / English):

- Language is switched via `GET /locale/{ar|en}` and stored in the session.
- The [`SetLocale`](app/Http/Middleware/SetLocale.php) middleware applies the chosen locale to every web request, setting `<html lang>` / `dir` and the font. Arabic is the default.
- Strings resolve through `__()` against the JSON catalogs [`lang/ar.json`](lang/ar.json) + [`lang/en.json`](lang/en.json) (**2 866 keys each**), plus `lang/{ar,en}/validation.php` for localized validation messages.
- **The translation keys are the Arabic source strings.** `lang/ar.json` is an identity map (key === value) that exists to enumerate the catalog, while `lang/en.json` maps each Arabic key to its English translation - so Blade calls e.g. `__('حفظ')`.
- **Both files are sorted with plain `SORT_STRING`** and must hold identical key sets. That is mechanical on purpose: they were previously ordered by a collation nothing could reproduce, which made every insertion a judgement call.

#### A missing key is invisible, so a test looks for it

`__()` falls back to returning its argument. A string with no catalog entry therefore renders *perfectly* in Arabic and silently renders **Arabic to English users too** - nothing throws, nothing is logged, and the page looks right to whoever wrote it. That is how 128 strings, most of the Super Admin portal, stayed untranslated without anyone noticing.

[`TranslationFilesTest`](tests/Feature/TranslationFilesTest.php) now scans `app/`, `resources/`, `routes/`, `config/` and `database/` for every `__()` and `trans_choice()` argument and fails with the offending string and file if it has no key. It also rejects an `en.json` value left as its Arabic source.

### RTL notes

Three mistakes account for most directional bugs in this codebase, all already fixed but easy to reintroduce:

- **`dir` on a block element also sets `text-align`.** Putting `dir="ltr"` on a `<td>` to keep a phone number or amount readable left-aligns the whole cell while its `<th>` stays start-aligned, and the column stops lining up. Put the `dir` on an inner inline `<span>` instead.
- **Directional glyphs need `rtl:-scale-x-100`.** Arrows, `send`, `logout` and `north_east` point the wrong way in RTL otherwise.
- **A time axis runs oldest-left, and `dir="ltr"` must cover the whole chart.** See below.

#### Time reads left to right, in every chart

Three charts plot time — the activity heatmap, the dashboard's weekly bars, and the reports' daily revenue — and all three are pinned `dir="ltr"` even in the Arabic UI. Letting them inherit RTL flips the axis, and a trend that reads right-to-left is not what anyone expects from a chart; it is the same reasoning that already keeps every date, phone number and amount `dir="ltr"`.

**Pin the whole chart, not one row of it.** The weekly chart once had its bars and its day captions as two sibling rows with `dir="ltr"` on only the bars. The bars ran oldest-left, the captions still ran oldest-right, and every bar was labelled with the wrong day — an order placed on a Monday displayed under Thursday. Nothing errored and the page looked plausible.

The fix was structural rather than corrective: each caption now lives **inside** the column it belongs to, so `dir` can only ever apply to both together. For the reports chart the attribute sits on the wrapper rather than the scroller, which also keeps the scroll-affordance gradient on the edge that actually overflows.

> **A test that reads source order cannot catch this.** Both layouts emit the days oldest-first, because the loop does; the flip happens in CSS at render time. The first attempt at this guard asserted exactly that and passed against the broken markup. [`HttpLayerSmokeTest`](tests/Feature/Tenant/HttpLayerSmokeTest.php) now asserts the structural property instead — each caption precedes the next column's bar — verified by reproducing the mirrored layout and watching it fail.

## Design system

Everything lives in [`resources/css/app.css`](resources/css/app.css). **Tailwind CSS v4 is configured in CSS, not JavaScript** - there is no `tailwind.config.js`, and adding one will not be read.

If your editor flags `Unknown at rule @custom-variant` or `@source`, that is the CSS language server not knowing Tailwind v4 syntax. The build is correct; point the editor at the Tailwind CSS IntelliSense extension or silence `css.lint.unknownAtRules`.

> **Never run `npm run build` straight after `php artisan view:clear`.** `app.css` scans `storage/framework/views/*.php` via `@source` (that directory is gitignored, so it needs declaring explicitly). With it emptied, the build succeeds, says nothing, and ships a stylesheet missing every utility that only appears in compiled Blade - about 18 kB of rules. Run `php artisan view:cache` first, or just build before clearing.

**Colour is two-layered.** Raw `R G B` triples are declared as `--c-<name>` under `:root` and again under `.dark`, then mapped once inside `@theme`:

```css
:root { --c-primary: 0 35 111; }
.dark { --c-primary: 182 196 255; }
@theme { --color-primary: rgb(var(--c-primary)); }
```

One utility name (`text-primary`) therefore follows the theme with no `dark:` variant at the call site. A few tokens are **deliberately fixed** and do not invert - `--c-primary-container`, `--c-secondary-container` and the `--c-sidebar*` family - because they are always a dark surface carrying light text.

**The dark variant is redefined, and this line is load-bearing:**

```css
@custom-variant dark (&:where(.dark, .dark *));
```

Tailwind v4 defaults `dark:` to `@media (prefers-color-scheme: dark)`. This app toggles a `.dark` class, so without that line **every `dark:` utility silently does nothing** - the utility compiles, the class lands in the HTML, and the colour never changes. That was a real bug: status-pill text measured **1.62:1** against its background in dark mode and **11.75:1** after the fix.

A single **unlayered** rule restyles every native `<select>` across both apps: it replaces the browser's default arrow - which renders on the wrong side under RTL and ignores the theme - with two inline SVG chevrons, one per colour scheme, positioned on the inline-end side from the page's `dir`. It is kept outside Tailwind's cascade layers so its arrow-side padding wins over layered `px-*` utilities in Tailwind v4. `<select multiple>` opts out.

### Fonts are vendored, not linked

Fonts are declared in [`vite.config.js`](vite.config.js) through `laravel-vite-plugin/fonts` and served from `/build` like any other hashed asset - so nothing is fetched from a third party at runtime, and `sw.js` caches them, which is what lets an offline load keep its typography and its icons.

- **Tajawal** (400/500/700/800, latin + arabic) is the UI face, with 400 and 700 preloaded. **Cairo** and **Inter** are declared but not preloaded; Inter carries numerals and small caps labels.
- **Material Symbols Outlined** is vendored as a single `woff2` in [`resources/fonts`](resources/fonts) via the `local()` provider. It is **not** the full release: pinning the axes the app never varies (`opsz 24, wght 400, GRAD 0`, leaving only `FILL` variable) cuts it from **3.9 MB to 447 KB**. Nothing is subset by icon *name* on purpose - catalogue icons come from a tenant's saved value and several views build the ligature in PHP, so a name-subset font would silently render an unlisted icon as its own text. See [`resources/fonts/README.md`](resources/fonts/README.md) for how to regenerate it.
- A **`preferWoff2`** Vite plugin drops the `woff` fallback for any face that also has a `woff2`, and deletes the orphaned files from `public/build/assets`.

**Icon fonts are ligature-based.** `<span class="material-symbols-outlined">search</span>` renders an icon because the font maps the *word* `search` to a glyph - so before the font loads, the page shows the literal word. The guard hides icons until the font is ready:

```css
.material-symbols-outlined { visibility: hidden; }
html.icons-ready .material-symbols-outlined { visibility: visible; }
```

The rule is intentionally **unlayered**, because Google ships `.material-symbols-outlined` unlayered and a layered rule would lose the cascade. On navigation the flag is read synchronously from `sessionStorage` and applied before first paint; going through `document.fonts.load()` (a promise, resolved *after* a paint) left one visibly iconless frame on every page change even though the font was already in cache. A `<noscript>` block reveals the icons unconditionally, and [`<x-fonts>`](resources/views/components/fonts.blade.php) takes an `:icons="false"` prop so the print receipt does not pull 447 kB to a thermal printer that will never render a glyph from it.

## Project structure

```
app/
├── Console/Commands/     # tenants:migrate, licenses:expire-overdue, backups:scheduled
├── Enums/                # BusinessType, BusinessCapability, PackageFeature, PermissionKey,
│                         #   LicenseStatus, StockMovementType, TaxCategory, ...
├── Exceptions/           # InvalidLicenseException, InsufficientBoxBalanceException,
│                         #   DeviceLimitReachedException
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/       # REST API (tenant) + Api/V1/Admin (platform)
│   │   └── Web/          # Blade web app (tenant + Auth) + Concerns/;
│   │                     #   Web/Admin/ is the full Super Admin portal
│   ├── Middleware/       # tenant.header, tenant.session, admin, package, capability, SetLocale
│   ├── Requests/         # FormRequests: Admin/*, Tenant/*, Web/*, Auth/*
│   └── Resources/        # API JSON resources: Admin/*, Tenant/*
├── Models/               # Platform models (License, Package, Admin, Box, ...)
│   └── Tenant/           # Tenant models (Transfer, Account, MenuItem, StockMovement, ...)
├── Notifications/Tenant/ # NewOrderNotification, OrderStatusChangedNotification
├── Policies/Tenant/      # Per-model authorization (Transfer, MenuItem, Role, ...)
├── Providers/            # AppServiceProvider (whereSearch macro, Sanctum model, composers)
├── Services/
│   ├── Admin/            # Platform services (License, Package, Finance, Settlement, Backup, ...)
│   ├── Tenant/           # Tenant services (Transfer, Stock, Invoice, Margin, Return, Fuel, ...)
│   ├── WhatsApp/         # Settings, PhoneNumber, NewOrderMessage, Sender, Result, ConfirmLink
│   ├── TenantConnectionResolver.php   # points the `tenant` connection at a license's DB
│   └── TenantProvisioner.php          # creates or links a full new tenant end-to-end
├── Settings/             # PlatformSettings, SettingRegistry, SettingType
└── Support/              # InvoiceQr (TLV), SearchTerm (LIKE escaping), Vin (ISO 3779)
bootstrap/app.php         # middleware aliases, priority pinning, guard-aware redirects
config/                   # auth (guards), database (tenant connection), backup, whatsapp, ...
database/
├── migrations/           # Platform schema (23 tables)
├── migrations/tenant/    # Per-tenant schema (68 live tables, 70 migrations)
└── seeders/              # DatabaseSeeder, PackageSeeder, TenantSeeder,
                          #   DemoTenantSeeder, PlatformDemoSeeder
docs/                     # verticals-analysis.md, landing-page-prompt.md
lang/                     # ar / en (JSON catalogs + validation.php)
resources/
├── css/app.css           # Tailwind v4 entrypoint, theme tokens, global form styling
├── fonts/                # Vendored Material Symbols woff2 (+ how to regenerate it)
├── js/                   # Alpine.js entrypoint + the offline queue
├── sw.js                 # Service worker template (build id stamped in at serve time)
└── views/                # Blade: 141 files across ~57 directories, admin/** included
routes/                   # web.php, api.php, console.php
```

### Shared table conventions

Every paginated table in the app - tenant **and** Super Admin - is built from the same two Blade components plus one controller trait:

| Piece | Role |
|-------|------|
| [`Web\Concerns\HasPerPage`](app/Http/Controllers/Web/Concerns/HasPerPage.php) | Resolves `?per_page=`, **whitelisted to 10 / 50 / 100** - a crafted value can never request an unbounded page size |
| [`<x-per-page>`](resources/views/components/per-page.blade.php) | The 10 / 50 / 100 selector. Auto-submits on change and re-emits every active filter as a hidden input (except `per_page` / `page`), so changing page size preserves filters and resets to page 1 |
| [`<x-table-footer>`](resources/views/components/table-footer.blade.php) | Combines the selector with a themed paginator built from the paginator's own methods rather than `->links()`, so it matches the design system in light/dark and under RTL |
| [`<x-keep-per-page>`](resources/views/components/keep-per-page.blade.php) | Goes inside every GET **filter** form, tenant and admin alike. The selector already re-emits active filters when it submits; without this the reverse never happened, so choosing 100-per-page and then searching silently dropped back to 10 |

The controller must call `->withQueryString()` so page links keep the active filters and `per_page`:

```php
$rows = $query->paginate($this->perPage($request))->withQueryString();
```

```blade
<x-table-footer :paginator="$rows" :perPage="$perPage" />
```

### Accessible names are not optional

Enforced by [`ViewMarkupTest`](tests/Feature/ViewMarkupTest.php), because a control with no accessible name renders perfectly and is only ever noticed by someone using a screen reader.

- **A `<label>` must name its control** — via `for`/`id`, by wrapping it, or by an id referenced through `aria-labelledby`. A bare `<label>` beside an input names nothing.
- **A placeholder is not a name.** Filter inputs and `<select>`s need `aria-label`; a first option reading «كل الحالات» says what the default is, never what the control filters.
- **A dialog needs `aria-labelledby`** and should move focus to its first field when it opens.
- **Ids must be unique per render.** A component used twice on one page — the icon picker is, once for a category and once for an entry — has to derive its ids from something instance-specific. A duplicate id is worse than none: the label points at whichever copy the browser saw first.
- **Every shell starts with a skip link.** All three put a sidebar and header ahead of the page, so reaching content by keyboard meant tabbing past ~30 controls on every load. [`<x-skip-link>`](resources/views/components/skip-link.blade.php) is the first thing in the tab order and hidden until focused.

> **`<main>` carries `tabindex="-1"`, and that is not decoration.** A `<main>` is not focusable by default: without it the browser scrolls to the target but leaves focus where it was, so the next Tab returns to the navigation just skipped. A skip link that only moves the viewport is worse than none, because it looks like it worked.

## Testing

```bash
php artisan test
# or, via the composer script (clears config first):
composer test
```

**926 tests** across **67 files** in `tests/Unit` and `tests/Feature`.

> **657 of them need a MySQL server to be up.** With one unreachable they all error identically - `SQLSTATE[HY000] [2002]` - which looks alarming and is not a code failure. Start MySQL (Laragon's, or any server the credentials below reach) before reading anything into a red run.

> Two of these suites guard against the same kind of defect: one that renders
> perfectly, passes every other test, and is only ever noticed by a user who
> cannot see the screen. A missing translation key falls back to printing the
> Arabic source; a `<label>` with no `for` announces nothing. Neither throws.

Platform tests run against an **in-memory SQLite** database (`phpunit.xml`). Tenant tests do not, and cannot: `database/migrations/tenant` uses MySQL-only DDL, and `RefreshDatabase` only ever runs `database/migrations` anyway. [`TenantTestCase`](tests/TenantTestCase.php) therefore points the `tenant` connection at a **dedicated, disposable MySQL database**, migrates it once, and wraps each test in a transaction it rolls back - the same isolation `RefreshDatabase` gives, done by hand because `migrate:fresh` has to target a non-default connection.

It also binds a synthetic `currentLicense` with **every** package feature enabled and a business type the test can choose, so a capability-gated path is exercised by asking for the vertical that has it rather than by disabling the gate.

So running the full suite needs MySQL reachable; `--testsuite=Unit` and the platform feature tests do not.

`TenantTestCase` finds that server through `DB_HOST` / `DB_PORT` / `DB_USERNAME` / `DB_PASSWORD`, defaulting to Laragon's `127.0.0.1` / `root` / no password - so a stock checkout needs no configuration, and a machine whose MySQL root *has* a password no longer has to patch the test case to run the suite.

Two deliberate limits on that:

- **`DB_DATABASE` is not read.** The harness creates and wipes `hawalaty_testing_tenant` itself, so it must never be aimed at a database you picked for something else.
- **Put test credentials in `.env.testing`, not `.env`,** if your `.env` points at anything you care about. `APP_ENV=testing` is set by `phpunit.xml`, so Laravel prefers `.env.testing` when it exists - which keeps a suite that *creates and drops a database* pointed at a server you chose for exactly that.

| Suite | Covers |
|-------|--------|
| [`Tenant/TransferServiceTest`](tests/Feature/Tenant/TransferServiceTest.php), [`ExpenseServiceTest`](tests/Feature/Tenant/ExpenseServiceTest.php) | The money paths: order creation and totals, expense windows |
| [`Tenant/AccountServiceTest`](tests/Feature/Tenant/AccountServiceTest.php), [`AccountPageTest`](tests/Feature/Tenant/AccountPageTest.php), [`OrderAccountFlowTest`](tests/Feature/Tenant/OrderAccountFlowTest.php) | Accounts, ledger entries, and an order landing against the right account |
| [`Tenant/CatalogPageTest`](tests/Feature/Tenant/CatalogPageTest.php), [`Api/CatalogPayloadTest`](tests/Feature/Api/CatalogPayloadTest.php) | The six shared catalogs, over HTTP and in the API payload |
| [`Tenant/BalanceServiceTest`](tests/Feature/Tenant/BalanceServiceTest.php), [`SalaryServiceTest`](tests/Feature/Tenant/SalaryServiceTest.php) | In-vs-out per account; a salary payment writing both an expense and a salary record atomically |
| [`BusinessTypeTest`](tests/Feature/BusinessTypeTest.php), [`Admin/TenantProvisioningNamingTest`](tests/Feature/Admin/TenantProvisioningNamingTest.php) | Every vertical's capability set, and a new workspace opening with its trade's wording and constant lists |
| [`Tenant/StockServiceTest`](tests/Feature/Tenant/StockServiceTest.php), [`InventoryHttpTest`](tests/Feature/Tenant/InventoryHttpTest.php) | The ledger: appended movements, derived on-hand, weighted-average costing |
| [`Tenant/BatchServiceTest`](tests/Feature/Tenant/BatchServiceTest.php), [`SerialWarrantyTest`](tests/Feature/Tenant/SerialWarrantyTest.php) | FEFO issuing and the recall question; serial identity and frozen warranty cover |
| [`Tenant/RecipeServiceTest`](tests/Feature/Tenant/RecipeServiceTest.php), [`ModifierAndProductionTest`](tests/Feature/Tenant/ModifierAndProductionTest.php) | Recipe explosion stopping at what is stocked; modifiers and production runs |
| [`Tenant/VariantServiceTest`](tests/Feature/Tenant/VariantServiceTest.php), [`FitmentServiceTest`](tests/Feature/Tenant/FitmentServiceTest.php) | Additive variant generation; fitment by vehicle, by VIN and by part number |
| [`Tenant/SchedulingWorkOrderTest`](tests/Feature/Tenant/SchedulingWorkOrderTest.php), [`PharmacyFuelTest`](tests/Feature/Tenant/PharmacyFuelTest.php), [`AllergenServiceTest`](tests/Feature/Tenant/AllergenServiceTest.php) | Double-booking refused, work orders billed through the sale path, dispensing refused rather than warned, wetstock variance, allergen declarations |
| [`Tenant/ReturnServiceTest`](tests/Feature/Tenant/ReturnServiceTest.php) | Returns as their own document, and the verticals that must not restock |
| [`Tenant/InvoiceServiceTest`](tests/Feature/Tenant/InvoiceServiceTest.php), [`InvoiceHttpTest`](tests/Feature/Tenant/InvoiceHttpTest.php) | Gapless numbering under contention, per-line tax, the hash chain, UBL and the QR payload |
| [`Tenant/MarginServiceTest`](tests/Feature/Tenant/MarginServiceTest.php), [`MarginHttpTest`](tests/Feature/Tenant/MarginHttpTest.php) | Cost read from the ledger, revenue net of discount and tax, ABC and valuation |
| [`Tenant/ItemMasterTest`](tests/Feature/Tenant/ItemMasterTest.php), [`MenuServiceTest`](tests/Feature/Tenant/MenuServiceTest.php) | The commerce fields on a catalog item, and the catalog itself |
| [`Tenant/SearchServiceTest`](tests/Feature/Tenant/SearchServiceTest.php) | Search across the tenant tables: phone→orders, soft-deleted rows staying hidden, permission gating |
| [`Tenant/SearchEscapingTest`](tests/Feature/Tenant/SearchEscapingTest.php) | `%` and `_` treated as literals on real rows, the escape character searchable for itself, the OR-group not swallowing a neighbouring filter |
| [`Console/ScheduledCommandsTest`](tests/Feature/Console/ScheduledCommandsTest.php) | The nightly commands: still registered, still resolving to real commands, expiry boundary (valid *through* the expiry date), suspended licences not swept in, idempotent on a second run, failed backup exiting non-zero |
| [`Tenant/SyncServiceTest`](tests/Feature/Tenant/SyncServiceTest.php) | Convergence: resends applying once, one bad event not taking the batch down, editing a record created offline, deleting what is already gone |
| [`Tenant/OfflineOrderQueueTest`](tests/Feature/Tenant/OfflineOrderQueueTest.php), [`OfflineCoverageTest`](tests/Feature/Tenant/OfflineCoverageTest.php) | The browser's drain over HTTP: the permission gate, idempotency, an unfixable payload coming back as failed, and what the worker is allowed to cache |
| [`Tenant/CustomerServiceTest`](tests/Feature/Tenant/CustomerServiceTest.php) | Phone-as-identity, including that a blank name never blanks a known one |
| [`Tenant/CatalogNamingTest`](tests/Feature/Tenant/CatalogNamingTest.php) | Tenant catalog wording and icon fallback |
| [`Tenant/RoleServiceTest`](tests/Feature/Tenant/RoleServiceTest.php), [`TeamServiceTest`](tests/Feature/Tenant/TeamServiceTest.php), [`RolePageTest`](tests/Feature/Tenant/RolePageTest.php) | Permissions, the last-owner guard, and the roles UI |
| [`Tenant/HttpLayerSmokeTest`](tests/Feature/Tenant/HttpLayerSmokeTest.php) | Every tenant page renders, is permission-guarded, and exposes a working paginator with a whitelisted page size |
| [`PublicPagesTest`](tests/Feature/PublicPagesTest.php), [`ContactFormTest`](tests/Feature/ContactFormTest.php) | The public pages (landing, privacy, terms, security, sitemap, robots) and the contact form, plus a structural check that **no public route renders a tenant-guard view without `tenant.session`** - the bug that 500'd `/help` |
| [`Admin/AdminPagesTest`](tests/Feature/Admin/AdminPagesTest.php), [`PlatformSettingsTest`](tests/Feature/Admin/PlatformSettingsTest.php) | Every Super Admin page renders and is guarded; the payment→box→license money flow; typed platform settings |
| [`Admin/SettlementTest`](tests/Feature/Admin/SettlementTest.php) | Clearing arithmetic, the rounding residual, period boundaries, and confirmed runs being immutable |
| [`Api/TenantApiCoverageTest`](tests/Feature/Api/TenantApiCoverageTest.php), [`TenantApiSmokeTest`](tests/Feature/Api/TenantApiSmokeTest.php) | The tenant read endpoints, requested **with data present**, each answering JSON and refusing a guest, plus the `X-License-Code` requirement |
| [`Api/TenantApiModulesTest`](tests/Feature/Api/TenantApiModulesTest.php) | The vertical modules over the API, including that a trade without a capability gets **403**, not an empty list |
| [`Api/TenantApiWriteTest`](tests/Feature/Api/TenantApiWriteTest.php) | The write paths land, and a member without the permission is refused — the API has no hidden nav item to rely on |
| [`Api/AdminApiSmokeTest`](tests/Feature/Api/AdminApiSmokeTest.php), [`AdminSettingsApiTest`](tests/Feature/Api/AdminSettingsApiTest.php) | The platform read endpoints, and that each refuses a **tenant** token — both guards issue Sanctum tokens against the same table |
| [`Api/ApiAuthTest`](tests/Feature/Api/ApiAuthTest.php) | Every login refusal, the 6/minute throttle, and that logout **revokes** the token rather than merely answering politely |
| [`Tenant/CatalogTransferTest`](tests/Feature/Tenant/CatalogTransferTest.php) | CSV export/import over HTTP, including that a name a spreadsheet would execute is neutralised **on the route**, not only in the service |
| [`TranslationFilesTest`](tests/Feature/TranslationFilesTest.php) | **Every `__()` string in the codebase has a catalog entry** (a missing one silently shows Arabic to English users), both files sorted and identical, no English value left as Arabic |
| [`ViewMarkupTest`](tests/Feature/ViewMarkupTest.php) | **Every `<label>` names a control**, every `for` matches exactly one `id`, no id duplicated or written outside a tag, and each shell offers a working skip link |
| [`Tenant/RouteAuthorizationTest`](tests/Feature/Tenant/RouteAuthorizationTest.php) | **Every tenant route that writes checks a permission** — `auth:tenant` only proves someone is signed in, and a hidden nav item is not access control |
| [`Tenant/QueryBudgetTest`](tests/Feature/Tenant/QueryBudgetTest.php) | The main pages cost the **same number of queries at 5 rows as at 25** — an N+1 is invisible to every other kind of test |
| [`ErrorPagesTest`](tests/Feature/ErrorPagesTest.php) | All five custom error views **render**, each offers a way back, and they survive the database being the thing that broke — asserting a status code never reaches these templates |
| [`Tenant/NotificationPageTest`](tests/Feature/Tenant/NotificationPageTest.php), [`OrderWhatsAppRoutingTest`](tests/Feature/Tenant/OrderWhatsAppRoutingTest.php) | The notification centre, and which account an order's message is routed to |
| [`WhatsApp/WhatsAppSenderTest`](tests/Feature/WhatsApp/WhatsAppSenderTest.php), [`WhatsAppWebhookTest`](tests/Feature/WhatsApp/WhatsAppWebhookTest.php) + the two unit tests | The send guard matrix, both drivers, the template parameter contract, E.164 normalisation, Arabic message formatting, and the webhook's handshake and signature check |

```bash
php artisan test --testsuite=Unit
php artisan test tests/Feature/Admin/AdminPagesTest.php
```

## Continuous integration & deployment

[`.github/workflows/main.yml`](.github/workflows/main.yml) is a **deployment** workflow: every push to `main` uploads the working tree to the hosting account over FTP (`SamKirkland/FTP-Deploy-Action`), using the `FTP_HOST` / `FTP_USERNAME` / `FTP_PASSWORD` / `FTP_PATH` repository secrets. `dangerous-clean-slate` is off, so it syncs rather than wiping the remote.

> ⚠️ **There is no CI job, and the deploy does not gate on one.** Nothing runs Pint, the front-end build or the test suite before the upload. Several suites here catch defects that render perfectly and pass every other check — a missing translation key, a `<label>` naming nothing, a write route with no permission check, an N+1, a `<main>` that cannot take focus — and **a guard nobody runs is not a guard**. Run them locally before pushing:
>
> ```bash
> ./vendor/bin/pint --test && npm run build && php artisan test
> ```

Two things a CI job would need whenever one is added:

- **A real MySQL server**, because `database/migrations/tenant` uses MySQL-only DDL and cannot run on sqlite. `TenantTestCase` creates its own `hawalaty_testing_tenant` database on first use, so only the *server* has to exist; the platform half of the suite stays on sqlite.
- **The `bcmath` extension**, which is required rather than optional: the box-balance guard and the settlement reconciliation compare money as decimal **strings**, never floats.

The build should run *before* the tests. `TranslationFilesTest` reads the source, so a build failure would otherwise ship a broken page no PHP test can see.

## Code style

Formatting is enforced with **Laravel Pint**:

```bash
./vendor/bin/pint        # fix
./vendor/bin/pint --test # check only
```

> `--dirty` only inspects files you changed, which is convenient locally and hides drift. Run the full pass before a release.

## License

Built with the [Laravel](https://laravel.com) framework. This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
