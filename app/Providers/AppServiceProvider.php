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
    public function register(): void
    {
        $this->app->singleton(PlatformSettings::class);
    }

    public function boot(): void
    {
        View::composer(['welcome', 'privacy', 'terms', 'security', 'help.index'], function ($view) {
            $settings = app(PlatformSettings::class);

            $view->with([
                'supportEmail' => $settings->get('support_email'),
                'supportPhone' => $settings->get('support_phone'),
            ]);
        });

        Sanctum::usePersonalAccessTokenModel(TenantPersonalAccessToken::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

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

    }
}
