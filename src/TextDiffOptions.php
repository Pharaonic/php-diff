<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

use InvalidArgumentException;

/**
 * Immutable options for Diff::text()
 *
 * Every method returns new options and leaves the current ones unchanged.
 */
final class TextDiffOptions
{
    private int $context = 3;

    private bool $pairModifiedLines = true;

    private float $similarityThreshold = 0.55;

    private string $inline = InlineMode::WORD;

    private bool $ignoreWhitespaceChanges = false;

    private bool $ignoreTrailingWhitespace = false;

    private int $maxLines = 50_000;

    private int $maxInputBytes = 10_485_760;

    private int $maxInlineLength = 1_000;

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * How many unchanged lines surround each change in a hunk (3 by default)
     */
    public function context(int $lines): self
    {
        self::atLeast('context', $lines, 0);

        $options = clone $this;
        $options->context = $lines;

        return $options;
    }

    /**
     * Whether removed and added lines that look alike are paired as modified (on by default)
     */
    public function pairModifiedLines(bool $pair = true): self
    {
        $options = clone $this;
        $options->pairModifiedLines = $pair;

        return $options;
    }

    /**
     * How similar, from 0 to 1, a removed and an added line must be to pair them (0.55 by default)
     */
    public function similarityThreshold(float $threshold): self
    {
        if (! ($threshold >= 0.0 && $threshold <= 1.0)) {
            throw new InvalidArgumentException('The similarity threshold must be between 0 and 1.');
        }

        $options = clone $this;
        $options->similarityThreshold = $threshold;

        return $options;
    }

    /**
     * How changes inside a modified line are highlighted: an InlineMode constant (word by default)
     */
    public function inline(string $mode): self
    {
        if (! in_array($mode, [InlineMode::NONE, InlineMode::WORD, InlineMode::CHARACTER], true)) {
            throw new InvalidArgumentException(sprintf('Unknown inline mode "%s"; use an InlineMode constant.', $mode));
        }

        $options = clone $this;
        $options->inline = $mode;

        return $options;
    }

    /**
     * Treat lines that differ only in the amount of whitespace as equal, like "git diff -b"
     */
    public function ignoreWhitespaceChanges(): self
    {
        $options = clone $this;
        $options->ignoreWhitespaceChanges = true;

        return $options;
    }

    /**
     * Treat lines that differ only in trailing whitespace as equal, like "git diff --ignore-space-at-eol"
     */
    public function ignoreTrailingWhitespace(): self
    {
        $options = clone $this;
        $options->ignoreTrailingWhitespace = true;

        return $options;
    }

    /**
     * The most lines either text may have (50,000 by default); longer texts throw
     */
    public function maxLines(int $lines): self
    {
        self::atLeast('maximum number of lines', $lines, 1);

        $options = clone $this;
        $options->maxLines = $lines;

        return $options;
    }

    /**
     * The most bytes either text may have (10 MiB by default); larger texts throw
     */
    public function maxInputBytes(int $bytes): self
    {
        self::atLeast('maximum number of bytes', $bytes, 1);

        $options = clone $this;
        $options->maxInputBytes = $bytes;

        return $options;
    }

    /**
     * The longest line, in bytes, that gets inline highlighting (1,000 by default); longer
     * modified lines are highlighted as a whole
     */
    public function maxInlineLength(int $bytes): self
    {
        self::atLeast('maximum inline length', $bytes, 1);

        $options = clone $this;
        $options->maxInlineLength = $bytes;

        return $options;
    }

    /**
     * @internal
     */
    public function contextLines(): int
    {
        return $this->context;
    }

    /**
     * @internal
     */
    public function pairsModifiedLines(): bool
    {
        return $this->pairModifiedLines;
    }

    /**
     * @internal
     */
    public function threshold(): float
    {
        return $this->similarityThreshold;
    }

    /**
     * @internal
     */
    public function inlineMode(): string
    {
        return $this->inline;
    }

    /**
     * @internal
     */
    public function ignoresWhitespaceChanges(): bool
    {
        return $this->ignoreWhitespaceChanges;
    }

    /**
     * @internal
     */
    public function ignoresTrailingWhitespace(): bool
    {
        return $this->ignoreTrailingWhitespace;
    }

    /**
     * @internal
     */
    public function lineLimit(): int
    {
        return $this->maxLines;
    }

    /**
     * @internal
     */
    public function byteLimit(): int
    {
        return $this->maxInputBytes;
    }

    /**
     * @internal
     */
    public function inlineLengthLimit(): int
    {
        return $this->maxInlineLength;
    }

    private static function atLeast(string $name, int $value, int $minimum): void
    {
        if ($value < $minimum) {
            throw new InvalidArgumentException(sprintf('The %s must be at least %d, %d given.', $name, $minimum, $value));
        }
    }
}
