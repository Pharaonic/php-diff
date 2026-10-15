<?php

declare(strict_types=1);

use Pharaonic\Diff\Change;
use Pharaonic\Diff\DiffResult;

/**
 * Each change as "type path", with " [aggregate]" for aggregate modifications
 *
 * @return list<string>
 */
function summary(DiffResult $result): array
{
    return array_map(
        static fn (Change $change): string => trim(sprintf('%s %s', $change->type(), $change->path()->toDot())) . ($change->isAggregate() ? ' [aggregate]' : ''),
        $result->changes(),
    );
}
