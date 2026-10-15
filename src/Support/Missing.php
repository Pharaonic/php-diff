<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Support;

/**
 * Stands for a value that does not exist, which is different from null
 *
 * @internal
 */
final class Missing
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function value(): self
    {
        return self::$instance ??= new self();
    }

    public static function is(mixed $value): bool
    {
        return $value instanceof self;
    }
}
