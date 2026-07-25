<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\ResolveTenantConnection;
use App\Http\Middleware\ResolveTenantConnectionFromSession;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Pre-existing app-wide bug, originally found and fixed while
        // verifying the admin auth gating task: Laravel 13's
        // ApplicationBuilder unconditionally defaults to
        // redirectGuestsTo(fn () => route('login')) BEFORE this callback
        // runs. Back when this app was a pure JSON API with no web routes at
        // all, any unauthenticated request to a route behind `auth:sanctum`
        // 500'd with "Route [login] not defined." instead of the intended
        // 401, because Authenticate::unauthenticated() calls redirectTo()
        // (which threw trying to resolve `route('login')`) before the
        // AuthenticationException even got a chance to be JSON-rendered by
        // shouldRenderJsonWhen() below - so it was set to always return
        // null ("never redirect").
        //
        // Now that routes/web.php has real session-authenticated pages
        // (tenant + admin), that blanket null broke THEM instead: a guest
        // hitting a protected web page got a bare 204 No Content rather
        // than being redirected to the right login screen (see
        // Illuminate\Foundation\Exceptions\Handler::unauthenticated - a null
        // redirectTo() on a non-JSON request short-circuits to
        // response()->noContent(401) rather than actually redirecting).
        // Made guard-aware instead: API requests still get null (→ plain
        // JSON 401), everything under /admin/* goes to the admin login
        // page, everything else goes to the tenant login page.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            return $request->is('admin/*') ? route('admin.login') : route('login');
        });

        // Mirror fix for the OTHER direction: `guest:tenant` / `guest:admin`
        // (Illuminate\Auth\Middleware\RedirectIfAuthenticated) guard GET
        // /login and /admin/login so an already-logged-in user re-visiting
        // them gets bounced onward instead of seeing the form again. Its
        // un-configured default falls back to `Route::has('dashboard') ?
        // route('dashboard') : ...` - which always resolves to the TENANT
        // dashboard route by name, even for an already-authenticated Super
        // Admin hitting /admin/login (there's no route literally named
        // `dashboard` in admin space, it's `admin.dashboard`). Guard-aware
        // here too, same as redirectGuestsTo above.
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->is('admin/*') ? route('admin.dashboard') : route('dashboard'),
        );

        // See BACKEND_PROMPT.md section 8.1 - apply to tenant-space route
        // groups only, always ahead of `auth:sanctum`, e.g.
        // Route::middleware(['tenant.header', 'auth:sanctum'])->group(...).
        // Never applied to /admin/* (Super Admin) routes.
        // Apply the chosen UI language (ar/en) to every server-rendered web
        // request so <html dir/lang> and all __() strings follow the toggle.
        $middleware->web(append: [SetLocale::class]);

        $middleware->alias([
            'tenant.header' => ResolveTenantConnection::class,
            // Session-cookie equivalent of tenant.header, for the Blade web
            // app - see App\Http\Middleware\ResolveTenantConnectionFromSession's
            // doc comment. Apply to tenant-space WEB route groups only,
            // always ahead of `auth:tenant`, e.g.
            // Route::middleware(['tenant.session', 'auth:tenant'])->group(...).
            'tenant.session' => ResolveTenantConnectionFromSession::class,
            // Gates /api/v1/admin/* routes - always paired with
            // auth:sanctum, NEVER with tenant.header. See
            // App\Http\Middleware\EnsureAdmin's doc comment.
            'admin' => EnsureAdmin::class,
        ]);

        // Laravel 11's `api` middleware group does NOT include throttle:api
        // by default (its $apiLimiter is unset unless this is called) -
        // verified by reading Illuminate\Foundation\Configuration\Middleware
        // directly rather than assumed, per BACKEND_PROMPT.md section 7's
        // rate-limiting requirement. Pairs with the 'api' RateLimiter
        // defined in AppServiceProvider::boot().
        $middleware->throttleApi();

        // Laravel's SortedMiddleware reorders route middleware based on
        // $middlewarePriority, NOT the order they're listed in the route
        // definition. The default priority list contains the
        // AuthenticatesRequests *interface* (which Authenticate/auth:sanctum
        // implements), not the concrete Authenticate class - passing the
        // concrete class here would silently fail to match (addToMiddleware-
        // PriorityBefore() falls through to appending at the very end when
        // it can't find `before` in the list) and leave the bug in place, so
        // the interface is used instead. Without this fix, the global `api`
        // group's `throttle:api` (also priority-listed) causes auth:sanctum
        // to be sorted to run BEFORE tenant.header despite being declared
        // after it in routes/api.php, so Sanctum would check the token
        // against the platform `mysql` connection instead of the tenant's.
        // This pins tenant.header immediately ahead of auth:sanctum in the
        // priority list so it always resolves the `tenant` connection first.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: ResolveTenantConnection::class,
        );

        // Same fix, same reason, for the session/cookie-based web flow.
        // Empirically confirmed necessary (see this task's report): the
        // 'web' middleware group's SubstituteBindings is priority-listed
        // (after the AuthenticatesRequests interface), and it sits ahead of
        // our route-level ['tenant.session', 'auth:tenant'] pair in the
        // collapsed middleware list. Illuminate\Routing\SortedMiddleware
        // only reorders priority-listed middleware relative to OTHER
        // priority-listed middleware - it moved auth:tenant (priority-listed
        // via the interface) to just before SubstituteBindings, which put it
        // ahead of tenant.session (not priority-listed at all) despite the
        // route declaring tenant.session first. Adding
        // ResolveTenantConnectionFromSession to the priority list, directly
        // ahead of AuthenticatesRequests, gives it its own anchor point so
        // it gets carried along with (and stays ahead of) auth:tenant during
        // that same reordering pass.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: ResolveTenantConnectionFromSession::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
