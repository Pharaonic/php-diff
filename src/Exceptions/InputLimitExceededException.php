<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Exceptions;

use LengthException;

/**
 * Thrown when a text is larger than the limits set on TextDiffOptions.
 */
final class InputLimitExceededException extends LengthException implements DiffException
{
    /**
     * @internal
     */
    public static function lines(string $side, int $lines, int $maxLines): self
    {
        return new self(sprintf('The %s text has %d lines, more than the maximum of %d.', $side, $lines, $maxLines));
    }

    /**
     * @internal
     */
    public static function bytes(string $side, int $bytes, int $maxBytes): self
    {
        return new self(sprintf('The %s text has %d bytes, more than the maximum of %d.', $side, $bytes, $maxBytes));
    }
}
