<?php

use App\Http\Controllers\Web\Auth\AdminAuthController;
use App\Http\Controllers\Web\Auth\TenantAuthController;
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

Route::middleware('guest:tenant')->group(function () {
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

    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
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
