<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Structural guarantees about the Blade templates that nothing else catches.
 *
 * These exist because of a real incident: a script that added `for`/`id` pairs
 * computed an offset with `strlen('<').strlen($tag)` - string CONCATENATION,
 * "1" and "6" becoming 16 instead of adding to 7 - and inserted ids
 * mid-attribute across thirteen files. One file ended up with four ids stacked
 * on a stray line after the closing form tag. Every test still passed, Pint was
 * clean, and the build succeeded, because none of them look at markup shape.
 *
 * A broken `for`/`id` pair is invisible in exactly the same way a missing
 * translation key is: the page renders, it looks right, and only someone using
 * a screen reader ever finds out.
 */
class ViewMarkupTest extends TestCase
{
    /**
     * Ids containing Blade interpolation (`id="delete-item-{{ $item->id }}"`)
     * are per-row by design and cannot be checked statically.
     */
    private const STATIC_ID = '/\bid="([^"{]+)"/';

    public function test_every_label_names_a_control(): void
    {
        $orphans = [];

        foreach ($this->templates() as $rel => $src) {
            preg_match_all('/<label\b([^>]*)>/', $src, $labels);

            foreach ($labels[1] as $attrs) {
                // Three valid ways a label can name a control:
                //   for="id"          the usual pairing;
                //   cursor-pointer    the wrapping kind used for checkbox rows;
                //   id="x" + aria-labelledby elsewhere, which is how the icon
                //                     picker names its BUTTON - its only form
                //                     control is a hidden input, and nothing can
                //                     focus or announce one of those.
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
            // A label's `for`, never Alpine's `x-for` or a bound `:for`.
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

    /** A duplicate id is worse than none: the label points at whichever came first. */
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

    /**
     * The exact shape of the incident above: an id written into the document
     * body rather than into a tag, where it renders as visible text.
     */
    public function test_no_id_attribute_sits_outside_a_tag(): void
    {
        $loose = [];

        foreach ($this->templates() as $rel => $src) {
            preg_match_all(self::STATIC_ID, $src, $ids, PREG_OFFSET_CAPTURE);

            foreach ($ids[0] as [$match, $offset]) {
                $before = substr($src, 0, $offset);
                $open = strrpos($before, '<');
                $close = strrpos($before, '>');

                // Inside a tag means an unclosed `<` precedes it.
                if ($open === false || ($close !== false && $close > $open)) {
                    $loose[] = "{$rel}: {$match} is not inside a tag";
                }
            }
        }

        $this->assertSame([], $loose);
    }

    /**
     * Blade comments stripped: a comment that discusses `<label>` or shows an
     * `id="..."` example is prose, not markup, and counting it produced false
     * findings while this was being written.
     *
     * @return array<string, string> relative path => source
     */
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
