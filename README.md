# Hawalaty

> A multi-tenant SaaS platform where each licensed business gets a fully isolated workspace - its own MySQL database - to record transactions, manage a product/menu catalog, customers, banks & wallets, expenses, a role-based team, and reports. It ships as both an Arabic-first (RTL), bilingual server-rendered web app **and** a versioned REST API with offline sync for mobile/SPA clients.

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
  - [Multi-tenancy - database per tenant](#multi-tenancy--database-per-tenant)
  - [Request lifecycle](#request-lifecycle)
  - [Authentication guards](#authentication-guards)
  - [Roles & permissions](#roles--permissions)
  - [Tenant provisioning](#tenant-provisioning)
- [Features](#features)
  - [Tenant workspace](#tenant-workspace)
  - [Catalog naming](#catalog-naming)
  - [Super Admin platform](#super-admin-platform)
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
- [Offline sync](#offline-sync)
- [WhatsApp order notifications](#whatsapp-order-notifications)
- [Localization](#localization)
  - [RTL notes](#rtl-notes)
- [Design system](#design-system)
- [Project structure](#project-structure)
- [Testing](#testing)
- [Code style](#code-style)
- [License](#license)

---

## Overview

**Hawalaty** is a **database-per-tenant** SaaS application. A platform operator sells **licenses** (bundled into **packages**) to businesses. Each license is backed by its own dedicated MySQL database that is created, migrated, and seeded automatically the moment a business signs up - so tenants are physically isolated from one another and from the platform.

Inside their workspace, a tenant's **team members** record day-to-day transactions ("transfers"), manage a product/menu catalog, customers, banks & wallets, expenses, and their own staff with fine-grained permissions, and generate printable receipts and reports.

Everything is exposed through **two delivery surfaces**:

- a **server-rendered Blade web app** (Arabic-first, right-to-left, bilingual AR/EN), and
- a **versioned JSON REST API** (`/api/v1`) for mobile / SPA clients, complete with an offline **push/pull sync** channel.

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

Every tenant model (e.g. [`Transfer`](app/Models/Tenant/Transfer.php)) hard-codes `getConnectionName(): 'tenant'`, so tenant Eloquent queries automatically follow whichever database the resolver most recently selected - they can never accidentally read platform data.

### Request lifecycle

The license is identified differently on each surface, but both resolve **before** authentication runs:

**Web (session cookie):**
```
Request → SetLocale → tenant.session → auth:tenant → controller
                      │                 │
                      │                 └─ authenticates the TeamMember against the tenant DB
                      └─ reads session('license_code'), points `tenant` connection at the right DB
```

**API (bearer token):**
```
Request → throttle:api → tenant.header → auth:sanctum → controller
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

### Roles & permissions

Every tenant database is seeded with **three system roles** over a fixed set of **seven permission keys** ([`App\Enums\PermissionKey`](app/Enums/PermissionKey.php)):

| Role | Permissions |
|------|-------------|
| **Owner** | All 7 permissions |
| **Manager** | All except `team.manage` |
| **Staff** | `transfers.create` only |

The seven permission keys are: `transfers.create`, `menu.manage`, `banks.manage`, `expenses.manage`, `constants.manage`, `devices.manage`, `team.manage`. Owners can define additional custom roles on top of the system ones.

Permissions are enforced at the request boundary by per-model policies in [`app/Policies/Tenant`](app/Policies/Tenant) - `TransferPolicy`, `BankPolicy`, `MenuItemPolicy`, `MenuCategoryPolicy`, `ExpensePolicy`, `ConstantListPolicy`, `DevicePolicy`, `TeamMemberPolicy`, `RolePolicy` - each mapping its actions onto a `PermissionKey`. Controllers authorize explicitly; the FormRequests deliberately return `true` from `authorize()` and leave the decision to the policy, so the web and API surfaces share one authorization path.

### Tenant provisioning

New tenants are created end-to-end by [`App\Services\TenantProvisioner`](app/Services/TenantProvisioner.php) - the **same** service used by both the public signup flow and the demo seeder. In one operation it:

1. Creates the `License` row (status **Trial**, 14-day expiry) linked to the chosen package.
2. Creates a **physical MySQL database + a dedicated MySQL user** with privileges scoped to that database (identifiers are derived from the random license code and whitelist-validated before being interpolated into DDL).
3. Records the connection details in a `DatabaseConnection` row.
4. Runs the tenant migrations against the new database (`php artisan tenants:migrate`).
5. Seeds the 7 permissions, the 3 system roles, and the **owner** team member.
6. Writes a platform `ActivityLog` entry.

If any step fails, it performs best-effort cleanup - dropping the database and user and deleting the platform rows already written - so a failed signup never leaks orphaned databases.

## Features

### Tenant workspace

| Area | What it does |
|------|--------------|
| **Dashboard** | Revenue tile, weekly-activity chart, recent transactions |
| **Transfers** | Record transactions (cash or bank/wallet), with line items, change handling, and a status (completed / pending / failed) |
| **History & receipts** | Searchable transaction history and printable **PDF receipts** (DomPDF) |
| **Customers** | Customer directory. Identity is the **phone number**, not the name - two walk-ins called "أحمد" are two customers, and the same phone is always the same person however the name is spelled that day |
| **Banks & wallets** | Manage banks and e-wallets used for transfers. Each carries an optional **WhatsApp number** (`banks.phone`, stored in E.164) that receives an automatic Arabic message whenever a bank/wallet order is created - see [WhatsApp order notifications](#whatsapp-order-notifications) |
| **Catalog** | Categories + entries with prices, per-entry icons, and CSV import/export. **Not assumed to be food** - see [Catalog naming](#catalog-naming) |
| **Expenses** | Track outgoings per bank/date |
| **Balance** (`/balance`) | Money in (completed orders) vs money out (expenses, with salaries as a subtotal), per account and in total |
| **Reports** | Aggregated reporting with CSV/PDF export and a year-long activity heatmap |
| **Constants** | Custom, user-defined lookup lists and items |
| **Team & payroll** | Team members with a daily or monthly **salary**. Paying one writes an expense **and** a salary record in a single transaction, so it lands in Expenses, Balance and Reports at once, against the account it actually came out of |
| **Roles** (`/roles`) | Create roles and tick the permissions they hold, described in plain language rather than by key |
| **Devices** | Registered client devices (for API/sync), with a per-package limit and revocation |
| **Activity log** | Read-only audit trail of tenant actions |
| **Settings** | Self-service profile & password, plus the catalog wording |
| **Notifications & Search** | In-app notification center, and a global search with keyboard navigation (`Ctrl`/`⌘`+`K`, arrows, `Enter`) |
| **Sync** | Offline `push` / `pull` endpoints for mobile clients |
| **Help** | In-app help / usage guide page (`/help`) |

Every list backed by a table is paginated, with a per-page control whose choice is remembered.

#### Catalog naming

The catalog ships worded as a restaurant menu - "قائمة الطعام", "فئة", "صنف" - because that is the common case, **not** because the app assumes food. A fruit shop, a fuel station and a hardware store run the same screens, and none of them sell "أصناف طعام".

Three labels are therefore the tenant's own, set from Settings and stored in `tenant_settings`:

| Key | Means | Default |
|-----|-------|---------|
| `catalog_label` | the section itself | `قائمة الطعام` |
| `catalog_category_label` | one grouping, singular | `فئة` |
| `catalog_item_label` | one entry, singular | `صنف` |

Resolved once per request by [`CatalogNaming`](app/Services/Tenant/CatalogNaming.php), which falls back to the defaults if the table is unreachable so a missing label can never take a page down. Each category and entry also carries an **icon** chosen from a trade-grouped whitelist ([`CatalogIcons`](app/Services/Tenant/CatalogIcons.php)); an entry with no icon falls back to its category's, then to a neutral glyph.

### Super Admin platform

The Super Admin portal is a **full server-rendered Blade UI** under `/admin/*` (see [`routes/web.php`](routes/web.php) and [`resources/views/admin`](resources/views/admin)) - not just an API surface. The `/api/v1/admin/*` endpoints in [REST API](#rest-api) mirror the same capabilities for programmatic clients.

| Page | URL | What it does |
|------|-----|--------------|
| **Dashboard** | `/admin/dashboard` | License KPIs (total / active / trial / suspended / expired) plus a finance snapshot and recent activity |
| **Licenses** | `/admin/licenses`, `/admin/licenses/{id}` | Issue a license (provisions a real database), inspect one - including a live read-only peek at its tenant's team roster - **suspend**, **activate**, change package |
| **Packages** | `/admin/packages` | Create / edit / delete the sellable product tiers |
| **Boxes** | `/admin/boxes` | Platform cash boxes (CRUD) |
| **Partners** | `/admin/partners` | Business partners and their profit-share percentage (CRUD) |
| **Payments** | `/admin/payments` | Record a customer payment - credits the box **and** renews the license atomically |
| **Finance** | `/admin/finance` | Consolidated summary (box balances, partner shares), plus the withdrawal and platform-expense forms |
| **Settlements** | `/admin/settlements`, `/admin/settlements/{id}` | Partner clearing (**المقاصة**) - see below |
| **Backups** | `/admin/backups` | On-demand platform/tenant backups (download / delete) |
| **Activity log** | `/admin/logs` | Platform-wide read-only audit trail |
| **Settings** | `/admin/settings` | Typed platform settings |

> Withdrawals and platform expenses have no page of their own - both are forms on `/admin/finance`, since both exist to move money out of a box.

#### Partner clearing (المقاصة)

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

## Domain model

Primary keys are **ULIDs** across the app. Financial tenant models use **soft deletes** so historical records (and the names they reference) survive deletion.

**Platform models** ([`app/Models`](app/Models)): `Admin`, `Package`, `License`, `DatabaseConnection`, `Box`, `Partner`, `Withdrawal`, `Payment`, `Expense`, `Setting`, `Settlement`, `SettlementLine`, `ActivityLog`, `Backup`, and `TenantPersonalAccessToken` - Sanctum's token model made connection-aware, so one registered class serves both the platform `mysql` connection (for `Admin` tokens) and the per-request `tenant` connection (for team-member tokens).

**Tenant models** ([`app/Models/Tenant`](app/Models/Tenant)): `TeamMember`, `Role`, `Permission`, `Transfer`, `TransferLineItem`, `Customer`, `Bank`, `MenuCategory`, `MenuItem`, `Expense`, `SalaryPayment`, `ConstantList`, `ConstantListItem`, `TenantSetting`, `Device`, `SyncEvent`, `AuditLog`.

**Key enums** ([`app/Enums`](app/Enums)):

| Enum | Values |
|------|--------|
| `LicenseStatus` | `trial`, `active`, `suspended`, `expired` |
| `TransferStatus` | `completed`, `pending`, `failed` |
| `PaymentMethod` | `cash`, `bank` |
| `ChangeMethod` | `cash`, `bank` |
| `BankType` | `bank`, `wallet` |
| `RoleKey` | `owner`, `manager`, `staff` |
| `PermissionKey` | the 7 keys listed above |
| `SalaryPeriod` | `daily`, `monthly` |
| `SettlementStatus` | `draft`, `confirmed` |
| `SyncOperation` | `create`, `update`, `delete` |
| `Backup*` / `Audit*` | backup status/trigger and audit action/category/entity types |

## Tech stack

- **PHP 8.3**, **Laravel 13**
- **Laravel Sanctum** - API token authentication
- **WhatsApp Cloud API** (Meta Graph API, called through Laravel's HTTP client - no SDK) - new-order notifications
- **barryvdh/laravel-dompdf** - PDF receipts & reports
- **Laravel Reverb** - installed for future real-time work; **no broadcasting is wired up yet** (nothing in `app/` broadcasts)
- **Laravel Tinker** (REPL), **Pint** (code style), **Pail** (live log viewer - ⚠️ *Linux/macOS only, requires the `pcntl` extension*)
- **Frontend:** Blade + **Tailwind CSS 4** + **Vite 8** + **Alpine.js**
- **Database:** MySQL / MariaDB - one platform database plus one database per tenant
- **Testing:** PHPUnit 12, Mockery, FakerPHP

## Requirements

- PHP **8.3+**
- Composer
- **MySQL 8+** (or MariaDB) - the provisioner creates a real database and user per tenant, so the configured DB user needs `CREATE DATABASE`, `CREATE USER`, and `GRANT` privileges (Laragon's `root` account has these out of the box)
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
# 3. Create the platform schema, then seed the admin, packages and a demo tenant
php artisan migrate
php artisan db:seed

# 4. Build front-end assets
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

- **Why MySQL is required.** Although `.env.example` defaults `DB_CONNECTION` to `sqlite`, the tenant provisioner issues `CREATE DATABASE` / `CREATE USER` / `GRANT` statements against the **`mysql`** connection to build each tenant's isolated database. Point both `DB_CONNECTION=mysql` and the `mysql` connection at the same server so the platform data and tenant provisioning share one MySQL instance.
- **Sessions, cache & queue** default to the `database` driver (`SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`), so the `composer dev` script also runs a queue worker.
- **Broadcasting** uses Laravel Reverb; set `BROADCAST_CONNECTION` and the `REVERB_*` variables to enable real-time features.
- **Backups** are configured in [`config/backup.php`](config/backup.php) and run via the scheduler (see [Scheduled tasks](#scheduled-tasks)).
- **WhatsApp order notifications** are configured in [`config/whatsapp.php`](config/whatsapp.php) - see [WhatsApp order notifications](#whatsapp-order-notifications). `WHATSAPP_DRIVER` defaults to `log`, which renders the message into the log instead of delivering it, so the feature works on a fresh clone with no credentials. Duplicate suppression (`WHATSAPP_DEDUPE_TTL`, default 3600s) needs a **shared** cache store - the default `CACHE_STORE=database` qualifies; under the `array` store it degrades to a no-op (risking a duplicate message, never a lost order).

## Default credentials

After running `php artisan db:seed`:

| Account | Login page | Email / code | Password |
|---------|-----------|--------------|----------|
| Platform Admin | `/admin/login` | `admin@hawalaty.test` | `password` |
| Demo tenant owner | `/login` | `owner@demo.test` + the printed license code | `password` |

[`DatabaseSeeder`](database/seeders/DatabaseSeeder.php) creates the platform admin and package catalog, then [`DemoTenantSeeder`](database/seeders/DemoTenantSeeder.php) provisions a complete demo tenant (a Palestinian restaurant with realistic Arabic data: banks, a menu, staff, ~55 transactions and expenses) and **prints its generated license code and login credentials** at the end of the seed run. The whole seed is idempotent - re-running it never leaks orphan databases.

## Artisan commands

| Command | Description |
|---------|-------------|
| `php artisan tenants:migrate {code}` | Run the tenant migrations against a single tenant's database |
| `php artisan tenants:migrate --all` | Migrate every active/trial license that has a database connection |
| `php artisan licenses:expire-overdue` | Mark overdue licenses as expired |
| `php artisan backups:scheduled` | Run the scheduled tenant backups |
| `php artisan db:seed` | Seed the platform admin, packages, and the demo tenant |
| `composer dev` | Start server + queue + logs + Vite concurrently - ⚠️ **broken on Windows**, see [Getting started](#getting-started) |
| `composer test` | Clear config and run the test suite |

## Scheduled tasks

Defined in [`routes/console.php`](routes/console.php):

- `licenses:expire-overdue` - **daily**
- `backups:scheduled` - **daily at 02:00**

In production, ensure the Laravel scheduler runs - add a single cron entry:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## REST API

All endpoints live under the `/api/v1` prefix (see [`routes/api.php`](routes/api.php)). Responses are JSON; unauthenticated API requests receive a plain `401`.

**Authentication**

| Method & path | Notes |
|---------------|-------|
| `POST /api/v1/auth/login` | Tenant login → Sanctum token (rate-limited) |
| `POST /api/v1/auth/signup` | Provisions a brand-new tenant |
| `POST /api/v1/auth/logout` | Requires `X-License-Code` + bearer token |
| `POST /api/v1/admin/auth/login` | Super Admin login → Sanctum token |

**Tenant routes** - require **both** the `X-License-Code` header and a Sanctum bearer token:

```
GET/POST/PUT/DELETE  /api/v1/transfers
GET                  /api/v1/transfers/{id}/receipt      → application/pdf
GET                  /api/v1/dashboard/stats, /api/v1/dashboard/weekly-activity
GET                  /api/v1/customers, /api/v1/customers/{id}
GET                  /api/v1/notifications
POST                 /api/v1/notifications/{id}/read, /api/v1/notifications/read-all
GET                  /api/v1/search?q=
… /banks  /menu-items  /menu-categories  /expenses  /constant-lists
… /team   /roles       /devices          /activity-log  /reports
POST /api/v1/sync/push        GET /api/v1/sync/pull
```

`/search` returns the same four groups as the header search and honours the **same permission rules** - banks and catalog entries only appear for a member who may manage them. Both surfaces call one [`SearchService`](app/Services/Tenant/SearchService.php); they used to be separate copies, which is how they drifted.

**Admin / platform routes** - require an admin Sanctum token and the `admin` middleware, and **never** the `X-License-Code` header:

```
GET/POST/PUT/DELETE  /api/v1/admin/packages
GET/POST … suspend|activate|change-package  /api/v1/admin/licenses
… /boxes  /partners  /withdrawals  /payments  /expenses  /settings
GET /api/v1/admin/finance/summary
GET/POST/DELETE + preview|confirm  /api/v1/admin/settlements
GET/POST/DELETE + download  /api/v1/admin/backups
```

`GET /api/v1/admin/settlements/preview` returns the live, uncommitted position for the open period without writing anything. A run refused by the *state* rather than the payload - a draft already exists, or the period ends before the open period starts - returns **422 with a message**, so a client can tell it apart from a validation failure.

## Offline sync

Mobile/SPA clients work offline and reconcile through two endpoints:

- `POST /api/v1/sync/push` - the client uploads queued create/update/delete operations ([`SyncOperation`](app/Enums/SyncOperation.php)).
- `GET /api/v1/sync/pull` - the client downloads server-side changes.

Sync is backed by the tenant's `Device` and `SyncEvent` models and handled by [`App\Services\Tenant\SyncService`](app/Services/Tenant/SyncService.php).

## WhatsApp order notifications

When a transfer is created with `payment_method = bank`, Hawalaty sends a formatted Arabic "new order" message to the WhatsApp number stored on the bank/wallet the operator selected.

**`banks.phone` *is* the WhatsApp number** - there is no separate column. It is stored in **E.164** (`+972595817016`) and normalised on write by [`PhoneNumber::toE164()`](app/Services/WhatsApp/PhoneNumber.php), called from `prepareForValidation()` on both [`StoreBankRequest`](app/Http/Requests/Tenant/Bank/StoreBankRequest.php) and [`UpdateBankRequest`](app/Http/Requests/Tenant/Bank/UpdateBankRequest.php), so the web form and the REST API normalise identically. The normaliser converts Arabic-Indic digits, strips separators, applies `WHATSAPP_DEFAULT_COUNTRY_CODE` to bare local numbers only, and collapses a leftover trunk zero (`+9720595817016` → `+972595817016`). Anything it cannot parse is left intact and rejected visibly by the `regex:/^\+[1-9]\d{7,14}$/` rule, rather than being silently nulled.

### Drivers

`WHATSAPP_DRIVER` selects the transport. An **unrecognised value falls back to `log`** rather than throwing - a typo in `.env` must never break the order screen.

| Driver | Behaviour |
|--------|-----------|
| `log` *(default)* | **Renders the full message to `storage/logs/laravel.log` and delivers nothing.** A fresh clone therefore demonstrates the feature with zero credentials, and nothing leaves the machine. |
| `cloud` | Real delivery via the **Meta WhatsApp Cloud API**. If `WHATSAPP_TOKEN` or `WHATSAPP_PHONE_NUMBER_ID` is blank it logs a warning and falls back to `log`. |
| `null` | Sends nothing and reports success. For tests. |

> **The default driver does not deliver anything.** Real delivery requires `WHATSAPP_DRIVER=cloud` *and* provider credentials.

### The Cloud API requires an approved template

The `cloud` driver **always** sends an approved WhatsApp *template*, never free-form text. Meta rejects free-form messages sent outside a 24-hour customer-initiated window with **error `131047`**, and a bank's number never initiates a conversation - so free-form would fail every time.

Before `WHATSAPP_DRIVER=cloud` works you must create a template in Meta Business Manager: named by `WHATSAPP_TEMPLATE_NEW_ORDER` (default `new_order`), in the language given by `WHATSAPP_TEMPLATE_LANGUAGE` (default `ar`), whose body has **exactly one** positional parameter `{{1}}` - the whole rendered order text is passed as that single parameter. Any other parameter count returns **error `132000`**.

### Sending is synchronous, never queued

[`WhatsAppSender`](app/Services/WhatsApp/WhatsAppSender.php) is called inline from `TransferService::create()`, inside the request. This is deliberate:

- `QUEUE_CONNECTION` is `database` and **no worker is assumed to be running**, so dispatching a job would write a `jobs` row that is never processed while reporting success to the operator.
- More seriously, [`TenantConnectionResolver`](app/Services/TenantConnectionResolver.php) **mutates process-global config** to point the `tenant` connection at the current license's database. A long-lived queue worker would leak one tenant's connection into another tenant's job.

Timeouts are correspondingly short (`WHATSAPP_CONNECT_TIMEOUT=3`, `WHATSAPP_TIMEOUT=5`) because the operator pays for them while waiting on the order screen. `send()` takes primitives only, so the feature can move behind a queued job the day a dedicated per-tenant worker exists.

### A WhatsApp failure can never fail or roll back an order

The order is already committed before the sender runs. Every failure path returns a [`WhatsAppResult`](app/Services/WhatsApp/WhatsAppResult.php) and logs instead of throwing, and the call site wraps it in `try/catch (Throwable)` anyway. `WhatsAppResult` distinguishes three outcomes - `sent`, `failed` (carrying the HTTP status and Meta's error code, since `131047` / `132000` / `190` each demand a different operator action), and `skipped` (nothing was wrong; the message simply did not apply: cash order, no bank, deleted bank, no number, feature disabled, or a de-duplicated double-submit).

### Migrating existing banks

The data-only migration [`2026_07_18_000001_normalize_bank_phones_to_e164`](database/migrations/tenant/2026_07_18_000001_normalize_bank_phones_to_e164.php) backfills pre-existing rows (e.g. `0599000001`) through the same `PhoneNumber::toE164()` the form uses, so stored values can never diverge from the validation rule. It lives under `database/migrations/tenant`, so plain `php artisan migrate` will **not** run it:

```bash
php artisan tenants:migrate --all
```

Without it, the next operator to edit an untouched bank gets a validation error on a `phone` field they never changed. Unparseable values are left exactly as-is for a human to inspect, and the migration is intentionally irreversible.

### Environment variables

All read by [`config/whatsapp.php`](config/whatsapp.php): `WHATSAPP_ENABLED`, `WHATSAPP_DRIVER`, `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_BASE_URL`, `WHATSAPP_API_VERSION`, `WHATSAPP_TEMPLATE_NEW_ORDER`, `WHATSAPP_TEMPLATE_LANGUAGE`, `WHATSAPP_CONNECT_TIMEOUT`, `WHATSAPP_TIMEOUT`, `WHATSAPP_DEDUPE_TTL`, `WHATSAPP_DEFAULT_COUNTRY_CODE`.

## Localization

The UI is **Arabic-first (RTL)** and bilingual (Arabic / English):

- Language is switched via `GET /locale/{ar|en}` and stored in the session.
- The [`SetLocale`](app/Http/Middleware/SetLocale.php) middleware applies the chosen locale to every web request, setting `<html lang>` / `dir` and the font. Arabic is the default.
- Strings resolve through `__()` against the JSON catalogs [`lang/ar.json`](lang/ar.json) + [`lang/en.json`](lang/en.json) (909 keys each), plus `lang/{ar,en}/validation.php` for localized validation messages.
- **The translation keys are the Arabic source strings.** `lang/ar.json` is an identity map (key === value) that exists to enumerate the catalog, while `lang/en.json` maps each Arabic key to its English translation - so Blade calls e.g. `__('حفظ')`. Both files must keep identical key sets, in the same order - enforced by [`TranslationFilesTest`](tests/Feature/TranslationFilesTest.php).
- Where a label is composed from a **tenant-supplied word**, use a placeholder (`__('إضافة :label', ['label' => $naming->item()])`) rather than gluing on a fixed adjective. Arabic adjectives agree in gender, so `":label جديدة"` is broken for every masculine noun the tenant might choose.

### RTL notes

Two mistakes account for most directional bugs in this codebase, both already fixed but easy to reintroduce:

- **`dir` on a block element also sets `text-align`.** Putting `dir="ltr"` on a `<td>` to keep a phone number or amount readable left-aligns the whole cell while its `<th>` stays start-aligned, and the column stops lining up. Put the `dir` on an inner inline `<span>` instead.
- **Directional glyphs need `rtl:-scale-x-100`.** Arrows, `send`, `logout` and `north_east` point the wrong way in RTL otherwise.

## Design system

Everything lives in [`resources/css/app.css`](resources/css/app.css). **Tailwind CSS v4 is configured in CSS, not JavaScript** - there is no `tailwind.config.js`, and adding one will not be read.

If your editor flags `Unknown at rule @custom-variant` or `@source`, that is the CSS language server not knowing Tailwind v4 syntax. The build is correct; point the editor at the Tailwind CSS IntelliSense extension or silence `css.lint.unknownAtRules`.

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

**Icon fonts are ligature-based.** `<span class="material-symbols-outlined">search</span>` renders an icon because the font maps the *word* `search` to a glyph - so before the font loads, the page shows the literal word. The guard hides icons until the font is ready:

```css
.material-symbols-outlined { visibility: hidden; }
html.icons-ready .material-symbols-outlined { visibility: visible; }
```

The rule is intentionally **unlayered**, because Google ships `.material-symbols-outlined` unlayered and a layered rule would lose the cascade. On navigation the flag is read synchronously from `sessionStorage` and applied before first paint; going through `document.fonts.load()` (a promise, resolved *after* a paint) left one visibly iconless frame on every page change even though the font was already in cache.

## Project structure

```
app/
├── Console/Commands/     # tenants:migrate, licenses:expire-overdue, backups:scheduled
├── Enums/                # LicenseStatus, RoleKey, PermissionKey, TransferStatus, ChangeMethod, ...
├── Exceptions/           # InvalidLicenseException, ...
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/       # REST API (tenant) + Api/V1/Admin (platform)
│   │   └── Web/          # Blade web app (tenant + Auth) + Concerns/HasPerPage;
│   │                     #   Web/Admin/ is the full Super Admin portal
│   ├── Middleware/       # tenant.header, tenant.session, admin (EnsureAdmin), SetLocale
│   ├── Requests/         # FormRequests: Admin/*, Tenant/*, Web/*, Auth/*
│   └── Resources/        # API JSON resources: Admin/*, Tenant/*
├── Models/               # Platform models (License, Package, Admin, Box, ...)
│   └── Tenant/           # Tenant models (Transfer, Bank, MenuItem, TeamMember, ...)
├── Notifications/Tenant/ # NewOrderNotification, OrderStatusChangedNotification
├── Policies/Tenant/      # Per-model authorization (Transfer, Bank, Role, ...)
├── Providers/            # AppServiceProvider
└── Services/
    ├── Admin/            # Platform services (License, Package, Finance, Backup, ...)
    ├── Tenant/           # Tenant services (Transfer, Dashboard, Report, Sync, ...)
    ├── WhatsApp/         # PhoneNumber, NewOrderMessage, WhatsAppSender, WhatsAppResult
    ├── TenantConnectionResolver.php   # points the `tenant` connection at a license's DB
    └── TenantProvisioner.php          # creates a full new tenant end-to-end
bootstrap/app.php         # middleware aliases, priority pinning, guard-aware redirects
config/                   # auth (guards), database (tenant connection), backup, whatsapp, ...
database/
├── migrations/           # Platform schema
├── migrations/tenant/    # Per-tenant schema
└── seeders/              # DatabaseSeeder, PackageSeeder, DemoTenantSeeder
lang/                     # ar / en (JSON catalogs + validation.php)
resources/
├── css/                  # Tailwind entrypoint + global form-control styling
├── js/                   # Alpine.js entrypoint
└── views/                # Blade: dashboard, transfers, admin/**, auth, layouts,
                          #   components/ (per-page, table-footer, ...)
routes/                   # web.php, api.php, console.php
```

### Shared table conventions

Every paginated table in the app - tenant **and** Super Admin, 14 controllers - is built from two Blade components plus one controller trait:

| Piece | Role |
|-------|------|
| [`Web\Concerns\HasPerPage`](app/Http/Controllers/Web/Concerns/HasPerPage.php) | Resolves `?per_page=`, **whitelisted to 10 / 50 / 100** - a crafted value can never request an unbounded page size |
| [`<x-per-page>`](resources/views/components/per-page.blade.php) | The 10 / 50 / 100 selector. Auto-submits on change and re-emits every active filter as a hidden input (except `per_page` / `page`), so changing page size preserves filters and resets to page 1 |
| [`<x-table-footer>`](resources/views/components/table-footer.blade.php) | Combines the selector with a themed paginator built from the paginator's own methods rather than `->links()`, so it matches the design system in light/dark and under RTL |

The controller must call `->withQueryString()` so page links keep the active filters and `per_page`:

```php
$rows = $query->paginate($this->perPage($request))->withQueryString();
```
```blade
<x-table-footer :paginator="$rows" :perPage="$perPage" />
```

A single **unlayered** rule in [`resources/css/app.css`](resources/css/app.css) restyles every native `<select>` across both apps: it replaces the browser's default arrow - which renders on the wrong side under RTL and ignores the theme - with two inline SVG chevrons, one per colour scheme, positioned on the inline-end side from the page's `dir`. It is kept outside Tailwind's cascade layers so its arrow-side padding wins over layered `px-*` utilities in Tailwind v4. `<select multiple>` opts out.

## Testing

```bash
php artisan test
# or, via the composer script (clears config first):
composer test
```

**294 tests, 4 479 assertions.**

Platform tests run against an **in-memory SQLite** database (`phpunit.xml`). Tenant tests do not, and cannot: `database/migrations/tenant` uses MySQL-only DDL, and `RefreshDatabase` only ever runs `database/migrations` anyway. [`TenantTestCase`](tests/TenantTestCase.php) therefore points the `tenant` connection at a **dedicated, disposable MySQL database**, migrates it once, and wraps each test in a transaction it rolls back - the same isolation `RefreshDatabase` gives, done by hand because `migrate:fresh` has to target a non-default connection.

So running the full suite needs MySQL reachable; `--testsuite=Unit` and the platform feature tests do not.

| Suite | Covers |
|-------|--------|
| [`Tenant/TransferServiceTest`](tests/Feature/Tenant/TransferServiceTest.php), [`ExpenseServiceTest`](tests/Feature/Tenant/ExpenseServiceTest.php), [`BankServiceTest`](tests/Feature/Tenant/BankServiceTest.php) | The money paths: order creation and totals, expense windows, bank deletion safety |
| [`Tenant/BalanceServiceTest`](tests/Feature/Tenant/BalanceServiceTest.php), [`SalaryServiceTest`](tests/Feature/Tenant/SalaryServiceTest.php) | In-vs-out per account; a salary payment writing both an expense and a salary record atomically |
| [`Tenant/SearchServiceTest`](tests/Feature/Tenant/SearchServiceTest.php) | Search across four tables: phone→orders, LIKE-wildcard escaping, soft-deleted rows staying hidden, permission gating |
| [`Tenant/CustomerServiceTest`](tests/Feature/Tenant/CustomerServiceTest.php) | Phone-as-identity, including that a blank name never blanks a known one |
| [`Tenant/CatalogNamingTest`](tests/Feature/Tenant/CatalogNamingTest.php) | Tenant catalog wording and icon fallback |
| [`Tenant/RoleServiceTest`](tests/Feature/Tenant/RoleServiceTest.php), [`TeamServiceTest`](tests/Feature/Tenant/TeamServiceTest.php), [`RolePageTest`](tests/Feature/Tenant/RolePageTest.php) | Permissions, the last-owner guard, and the roles UI |
| [`Tenant/HttpLayerSmokeTest`](tests/Feature/Tenant/HttpLayerSmokeTest.php) | Every tenant page renders, is permission-guarded, and exposes a working paginator with a whitelisted page size |
| [`PublicPagesTest`](tests/Feature/PublicPagesTest.php) | The public pages, plus a structural check that **no public route renders a tenant-guard view without `tenant.session`** - the bug that 500'd `/help` |
| [`Admin/AdminPagesTest`](tests/Feature/Admin/AdminPagesTest.php) | Every Super Admin page renders and is guarded; the payment→box→license money flow |
| [`Admin/SettlementTest`](tests/Feature/Admin/SettlementTest.php) | Clearing arithmetic, the rounding residual, period boundaries, and confirmed runs being immutable |
| [`Api/TenantApiCoverageTest`](tests/Feature/Api/TenantApiCoverageTest.php) | The customer, notification, search and receipt endpoints, plus the `X-License-Code` requirement |
| [`TranslationFilesTest`](tests/Feature/TranslationFilesTest.php) | `ar.json` and `en.json` hold the same keys in the same order, and every Arabic key maps to itself |
| [`WhatsApp/WhatsAppSenderTest`](tests/Feature/WhatsApp/WhatsAppSenderTest.php) + the two unit tests | The send guard matrix, both drivers, E.164 normalisation, Arabic message formatting |

```bash
php artisan test --testsuite=Unit
php artisan test tests/Feature/Admin/AdminPagesTest.php
```

## Code style

Formatting is enforced with **Laravel Pint**:

```bash
./vendor/bin/pint        # fix
./vendor/bin/pint --test # check only
```

## License

Built with the [Laravel](https://laravel.com) framework. This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
