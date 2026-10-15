<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

/**
 * A piece of a modified line: unchanged, or removed from the old line or added to the new one
 */
final class InlineSegment
{
    /**
     * @internal Segments are created by Diff::text().
     */
    public function __construct(private string $type, private string $text)
    {
    }

    /**
     * ChangeType::UNCHANGED, or ChangeType::REMOVED on a removed line and ChangeType::ADDED on an
     * added line
     */
    public function type(): string
    {
        return $this->type;
    }

    public function text(): string
    {
        return $this->text;
    }

    /**
     * @return array{type: string, text: string}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'text' => $this->text];
    }
}
