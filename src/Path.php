<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

use InvalidArgumentException;
use Pharaonic\Diff\Support\DotNotation;

/**
 * The location of a value inside the compared data, as a list of keys and list indexes
 *
 * The root is the empty path. Segments are stored the way PHP stores array keys, so "4" and 4 are
 * the same segment.
 */
final class Path
{
    /**
     * @param list<int|string> $segments
     */
    private function __construct(private array $segments) {}

    /**
     * @param array<int|string> $segments
     */
    public static function fromSegments(array $segments): self
    {
        return new self(array_map([self::class, 'segment'], array_values($segments)));
    }

    /**
     * Parse dot notation: "users.4.email", with "\.", "\*" and "\\" for a literal dot, star or
     * backslash inside a key. The empty string is the root.
     */
    public static function fromDot(string $path): self
    {
        return self::fromSegments(array_map(
            static fn(?string $segment): string => $segment ?? '*',
            DotNotation::parse($path),
        ));
    }

    /**
     * @return list<int|string>
     */
    public function segments(): array
    {
        return $this->segments;
    }

    public function isRoot(): bool
    {
        return $this->segments === [];
    }

    /**
     * Dot notation, escaped so that fromDot() reads it back
     */
    public function toDot(): string
    {
        return DotNotation::format($this->segments);
    }

    /**
     * RFC 6901 JSON Pointer: "" for the root, "/users/4/email" otherwise
     */
    public function toJsonPointer(): string
    {
        $pointer = '';

        foreach ($this->segments as $segment) {
            $pointer .= '/' . strtr((string) $segment, ['~' => '~0', '/' => '~1']);
        }

        return $pointer;
    }

    private static function segment(mixed $segment): int|string
    {
        if (! is_int($segment) && ! is_string($segment)) {
            throw new InvalidArgumentException(sprintf('A path segment must be an int or a string, %s given.', get_debug_type($segment)));
        }

        // Let PHP cast the key exactly as it would inside an array.
        return array_key_first([$segment => true]);
    }
}
