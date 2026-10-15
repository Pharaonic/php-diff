<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Text;

use Pharaonic\Diff\Algorithm\Myers;
use Pharaonic\Diff\ChangeType;
use Pharaonic\Diff\InlineMode;
use Pharaonic\Diff\InlineSegment;

/**
 * Splits a removed line and its paired added line into unchanged, removed and added pieces
 *
 * Word mode compares words, runs of whitespace and single punctuation characters; character mode
 * compares grapheme clusters, or bytes when a line is not valid UTF-8. The pieces of each line
 * join back into the line exactly.
 *
 * @internal
 */
final class Inline
{
    /**
     * @return array{list<InlineSegment>, list<InlineSegment>} The removed line's pieces, then the added line's
     */
    public static function segments(string $old, string $new, string $mode): array
    {
        $oldTokens = self::tokens($old, $mode);
        $newTokens = self::tokens($new, $mode);
        $oldPieces = [];
        $newPieces = [];

        foreach (Myers::diff($oldTokens, $newTokens) as [$operation, $oldIndex, $newIndex]) {
            if ($operation === Myers::EQUAL) {
                self::append($oldPieces, ChangeType::UNCHANGED, $oldTokens[$oldIndex]);
                self::append($newPieces, ChangeType::UNCHANGED, $newTokens[$newIndex]);
            } elseif ($operation === Myers::DELETE) {
                self::append($oldPieces, ChangeType::REMOVED, $oldTokens[$oldIndex]);
            } else {
                self::append($newPieces, ChangeType::ADDED, $newTokens[$newIndex]);
            }
        }

        return [self::build($oldPieces), self::build($newPieces)];
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $line, string $mode): array
    {
        if ($line === '') {
            return [];
        }

        if ($mode === InlineMode::WORD) {
            return Tokens::words($line, true);
        }

        if (preg_match_all('/\X/u', $line, $matches) !== false && $matches[0] !== []) {
            return $matches[0];
        }

        return str_split($line);
    }

    /**
     * @param list<array{string, string}> $pieces
     */
    private static function append(array &$pieces, string $type, string $text): void
    {
        $last = array_pop($pieces);

        if ($last !== null && $last[0] === $type) {
            $text = $last[1] . $text;
        } elseif ($last !== null) {
            $pieces[] = $last;
        }

        $pieces[] = [$type, $text];
    }

    /**
     * @param list<array{string, string}> $pieces
     *
     * @return list<InlineSegment>
     */
    private static function build(array $pieces): array
    {
        return array_map(static fn (array $piece): InlineSegment => new InlineSegment($piece[0], $piece[1]), $pieces);
    }
}
