<?php

use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\Auth\AdminAuthController;
use App\Http\Controllers\Web\Auth\TenantAuthController;
use App\Http\Controllers\Web\BankController;
use App\Http\Controllers\Web\ConstantController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\ExpenseController;
use App\Http\Controllers\Web\HistoryController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\ReceiptController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\TeamController;
use App\Http\Controllers\Web\TransferController;
use Illuminate\Support\Facades\Route;

// Everything below is the server-rendered Blade web app that sits alongside
// the JSON API in routes/api.php (untouched, still used by future mobile
// clients). See BACKEND_PROMPT.md for the domain model and this task's
// report for the session-based tenant connection resolution this relies on.

Route::view('/', 'welcome')->name('home');

// UI language toggle (ar/en). Public so guests can switch on the auth pages
// too; stores the choice in the session, then returns to the previous page.
Route::get('locale/{locale}', function (string $locale) {
    if (in_array($locale, \App\Http\Middleware\SetLocale::SUPPORTED, true)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Tenant space — a license's own team members
|--------------------------------------------------------------------------
*/

// `tenant.session` ahead of `guest:tenant` here too — same reasoning as the
// logout route below: `guest:tenant` (RedirectIfAuthenticated) also has to
// resolve "is there already a logged-in tenant user in this session?",
// which queries `team_members` on the `tenant` connection. A visitor whose
// session already carries a valid license_code + logged-in user (e.g.
// re-opening /login in a second tab) would 500 here instead of being
// redirected to the dashboard without this middleware first.
Route::middleware(['tenant.session', 'guest:tenant'])->group(function () {
    Route::get('login', [TenantAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [TenantAuthController::class, 'login']);
    Route::get('signup', [TenantAuthController::class, 'showSignup'])->name('signup');
    Route::post('signup', [TenantAuthController::class, 'signup']);
});

Route::get('license-inactive', function () {
    return view('auth.license-inactive');
})->name('license.inactive');

// `tenant.session` MUST run ahead of `auth:tenant` on EVERY route that uses
// the `tenant` guard — not just the "page content" routes. Empirically
// caught during this task's verification: `auth:tenant`'s own job of
// re-hydrating the logged-in TeamMember from the session id queries the
// `team_members` provider, which lives on the dynamic `tenant` connection
// (see TeamMember::getConnectionName()) — so even the logout route 500s
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

    Route::get('history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('transfers/{transfer}/receipt', [ReceiptController::class, 'show'])->name('transfers.receipt');

    // Customers
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    // Banks & wallets
    Route::get('banks', [BankController::class, 'index'])->name('banks.index');
    Route::post('banks', [BankController::class, 'store'])->name('banks.store');
    Route::put('banks/{bank}', [BankController::class, 'update'])->name('banks.update');
    Route::delete('banks/{bank}', [BankController::class, 'destroy'])->name('banks.destroy');

    // Menu — categories + items
    Route::get('menu', [MenuController::class, 'index'])->name('menu.index');
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
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    // Constants — lists + items
    Route::get('constants', [ConstantController::class, 'index'])->name('constants.index');
    Route::post('constants', [ConstantController::class, 'store'])->name('constants.store');
    Route::delete('constants/{list}', [ConstantController::class, 'destroy'])->name('constants.destroy');
    Route::post('constants/{list}/items', [ConstantController::class, 'storeItem'])->name('constants.items.store');
    Route::delete('constants/{list}/items/{item}', [ConstantController::class, 'destroyItem'])->name('constants.items.destroy');

    // Team
    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::post('team', [TeamController::class, 'store'])->name('team.store');
    Route::put('team/{teamMember}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('team/{teamMember}', [TeamController::class, 'destroy'])->name('team.destroy');

    // Devices
    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

    // Activity log (read-only)
    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    // Settings (self-service profile + password)
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings/profile', [SettingsController::class, 'update'])->name('settings.profile.update');

    // Help
    Route::view('help', 'help.index')->name('help.index');
});

/*
|--------------------------------------------------------------------------
| Super Admin portal
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AdminAuthController::class, 'login']);
    });

    Route::post('logout', [AdminAuthController::class, 'logout'])
        ->middleware('auth:admin')
        ->name('logout');

    Route::middleware('auth:admin')->group(function () {
        Route::get('dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');
    });
});
