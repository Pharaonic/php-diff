<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Exceptions;

use Pharaonic\Diff\Path;

/**
 * Thrown when a value nests deeper than the maximum depth, which includes self-referencing arrays.
 */
final class MaximumDepthExceededException extends UnsupportedValueException
{
    /**
     * @internal
     */
    public static function limit(Path $path, int $maxDepth): self
    {
        return new self(sprintf(
            'The value is nested deeper than the maximum depth of %d %s; it may reference itself.',
            $maxDepth,
            self::where($path),
        ));
    }
}
