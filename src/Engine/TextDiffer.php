<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Engine;

use Pharaonic\Diff\Algorithm\Myers;
use Pharaonic\Diff\ChangeType;
use Pharaonic\Diff\DiffLine;
use Pharaonic\Diff\Exceptions\InputLimitExceededException;
use Pharaonic\Diff\InlineMode;
use Pharaonic\Diff\ModifiedLinePair;
use Pharaonic\Diff\Text\Hunks;
use Pharaonic\Diff\Text\Inline;
use Pharaonic\Diff\Text\LinePairs;
use Pharaonic\Diff\Text\Lines;
use Pharaonic\Diff\TextDiffOptions;
use Pharaonic\Diff\TextDiffResult;

/**
 * Aligns the lines of two texts
 *
 * Lines are compared by a key: their content, optionally with whitespace changes ignored, and
 * for the last line of a text without a final line ending, a marker, so that adding or removing
 * that line ending changes the last line as it does in Git.
 *
 * @internal
 */
final class TextDiffer
{
    private const NO_NEWLINE = "\0no newline";

    public function __construct(private TextDiffOptions $options)
    {
    }

    public function diff(string $before, string $after): TextDiffResult
    {
        $old = $this->split($before, 'old');
        $new = $this->split($after, 'new');

        $ids = [];
        $oldTokens = [];
        $newTokens = [];

        foreach ($this->keys($old) as $key) {
            $oldTokens[] = $ids[$key] ??= count($ids);
        }

        foreach ($this->keys($new) as $key) {
            $newTokens[] = $ids[$key] ??= count($ids);
        }

        $lines = [];

        foreach (Myers::diff($oldTokens, $newTokens) as [$operation, $oldIndex, $newIndex]) {
            if ($operation === Myers::EQUAL) {
                $lines[] = new DiffLine(ChangeType::UNCHANGED, $old->lines[$oldIndex], $oldIndex + 1, $newIndex + 1);
            } elseif ($operation === Myers::DELETE) {
                $lines[] = new DiffLine(ChangeType::REMOVED, $old->lines[$oldIndex], $oldIndex + 1, null);
            } else {
                $lines[] = new DiffLine(ChangeType::ADDED, $new->lines[$newIndex], null, $newIndex + 1);
            }
        }

        $found = LinePairs::find($lines, $this->options);
        $highlighted = [];

        foreach ($found as [$removed, $added]) {
            [$highlighted[$removed], $highlighted[$added]] = $this->highlight($lines[$removed], $lines[$added]);
        }

        $final = [];

        foreach ($lines as $index => $line) {
            $final[] = $highlighted[$index] ?? $line;
        }

        $lines = $final;
        $pairs = array_map(static fn (array $pair): ModifiedLinePair => new ModifiedLinePair($lines[$pair[0]], $lines[$pair[1]]), $found);

        return new TextDiffResult(
            $lines,
            Hunks::build($lines, $this->options->contextLines()),
            $pairs,
            $old->endsWithNewline,
            $new->endsWithNewline,
        );
    }

    /**
     * A paired removed and added line with their inline pieces, unchanged when highlighting is off
     * or either line is longer than the inline limit
     *
     * @return array{DiffLine, DiffLine}
     */
    private function highlight(DiffLine $old, DiffLine $new): array
    {
        $limit = $this->options->inlineLengthLimit();

        if ($this->options->inlineMode() === InlineMode::NONE || strlen($old->content()) > $limit || strlen($new->content()) > $limit) {
            return [$old, $new];
        }

        [$oldSegments, $newSegments] = Inline::segments($old->content(), $new->content(), $this->options->inlineMode());

        return [
            new DiffLine($old->type(), $old->content(), $old->oldLineNumber(), null, $oldSegments),
            new DiffLine($new->type(), $new->content(), null, $new->newLineNumber(), $newSegments),
        ];
    }

    private function split(string $text, string $side): Lines
    {
        if (strlen($text) > $this->options->byteLimit()) {
            throw InputLimitExceededException::bytes($side, strlen($text), $this->options->byteLimit());
        }

        $lines = Lines::split($text);

        if (count($lines->lines) > $this->options->lineLimit()) {
            throw InputLimitExceededException::lines($side, count($lines->lines), $this->options->lineLimit());
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function keys(Lines $lines): array
    {
        $keys = [];
        $last = count($lines->lines) - 1;

        foreach ($lines->lines as $index => $line) {
            if ($this->options->ignoresWhitespaceChanges()) {
                $line = rtrim((string) preg_replace('/[ \t]+/', ' ', $line), ' ');
            } elseif ($this->options->ignoresTrailingWhitespace()) {
                $line = rtrim($line, " \t");
            }

            $keys[] = $index === $last && ! $lines->endsWithNewline ? $line . self::NO_NEWLINE : $line;
        }

        return $keys;
    }
}
