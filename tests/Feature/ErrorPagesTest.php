<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The custom error pages, actually rendered.
 *
 * They existed with no coverage at all: the suite asserts status codes, which
 * Laravel produces without ever reaching these templates. So a typo, a renamed
 * route, or a translation key removed from under one of them would sit there
 * unnoticed until a real user hit it - and an error page that itself throws is
 * the worst version of the failure, because it replaces a clear "not found"
 * with a blank 500 and buries the original cause.
 *
 * They also render through `layouts.guest`, which reads platform settings. A
 * 500 page that queries a database which is *why* the request 500'd would be a
 * loop; PlatformSettings::raw() swallows Throwable for exactly this reason, and
 * `test_they_render_with_no_settings_table` holds that line.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{int}> */
    public static function statuses(): array
    {
        return [
            '403 forbidden' => [403],
            '404 not found' => [404],
            '419 page expired' => [419],
            '429 too many requests' => [429],
            '500 server error' => [500],
        ];
    }

    #[DataProvider('statuses')]
    public function test_the_error_page_renders(int $status): void
    {
        $html = view("errors.{$status}")->render();

        $this->assertNotSame('', trim($html), "errors.{$status} rendered nothing.");
        // It must be the branded page, not a bare framework fallback.
        $this->assertStringContainsString('</html>', $html);
    }

    /**
     * Every error page has to offer a way out. One that only states the problem
     * leaves the user with the back button and nothing else.
     */
    #[DataProvider('statuses')]
    public function test_the_error_page_offers_a_way_back(int $status): void
    {
        $html = view("errors.{$status}")->render();

        $this->assertMatchesRegularExpression(
            '/<a\b[^>]*href="[^"]+"/',
            $html,
            "errors.{$status} gives the user no link out.",
        );
    }

    /**
     * The 500 page must survive the database being the thing that is broken.
     *
     * Dropping the settings table reproduces it: the guest layout asks
     * PlatformSettings for the site name, and if that lookup threw, the error
     * page would fail while reporting a failure.
     */
    public function test_they_render_with_no_settings_table(): void
    {
        Schema::drop('settings');

        foreach ([403, 404, 419, 429, 500] as $status) {
            $html = view("errors.{$status}")->render();
            $this->assertNotSame('', trim($html), "errors.{$status} died without the settings table.");
        }
    }
}
