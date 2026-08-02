<?php

namespace App\Providers;

use App\Models\Package;
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
use Throwable;

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

        /*
         * The landing page prices what is actually sold.
         *
         * Its plan table was written by hand, so it drifted the moment a package
         * changed - it advertised 50 devices on a tier that grants 10, and 2 on
         * one that grants 3. Reading the packages means the page cannot promise
         * something the app will not honour. Same for the trial length, which is
         * an operator setting and was quoted as a constant in four places.
         *
         * Its own composer: the pages above need neither, and this one costs a
         * query.
         */
        View::composer('welcome', function ($view) {
            // Degrades rather than throwing: this page is the public front door
            // and must still render if the database is unreachable or not yet
            // migrated. Without packages the pricing grid simply shows the
            // "talk to us" card.
            try {
                $packages = Package::orderBy('price')->get();
                $trialDays = (int) app(PlatformSettings::class)->get('trial_days');
            } catch (Throwable) {
                $packages = collect();
                $trialDays = 14;
            }

            $view->with([
                'packages' => $packages,
                'trialDays' => $trialDays ?: 14,
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
