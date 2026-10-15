<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Exceptions;

use Pharaonic\Diff\Path;
use UnexpectedValueException;

/**
 * Thrown when an item of a list compared by identity has no identity or shares it with another item.
 */
final class IdentityException extends UnexpectedValueException implements DiffException
{
    /**
     * @internal
     */
    public static function duplicate(Path $list, int $index, int $firstIndex): self
    {
        return new self(sprintf(
            'The items at indexes %d and %d of the list at %s have the same identity.',
            $firstIndex,
            $index,
            self::where($list),
        ));
    }

    /**
     * @internal
     */
    public static function missing(Path $list, int $index, int|string $key): self
    {
        return new self(sprintf(
            'The item at index %d of the list at %s has no identity key "%s".',
            $index,
            self::where($list),
            $key,
        ));
    }

    private static function where(Path $path): string
    {
        return $path->isRoot() ? 'the root' : sprintf('path "%s"', $path->toDot());
    }
}
