<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Text;

use Pharaonic\Diff\ChangeType;
use Pharaonic\Diff\DiffLine;
use Pharaonic\Diff\ModifiedLinePair;
use Pharaonic\Diff\TextDiffOptions;

/**
 * Pairs the removed and added lines of each change that look alike
 *
 * Inside a change, the most similar removed and added lines at or above the threshold are paired
 * first, earliest on ties, then the lines before and after that pair are paired the same way, so
 * pairs never cross. A change with more than MAX_COMPARISONS candidate pairs only compares lines
 * at the same position in the change.
 *
 * @internal
 */
final class LinePairs
{
    private const MAX_COMPARISONS = 10_000;

    /**
     * @param list<DiffLine> $lines
     *
     * @return list<ModifiedLinePair>
     */
    public static function find(array $lines, TextDiffOptions $options): array
    {
        if (! $options->pairsModifiedLines()) {
            return [];
        }

        $pairs = [];
        $removed = [];
        $added = [];

        foreach ([...$lines, null] as $line) {
            if ($line !== null && $line->type() === ChangeType::REMOVED) {
                $removed[] = $line;
            } elseif ($line !== null && $line->type() === ChangeType::ADDED) {
                $added[] = $line;
            } else {
                array_push($pairs, ...self::pairChange($removed, $added, $options));
                $removed = $added = [];
            }
        }

        return $pairs;
    }

    /**
     * @param list<DiffLine> $removed
     * @param list<DiffLine> $added
     *
     * @return list<ModifiedLinePair>
     */
    private static function pairChange(array $removed, array $added, TextDiffOptions $options): array
    {
        if ($removed === [] || $added === []) {
            return [];
        }

        $threshold = $options->threshold();
        $maxLength = $options->inlineLengthLimit();

        if (count($removed) * count($added) > self::MAX_COMPARISONS) {
            $pairs = [];

            for ($i = 0, $count = min(count($removed), count($added)); $i < $count; $i++) {
                if (Similarity::ratio($removed[$i]->content(), $added[$i]->content(), $maxLength) >= $threshold) {
                    $pairs[] = new ModifiedLinePair($removed[$i], $added[$i]);
                }
            }

            return $pairs;
        }

        $scores = [];

        foreach ($removed as $i => $old) {
            foreach ($added as $j => $new) {
                $scores[$i][$j] = Similarity::ratio($old->content(), $new->content(), $maxLength);
            }
        }

        $matches = [];
        self::match($scores, $threshold, 0, count($removed), 0, count($added), $matches);
        ksort($matches);

        $pairs = [];

        foreach ($matches as $i => $j) {
            $pairs[] = new ModifiedLinePair($removed[$i], $added[$j]);
        }

        return $pairs;
    }

    /**
     * @param array<int, array<int, float>> $scores
     * @param array<int, int> $matches
     */
    private static function match(array $scores, float $threshold, int $fromRemoved, int $toRemoved, int $fromAdded, int $toAdded, array &$matches): void
    {
        $best = null;
        $bestScore = $threshold;

        for ($i = $fromRemoved; $i < $toRemoved; $i++) {
            for ($j = $fromAdded; $j < $toAdded; $j++) {
                if ($scores[$i][$j] > $bestScore || ($best === null && $scores[$i][$j] >= $bestScore)) {
                    $best = [$i, $j];
                    $bestScore = $scores[$i][$j];
                }
            }
        }

        if ($best === null) {
            return;
        }

        [$i, $j] = $best;
        $matches[$i] = $j;

        self::match($scores, $threshold, $fromRemoved, $i, $fromAdded, $j, $matches);
        self::match($scores, $threshold, $i + 1, $toRemoved, $j + 1, $toAdded, $matches);
    }
}
