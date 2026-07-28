<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Guards on the two JSON translation catalogues.
 *
 * The one that earns its keep is `test_every_translated_string_has_a_key`. A
 * missing key is INVISIBLE: `__()` falls back to returning the key itself, so
 * the Arabic renders perfectly for Arabic users while English users quietly get
 * Arabic too. Nothing throws, nothing is logged, and the page looks fine to
 * whoever wrote it. That is how 128 strings - most of the Super Admin portal -
 * ended up untranslated without anyone noticing.
 *
 * Ordering is asserted as a plain SORT_STRING sort. The files were previously
 * ordered by a collation nothing could reproduce, which made every insertion a
 * judgement call; a mechanical order is one a test can actually enforce.
 */
class TranslationFilesTest extends TestCase
{
    /** Directories whose source can contain translatable strings. */
    private const SCANNED = ['app', 'resources', 'routes', 'config', 'database'];

    /** @return array<string, array{string}> */
    public static function localeProvider(): array
    {
        return ['ar' => ['ar'], 'en' => ['en']];
    }

    /**
     * Every Arabic string passed to __() or trans_choice() must exist in the
     * catalogue, or the English build silently shows Arabic.
     */
    public function test_every_translated_string_has_a_key(): void
    {
        $catalogue = $this->load('ar');
        $missing = [];

        foreach ($this->sourceFiles() as $path) {
            foreach ($this->translatableStrings((string) file_get_contents($path)) as $key) {
                // Only Arabic strings are catalogue keys; 'success' and friends
                // are flash-message channels, not user-facing copy.
                if (! preg_match('/\p{Arabic}/u', $key)) {
                    continue;
                }

                if (! array_key_exists($key, $catalogue)) {
                    $missing[$key] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
                }
            }
        }

        $this->assertSame([], $missing, sprintf(
            "%d translated string(s) have no entry in lang/ar.json, so English users see Arabic:\n  - %s",
            count($missing),
            implode("\n  - ", array_map(
                fn ($file, $key) => "\"{$key}\"  ({$file})",
                $missing,
                array_keys($missing),
            )),
        ));
    }

    #[DataProvider('localeProvider')]
    public function test_the_catalogue_is_sorted(string $locale): void
    {
        $keys = array_keys($this->load($locale));
        $sorted = $keys;
        sort($sorted, SORT_STRING);

        // Reported as the first offender rather than a 1000-line array diff.
        foreach ($keys as $i => $key) {
            $this->assertSame(
                $sorted[$i],
                $key,
                "lang/{$locale}.json is out of order at position {$i}: expected \"{$sorted[$i]}\", found \"{$key}\".",
            );
        }
    }

    public function test_both_locales_define_the_same_keys(): void
    {
        $ar = array_keys($this->load('ar'));
        $en = array_keys($this->load('en'));

        $this->assertSame([], array_values(array_diff($ar, $en)), 'Keys present in ar.json but missing from en.json.');
        $this->assertSame([], array_values(array_diff($en, $ar)), 'Keys present in en.json but missing from ar.json.');
    }

    /**
     * In ar.json the value IS the key - Arabic is the source language, so any
     * other value there is a typo that silently changes what users read.
     */
    public function test_arabic_values_mirror_their_keys(): void
    {
        foreach ($this->load('ar') as $key => $value) {
            $this->assertSame($key, $value, "lang/ar.json: \"{$key}\" does not map to itself.");
        }
    }

    /** An English value left as its Arabic key is an untranslated string. */
    public function test_no_english_value_is_still_arabic(): void
    {
        $untranslated = [];

        foreach ($this->load('en') as $key => $value) {
            $this->assertNotSame('', trim((string) $value), "lang/en.json: \"{$key}\" is empty.");

            if ($key === $value && preg_match('/\p{Arabic}/u', (string) $value)) {
                $untranslated[] = $key;
            }
        }

        $this->assertSame([], $untranslated, 'lang/en.json still holds the Arabic source for these keys.');
    }

    #[DataProvider('localeProvider')]
    public function test_no_value_is_left_empty(string $locale): void
    {
        foreach ($this->load($locale) as $key => $value) {
            $this->assertNotSame('', trim((string) $value), "lang/{$locale}.json: \"{$key}\" is empty.");
        }
    }

    /**
     * Both quote styles, because a string containing an apostrophe is written
     * with double quotes and would otherwise be skipped by the scan.
     *
     * @return array<int, string>
     */
    private function translatableStrings(string $source): array
    {
        $found = [];

        $patterns = [
            "/(?:__|trans_choice)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/",
            '/(?:__|trans_choice)\(\s*"((?:[^"\\\\]|\\\\.)*)"/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $source, $matches)) {
                foreach ($matches[1] as $raw) {
                    $found[] = stripcslashes($raw);
                }
            }
        }

        return $found;
    }

    /** @return array<int, string> */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (self::SCANNED as $dir) {
            $path = base_path($dir);

            if (! is_dir($path)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /** @return array<string, string> */
    private function load(string $locale): array
    {
        $path = lang_path("{$locale}.json");
        $this->assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded, "lang/{$locale}.json is not valid JSON.");

        return $decoded;
    }
}
