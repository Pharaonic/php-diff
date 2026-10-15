<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Tests\Fixtures;

final class Money
{
    public function __construct(public int $cents, public string $currency)
    {
    }
}
