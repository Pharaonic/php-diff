<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

use Closure;
use InvalidArgumentException;
use Pharaonic\Diff\Support\Arrays;
use Pharaonic\Diff\Support\DotNotation;

/**
 * Immutable options for Diff::between()
 *
 * Every method returns new options and leaves the current ones unchanged.
 *
 * Paths use dot notation: "user.email", "items.*.price" ("*" matches any one key or list index),
 * with "\." and "\*" for a literal dot or star inside a key. When several list rules match the
 * same path, the last one added wins.
 */
final class DiffOptions
{
    /**
     * @var list<list<string|null>>
     */
    private array $ignore = [];

    /**
     * @var list<list<string|null>>
     */
    private array $only = [];

    /**
     * @var list<list<string|null>>
     */
    private array $redact = [];

    private int $maxDepth = 64;

    private float $floatTolerance = 0.0;

    /**
     * @var list<array{list<string|null>, string|null, list<int|string>}>
     */
    private array $listRules = [];

    private bool $detectMoves = false;

    /**
     * @var list<array{class-string, Closure}>
     */
    private array $normalizers = [];

    /**
     * @var list<array{class-string, Closure}>
     */
    private array $comparators = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Leave out changes at these paths and everything below them, without traversing them
     *
     * @param string|list<string> $paths
     */
    public function ignore(string|array $paths): self
    {
        $options = clone $this;
        $options->ignore = [...$this->ignore, ...self::patterns($paths)];

        return $options;
    }

    /**
     * Report only changes at these paths and below them; ignore() still wins
     *
     * Branches that cannot lead to these paths are not traversed. Containers above them are
     * traversed but never reported, not even when a whole container was added, removed or
     * changed type.
     *
     * @param string|list<string> $paths
     */
    public function only(string|array $paths): self
    {
        $options = clone $this;
        $options->only = [...$this->only, ...self::patterns($paths)];

        return $options;
    }

    /**
     * Compare these values but store Change::REDACTED instead of them in the result
     *
     * @param string|list<string> $paths
     */
    public function redact(string|array $paths): self
    {
        $options = clone $this;
        $options->redact = [...$this->redact, ...self::patterns($paths)];

        return $options;
    }

    /**
     * How deep the compared values may nest (64 by default); deeper values throw
     */
    public function maxDepth(int $depth): self
    {
        if ($depth < 1) {
            throw new InvalidArgumentException(sprintf('The maximum depth must be at least 1, %d given.', $depth));
        }

        $options = clone $this;
        $options->maxDepth = $depth;

        return $options;
    }

    /**
     * Treat two floats as equal when they differ by at most this absolute amount
     */
    public function floatTolerance(float $tolerance): self
    {
        if (! is_finite($tolerance) || $tolerance < 0) {
            throw new InvalidArgumentException('The float tolerance must be a finite number of at least 0.');
        }

        $options = clone $this;
        $options->floatTolerance = $tolerance;

        return $options;
    }

    /**
     * Compare the lists at a path with one of the ListStrategy constants
     */
    public function listStrategy(string $path, string $strategy): self
    {
        if (! in_array($strategy, [ListStrategy::SEQUENCE, ListStrategy::POSITION, ListStrategy::UNORDERED], true)) {
            throw new InvalidArgumentException(sprintf('Unknown list strategy "%s"; use a ListStrategy constant.', $strategy));
        }

        $options = clone $this;
        $options->listRules[] = [DotNotation::parse($path), $strategy, []];

        return $options;
    }

    /**
     * Compare the lists at a path as records identified by one key or several keys together
     *
     * @param int|string|list<int|string> $keys
     */
    public function identify(string $path, int|string|array $keys): self
    {
        $keys = self::identityKeys($keys);

        $options = clone $this;
        $options->listRules[] = [DotNotation::parse($path), null, $keys];

        return $options;
    }

    /**
     * Report records of identified lists that changed their order relative to the others as moved
     */
    public function detectMoves(): self
    {
        $options = clone $this;
        $options->detectMoves = true;

        return $options;
    }

    /**
     * Turn instances of a class or interface into a comparable value: a scalar, null or an array
     *
     * @param class-string $class
     * @param callable(object): mixed $normalizer
     */
    public function normalize(string $class, callable $normalizer): self
    {
        $options = clone $this;
        $options->normalizers[] = [self::type($class), Closure::fromCallable($normalizer)];

        return $options;
    }

    /**
     * Decide whether two instances of a class or interface are equal; only true means equal, and
     * a different pair is one modified change that is never compared further
     *
     * @param class-string $class
     * @param callable(object, object): bool $equals
     */
    public function compare(string $class, callable $equals): self
    {
        $options = clone $this;
        $options->comparators[] = [self::type($class), Closure::fromCallable($equals)];

        return $options;
    }

    /**
     * @internal
     *
     * @return list<list<string|null>>
     */
    public function ignoredPatterns(): array
    {
        return $this->ignore;
    }

    /**
     * @internal
     *
     * @return list<list<string|null>>
     */
    public function onlyPatterns(): array
    {
        return $this->only;
    }

    /**
     * @internal
     *
     * @return list<list<string|null>>
     */
    public function redactedPatterns(): array
    {
        return $this->redact;
    }

    /**
     * @internal
     */
    public function depthLimit(): int
    {
        return $this->maxDepth;
    }

    /**
     * @internal
     */
    public function tolerance(): float
    {
        return $this->floatTolerance;
    }

    /**
     * Pattern, strategy (a ListStrategy constant, or null for identity) and identity keys, in the
     * order they were added
     *
     * @internal
     *
     * @return list<array{list<string|null>, string|null, list<int|string>}>
     */
    public function listRules(): array
    {
        return $this->listRules;
    }

    /**
     * @internal
     */
    public function movesDetected(): bool
    {
        return $this->detectMoves;
    }

    /**
     * @internal
     *
     * @return list<array{class-string, Closure}>
     */
    public function normalizers(): array
    {
        return $this->normalizers;
    }

    /**
     * @internal
     *
     * @return list<array{class-string, Closure}>
     */
    public function comparators(): array
    {
        return $this->comparators;
    }

    /**
     * @param int|string|array<mixed> $keys
     *
     * @return list<int|string>
     */
    private static function identityKeys(int|string|array $keys): array
    {
        $keys = is_array($keys) ? $keys : [$keys];

        if ($keys === [] || ! Arrays::isList($keys)) {
            throw new InvalidArgumentException('Identity keys must be a key or a non-empty list of keys.');
        }

        $identityKeys = [];

        foreach ($keys as $key) {
            if (! is_int($key) && ! is_string($key)) {
                throw new InvalidArgumentException(sprintf('An identity key must be an int or a string, %s given.', get_debug_type($key)));
            }

            $identityKeys[] = $key;
        }

        return $identityKeys;
    }

    /**
     * @param string|array<mixed> $paths
     *
     * @return list<list<string|null>>
     */
    private static function patterns(string|array $paths): array
    {
        $patterns = [];

        foreach ((array) $paths as $path) {
            if (! is_string($path)) {
                throw new InvalidArgumentException(sprintf('A path must be a string, %s given.', get_debug_type($path)));
            }

            $patterns[] = DotNotation::parse($path);
        }

        return $patterns;
    }

    /**
     * @return class-string
     */
    private static function type(string $class): string
    {
        if (! class_exists($class) && ! interface_exists($class)) {
            throw new InvalidArgumentException(sprintf('The class or interface "%s" does not exist.', $class));
        }

        return $class;
    }
}
