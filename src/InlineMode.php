<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

/**
 * How changes inside a modified line are highlighted, set with TextDiffOptions::inline()
 */
final class InlineMode
{
    public const NONE = 'none';

    public const WORD = 'word';

    /**
     * Grapheme clusters for valid UTF-8, bytes otherwise.
     */
    public const CHARACTER = 'character';

    private function __construct() {}
}
