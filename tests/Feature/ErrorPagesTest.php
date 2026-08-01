<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

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

        $this->assertStringContainsString('</html>', $html);
    }

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

    public function test_they_render_with_no_settings_table(): void
    {
        Schema::drop('settings');

        foreach ([403, 404, 419, 429, 500] as $status) {
            $html = view("errors.{$status}")->render();
            $this->assertNotSame('', trim($html), "errors.{$status} died without the settings table.");
        }
    }
}
