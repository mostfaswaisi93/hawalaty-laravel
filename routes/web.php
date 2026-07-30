<?php

use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Web\Admin\BackupController as AdminBackupController;
use App\Http\Controllers\Web\Admin\BoxController as AdminBoxController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Web\Admin\LicenseController as AdminLicenseController;
use App\Http\Controllers\Web\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Web\Admin\PartnerController as AdminPartnerController;
use App\Http\Controllers\Web\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Web\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Web\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Web\Admin\SettlementController as AdminSettlementController;
use App\Http\Controllers\Web\Auth\AdminAuthController;
use App\Http\Controllers\Web\Auth\TenantAuthController;
use App\Http\Controllers\Web\BalanceController;
use App\Http\Controllers\Web\BankController;
use App\Http\Controllers\Web\ConstantController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\ExpenseController;
use App\Http\Controllers\Web\HistoryController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\OrderConfirmationController;
use App\Http\Controllers\Web\ReceiptController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\WhatsAppSettingsController;
use App\Http\Controllers\Web\SyncController as WebSyncController;
use App\Http\Controllers\Web\TeamController;
use App\Http\Controllers\Web\TransferController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// Everything below is the server-rendered Blade web app that sits alongside
// the JSON API in routes/api.php (untouched, still used by future mobile
// clients). See BACKEND_PROMPT.md for the domain model and this task's
// report for the session-based tenant connection resolution this relies on.

Route::view('/', 'welcome')->name('home');

// UI language toggle (ar/en). Public so guests can switch on the auth pages
// too; stores the choice in the session, then returns to the previous page.
Route::get('locale/{locale}', function (string $locale) {
    if (in_array($locale, SetLocale::SUPPORTED, true)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

// Public content pages. These are linked from the guest footer, which renders on
// login, signup and every error page, so they must be reachable without a
// session - previously they were linked but had no route at all and 404'd.
Route::view('privacy', 'privacy')->name('privacy');
Route::view('terms', 'terms')->name('terms');

// Help is public too, for the same reason: the guest footer links it. The view
// carries no authenticated data and picks its own layout depending on whether a
// tenant is signed in, so one route serves both audiences.
// `tenant.session` is REQUIRED even though this route is public: the view picks
// its layout with `auth('tenant')->check()`, and that guard resolves the logged
// -in TeamMember from the `tenant` connection. Without this middleware the
// connection is never configured, so a SIGNED-IN tenant clicking "المساعدة"
// got a 500 ("Database: " empty) while a guest saw the page fine. The
// middleware passes straight through when the session carries no license_code,
// so the page stays reachable for guests - see
// App\Http\Middleware\ResolveTenantConnectionFromSession.
Route::view('help', 'help.index')->middleware('tenant.session')->name('help.index');

/*
|--------------------------------------------------------------------------
| Tenant space - a license's own team members
|--------------------------------------------------------------------------
*/

// `tenant.session` ahead of `guest:tenant` here too - same reasoning as the
// logout route below: `guest:tenant` (RedirectIfAuthenticated) also has to
// resolve "is there already a logged-in tenant user in this session?",
// which queries `team_members` on the `tenant` connection. A visitor whose
// session already carries a valid license_code + logged-in user (e.g.
// re-opening /login in a second tab) would 500 here instead of being
// redirected to the dashboard without this middleware first.
Route::middleware(['tenant.session', 'guest:tenant'])->group(function () {
    Route::get('login', [TenantAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [TenantAuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('signup', [TenantAuthController::class, 'showSignup'])->name('signup');
    Route::post('signup', [TenantAuthController::class, 'signup']);
});

Route::get('license-inactive', function () {
    return view('auth.license-inactive');
})->name('license.inactive');

// Public, login-free bank-transfer confirmation. Opened by the account owner
// from the WhatsApp link: no session and no X-License-Code, so the tenant is
// selected from the license code in the path and the order is found by its
// unguessable token (see Web\OrderConfirmationController). The POST is
// throttled since the token is the only gate.
Route::get('order/confirm/{license}/{token}', [OrderConfirmationController::class, 'show'])->name('order.confirm');
Route::post('order/confirm/{license}/{token}', [OrderConfirmationController::class, 'act'])
    ->middleware('throttle:10,1')
    ->name('order.confirm.act');

// `tenant.session` MUST run ahead of `auth:tenant` on EVERY route that uses
// the `tenant` guard - not just the "page content" routes. Empirically
// caught during this task's verification: `auth:tenant`'s own job of
// re-hydrating the logged-in TeamMember from the session id queries the
// `team_members` provider, which lives on the dynamic `tenant` connection
// (see TeamMember::getConnectionName()) - so even the logout route 500s
// with a bogus/unconfigured `tenant` connection ("Access denied for user
// ''@'localhost'") without this middleware ahead of it, despite logout
// itself never touching tenant data. See
// App\Http\Middleware\ResolveTenantConnectionFromSession's doc comment and
// this task's report for the middleware-priority fix that guarantees the
// ordering below actually holds at runtime.
Route::middleware(['tenant.session', 'auth:tenant'])->group(function () {
    Route::post('logout', [TenantAuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');

    // Drain for orders taken while the connection was down. Session-authed on
    // purpose - see Web\SyncController for why it does not reuse the API route.
    Route::post('sync/push', [WebSyncController::class, 'push'])->name('sync.push');

    Route::get('history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('transfers/{transfer}/receipt', [ReceiptController::class, 'show'])->name('transfers.receipt');

    // Customers
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    // Global search (header search pill)
    Route::get('search', [SearchController::class, 'index'])->name('search.index');

    // Notifications (header bell dropdown)
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Banks & wallets
    Route::get('banks', [BankController::class, 'index'])->name('banks.index');
    Route::post('banks', [BankController::class, 'store'])->name('banks.store');
    Route::put('banks/{bank}', [BankController::class, 'update'])->name('banks.update');
    Route::delete('banks/{bank}', [BankController::class, 'destroy'])->name('banks.destroy');

    // Menu - categories + items
    Route::get('menu', [MenuController::class, 'index'])->name('menu.index');
    Route::get('menu/export', [MenuController::class, 'export'])->name('menu.export');
    Route::post('menu/import', [MenuController::class, 'import'])->name('menu.import');
    Route::post('menu/categories', [MenuController::class, 'storeCategory'])->name('menu.categories.store');
    Route::delete('menu/categories/{category}', [MenuController::class, 'destroyCategory'])->name('menu.categories.destroy');
    Route::post('menu/items', [MenuController::class, 'storeItem'])->name('menu.items.store');
    Route::put('menu/items/{item}', [MenuController::class, 'updateItem'])->name('menu.items.update');
    Route::delete('menu/items/{item}', [MenuController::class, 'destroyItem'])->name('menu.items.destroy');

    // Expenses
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // Reports
    // الموازنة المالية - money in vs money out per account. Read-only.
    Route::get('balance', [BalanceController::class, 'index'])->name('balance.index');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    // Constants - lists + items
    Route::get('constants', [ConstantController::class, 'index'])->name('constants.index');
    // What this tenant calls its catalogue - a per-account constant, so it lives
    // on the constants page rather than in a settings screen of its own.
    Route::put('constants/naming', [ConstantController::class, 'updateNaming'])->name('constants.naming.update');
    Route::post('constants', [ConstantController::class, 'store'])->name('constants.store');
    Route::delete('constants/{list}', [ConstantController::class, 'destroy'])->name('constants.destroy');
    Route::post('constants/{list}/items', [ConstantController::class, 'storeItem'])->name('constants.items.store');
    Route::delete('constants/{list}/items/{item}', [ConstantController::class, 'destroyItem'])->name('constants.items.destroy');

    // Team
    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::post('team', [TeamController::class, 'store'])->name('team.store');
    Route::put('team/{teamMember}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('team/{teamMember}', [TeamController::class, 'destroy'])->name('team.destroy');

    // Roles + permissions. Gated on TeamManage: inventing a role is the same
    // power as assigning one.
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // Payroll. Paying a salary writes an `expenses` row in the same
    // transaction, so it lands in المصروفات and the reports rather than being
    // a parallel ledger nobody totals.
    Route::put('team/{teamMember}/salary', [TeamController::class, 'updateSalary'])->name('team.salary.update');
    Route::post('team/{teamMember}/salary/pay', [TeamController::class, 'paySalary'])->name('team.salary.pay');

    // Devices
    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

    // Activity log (read-only)
    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    // Settings (self-service profile + password)
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings/profile', [SettingsController::class, 'update'])->name('settings.profile.update');

    // WhatsApp credentials. Per-tenant on purpose: each business owns its own
    // Meta app, its own number and its own bill, so these can never live in the
    // shared .env. Permission is enforced in the controller.
    Route::get('settings/whatsapp', [WhatsAppSettingsController::class, 'index'])->name('whatsapp.settings');
    Route::put('settings/whatsapp', [WhatsAppSettingsController::class, 'update'])->name('whatsapp.settings.update');
    Route::post('settings/whatsapp/test', [WhatsAppSettingsController::class, 'test'])->name('whatsapp.settings.test');
});

/*
|--------------------------------------------------------------------------
| Super Admin portal
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AdminAuthController::class, 'login'])->middleware('throttle:6,1');
    });

    Route::post('logout', [AdminAuthController::class, 'logout'])
        ->middleware('auth:admin')
        ->name('logout');

    Route::middleware('auth:admin')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Licenses - provision, inspect, suspend/activate, change package
        Route::get('licenses', [AdminLicenseController::class, 'index'])->name('licenses.index');
        Route::post('licenses', [AdminLicenseController::class, 'store'])->name('licenses.store');
        Route::get('licenses/{license}', [AdminLicenseController::class, 'show'])->name('licenses.show');
        Route::post('licenses/{license}/suspend', [AdminLicenseController::class, 'suspend'])->name('licenses.suspend');
        Route::post('licenses/{license}/activate', [AdminLicenseController::class, 'activate'])->name('licenses.activate');
        // Runs the tenant schema migrations against the license's linked cPanel database.
        Route::post('licenses/{license}/migrate', [AdminLicenseController::class, 'migrate'])->name('licenses.migrate');
        Route::post('licenses/{license}/change-package', [AdminLicenseController::class, 'changePackage'])->name('licenses.change-package');

        // Packages
        Route::get('packages', [AdminPackageController::class, 'index'])->name('packages.index');
        Route::post('packages', [AdminPackageController::class, 'store'])->name('packages.store');
        Route::put('packages/{package}', [AdminPackageController::class, 'update'])->name('packages.update');
        Route::delete('packages/{package}', [AdminPackageController::class, 'destroy'])->name('packages.destroy');

        // Boxes
        Route::get('boxes', [AdminBoxController::class, 'index'])->name('boxes.index');
        Route::post('boxes', [AdminBoxController::class, 'store'])->name('boxes.store');
        Route::put('boxes/{box}', [AdminBoxController::class, 'update'])->name('boxes.update');
        Route::delete('boxes/{box}', [AdminBoxController::class, 'destroy'])->name('boxes.destroy');

        // Partners
        Route::get('partners', [AdminPartnerController::class, 'index'])->name('partners.index');
        Route::post('partners', [AdminPartnerController::class, 'store'])->name('partners.store');
        Route::put('partners/{partner}', [AdminPartnerController::class, 'update'])->name('partners.update');
        Route::delete('partners/{partner}', [AdminPartnerController::class, 'destroy'])->name('partners.destroy');

        // Payments (records payment + renews license)
        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::post('payments', [AdminPaymentController::class, 'store'])->name('payments.store');

        // Finance summary + money-out actions (withdrawals, platform expenses)
        Route::get('finance', [AdminFinanceController::class, 'index'])->name('finance');
        Route::post('finance/withdrawals', [AdminFinanceController::class, 'storeWithdrawal'])->name('finance.withdrawals.store');
        Route::post('finance/expenses', [AdminFinanceController::class, 'storeExpense'])->name('finance.expenses.store');

        // Partner clearing (المقاصة). No update route by design: a run is
        // recomputed, not edited - a draft is deleted and re-created instead,
        // and a confirmed run is an accounting document that never changes.
        Route::get('settlements', [AdminSettlementController::class, 'index'])->name('settlements.index');
        Route::post('settlements', [AdminSettlementController::class, 'store'])->name('settlements.store');
        Route::get('settlements/{settlement}', [AdminSettlementController::class, 'show'])->name('settlements.show');
        Route::post('settlements/{settlement}/confirm', [AdminSettlementController::class, 'confirm'])->name('settlements.confirm');
        Route::delete('settlements/{settlement}', [AdminSettlementController::class, 'destroy'])->name('settlements.destroy');

        // Backups
        Route::get('backups', [AdminBackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [AdminBackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{backup}/download', [AdminBackupController::class, 'download'])->name('backups.download');
        Route::delete('backups/{backup}', [AdminBackupController::class, 'destroy'])->name('backups.destroy');

        // Activity log (read-only)
        Route::get('logs', [AdminActivityLogController::class, 'index'])->name('logs.index');

        // Platform key/value settings
        Route::get('settings', [AdminSettingController::class, 'index'])->name('settings.index');
        // PUT is the grouped, typed form; POST stays the free-form key/value
        // upsert behind the "custom keys" section.
        Route::put('settings', [AdminSettingController::class, 'update'])->name('settings.update');
        Route::post('settings', [AdminSettingController::class, 'upsert'])->name('settings.upsert');
        Route::delete('settings/{key}', [AdminSettingController::class, 'destroy'])->name('settings.destroy');

        // The admin's own account - separate from settings above, which is
        // platform-wide key/value config rather than anything personal.
        Route::get('profile', [AdminProfileController::class, 'index'])->name('profile.index');
        Route::put('profile', [AdminProfileController::class, 'update'])->name('profile.update');
    });
});
