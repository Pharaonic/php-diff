<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

/**
 * One line of a text diff: added, removed or unchanged
 *
 * The content never includes the line ending. An unchanged line keeps its old content, which
 * matters only when whitespace changes are ignored.
 */
final class DiffLine
{
    /**
     * @internal Lines are created by Diff::text().
     */
    public function __construct(
        private string $type,
        private string $content,
        private ?int $oldLineNumber,
        private ?int $newLineNumber,
    ) {
    }

    /**
     * ChangeType::ADDED, ChangeType::REMOVED or ChangeType::UNCHANGED
     */
    public function type(): string
    {
        return $this->type;
    }

    public function content(): string
    {
        return $this->content;
    }

    /**
     * The line's number in the old text, starting at 1, or null for an added line
     */
    public function oldLineNumber(): ?int
    {
        return $this->oldLineNumber;
    }

    /**
     * The line's number in the new text, starting at 1, or null for a removed line
     */
    public function newLineNumber(): ?int
    {
        return $this->newLineNumber;
    }

    /**
     * @return array{type: string, content: string, old_line: int|null, new_line: int|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'content' => $this->content,
            'old_line' => $this->oldLineNumber,
            'new_line' => $this->newLineNumber,
        ];
    }
}
