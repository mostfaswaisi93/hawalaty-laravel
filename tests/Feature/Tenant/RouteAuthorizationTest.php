<?php

namespace Tests\Feature\Tenant;

use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

class RouteAuthorizationTest extends TestCase
{
    private const SELF_SERVICE = [
        'logout',
        'notifications/{notification}/read',
        'notifications/read-all',
        'settings/profile',
    ];

    public function test_every_tenant_write_route_checks_a_permission(): void
    {
        $sources = [];
        $ungated = [];
        $gated = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $writes = array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']);

            if (! $writes || ! in_array('auth:tenant', $route->gatherMiddleware(), true)) {
                continue;
            }

            $uses = $route->getAction()['uses'] ?? null;

            if (! is_string($uses) || ! str_contains($uses, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $uses);

            $sources[$class] ??= (string) file_get_contents((new ReflectionClass($class))->getFileName());

            $body = $this->methodBody($sources[$class], $method);

            $checks = str_contains($body, 'hasPermission')
                || str_contains($body, 'authorize')
                || str_contains($body, 'canManage')
                || str_contains($body, 'abort_unless');

            if ($checks) {
                $gated++;

                continue;
            }

            if (in_array($route->uri(), self::SELF_SERVICE, true)) {
                continue;
            }

            $ungated[] = sprintf(
                '%s /%s -> %s@%s',
                implode('|', $writes),
                $route->uri(),
                class_basename($class),
                $method,
            );
        }

        $this->assertGreaterThan(20, $gated, 'The scan matched almost no routes - it is broken, not the app.');

        $this->assertSame([], $ungated, sprintf(
            "%d tenant write route(s) enforce no permission. Any signed-in member can call them.\n".
            "If one genuinely acts only on the caller's own data, add it to SELF_SERVICE - after reading it.",
            count($ungated),
        ));
    }

    private function methodBody(string $source, string $method): string
    {
        $at = strpos($source, "function {$method}(");

        if ($at === false) {
            return '';
        }

        $open = strpos($source, '{', $at);

        if ($open === false) {
            return '';
        }

        $depth = 0;

        for ($i = $open, $len = strlen($source); $i < $len; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($source, $open, $i - $open + 1);
                }
            }
        }

        return '';
    }
}
