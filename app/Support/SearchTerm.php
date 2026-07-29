<?php

namespace App\Support;

/**
 * Turns whatever a user typed into a search box into a LIKE pattern that means
 * what they typed.
 *
 * Every list page interpolated the raw term into "%{$search}%". Bindings keep
 * that injection-safe, but `%` and `_` are still WILDCARDS inside the pattern,
 * so the search silently answered a different question than the one asked:
 * `abu_ali@x.com` also matched `abuXali@x.com`, and a lone `%` returned the
 * entire table. Underscores are not exotic here - they turn up in the very
 * columns that get searched most, addresses and phone numbers.
 *
 * The escape character is `!`, not the conventional backslash, and that choice
 * is the whole reason this is portable. A backslash has to be written `'\\'` in
 * MySQL (which processes escapes inside string literals) but `'\'` in SQLite
 * (which does not, and rejects a two-character escape) - so the SQL text would
 * have to differ per driver, on a codebase that runs MySQL for tenants and
 * SQLite for the platform test suite. `!` is literal everywhere.
 *
 * Laravel 13 ships a native whereLike(), which is NOT this: it compiles to a
 * bare `LIKE ?` with no ESCAPE clause and passes the value through untouched,
 * so it has the same hole. That is also why the macro is named `whereSearch` -
 * a macro named `whereLike` would be shadowed by the real method and silently
 * never run.
 */
final class SearchTerm
{
    public const ESCAPE = '!';

    /**
     * A contains-match pattern in which every character the user typed is
     * literal.
     */
    public static function pattern(string $term): string
    {
        // The escape character must be doubled FIRST, or the `!` this method
        // introduces when escaping `%` would itself be escaped on a later pass.
        $escaped = str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $term,
        );

        return '%'.$escaped.'%';
    }
}
