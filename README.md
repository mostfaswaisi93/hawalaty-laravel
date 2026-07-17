# Hawalaty

> A multi-tenant SaaS platform where each licensed business gets a fully isolated workspace — its own MySQL database — to record transactions, manage a product/menu catalog, customers, banks & wallets, expenses, a role-based team, and reports. It ships as both an Arabic-first (RTL), bilingual server-rendered web app **and** a versioned REST API with offline sync for mobile/SPA clients.

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
  - [Multi-tenancy — database per tenant](#multi-tenancy--database-per-tenant)
  - [Request lifecycle](#request-lifecycle)
  - [Authentication guards](#authentication-guards)
  - [Roles & permissions](#roles--permissions)
  - [Tenant provisioning](#tenant-provisioning)
- [Features](#features)
  - [Tenant workspace](#tenant-workspace)
  - [Super Admin platform](#super-admin-platform)
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
- [Localization](#localization)
- [Project structure](#project-structure)
- [Testing](#testing)
- [Code style](#code-style)
- [License](#license)

---

## Overview

**Hawalaty** is a **database-per-tenant** SaaS application. A platform operator sells **licenses** (bundled into **packages**) to businesses. Each license is backed by its own dedicated MySQL database that is created, migrated, and seeded automatically the moment a business signs up — so tenants are physically isolated from one another and from the platform.

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

### Multi-tenancy — database per tenant

Each tenant has its **own physical MySQL database**. There is a single `tenant` connection in [`config/database.php`](config/database.php) whose settings are **rewritten at runtime, per request**, to point at the correct tenant database. The rewrite is done by [`App\Services\TenantConnectionResolver`](app/Services/TenantConnectionResolver.php):

1. Look up the `License` by its `license_code`.
2. Reject it if it's **suspended** or **expired**.
3. Read the connection details from the license's related `DatabaseConnection` row.
4. `config(['database.connections.tenant' => ...])` and `DB::purge('tenant')` so the next tenant query hits the right database.

Every tenant model (e.g. [`Transfer`](app/Models/Tenant/Transfer.php)) hard-codes `getConnectionName(): 'tenant'`, so tenant Eloquent queries automatically follow whichever database the resolver most recently selected — they can never accidentally read platform data.

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

Ordering is critical: if the auth guard ran first it would query the wrong database. Laravel's `SortedMiddleware` reorders middleware by a priority list, so both resolvers are explicitly pinned **ahead of** the auth middleware in [`bootstrap/app.php`](bootstrap/app.php) via `prependToPriorityList(...)`. The same file also makes the "redirect unauthenticated users" behavior **guard-aware** — API requests get a plain JSON `401`, `/admin/*` redirects to the admin login, everything else to the tenant login.

If a license becomes invalid **between** page loads (an admin suspends it, or it expires), the web resolver tears down the session and redirects to a dedicated `license.inactive` page instead of letting a stale connection blow up the next query.

### Authentication guards

Four guards are configured in [`config/auth.php`](config/auth.php):

| Guard | Driver | Provider | Used for |
|-------|--------|----------|----------|
| `admin` | session | `Admin` | Super Admin portal (web) |
| `tenant` | session | `TeamMember` (tenant DB) | Tenant team members (web) |
| `sanctum` | token | — | REST API (both tenant and admin routes) |
| `web` | session | `Admin` | Laravel framework default (kept for compatibility) |

The `admin` and `tenant` web guards are declared explicitly (rather than relying on Laravel's implicit default guard) so route middleware can say `auth:admin` / `auth:tenant` unambiguously.

### Roles & permissions

Every tenant database is seeded with **three system roles** over a fixed set of **seven permission keys** ([`App\Enums\PermissionKey`](app/Enums/PermissionKey.php)):

| Role | Permissions |
|------|-------------|
| **Owner** | All 7 permissions |
| **Manager** | All except `team.manage` |
| **Staff** | `transfers.create` only |

The seven permission keys are: `transfers.create`, `menu.manage`, `banks.manage`, `expenses.manage`, `constants.manage`, `devices.manage`, `team.manage`. Owners can define additional custom roles on top of the system ones.

### Tenant provisioning

New tenants are created end-to-end by [`App\Services\TenantProvisioner`](app/Services/TenantProvisioner.php) — the **same** service used by both the public signup flow and the demo seeder. In one operation it:

1. Creates the `License` row (status **Trial**, 14-day expiry) linked to the chosen package.
2. Creates a **physical MySQL database + a dedicated MySQL user** with privileges scoped to that database (identifiers are derived from the random license code and whitelist-validated before being interpolated into DDL).
3. Records the connection details in a `DatabaseConnection` row.
4. Runs the tenant migrations against the new database (`php artisan tenants:migrate`).
5. Seeds the 7 permissions, the 3 system roles, and the **owner** team member.
6. Writes a platform `ActivityLog` entry.

If any step fails, it performs best-effort cleanup — dropping the database and user and deleting the platform rows already written — so a failed signup never leaks orphaned databases.

## Features

### Tenant workspace

| Area | What it does |
|------|--------------|
| **Dashboard** | Revenue tile, weekly-activity chart, recent transactions |
| **Transfers** | Record transactions (cash or bank/wallet), with line items, change handling, and a status (completed / pending / failed) |
| **History & receipts** | Searchable transaction history and printable **PDF receipts** (DomPDF) |
| **Customers** | Customer directory linked to transactions |
| **Banks & wallets** | Manage banks and e-wallets used for transfers |
| **Menu** | Product catalog: categories + items, with import/export |
| **Expenses** | Track outgoings per bank/date |
| **Reports** | Aggregated reporting with export |
| **Constants** | Custom, user-defined lookup lists and items |
| **Team** | Team members, roles, and per-permission access |
| **Devices** | Registered client devices (for API/sync) |
| **Activity log** | Read-only audit trail of tenant actions |
| **Settings** | Self-service profile & password |
| **Notifications & Search** | In-app notification center and global search |
| **Sync** | Offline `push` / `pull` endpoints for mobile clients |

### Super Admin platform

| Area | What it does |
|------|--------------|
| **Packages** | Define the sellable product tiers |
| **Licenses** | Issue, view, **suspend**, **activate**, change package, and expire licenses |
| **Boxes / Partners** | Platform cash boxes and business partners |
| **Withdrawals / Payments** | Money movements against boxes/partners/licenses |
| **Expenses** | Platform-level expense tracking |
| **Finance summary** | Consolidated financial overview |
| **Settings** | Key/value platform settings |
| **Activity log** | Platform-wide audit trail |
| **Backups** | On-demand and scheduled tenant backups (download / delete) |

## Domain model

Primary keys are **ULIDs** across the app. Financial tenant models use **soft deletes** so historical records (and the names they reference) survive deletion.

**Platform models** ([`app/Models`](app/Models)): `Admin`, `Package`, `License`, `DatabaseConnection`, `Box`, `Partner`, `Withdrawal`, `Payment`, `Expense`, `Setting`, `ActivityLog`, `Backup`.

**Tenant models** ([`app/Models/Tenant`](app/Models/Tenant)): `TeamMember`, `Role`, `Permission`, `Transfer`, `TransferLineItem`, `Customer`, `Bank`, `MenuCategory`, `MenuItem`, `Expense`, `ConstantList`, `ConstantListItem`, `Device`, `SyncEvent`, `AuditLog`.

**Key enums** ([`app/Enums`](app/Enums)):

| Enum | Values |
|------|--------|
| `LicenseStatus` | `trial`, `active`, `suspended`, `expired` |
| `TransferStatus` | `completed`, `pending`, `failed` |
| `PaymentMethod` | `cash`, `bank` |
| `BankType` | `bank`, `wallet` |
| `RoleKey` | `owner`, `manager`, `staff` |
| `PermissionKey` | the 7 keys listed above |
| `SyncOperation` | `create`, `update`, `delete` |
| `Backup*` / `Audit*` | backup status/trigger and audit action/category/entity types |

## Tech stack

- **PHP 8.3**, **Laravel 13**
- **Laravel Sanctum** — API token authentication
- **Laravel Reverb** — real-time / WebSocket broadcasting
- **barryvdh/laravel-dompdf** — PDF receipts & reports
- **Laravel Tinker** (REPL), **Pail** (live log viewer), **Pint** (code style)
- **Frontend:** Blade + **Tailwind CSS 4** + **Vite 8** + **Alpine.js**
- **Database:** MySQL / MariaDB — one platform database plus one database per tenant
- **Testing:** PHPUnit 12, Mockery, FakerPHP

## Requirements

- PHP **8.3+**
- Composer
- **MySQL 8+** (or MariaDB) — the provisioner creates a real database and user per tenant, so the configured DB user needs `CREATE DATABASE`, `CREATE USER`, and `GRANT` privileges (Laragon's `root` account has these out of the box)
- Node.js **20+** and npm

## Getting started

```bash
# 1. Install PHP & JS dependencies
composer install
npm install

# 2. Set up the environment
cp .env.example .env
php artisan key:generate
```

Configure the platform database in `.env` (MySQL is required — see [Configuration](#configuration)):

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

The app is now available at `http://localhost:8000` — tenant login at `/login`, Super Admin at `/admin/login`.

> **Platform vs. tenant migrations:** `php artisan migrate` applies only the **platform** schema in [`database/migrations`](database/migrations). The **tenant** schema in [`database/migrations/tenant`](database/migrations/tenant) is *not* run by the plain command — it is applied per-tenant automatically during provisioning, or manually with `php artisan tenants:migrate` (see [Artisan commands](#artisan-commands)).

## Configuration

- **Why MySQL is required.** Although `.env.example` defaults `DB_CONNECTION` to `sqlite`, the tenant provisioner issues `CREATE DATABASE` / `CREATE USER` / `GRANT` statements against the **`mysql`** connection to build each tenant's isolated database. Point both `DB_CONNECTION=mysql` and the `mysql` connection at the same server so the platform data and tenant provisioning share one MySQL instance.
- **Sessions, cache & queue** default to the `database` driver (`SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`), so the `composer dev` script also runs a queue worker.
- **Broadcasting** uses Laravel Reverb; set `BROADCAST_CONNECTION` and the `REVERB_*` variables to enable real-time features.
- **Backups** are configured in [`config/backup.php`](config/backup.php) and run via the scheduler (see [Scheduled tasks](#scheduled-tasks)).

## Default credentials

After running `php artisan db:seed`:

| Account | Login page | Email / code | Password |
|---------|-----------|--------------|----------|
| Platform Admin | `/admin/login` | `admin@hawalaty.test` | `password` |
| Demo tenant owner | `/login` | `owner@demo.test` + the printed license code | `password` |

[`DatabaseSeeder`](database/seeders/DatabaseSeeder.php) creates the platform admin and package catalog, then [`DemoTenantSeeder`](database/seeders/DemoTenantSeeder.php) provisions a complete demo tenant (a Palestinian restaurant with realistic Arabic data: banks, a menu, staff, ~55 transactions and expenses) and **prints its generated license code and login credentials** at the end of the seed run. The whole seed is idempotent — re-running it never leaks orphan databases.

## Artisan commands

| Command | Description |
|---------|-------------|
| `php artisan tenants:migrate {code}` | Run the tenant migrations against a single tenant's database |
| `php artisan tenants:migrate --all` | Migrate every active/trial license that has a database connection |
| `php artisan licenses:expire-overdue` | Mark overdue licenses as expired |
| `php artisan backups:scheduled` | Run the scheduled tenant backups |
| `php artisan db:seed` | Seed the platform admin, packages, and the demo tenant |
| `composer dev` | Start server + queue + logs + Vite concurrently |
| `composer test` | Clear config and run the test suite |

## Scheduled tasks

Defined in [`routes/console.php`](routes/console.php):

- `licenses:expire-overdue` — **daily**
- `backups:scheduled` — **daily at 02:00**

In production, ensure the Laravel scheduler runs — add a single cron entry:

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

**Tenant routes** — require **both** the `X-License-Code` header and a Sanctum bearer token:

```
GET/POST/PUT/DELETE  /api/v1/transfers
GET                  /api/v1/dashboard/stats, /api/v1/dashboard/weekly-activity
… /banks  /menu-items  /menu-categories  /expenses  /constant-lists
… /team   /roles       /devices          /activity-log  /reports
POST /api/v1/sync/push        GET /api/v1/sync/pull
```

**Admin / platform routes** — require an admin Sanctum token and the `admin` middleware, and **never** the `X-License-Code` header:

```
GET/POST/PUT/DELETE  /api/v1/admin/packages
GET/POST … suspend|activate|change-package  /api/v1/admin/licenses
… /boxes  /partners  /withdrawals  /payments  /expenses  /settings
GET /api/v1/admin/finance/summary
GET/POST/DELETE + download  /api/v1/admin/backups
```

## Offline sync

Mobile/SPA clients work offline and reconcile through two endpoints:

- `POST /api/v1/sync/push` — the client uploads queued create/update/delete operations ([`SyncOperation`](app/Enums/SyncOperation.php)).
- `GET /api/v1/sync/pull` — the client downloads server-side changes.

Sync is backed by the tenant's `Device` and `SyncEvent` models and handled by [`App\Services\Tenant\SyncService`](app/Services/Tenant/SyncService.php).

## Localization

The UI is **Arabic-first (RTL)** and bilingual (Arabic / English):

- Language is switched via `GET /locale/{ar|en}` and stored in the session.
- The [`SetLocale`](app/Http/Middleware/SetLocale.php) middleware applies the chosen locale to every web request, setting `<html lang>` / `dir` and the font. Arabic is the default.
- Strings resolve through `__()` against the JSON catalogs [`lang/ar.json`](lang/ar.json) + [`lang/en.json`](lang/en.json), plus PHP line files under [`lang/ar`](lang/ar) and [`lang/en`](lang/en) (including localized validation messages).

## Project structure

```
app/
├── Console/Commands/     # tenants:migrate, licenses:expire-overdue, backups:scheduled
├── Enums/                # LicenseStatus, RoleKey, PermissionKey, TransferStatus, ...
├── Exceptions/           # InvalidLicenseException, ...
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/       # REST API (tenant) + Api/V1/Admin (platform)
│   │   └── Web/          # Blade web app (tenant, Auth, admin)
│   └── Middleware/       # tenant.header, tenant.session, admin (EnsureAdmin), SetLocale
├── Models/               # Platform models (License, Package, Admin, Box, ...)
│   └── Tenant/           # Tenant models (Transfer, Bank, MenuItem, TeamMember, ...)
└── Services/
    ├── Admin/            # Platform services (License, Package, Finance, Backup, ...)
    ├── Tenant/           # Tenant services (Transfer, Dashboard, Report, Sync, ...)
    ├── TenantConnectionResolver.php   # points the `tenant` connection at a license's DB
    └── TenantProvisioner.php          # creates a full new tenant end-to-end
bootstrap/app.php         # middleware aliases, priority pinning, guard-aware redirects
config/                   # auth (guards), database (tenant connection), backup, sanctum, ...
database/
├── migrations/           # Platform schema
├── migrations/tenant/    # Per-tenant schema
└── seeders/              # DatabaseSeeder, PackageSeeder, DemoTenantSeeder
lang/                     # ar / en (JSON catalogs + PHP line files)
resources/views/          # Blade: dashboard, transfers, admin, auth, layouts, ...
routes/                   # web.php, api.php, console.php
```

## Testing

```bash
php artisan test
# or, via the composer script (clears config first):
composer test
```

## Code style

Formatting is enforced with **Laravel Pint**:

```bash
./vendor/bin/pint        # fix
./vendor/bin/pint --test # check only
```

## License

Built with the [Laravel](https://laravel.com) framework. This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
