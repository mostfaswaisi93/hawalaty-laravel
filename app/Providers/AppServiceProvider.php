<?php

namespace App\Providers;

use App\Models\TenantPersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sanctum only supports one globally-registered token model, so it
        // must serve BOTH the Super Admin portal (Admin, platform `mysql`
        // connection) and every tenant's team members (`tenant` connection).
        // TenantPersonalAccessToken::getConnectionName() resolves which one
        // per instance/request — see its doc comment and BACKEND_PROMPT.md
        // section 8.1 for the full reasoning.
        Sanctum::usePersonalAccessTokenModel(TenantPersonalAccessToken::class);

        // Required for `$middleware->throttleApi()` (bootstrap/app.php) to
        // actually work — ThrottleRequests throws if no limiter named 'api'
        // is registered. Laravel 11's skeleton has no RouteServiceProvider
        // defining this by default (unlike Laravel 8-10), so it must be
        // added explicitly. See BACKEND_PROMPT.md section 7.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Policy auto-discovery for models under App\Models\Tenant\* WAS
        // verified via a real authorization call
        // (Gate::getPolicyFor(App\Models\Tenant\Bank::class)) to correctly
        // resolve App\Policies\Tenant\BankPolicy — and every other tenant
        // policy — with NO explicit Gate::policy() registration needed.
        // Reason: Illuminate\Auth\Access\Gate::guessPolicyName() replaces
        // "\Models\" with "\Policies\" ANYWHERE it occurs in the model's
        // namespace, not just as the first segment, so
        // App\Models\Tenant\Bank correctly guesses
        // App\Policies\Tenant\BankPolicy among its candidates. Explicit
        // registration was tested and found unnecessary; left out here
        // rather than added as dead defensive code.
    }
}
