<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

use JsonSerializable;
use Pharaonic\Diff\Renderer\UnifiedRenderer;

/**
 * The lines of two texts, aligned: unchanged, removed and added lines in reading order, with the
 * removed lines of a change before its added lines
 */
final class TextDiffResult implements JsonSerializable
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
     * A unified diff that `git apply` and `patch` accept, or an empty string when the texts are
     * equal; its lines end with "\n"
     */
    public function toUnified(string $oldLabel = 'before', string $newLabel = 'after'): string
    {
        return UnifiedRenderer::render($this, $oldLabel, $newLabel);
    }

    /**
     * @return array{stats: array{added: int, removed: int, unchanged: int}, old_ends_with_newline: bool, new_ends_with_newline: bool, hunks: list<array{old_start: int, old_length: int, new_start: int, new_length: int, lines: list<array{type: string, content: string, old_line: int|null, new_line: int|null}>}>}
     */
    public function toArray(): array
    {
        return [
            'stats' => $this->stats(),
            'old_ends_with_newline' => $this->oldEndsWithNewline,
            'new_ends_with_newline' => $this->newEndsWithNewline,
            'hunks' => array_map(static fn (Hunk $hunk): array => $hunk->toArray(), $this->hunks),
        ];
    }

    /**
     * @return array{stats: array{added: int, removed: int, unchanged: int}, old_ends_with_newline: bool, new_ends_with_newline: bool, hunks: list<array{old_start: int, old_length: int, new_start: int, new_length: int, lines: list<array{type: string, content: string, old_line: int|null, new_line: int|null}>}>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Encode toArray() as JSON; JSON_THROW_ON_ERROR is always added
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
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
