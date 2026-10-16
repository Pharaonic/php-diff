<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

use Pharaonic\Diff\Engine\StructuredDiffer;
use Pharaonic\Diff\Engine\TextDiffer;

/**
 * What changed between an old value and a new one
 */
final class Diff
{
    private function __construct() {}

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

    /**
     * Compare two texts line by line
     *
     * Lines end with "\n", "\r\n" or "\r", which are all treated alike; whether a text ends with a
     * line ending is compared too.
     */
    public static function text(string $before, string $after, ?TextDiffOptions $options = null): TextDiffResult
    {
        return (new TextDiffer($options ?? TextDiffOptions::create()))->diff($before, $after);
    }
}
