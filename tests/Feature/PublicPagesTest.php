<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The pages the guest footer links to. They must render without a session, and
 * `/help` additionally has to survive being opened by a SIGNED-IN tenant.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [
            'landing' => ['/'],
            'privacy' => ['/privacy'],
            'terms' => ['/terms'],
            'help' => ['/help'],
        ];
    }

    /** @dataProvider pages */
    #[DataProvider('pages')]
    public function test_a_guest_can_read_the_page(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    /**
     * Regression: `help.index` renders `layouts.app` or `layouts.guest` based on
     * `auth('tenant')->check()`. That guard resolves a TeamMember from the
     * `tenant` connection, which only exists once
     * ResolveTenantConnectionFromSession (`tenant.session`) has run. The route
     * was published without it, so a signed-in tenant opening المساعدة hit a
     * 500 - "Plugin 'mysql_native_password' is not loaded ... Database: " -
     * while a guest saw the page fine, which is why it slipped through.
     */
    public function test_the_help_route_resolves_the_tenant_connection_first(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'help.index');

        $this->assertNotNull($route, 'The help.index route is gone.');
        $this->assertContains(
            'tenant.session',
            $route->gatherMiddleware(),
            'help.index renders a tenant-guard-dependent view but never configures the tenant connection.'
        );
    }

    /**
     * Any other `Route::view()` whose template consults the tenant guard needs
     * the same middleware, so this catches the next one rather than only the one
     * that already broke.
     *
     * Two things this test got wrong before, both of which made it assert
     * NOTHING while reading like a safety net:
     *
     *   - it looked for the template in `$route->getAction()['view']`, but
     *     Route::view() stores it in `$route->defaults` - so every route was
     *     skipped and the loop body never ran once;
     *   - `glob('views/**\/*.blade.php')` is a single level deep, not recursive,
     *     so a dozen templates were never scanned at all.
     *
     * The two assertions below the scan exist so it can never silently degrade
     * to a no-op again: if the scan stops finding guard views, or stops matching
     * any route, the test fails instead of passing green.
     *
     * Scope note: this covers `Route::view()` routes only. A controller that
     * returns a guard-dependent view is not reachable by static inspection here.
     */
    public function test_every_route_rendering_a_tenant_guard_view_configures_the_connection(): void
    {
        $guardViews = $this->viewsUsingTheTenantGuard();

        $this->assertNotEmpty(
            $guardViews,
            'No template was found using the tenant guard - the scan is broken, and this test would pass without checking anything.'
        );

        $checked = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            // Route::view() puts the template in `defaults`, NOT in the action.
            $view = $route->defaults['view'] ?? $route->getAction()['view'] ?? null;

            if ($view === null || ! in_array($view, $guardViews, true)) {
                continue;
            }

            $checked++;

            $this->assertContains(
                'tenant.session',
                $route->gatherMiddleware(),
                "Route `{$route->uri()}` renders `{$view}`, which uses the tenant guard, without `tenant.session`."
            );
        }

        $this->assertGreaterThan(
            0,
            $checked,
            'No route was matched to a guard-dependent template, so nothing was verified.'
        );
    }

    /**
     * Every Blade template that consults `auth('tenant')`, as dotted view names.
     * Recursive on purpose - `glob('**')` only descends one level.
     *
     * @return array<int, string>
     */
    private function viewsUsingTheTenantGuard(): array
    {
        $root = resource_path('views');
        $found = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            if (! str_contains((string) file_get_contents($file->getPathname()), "auth('tenant')")) {
                continue;
            }

            $found[] = str_replace(
                ['\\', '/'],
                '.',
                trim(str_replace([$root, '.blade.php'], '', $file->getPathname()), '\\/'),
            );
        }

        return $found;
    }
}
