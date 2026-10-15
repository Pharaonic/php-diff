<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Support;

use Closure;
use Generator;

/**
 * @internal
 */
final class Values
{
    /**
     * Why a value cannot be compared
     */
    public static function describe(mixed $value): string
    {
        if ($value instanceof Closure) {
            return 'A closure cannot be compared';
        }

        if ($value instanceof Generator) {
            return 'A generator cannot be compared';
        }

        if (is_object($value)) {
            return sprintf('An object of class %s cannot be compared without a normalizer', get_class($value));
        }

        return sprintf('A value of type %s cannot be compared', get_debug_type($value));
    }
}
