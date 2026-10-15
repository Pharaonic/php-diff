<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Support;

use Pharaonic\Diff\DiffOptions;

/**
 * Decides which paths are compared and reported, from DiffOptions::ignore() and only()
 *
 * A path is ignored when it is at or below an ignore() pattern, which wins over only(). With
 * only() patterns, a path is reported when it is at or below one of them, and only traversed
 * when it leads to one of them; every other path is skipped without being traversed.
 *
 * @internal
 */
final class PathFilter
{
    /**
     * Not compared at all
     */
    public const EXCLUDED = 0;

    /**
     * Compared to reach a path below it, but its own changes are not reported
     */
    public const TRAVERSED = 1;

    /**
     * Compared and reported
     */
    public const INCLUDED = 2;

    /**
     * @var list<list<string|null>>
     */
    private array $ignore;

    /**
     * @var list<list<string|null>>
     */
    private array $only;

    public function __construct(DiffOptions $options)
    {
        $this->ignore = $options->ignoredPatterns();
        $this->only = $options->onlyPatterns();
    }

    /**
     * @param list<int|string> $path
     *
     * @return self::EXCLUDED|self::TRAVERSED|self::INCLUDED
     */
    public function state(array $path): int
    {
        foreach ($this->ignore as $pattern) {
            if (self::covers($pattern, $path)) {
                return self::EXCLUDED;
            }
        }

        if ($this->only === []) {
            return self::INCLUDED;
        }

        $state = self::EXCLUDED;

        foreach ($this->only as $pattern) {
            if (self::covers($pattern, $path)) {
                return self::INCLUDED;
            }

            if (count($pattern) > count($path) && self::prefixMatches($pattern, $path)) {
                $state = self::TRAVERSED;
            }
        }

        return $state;
    }

    /**
     * Whether a change at either path may be reported
     *
     * @param list<int|string>|null $oldPath
     * @param list<int|string>|null $newPath
     */
    public function reports(?array $oldPath, ?array $newPath): bool
    {
        $old = $oldPath === null ? null : $this->state($oldPath);
        $new = $newPath === null ? null : $this->state($newPath);

        if ($old === self::EXCLUDED || $new === self::EXCLUDED) {
            return false;
        }

        return $old === self::INCLUDED || $new === self::INCLUDED;
    }

    /**
     * Whether the path is at or below what the pattern matches
     *
     * @param list<string|null> $pattern
     * @param list<int|string> $path
     */
    private static function covers(array $pattern, array $path): bool
    {
        return count($pattern) <= count($path) && self::prefixMatches($pattern, array_slice($path, 0, count($pattern)));
    }

    /**
     * Whether the path matches the start of the pattern
     *
     * @param list<string|null> $pattern
     * @param list<int|string> $path
     */
    private static function prefixMatches(array $pattern, array $path): bool
    {
        foreach ($path as $i => $key) {
            if ($pattern[$i] !== null && $pattern[$i] !== (string) $key) {
                return false;
            }
        }

        return true;
    }
}
