<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

/**
 * The kinds of change a diff reports
 *
 * Structured diffs report added, removed, modified and moved values. Text diff lines are added,
 * removed or unchanged.
 */
final class ChangeType
{
    public const ADDED = 'added';

    public const REMOVED = 'removed';

    public const MODIFIED = 'modified';

    public const MOVED = 'moved';

    public const UNCHANGED = 'unchanged';

    private function __construct()
    {
    }
}
