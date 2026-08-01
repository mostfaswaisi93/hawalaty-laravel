<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ViewMarkupTest extends TestCase
{
    private const STATIC_ID = '/\bid="([^"{]+)"/';

    public function test_every_label_names_a_control(): void
    {
        $orphans = [];

        foreach ($this->templates() as $rel => $src) {
            preg_match_all('/<label\b([^>]*)>/', $src, $labels);

            foreach ($labels[1] as $attrs) {
                if (str_contains($attrs, 'for=') || str_contains($attrs, 'cursor-pointer')) {
                    continue;
                }

                if (str_contains($attrs, 'id=') && str_contains($src, 'aria-labelledby=')) {
                    continue;
                }

                $orphans[] = $rel;
            }
        }

        $this->assertSame([], array_values(array_unique($orphans)), sprintf(
            "%d <label> element(s) name no control, so a screen reader reads their field as unlabelled.\n".
            'Give the control an id and the label a matching `for`.',
            count($orphans),
        ));
    }

    public function test_every_for_attribute_points_at_exactly_one_id(): void
    {
        $broken = [];

        foreach ($this->templates() as $rel => $src) {
            preg_match_all('/(?<![-\w:])for="([^"{]+)"/', $src, $fors);

            foreach ($fors[1] as $id) {
                $matches = preg_match_all('/\bid="'.preg_quote($id, '/').'"/', $src);

                if ($matches !== 1) {
                    $broken[] = "{$rel}: for=\"{$id}\" matches {$matches} id(s), expected exactly 1";
                }
            }
        }

        $this->assertSame([], $broken);
    }

    public function test_no_static_id_is_used_twice_in_one_template(): void
    {
        $duplicates = [];

        foreach ($this->templates() as $rel => $src) {
            preg_match_all(self::STATIC_ID, $src, $ids);

            foreach (array_count_values($ids[1]) as $id => $count) {
                if ($count > 1) {
                    $duplicates[] = "{$rel}: id=\"{$id}\" appears {$count} times";
                }
            }
        }

        $this->assertSame([], $duplicates);
    }

    public function test_no_id_attribute_sits_outside_a_tag(): void
    {
        $loose = [];

        foreach ($this->templates() as $rel => $src) {
            preg_match_all(self::STATIC_ID, $src, $ids, PREG_OFFSET_CAPTURE);

            foreach ($ids[0] as [$match, $offset]) {
                $before = substr($src, 0, $offset);
                $open = strrpos($before, '<');
                $close = strrpos($before, '>');

                if ($open === false || ($close !== false && $close > $open)) {
                    $loose[] = "{$rel}: {$match} is not inside a tag";
                }
            }
        }

        $this->assertSame([], $loose);
    }

    public function test_each_shell_offers_a_working_skip_link(): void
    {
        foreach (['layouts/app.blade.php', 'layouts/admin.blade.php'] as $shell) {
            $src = $this->templates()[$shell] ?? null;
            $this->assertNotNull($src, "{$shell} is gone.");

            $this->assertStringContainsString('<x-skip-link', $src, "{$shell} has no skip link.");
            $this->assertMatchesRegularExpression(
                '/<main[^>]*\bid="main-content"/',
                $src,
                "{$shell}: the skip link's target id is missing from <main>.",
            );
            $this->assertMatchesRegularExpression(
                '/<main[^>]*\btabindex="-1"/',
                $src,
                "{$shell}: <main> is not focusable, so the skip link moves the viewport but not focus.",
            );
        }

        $link = $this->templates()['components/skip-link.blade.php'] ?? null;
        $this->assertNotNull($link, 'The skip-link component is gone.');
        $this->assertStringContainsString('href="#main-content"', $link);

        $this->assertStringContainsString('sr-only', $link);
        $this->assertStringContainsString('focus:not-sr-only', $link);
    }

    public function test_every_linked_icon_exists_and_is_not_empty(): void
    {
        $missing = [];

        foreach ($this->templates() as $rel => $src) {
            if (! preg_match_all('/(?:href|content)="(\/[\w.\-]+\.(?:ico|svg|png|webmanifest))"/', $src, $m)) {
                continue;
            }

            foreach (array_unique($m[1]) as $path) {
                $file = public_path(ltrim($path, '/'));

                if (! file_exists($file)) {
                    $missing[] = "{$rel}: {$path} does not exist";
                } elseif (filesize($file) === 0) {
                    $missing[] = "{$rel}: {$path} is an empty file";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_the_web_manifest_is_valid(): void
    {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $manifest = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($manifest, 'manifest.webmanifest is not valid JSON.');

        foreach (['name', 'start_url', 'display', 'theme_color', 'icons'] as $key) {
            $this->assertArrayHasKey($key, $manifest, "manifest is missing `{$key}`.");
        }

        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);

        foreach ($manifest['icons'] as $icon) {
            $file = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($file, "manifest icon {$icon['src']} is missing.");
            $this->assertGreaterThan(0, filesize($file), "manifest icon {$icon['src']} is empty.");
        }
    }

    private function templates(): array
    {
        $root = resource_path('views');
        $out = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $rel = str_replace([$root.DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file->getPathname());

            $out[$rel] = preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($file->getPathname()));
        }

        ksort($out);

        return $out;
    }
}
