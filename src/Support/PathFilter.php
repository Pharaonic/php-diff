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
            if (Pattern::covers($pattern, $path)) {
                return self::EXCLUDED;
            }
        }

        if ($this->only === []) {
            return self::INCLUDED;
        }

        $state = self::EXCLUDED;

        foreach ($this->only as $pattern) {
            if (Pattern::covers($pattern, $path)) {
                return self::INCLUDED;
            }

            if (Pattern::leadsTo($pattern, $path)) {
                $state = self::TRAVERSED;
            }
        }

        return $state;
    }

    /**
     * Whether a value at these paths is skipped without being traversed
     *
     * @param list<int|string>|null $oldPath
     * @param list<int|string>|null $newPath
     */
    public function skips(?array $oldPath, ?array $newPath): bool
    {
        return ($oldPath !== null && $this->state($oldPath) === self::EXCLUDED)
            || ($newPath !== null && $this->state($newPath) === self::EXCLUDED);
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
}
