<?php

namespace App\Support;

final class SearchTerm
{
    public const ESCAPE = '!';

    public static function pattern(string $term): string
    {
        $escaped = str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $term,
        );

        return '%'.$escaped.'%';
    }
}
