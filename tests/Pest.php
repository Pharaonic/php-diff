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
        static fn(Change $change): string => trim(sprintf('%s %s', $change->type(), $change->path()->toDot())) . ($change->isAggregate() ? ' [aggregate]' : ''),
        $result->changes(),
    );
}

/**
 * Each change as "type path", with "old->new" when a list item changed its index
 *
 * @return list<string>
 */
function moves(DiffResult $result): array
{
    return array_map(static function (Change $change): string {
        $old = $change->oldPath()?->toDot();
        $new = $change->newPath()?->toDot();
        $where = $old !== null && $new !== null && $old !== $new ? "{$old}->{$new}" : $change->path()->toDot();

        return $change->type() . ' ' . $where . ($change->isAggregate() ? ' [aggregate]' : '');
    }, $result->changes());
}

/**
 * Each line as " content", "-content" or "+content", like a unified diff body
 *
 * @return list<string>
 */
function textLines(Pharaonic\Diff\TextDiffResult $result): array
{
    $markers = ['unchanged' => ' ', 'removed' => '-', 'added' => '+'];

    return array_map(
        static fn(Pharaonic\Diff\DiffLine $line): string => $markers[$line->type()] . $line->content(),
        $result->lines(),
    );
}
