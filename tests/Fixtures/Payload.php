<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Tests\Fixtures;

use JsonSerializable;

final class Payload implements JsonSerializable
{
    public function __construct(public mixed $data)
    {
    }

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }
}
