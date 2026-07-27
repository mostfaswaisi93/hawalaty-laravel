<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The two JSON translation files are maintained by hand, which is exactly why
 * they need a guard: a key added to one and forgotten in the other shows the
 * raw Arabic string to English users, and neither Pint nor the browser notices.
 *
 * What is NOT asserted here is a global sort order. The files are ordered by a
 * collation that is not PHP's SORT_STRING - keys opening with `:`, `«`, `(` and
 * `{` sit in an order no plain string sort reproduces - and rewriting 900 keys
 * to satisfy a test would be churn for a property no user can observe. The
 * order that does matter is that the two files agree, so that they stay
 * reviewable side by side; that is asserted below.
 */
class TranslationFilesTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function localeProvider(): array
    {
        return ['ar' => ['ar'], 'en' => ['en']];
    }

    public function test_both_locales_define_the_same_keys_in_the_same_order(): void
    {
        $ar = array_keys($this->load('ar'));
        $en = array_keys($this->load('en'));

        $this->assertSame([], array_values(array_diff($ar, $en)), 'Keys present in ar.json but missing from en.json.');
        $this->assertSame([], array_values(array_diff($en, $ar)), 'Keys present in en.json but missing from ar.json.');

        // Reported as the first offender rather than a 900-line array diff.
        foreach ($ar as $i => $key) {
            $this->assertSame(
                $key,
                $en[$i] ?? null,
                "lang/en.json diverges from ar.json at position {$i}: expected \"{$key}\".",
            );
        }
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

    #[DataProvider('localeProvider')]
    public function test_no_value_is_left_empty(string $locale): void
    {
        foreach ($this->load($locale) as $key => $value) {
            $this->assertNotSame('', trim((string) $value), "lang/{$locale}.json: \"{$key}\" is empty.");
        }
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
