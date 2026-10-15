<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

/**
 * The lines of two texts, aligned: unchanged, removed and added lines in reading order, with the
 * removed lines of a change before its added lines
 */
final class TextDiffResult
{
    /**
     * @param list<DiffLine> $lines
     * @param list<Hunk> $hunks
     *
     * @internal Results are created by Diff::text().
     */
    public function __construct(
        private array $lines,
        private array $hunks,
        private bool $oldEndsWithNewline,
        private bool $newEndsWithNewline,
    ) {
    }

    public function hasChanges(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->type() !== ChangeType::UNCHANGED) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<DiffLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * The changes with TextDiffOptions::context() unchanged lines around them; none when the texts
     * are equal
     *
     * @return list<Hunk>
     */
    public function hunks(): array
    {
        return $this->hunks;
    }

    /**
     * @return list<DiffLine>
     */
    public function addedLines(): array
    {
        return $this->ofType(ChangeType::ADDED);
    }

    /**
     * @return list<DiffLine>
     */
    public function removedLines(): array
    {
        return $this->ofType(ChangeType::REMOVED);
    }

    /**
     * @return array{added: int, removed: int, unchanged: int}
     */
    public function stats(): array
    {
        return [
            'added' => count($this->addedLines()),
            'removed' => count($this->removedLines()),
            'unchanged' => count($this->ofType(ChangeType::UNCHANGED)),
        ];
    }

    /**
     * @internal
     */
    public function oldEndsWithNewline(): bool
    {
        return $this->oldEndsWithNewline;
    }

    /**
     * @internal
     */
    public function newEndsWithNewline(): bool
    {
        return $this->newEndsWithNewline;
    }

    /**
     * @return list<DiffLine>
     */
    private function ofType(string $type): array
    {
        return array_values(array_filter($this->lines, static fn (DiffLine $line): bool => $line->type() === $type));
    }
}
