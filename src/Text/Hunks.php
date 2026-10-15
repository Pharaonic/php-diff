<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Text;

use Pharaonic\Diff\ChangeType;
use Pharaonic\Diff\DiffLine;
use Pharaonic\Diff\Hunk;

/**
 * Groups aligned lines into hunks: each change gets up to $context unchanged lines on both sides,
 * and changes separated by at most twice that many unchanged lines share one hunk.
 *
 * @internal
 */
final class Hunks
{
    /**
     * @param list<DiffLine> $lines
     *
     * @return list<Hunk>
     */
    public static function build(array $lines, int $context): array
    {
        $ranges = [];

        foreach ($lines as $index => $line) {
            if ($line->type() === ChangeType::UNCHANGED) {
                continue;
            }

            $last = count($ranges) - 1;

            if ($last >= 0 && $index - $ranges[$last][1] - 1 <= 2 * $context) {
                $ranges[$last][1] = $index;
            } else {
                $ranges[] = [$index, $index];
            }
        }

        $hunks = [];
        $count = count($lines);

        foreach ($ranges as [$first, $last]) {
            $start = max(0, $first - $context);
            $end = min($count - 1, $last + $context);
            $hunks[] = self::hunk($lines, $start, $end);
        }

        return $hunks;
    }

    /**
     * @param list<DiffLine> $lines
     */
    private static function hunk(array $lines, int $start, int $end): Hunk
    {
        $oldBefore = 0;
        $newBefore = 0;

        for ($i = 0; $i < $start; $i++) {
            $oldBefore += $lines[$i]->oldLineNumber() === null ? 0 : 1;
            $newBefore += $lines[$i]->newLineNumber() === null ? 0 : 1;
        }

        $hunkLines = array_slice($lines, $start, $end - $start + 1);
        $oldLength = 0;
        $newLength = 0;

        foreach ($hunkLines as $line) {
            $oldLength += $line->oldLineNumber() === null ? 0 : 1;
            $newLength += $line->newLineNumber() === null ? 0 : 1;
        }

        return new Hunk(
            $oldLength === 0 ? $oldBefore : $oldBefore + 1,
            $oldLength,
            $newLength === 0 ? $newBefore : $newBefore + 1,
            $newLength,
            $hunkLines,
        );
    }
}
