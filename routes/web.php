<?php

use App\Http\Controllers\Web\Auth\AdminAuthController;
use App\Http\Controllers\Web\Auth\TenantAuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\HistoryController;
use App\Http\Controllers\Web\TransferController;
use Illuminate\Support\Facades\Route;

// Everything below is the server-rendered Blade web app that sits alongside
// the JSON API in routes/api.php (untouched, still used by future mobile
// clients). See BACKEND_PROMPT.md for the domain model and this task's
// report for the session-based tenant connection resolution this relies on.

Route::redirect('/', '/login');

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
