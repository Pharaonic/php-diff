<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

/**
 * A run of changed lines with the unchanged lines around them, as in a unified diff
 *
 * When a side has no lines in the hunk, its start is the number of the line the hunk comes
 * after, or 0 at the start of the text, as Git and GNU diff write it.
 */
final class Hunk
{
    /**
     * @param list<DiffLine> $lines
     *
     * @internal Hunks are created by Diff::text().
     */
    public function __construct(
        private int $oldStart,
        private int $oldLength,
        private int $newStart,
        private int $newLength,
        private array $lines,
    ) {
    }

    public function oldStart(): int
    {
        return $this->oldStart;
    }

    public function oldLength(): int
    {
        return $this->oldLength;
    }

    public function newStart(): int
    {
        return $this->newStart;
    }

    public function newLength(): int
    {
        return $this->newLength;
    }

    /**
     * @return list<DiffLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return array{old_start: int, old_length: int, new_start: int, new_length: int, lines: list<array{type: string, content: string, old_line: int|null, new_line: int|null}>}
     */
    public function toArray(): array
    {
        return [
            'old_start' => $this->oldStart,
            'old_length' => $this->oldLength,
            'new_start' => $this->newStart,
            'new_length' => $this->newLength,
            'lines' => array_map(static fn (DiffLine $line): array => $line->toArray(), $this->lines),
        ];
    }
}
