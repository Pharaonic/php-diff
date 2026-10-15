<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

/**
 * A removed line and an added line that look alike, read together as one modified line
 *
 * Both lines still appear on their own in TextDiffResult::lines() and in hunks.
 */
final class ModifiedLinePair
{
    /**
     * @internal Pairs are created by Diff::text().
     */
    public function __construct(private DiffLine $removed, private DiffLine $added)
    {
    }

    public function removed(): DiffLine
    {
        return $this->removed;
    }

    public function added(): DiffLine
    {
        return $this->added;
    }

    /**
     * @return array{old_line: int|null, new_line: int|null}
     */
    public function toArray(): array
    {
        return ['old_line' => $this->removed->oldLineNumber(), 'new_line' => $this->added->newLineNumber()];
    }
}
