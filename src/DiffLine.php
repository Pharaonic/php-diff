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
     * @param list<InlineSegment> $inlineSegments
     *
     * @internal Lines are created by Diff::text().
     */
    public function __construct(
        private string $type,
        private string $content,
        private ?int $oldLineNumber,
        private ?int $newLineNumber,
        private array $inlineSegments = [],
    ) {}

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
     * The unchanged and changed pieces of a modified line, which join back into its content;
     * empty when the whole line counts as changed: an unchanged or unpaired line, a line longer
     * than TextDiffOptions::maxInlineLength(), or InlineMode::NONE
     *
     * @return list<InlineSegment>
     */
    public function inlineSegments(): array
    {
        return $this->inlineSegments;
    }

    /**
     * @return array{type: string, content: string, old_line: int|null, new_line: int|null, inline: list<array{type: string, text: string}>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'content' => $this->content,
            'old_line' => $this->oldLineNumber,
            'new_line' => $this->newLineNumber,
            'inline' => array_map(static fn(InlineSegment $segment): array => $segment->toArray(), $this->inlineSegments),
        ];
    }
}
