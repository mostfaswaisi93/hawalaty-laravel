<?php

namespace App\Providers;

use App\Models\TenantPersonalAccessToken;
use App\Settings\PlatformSettings;
use App\Support\SearchTerm;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton so the settings table is read at most once per request even
        // on a cache miss - it is consulted by the admin dashboard and by the
        // public help/privacy/terms pages, which render constantly.
        $this->app->singleton(PlatformSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The public-facing pages all print the support address, which used to
        // be the hardcoded literal `support@hawalaty.test` in five places. It is
        // now the `support_email` platform setting. Scoped to these four views
        // rather than shared globally so the settings table is not touched on
        // every render of every page.
        View::composer(['welcome', 'privacy', 'terms', 'help.index'], function ($view) {
            $settings = app(PlatformSettings::class);

            $view->with([
                'supportEmail' => $settings->get('support_email'),
                'supportPhone' => $settings->get('support_phone'),
            ]);
        });

        // Sanctum only supports one globally-registered token model, so it
        // must serve BOTH the Super Admin portal (Admin, platform `mysql`
        // connection) and every tenant's team members (`tenant` connection).
        // TenantPersonalAccessToken::getConnectionName() resolves which one
        // per instance/request - see its doc comment and BACKEND_PROMPT.md
        // section 8.1 for the full reasoning.
        Sanctum::usePersonalAccessTokenModel(TenantPersonalAccessToken::class);

        // Required for `$middleware->throttleApi()` (bootstrap/app.php) to
        // actually work - ThrottleRequests throws if no limiter named 'api'
        // is registered. Laravel 11's skeleton has no RouteServiceProvider
        // defining this by default (unlike Laravel 8-10), so it must be
        // added explicitly. See BACKEND_PROMPT.md section 7.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Every list page's search box, in one place. Each one used to build
        // its own "%{$search}%" and OR-group by hand; see SearchTerm for why
        // that answered the wrong question, and why the escape character is
        // `!`. Registered on the QUERY builder so it reaches Eloquent too, by
        // Eloquent\Builder::__call forwarding.
        //
        // The OR-group is always parenthesised: without it, a term OR'd across
        // three columns would escape its own condition and swallow the status
        // and date filters sitting beside it.
        Builder::macro('whereSearch', function (array|string $columns, ?string $term) {
            $term = trim((string) $term);

            if ($term === '') {
                return $this;
            }

            $pattern = SearchTerm::pattern($term);
            $grammar = $this->getGrammar();

            return $this->where(function ($query) use ($columns, $pattern, $grammar) {
                foreach ((array) $columns as $column) {
                    $query->orWhereRaw(
                        $grammar->wrap($column)." like ? escape '".SearchTerm::ESCAPE."'",
                        [$pattern],
                    );
                }
            });
        });

        // Policy auto-discovery for models under App\Models\Tenant\* WAS
        // verified via a real authorization call
        // (Gate::getPolicyFor(App\Models\Tenant\Bank::class)) to correctly
        // resolve App\Policies\Tenant\BankPolicy - and every other tenant
        // policy - with NO explicit Gate::policy() registration needed.
        // Reason: Illuminate\Auth\Access\Gate::guessPolicyName() replaces
        // "\Models\" with "\Policies\" ANYWHERE it occurs in the model's
        // namespace, not just as the first segment, so
        // App\Models\Tenant\Bank correctly guesses
        // App\Policies\Tenant\BankPolicy among its candidates. Explicit
        // registration was tested and found unnecessary; left out here
        // rather than added as dead defensive code.
    }
}
