<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Text;

/**
 * Splits a line into words, single punctuation characters and, optionally, runs of whitespace
 *
 * Valid UTF-8 is split on Unicode letters and digits; anything else is split byte by byte, so
 * malformed input still splits deterministically.
 *
 * @internal
 */
final class Tokens
{
    /**
     * @return list<string>
     */
    public static function words(string $line, bool $keepWhitespace = false): array
    {
        $pattern = $keepWhitespace ? '/\w+|\s+|[^\w\s]/' : '/\w+|[^\w\s]/';

        if (preg_match('//u', $line) === 1) {
            $pattern .= 'u';
        }

        preg_match_all($pattern, $line, $matches);

        return $matches[0];
    }
}
