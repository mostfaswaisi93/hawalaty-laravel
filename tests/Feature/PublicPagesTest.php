<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
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
     * Any other public route whose view consults the tenant guard needs the
     * same middleware, so this catches the next one rather than only the one
     * that already broke.
     */
    public function test_every_route_rendering_a_tenant_guard_view_configures_the_connection(): void
    {
        $guardViews = collect(glob(resource_path('views/**/*.blade.php')))
            ->merge(glob(resource_path('views/*.blade.php')))
            ->filter(fn ($file) => str_contains((string) file_get_contents($file), "auth('tenant')"))
            ->map(fn ($file) => str_replace(['\\', '/'], '.', trim(str_replace([resource_path('views'), '.blade.php'], '', $file), '\\/')))
            ->values();

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $action = $route->getAction();
            $view = $action['view'] ?? null;

            if ($view === null || ! $guardViews->contains($view)) {
                continue;
            }

            $this->assertContains(
                'tenant.session',
                $route->gatherMiddleware(),
                "Route `{$route->uri()}` renders `{$view}`, which uses the tenant guard, without `tenant.session`."
            );
        }
    }
}
