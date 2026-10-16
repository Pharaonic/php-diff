<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Support;

use InvalidArgumentException;

/**
 * Dot notation, with the same syntax as pharaonic/php-dot-array and pharaonic/php-fingerprint
 *
 * Segments are joined with ".", and a literal ".", "*" or "\" inside a key is escaped with "\".
 * In a pattern, a segment that is exactly "*" matches any one key or list index; in a path it is
 * the key "*". Empty segments are rejected; the empty string is the root.
 *
 * @internal
 */
final class DotNotation
{
    /**
     * Parse a pattern into segments where null stands for the "*" wildcard
     *
     * @return list<string|null>
     */
    public static function parse(string $dot): array
    {
        if ($dot === '') {
            return [];
        }

        $segments = [];
        $segment = '';
        $escaped = false;
        $length = strlen($dot);

        for ($i = 0; $i < $length; $i++) {
            $char = $dot[$i];

            if ($char === '\\' && $i + 1 < $length && strpos('.*\\', $dot[$i + 1]) !== false) {
                $segment .= $dot[++$i];
                $escaped = true;
            } elseif ($char === '.') {
                $segments[] = self::segment($dot, $segment, $escaped);
                $segment = '';
                $escaped = false;
            } else {
                $segment .= $char;
            }
        }

        $segments[] = self::segment($dot, $segment, $escaped);

        return $segments;
    }

    /**
     * @param list<int|string> $segments
     */
    public static function format(array $segments): string
    {
        return implode('.', array_map(
            static fn(int|string $segment): string => addcslashes((string) $segment, '.*\\'),
            $segments,
        ));
    }

    private static function segment(string $dot, string $segment, bool $escaped): ?string
    {
        if ($segment === '') {
            throw new InvalidArgumentException(sprintf('The path "%s" has an empty segment.', $dot));
        }

        return $segment === '*' && ! $escaped ? null : $segment;
    }
}
