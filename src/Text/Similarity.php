<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Text;

use Pharaonic\Diff\Algorithm\Myers;

/**
 * How alike two lines are, from 0 to 1: twice the number of words and punctuation characters
 * they share in order, divided by how many both lines have together
 *
 * Lines longer than the inline limit are scored by their common prefix and suffix instead, which
 * stays fast on huge lines. Lines without any words are compared character by character.
 *
 * @internal
 */
final class Similarity
{
    public static function ratio(string $a, string $b, int $maxLength): float
    {
        if ($a === $b) {
            return 1.0;
        }

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if (strlen($a) > $maxLength || strlen($b) > $maxLength) {
            return self::edges($a, $b);
        }

        $aTokens = Tokens::words($a);
        $bTokens = Tokens::words($b);

        if ($aTokens === [] || $bTokens === []) {
            $aTokens = str_split($a);
            $bTokens = str_split($b);
        }

        $common = 0;

        foreach (Myers::diff($aTokens, $bTokens) as [$operation]) {
            $common += $operation === Myers::EQUAL ? 1 : 0;
        }

        return 2 * $common / (count($aTokens) + count($bTokens));
    }

    private static function edges(string $a, string $b): float
    {
        $max = min(strlen($a), strlen($b));
        $prefix = strspn($a ^ $b, "\0");
        $prefix = min($prefix, $max);
        $suffix = 0;

        while ($suffix < $max - $prefix && $a[strlen($a) - 1 - $suffix] === $b[strlen($b) - 1 - $suffix]) {
            $suffix++;
        }

        return 2 * ($prefix + $suffix) / (strlen($a) + strlen($b));
    }
}
