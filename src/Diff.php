<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

use Pharaonic\Diff\Engine\StructuredDiffer;

/**
 * What changed between an old value and a new one
 */
final class Diff
{
    private function __construct()
    {
    }

    /**
     * Compare two structured values: scalars, null and arrays
     */
    public static function between(mixed $before, mixed $after, ?DiffOptions $options = null): DiffResult
    {
        return new DiffResult((new StructuredDiffer($options ?? DiffOptions::create()))->diff($before, $after));
    }
}
