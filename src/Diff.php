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
     * Compare two structured values: scalars, null, arrays and objects
     *
     * Objects are compared through DiffOptions::compare(), as the same instant for dates, or
     * after normalization through DiffOptions::normalize(), jsonSerialize() or, for stdClass,
     * their public properties. Other objects, resources, closures and generators throw an
     * UnsupportedValueException.
     */
    public static function between(mixed $before, mixed $after, ?DiffOptions $options = null): DiffResult
    {
        return new DiffResult((new StructuredDiffer($options ?? DiffOptions::create()))->diff($before, $after));
    }
}
