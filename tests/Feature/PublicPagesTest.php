<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [
            'landing' => ['/'],
            'privacy' => ['/privacy'],
            'terms' => ['/terms'],
            'security' => ['/security'],
            'help' => ['/help'],

            'sitemap' => ['/sitemap.xml'],
            'robots' => ['/robots.txt'],
        ];
    }

    #[DataProvider('pages')]
    public function test_a_guest_can_read_the_page(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

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

    public function test_every_route_rendering_a_tenant_guard_view_configures_the_connection(): void
    {
        $guardViews = $this->viewsUsingTheTenantGuard();

        $this->assertNotEmpty(
            $guardViews,
            'No template was found using the tenant guard - the scan is broken, and this test would pass without checking anything.'
        );

        $checked = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
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
