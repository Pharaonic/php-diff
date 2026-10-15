<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Engine;

use Closure;
use Generator;
use Pharaonic\Diff\Change;
use Pharaonic\Diff\ChangeType;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\Exceptions\MaximumDepthExceededException;
use Pharaonic\Diff\Exceptions\UnsupportedValueException;
use Pharaonic\Diff\Path;
use Pharaonic\Diff\Support\Arrays;
use Pharaonic\Diff\Support\PathFilter;

/**
 * Compares two structured values and collects their changes in traversal order
 *
 * @internal
 */
final class StructuredDiffer
{
    private const SCALAR = 'scalar';

    private const MAP = 'map';

    private const LIST = 'list';

    private const EMPTY = 'empty';

    /**
     * @var list<Change>
     */
    private array $changes = [];

    private PathFilter $filter;

    public function __construct(private DiffOptions $options)
    {
        $this->filter = new PathFilter($options);
    }

    /**
     * @return list<Change>
     */
    public function diff(mixed $before, mixed $after): array
    {
        $this->changes = [];

        if ($this->filter->state([]) !== PathFilter::EXCLUDED) {
            $this->compare($before, $after, []);
        }

        return $this->changes;
    }

    /**
     * Compare two values that exist at the same path and report whether anything changed
     *
     * @param list<int|string> $path
     */
    private function compare(mixed $before, mixed $after, array $path): bool
    {
        $beforeKind = $this->kind($before, $path);
        $afterKind = $this->kind($after, $path);

        if (! is_array($before) || ! is_array($after)) {
            if (! is_array($before) && ! is_array($after) && $this->scalarsEqual($before, $after)) {
                return false;
            }

            return $this->modified($path, $before, $after);
        }

        $kind = $this->containerKind($beforeKind, $afterKind);

        if ($kind === null) {
            return $this->modified($path, $before, $after);
        }

        if ($kind === self::EMPTY) {
            return false;
        }

        if (count($path) >= $this->options->depthLimit()) {
            throw MaximumDepthExceededException::limit(Path::fromSegments($path), $this->options->depthLimit());
        }

        $position = count($this->changes);
        $changed = $kind === self::MAP
            ? $this->compareMap($before, $after, $path)
            : $this->compareList($before, $after, $path);

        if ($changed && $path !== [] && $this->filter->reports($path, $path)) {
            array_splice($this->changes, $position, 0, [$this->change(ChangeType::MODIFIED, $path, $path, $before, $after, true)]);
        }

        return $changed;
    }

    /**
     * Keys are matched by name: new keys in their new order first, then removed keys in their old order.
     *
     * @param array<mixed> $before
     * @param array<mixed> $after
     * @param list<int|string> $path
     */
    private function compareMap(array $before, array $after, array $path): bool
    {
        $changed = false;

        foreach ($after as $key => $value) {
            $child = [...$path, $key];

            if ($this->filter->state($child) === PathFilter::EXCLUDED) {
                continue;
            }

            $changed = (array_key_exists($key, $before)
                ? $this->compare($before[$key], $value, $child)
                : $this->added($child, $value)) || $changed;
        }

        foreach ($before as $key => $value) {
            if (! array_key_exists($key, $after)) {
                $changed = $this->removed([...$path, $key], $value) || $changed;
            }
        }

        return $changed;
    }

    /**
     * @param array<mixed> $before
     * @param array<mixed> $after
     * @param list<int|string> $path
     */
    private function compareList(array $before, array $after, array $path): bool
    {
        $changed = false;
        $length = max(count($before), count($after));

        for ($index = 0; $index < $length; $index++) {
            $child = [...$path, $index];

            if ($this->filter->state($child) === PathFilter::EXCLUDED) {
                continue;
            }

            if (! array_key_exists($index, $after)) {
                $changed = $this->removed($child, $before[$index]) || $changed;
            } elseif (! array_key_exists($index, $before)) {
                $changed = $this->added($child, $after[$index]) || $changed;
            } else {
                $changed = $this->compare($before[$index], $after[$index], $child) || $changed;
            }
        }

        return $changed;
    }

    /**
     * The kind both containers are compared as, or null when they cannot be compared; an empty
     * array takes the kind of the other side.
     */
    private function containerKind(string $before, string $after): ?string
    {
        if ($before === self::EMPTY) {
            return $after;
        }

        if ($after === self::EMPTY || $before === $after) {
            return $before;
        }

        return null;
    }

    /**
     * @param list<int|string> $path
     */
    private function kind(mixed $value, array $path): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return self::EMPTY;
            }

            return Arrays::isList($value) ? self::LIST : self::MAP;
        }

        if ($value === null || is_scalar($value)) {
            return self::SCALAR;
        }

        throw UnsupportedValueException::at(Path::fromSegments($path), self::describe($value));
    }

    private function scalarsEqual(mixed $before, mixed $after): bool
    {
        if (is_float($before) && is_float($after)) {
            return $this->floatsEqual($before, $after);
        }

        return $before === $after;
    }

    private function floatsEqual(float $before, float $after): bool
    {
        if (is_nan($before) || is_nan($after)) {
            return is_nan($before) && is_nan($after);
        }

        // Also makes -0.0 equal to 0.0.
        if ($before == $after) {
            return true;
        }

        if (is_infinite($before) || is_infinite($after)) {
            return false;
        }

        return abs($before - $after) <= $this->options->tolerance();
    }

    /**
     * @param list<int|string> $path
     */
    private function added(array $path, mixed $value): bool
    {
        return $this->emit(ChangeType::ADDED, null, $path, null, $value);
    }

    /**
     * @param list<int|string> $path
     */
    private function removed(array $path, mixed $value): bool
    {
        return $this->emit(ChangeType::REMOVED, $path, null, $value, null);
    }

    /**
     * @param list<int|string> $path
     */
    private function modified(array $path, mixed $before, mixed $after): bool
    {
        return $this->emit(ChangeType::MODIFIED, $path, $path, $before, $after);
    }

    /**
     * Record a direct change when the filters report it
     *
     * @param list<int|string>|null $oldPath
     * @param list<int|string>|null $newPath
     */
    private function emit(string $type, ?array $oldPath, ?array $newPath, mixed $before, mixed $after): bool
    {
        if (! $this->filter->reports($oldPath, $newPath)) {
            return false;
        }

        if ($oldPath !== null) {
            $this->kind($before, $oldPath);
        }

        if ($newPath !== null) {
            $this->kind($after, $newPath);
        }

        $this->changes[] = $this->change($type, $oldPath, $newPath, $before, $after);

        return true;
    }

    /**
     * @param list<int|string>|null $oldPath
     * @param list<int|string>|null $newPath
     */
    private function change(string $type, ?array $oldPath, ?array $newPath, mixed $before, mixed $after, bool $aggregate = false): Change
    {
        return new Change(
            $type,
            $oldPath === null ? null : Path::fromSegments($oldPath),
            $newPath === null ? null : Path::fromSegments($newPath),
            $oldPath !== null,
            $before,
            $newPath !== null,
            $after,
            $aggregate,
        );
    }

    private static function describe(mixed $value): string
    {
        if ($value instanceof Closure) {
            return 'A closure cannot be compared';
        }

        if ($value instanceof Generator) {
            return 'A generator cannot be compared';
        }

        if (is_object($value)) {
            return sprintf('An object of class %s cannot be compared without a normalizer', get_class($value));
        }

        return sprintf('A value of type %s cannot be compared', get_debug_type($value));
    }
}
