<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Support;

/**
 * Matching of parsed dot-notation patterns, where null stands for the "*" wildcard
 *
 * @internal
 */
final class Pattern
{
    /**
     * Whether the pattern matches exactly this path
     *
     * @param list<string|null> $pattern
     * @param list<int|string> $path
     */
    public static function matches(array $pattern, array $path): bool
    {
        return count($pattern) === count($path) && self::startsWith($pattern, $path);
    }

    /**
     * Whether the path is at or below what the pattern matches
     *
     * @param list<string|null> $pattern
     * @param list<int|string> $path
     */
    public static function covers(array $pattern, array $path): bool
    {
        return count($pattern) <= count($path) && self::startsWith($pattern, array_slice($path, 0, count($pattern)));
    }

    /**
     * Whether the path is strictly above what the pattern matches
     *
     * @param list<string|null> $pattern
     * @param list<int|string> $path
     */
    public static function leadsTo(array $pattern, array $path): bool
    {
        return count($pattern) > count($path) && self::startsWith($pattern, $path);
    }

    /**
     * @param list<string|null> $pattern
     * @param list<int|string> $path
     */
    private static function startsWith(array $pattern, array $path): bool
    {
        foreach ($path as $i => $key) {
            if ($pattern[$i] !== null && $pattern[$i] !== (string) $key) {
                return false;
            }
        }

        return true;
    }
}
