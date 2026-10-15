<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Exceptions;

use InvalidArgumentException;
use Pharaonic\Diff\Path;

/**
 * Thrown when a value cannot be compared: a resource, a closure, a generator, an object without
 * a normalizer, or an object that contains itself.
 */
class UnsupportedValueException extends InvalidArgumentException implements DiffException
{
    /**
     * @internal
     */
    public static function at(Path $path, string $reason): self
    {
        return new self(sprintf('%s %s.', $reason, self::where($path)));
    }

    /**
     * @internal
     */
    public static function circular(Path $path, object $value): self
    {
        return self::at($path, sprintf('The %s object contains itself', get_class($value)));
    }

    protected static function where(Path $path): string
    {
        return $path->isRoot() ? 'at the root' : sprintf('at path "%s"', $path->toDot());
    }
}
