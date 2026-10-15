<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Support;

/**
 * @internal
 */
final class Arrays
{
    /**
     * Whether the keys are 0 to n-1 in order (array_is_list() only exists from PHP 8.1)
     *
     * @param array<mixed> $value
     *
     * @phpstan-assert-if-true list<mixed> $value
     */
    public static function isList(array $value): bool
    {
        $expected = 0;

        foreach ($value as $key => $_) {
            if ($key !== $expected++) {
                return false;
            }
        }

        return true;
    }
}
