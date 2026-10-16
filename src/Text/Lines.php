<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Text;

/**
 * The lines of a text, split on "\r\n", "\r" and "\n", and whether it ends with a line ending
 *
 * @internal
 */
final class Lines
{
    /**
     * @param list<string> $lines
     */
    private function __construct(public array $lines, public bool $endsWithNewline) {}

    public static function split(string $text): self
    {
        if ($text === '') {
            return new self([], false);
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        $lines = $lines === false ? [$text] : $lines;
        $endsWithNewline = end($lines) === '';

        if ($endsWithNewline) {
            array_pop($lines);
        }

        return new self($lines, $endsWithNewline);
    }
}
