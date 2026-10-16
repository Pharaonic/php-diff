<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

/**
 * How a list is compared, set per path with DiffOptions::listStrategy()
 *
 * Lists of records are compared by identity with DiffOptions::identify() instead.
 */
final class ListStrategy
{
    /**
     * Align both lists so an insertion or removal does not shift every later item (the default).
     */
    public const SEQUENCE = 'sequence';

    /**
     * Compare items index by index, for lists where the position is the item's identity.
     */
    public const POSITION = 'position';

    /**
     * Ignore order but keep counts, so a list behaves like a multiset.
     */
    public const UNORDERED = 'unordered';

    private function __construct() {}
}
