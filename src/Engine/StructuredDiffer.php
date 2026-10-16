<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Engine;

use Pharaonic\Diff\Algorithm\Myers;
use Pharaonic\Diff\Change;
use Pharaonic\Diff\ChangeType;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\Exceptions\IdentityException;
use Pharaonic\Diff\Exceptions\MaximumDepthExceededException;
use Pharaonic\Diff\Exceptions\UnsupportedValueException;
use Pharaonic\Diff\ListStrategy;
use Pharaonic\Diff\Path;
use Pharaonic\Diff\Support\Arrays;
use Pharaonic\Diff\Support\Canonical;
use Pharaonic\Diff\Support\Objects;
use Pharaonic\Diff\Support\PathFilter;
use Pharaonic\Diff\Support\Snapshot;
use Pharaonic\Diff\Support\Values;

/**
 * Compares two structured values and collects their changes in traversal order
 *
 * A value inside a list may have different old and new paths, so both are tracked separately.
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
     * Two records without an identity are paired when at least this share of their keys is common.
     */
    private const MIN_SHARED_KEYS = 0.5;

    /**
     * A replaced run with more removed × added items than this only pairs items at the same position.
     */
    private const MAX_PAIRING_COMPARISONS = 10_000;

    /**
     * @var list<Change>
     */
    private array $changes = [];

    private PathFilter $filter;

    private Canonical $canonical;

    private Objects $objects;

    private Snapshot $snapshot;

    /**
     * The objects being compared around the current values, for each side
     *
     * @var list<object>
     */
    private array $beforeAncestors = [];

    /**
     * @var list<object>
     */
    private array $afterAncestors = [];

    public function __construct(private DiffOptions $options)
    {
        $this->filter = new PathFilter($options);
        $this->objects = new Objects($options);
        $this->canonical = new Canonical($options, $this->filter, $this->objects);
        $this->snapshot = new Snapshot($options, $this->filter, $this->objects);
    }

    /**
     * @return list<Change>
     */
    public function diff(mixed $before, mixed $after): array
    {
        $this->changes = [];
        $this->beforeAncestors = [];
        $this->afterAncestors = [];

        if (! $this->filter->skips([], [])) {
            $this->compare($before, $after, [], []);
        }

        return $this->changes;
    }

    /**
     * Compare two values that both exist and report whether a change was recorded
     *
     * A comparator that claims both values decides alone; other objects are normalized first.
     *
     * @param list<int|string> $old
     * @param list<int|string> $new
     */
    private function compare(mixed $before, mixed $after, array $old, array $new): bool
    {
        $comparator = $this->objects->comparator($before, $after);

        if ($comparator !== null) {
            return $comparator($before, $after) === true ? false : $this->emit(ChangeType::MODIFIED, $old, $new, $before, $after);
        }

        $beforeDepth = count($this->beforeAncestors);
        $afterDepth = count($this->afterAncestors);

        try {
            $before = $this->enter($before, $old, $this->beforeAncestors);
            $after = $this->enter($after, $new, $this->afterAncestors);

            return $this->compareValues($before, $after, $old, $new);
        } finally {
            array_splice($this->beforeAncestors, $beforeDepth);
            array_splice($this->afterAncestors, $afterDepth);
        }
    }

    /**
     * Normalize an object, failing when it is already being compared further up
     *
     * @param list<int|string> $path
     * @param list<object> $ancestors
     */
    private function enter(mixed $value, array $path, array &$ancestors): mixed
    {
        if (! is_object($value) || $this->objects->comparedAs($value) !== null) {
            return $value;
        }

        foreach ($ancestors as $ancestor) {
            if ($ancestor === $value) {
                throw UnsupportedValueException::circular(Path::fromSegments($path), $value);
            }
        }

        $ancestors[] = $value;

        return $this->objects->normalize($value, $path);
    }

    /**
     * @param list<int|string> $old
     * @param list<int|string> $new
     */
    private function compareValues(mixed $before, mixed $after, array $old, array $new): bool
    {
        $beforeKind = $this->kind($before, $old);
        $afterKind = $this->kind($after, $new);

        if (! is_array($before) || ! is_array($after)) {
            if (! is_array($before) && ! is_array($after) && $this->scalarsEqual($before, $after)) {
                return false;
            }

            return $this->emit(ChangeType::MODIFIED, $old, $new, $before, $after);
        }

        $kind = $this->containerKind($beforeKind, $afterKind);

        if ($kind === null) {
            return $this->emit(ChangeType::MODIFIED, $old, $new, $before, $after);
        }

        if ($kind === self::EMPTY) {
            return false;
        }

        if (count($new) >= $this->options->depthLimit()) {
            throw MaximumDepthExceededException::limit(Path::fromSegments($new), $this->options->depthLimit());
        }

        $position = count($this->changes);
        $changed = $kind === self::MAP
            ? $this->compareMap($before, $after, $old, $new)
            : $this->compareList(array_values($before), array_values($after), $old, $new);

        if ($changed && $new !== [] && $this->filter->reports($old, $new)) {
            array_splice($this->changes, $position, 0, [$this->change(ChangeType::MODIFIED, $old, $new, $before, $after, true)]);
        }

        return $changed;
    }

    /**
     * Keys are matched by name: new keys in their new order first, then removed keys in their old order.
     *
     * @param array<mixed> $before
     * @param array<mixed> $after
     * @param list<int|string> $old
     * @param list<int|string> $new
     */
    private function compareMap(array $before, array $after, array $old, array $new): bool
    {
        $changed = false;

        foreach ($after as $key => $value) {
            if (! array_key_exists($key, $before)) {
                $changed = $this->emit(ChangeType::ADDED, null, [...$new, $key], null, $value) || $changed;
            } elseif (! $this->filter->skips([...$old, $key], [...$new, $key])) {
                $changed = $this->compare($before[$key], $value, [...$old, $key], [...$new, $key]) || $changed;
            }
        }

        foreach ($before as $key => $value) {
            if (! array_key_exists($key, $after)) {
                $changed = $this->emit(ChangeType::REMOVED, [...$old, $key], null, $value, null) || $changed;
            }
        }

        return $changed;
    }

    /**
     * @param list<mixed> $before
     * @param list<mixed> $after
     * @param list<int|string> $old
     * @param list<int|string> $new
     */
    private function compareList(array $before, array $after, array $old, array $new): bool
    {
        [$strategy, $keys] = $this->canonical->listRule($new);

        if ($strategy === null) {
            return $this->compareIdentified($before, $after, $old, $new, $keys);
        }

        if ($strategy === ListStrategy::POSITION) {
            return $this->comparePositions($before, $after, $old, $new);
        }

        if ($strategy === ListStrategy::UNORDERED) {
            return $this->compareUnordered($before, $after, $old, $new);
        }

        return $this->compareSequence($before, $after, $old, $new);
    }

    /**
     * Align both lists so an insertion or removal does not shift every later item, then pair the
     * removed and added items of each replaced run that look like the same record.
     *
     * @param list<mixed> $before
     * @param list<mixed> $after
     * @param list<int|string> $old
     * @param list<int|string> $new
     */
    private function compareSequence(array $before, array $after, array $old, array $new): bool
    {
        [$beforeTokens, $afterTokens] = $this->tokens($before, $after, $old, $new);
        $changed = false;
        $removed = [];
        $added = [];

        foreach (Myers::diff($beforeTokens, $afterTokens) as [$operation, $oldIndex, $newIndex]) {
            if ($operation === Myers::DELETE) {
                $removed[] = $oldIndex;
            } elseif ($operation === Myers::INSERT) {
                $added[] = $newIndex;
            } else {
                $changed = $this->replace($before, $after, $old, $new, $removed, $added, true) || $changed;
                $removed = $added = [];
            }
        }

        return $this->replace($before, $after, $old, $new, $removed, $added, true) || $changed;
    }

    /**
     * @param list<mixed> $before
     * @param list<mixed> $after
     * @param list<int|string> $old
     * @param list<int|string> $new
     */
    private function comparePositions(array $before, array $after, array $old, array $new): bool
    {
        $changed = false;

        foreach ($after as $index => $value) {
            if (! array_key_exists($index, $before)) {
                $changed = $this->emit(ChangeType::ADDED, null, [...$new, $index], null, $value) || $changed;
            } elseif (! $this->filter->skips([...$old, $index], [...$new, $index])) {
                $changed = $this->compare($before[$index], $value, [...$old, $index], [...$new, $index]) || $changed;
            }
        }

        for ($index = count($after), $count = count($before); $index < $count; $index++) {
            $changed = $this->emit(ChangeType::REMOVED, [...$old, $index], null, $before[$index], null) || $changed;
        }

        return $changed;
    }

    /**
     * Match equal items regardless of order, keeping counts, then pair the remaining records.
     *
     * @param list<mixed> $before
     * @param list<mixed> $after
     * @param list<int|string> $old
     * @param list<int|string> $new
     */
    private function compareUnordered(array $before, array $after, array $old, array $new): bool
    {
        [$beforeTokens, $afterTokens] = $this->tokens($before, $after, $old, $new);
        $indexes = [];

        foreach ($beforeTokens as $index => $token) {
            $indexes[$token][] = $index;
        }

        $matched = [];
        $added = [];

        foreach ($afterTokens as $index => $token) {
            $next = $matched[$token] ?? 0;

            if (isset($indexes[$token][$next])) {
                $matched[$token] = $next + 1;
            } else {
                $added[] = $index;
            }
        }

        $removed = [];

        foreach ($indexes as $token => $tokenIndexes) {
            array_push($removed, ...array_slice($tokenIndexes, $matched[$token] ?? 0));
        }

        sort($removed);

        return $this->replace($before, $after, $old, $new, $removed, $added, false);
    }

    /**
     * Match records by their identity keys; with move detection, records that left the longest
     * run of records still in the same relative order are reported as moved.
     *
     * @param list<mixed> $before
     * @param list<mixed> $after
     * @param list<int|string> $old
     * @param list<int|string> $new
     * @param list<int|string> $keys
     */
    private function compareIdentified(array $before, array $after, array $old, array $new, array $keys): bool
    {
        $beforeIndexes = $this->identities($before, $old, $keys);
        $afterIndexes = $this->identities($after, $new, $keys);
        $moved = $this->options->movesDetected() ? $this->movedIdentities($beforeIndexes, $afterIndexes) : [];
        $changed = false;

        foreach ($afterIndexes as $identity => $newIndex) {
            $oldIndex = $beforeIndexes[$identity] ?? null;
            $newPath = [...$new, $newIndex];

            if ($oldIndex === null) {
                $changed = $this->emit(ChangeType::ADDED, null, $newPath, null, $after[$newIndex]) || $changed;

                continue;
            }

            $oldPath = [...$old, $oldIndex];

            if ($this->filter->skips($oldPath, $newPath)) {
                continue;
            }

            if (isset($moved[$identity])) {
                $changed = $this->emit(ChangeType::MOVED, $oldPath, $newPath, $before[$oldIndex], $after[$newIndex]) || $changed;
            }

            $changed = $this->compare($before[$oldIndex], $after[$newIndex], $oldPath, $newPath) || $changed;
        }

        foreach ($beforeIndexes as $identity => $oldIndex) {
            if (! isset($afterIndexes[$identity])) {
                $changed = $this->emit(ChangeType::REMOVED, [...$old, $oldIndex], null, $before[$oldIndex], null) || $changed;
            }
        }

        return $changed;
    }

    /**
     * Report a replaced run of items: removed items that were not paired first, then the added
     * items in their new order, each compared with its paired removed item when it has one.
     *
     * @param list<mixed> $before
     * @param list<mixed> $after
     * @param list<int|string> $old
     * @param list<int|string> $new
     * @param list<int> $removed
     * @param list<int> $added
     */
    private function replace(array $before, array $after, array $old, array $new, array $removed, array $added, bool $positional): bool
    {
        if ($removed === [] && $added === []) {
            return false;
        }

        $pairs = $this->pairs($before, $after, $old, $new, $removed, $added, $positional);
        $paired = array_flip($pairs);
        $changed = false;

        foreach ($removed as $oldIndex) {
            if (! isset($paired[$oldIndex])) {
                $changed = $this->emit(ChangeType::REMOVED, [...$old, $oldIndex], null, $before[$oldIndex], null) || $changed;
            }
        }

        foreach ($added as $newIndex) {
            if (! isset($pairs[$newIndex])) {
                $changed = $this->emit(ChangeType::ADDED, null, [...$new, $newIndex], null, $after[$newIndex]) || $changed;

                continue;
            }

            $oldPath = [...$old, $pairs[$newIndex]];
            $newPath = [...$new, $newIndex];

            if (! $this->filter->skips($oldPath, $newPath)) {
                $changed = $this->compare($before[$pairs[$newIndex]], $after[$newIndex], $oldPath, $newPath) || $changed;
            }
        }

        return $changed;
    }

    /**
     * Pair removed and added items in order, never crossing an earlier pair. Scalars pair only in
     * a sequence run where as many items were removed as added; records pair only when they
     * share most of their keys and at least one value. Very large runs only pair items at the
     * same position in the run.
     *
     * @param list<mixed> $before
     * @param list<mixed> $after
     * @param list<int|string> $old
     * @param list<int|string> $new
     * @param list<int> $removed
     * @param list<int> $added
     *
     * @return array<int, int> Old index by new index
     */
    private function pairs(array $before, array $after, array $old, array $new, array $removed, array $added, bool $positional): array
    {
        $scalars = $positional && count($removed) === count($added);
        $pairs = [];

        if (count($removed) * count($added) > self::MAX_PAIRING_COMPARISONS) {
            foreach ($added as $offset => $newIndex) {
                if (isset($removed[$offset]) && $this->pairable($before[$removed[$offset]], $after[$newIndex], [...$old, $removed[$offset]], [...$new, $newIndex], $scalars)) {
                    $pairs[$newIndex] = $removed[$offset];
                }
            }

            return $pairs;
        }

        $next = 0;

        foreach ($added as $newIndex) {
            for ($i = $next, $count = count($removed); $i < $count; $i++) {
                if ($this->pairable($before[$removed[$i]], $after[$newIndex], [...$old, $removed[$i]], [...$new, $newIndex], $scalars)) {
                    $pairs[$newIndex] = $removed[$i];
                    $next = $i + 1;

                    break;
                }
            }
        }

        return $pairs;
    }

    /**
     * @param list<int|string> $old
     * @param list<int|string> $new
     */
    private function pairable(mixed $before, mixed $after, array $old, array $new, bool $scalars): bool
    {
        $before = $this->objects->normalize($before, $old);
        $after = $this->objects->normalize($after, $new);

        if (! is_array($before) || ! is_array($after)) {
            return $scalars && ! is_array($before) && ! is_array($after);
        }

        if ($before === [] || $after === []) {
            return false;
        }

        $beforeIsList = Arrays::isList($before);

        if ($beforeIsList !== Arrays::isList($after)) {
            return false;
        }

        if ($beforeIsList) {
            $tokens = array_map(fn (mixed $item): string => $this->canonical->encode($item, []), $after);

            foreach ($before as $item) {
                if (in_array($this->canonical->encode($item, []), $tokens, true)) {
                    return true;
                }
            }

            return false;
        }

        $shared = array_intersect_key($before, $after);

        if (count($shared) < self::MIN_SHARED_KEYS * count($before + $after)) {
            return false;
        }

        foreach ($shared as $key => $value) {
            if ($this->canonical->encode($value, [...$old, $key]) === $this->canonical->encode($after[$key], [...$new, $key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Interned tokens of both lists, so equal items share a token
     *
     * @param list<mixed> $before
     * @param list<mixed> $after
     * @param list<int|string> $old
     * @param list<int|string> $new
     *
     * @return array{list<int>, list<int>}
     */
    private function tokens(array $before, array $after, array $old, array $new): array
    {
        $ids = [];
        $beforeTokens = [];
        $afterTokens = [];

        foreach ($before as $index => $item) {
            $beforeTokens[] = $ids[$this->canonical->encode($item, [...$old, $index])] ??= count($ids);
        }

        foreach ($after as $index => $item) {
            $afterTokens[] = $ids[$this->canonical->encode($item, [...$new, $index])] ??= count($ids);
        }

        return [$beforeTokens, $afterTokens];
    }

    /**
     * Each item's index by its typed identity, in list order
     *
     * @param list<mixed> $items
     * @param list<int|string> $path
     * @param list<int|string> $keys
     *
     * @return array<string, int>
     */
    private function identities(array $items, array $path, array $keys): array
    {
        $indexes = [];

        foreach ($items as $index => $item) {
            $item = $this->objects->normalize($item, [...$path, $index]);
            $identity = '';

            foreach ($keys as $key) {
                if (! is_array($item) || ! array_key_exists($key, $item)) {
                    throw IdentityException::missing(Path::fromSegments($path), $index, $key);
                }

                $identity .= $this->canonical->encode($item[$key], [...$path, $index, $key]);
            }

            if (isset($indexes[$identity])) {
                throw IdentityException::duplicate(Path::fromSegments($path), $index, $indexes[$identity]);
            }

            $indexes[$identity] = $index;
        }

        return $indexes;
    }

    /**
     * The identities of both lists that are not in the longest common subsequence of the
     * identities they share
     *
     * @param array<string, int> $before
     * @param array<string, int> $after
     *
     * @return array<string, true>
     */
    private function movedIdentities(array $before, array $after): array
    {
        $common = array_keys(array_intersect_key($after, $before));
        $ids = array_flip($common);
        $beforeOrder = [];

        foreach ($before as $identity => $index) {
            if (isset($ids[$identity])) {
                $beforeOrder[] = $ids[$identity];
            }
        }

        $moved = [];

        foreach (Myers::diff($beforeOrder, array_keys($common)) as [$operation, , $newIndex]) {
            if ($operation === Myers::INSERT) {
                $moved[$common[$newIndex]] = true;
            }
        }

        return $moved;
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

        // Objects left after normalization are claimed by a comparator.
        if ($value === null || is_scalar($value) || is_object($value)) {
            return self::SCALAR;
        }

        throw UnsupportedValueException::at(Path::fromSegments($path), Values::describe($value));
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

        $this->changes[] = $this->change($type, $oldPath, $newPath, $before, $after);

        return true;
    }

    /**
     * @param list<int|string>|null $oldPath
     * @param list<int|string>|null $newPath
     */
    private function change(string $type, ?array $oldPath, ?array $newPath, mixed $before, mixed $after, bool $aggregate = false): Change
    {
        // A value is redacted as a whole when either of its paths is redacted.
        $redacted = ($oldPath !== null && $this->snapshot->redacts($oldPath))
            || ($newPath !== null && $this->snapshot->redacts($newPath));

        return new Change(
            $type,
            $oldPath === null ? null : Path::fromSegments($oldPath),
            $newPath === null ? null : Path::fromSegments($newPath),
            $oldPath !== null,
            $oldPath === null ? null : ($redacted ? Change::REDACTED : $this->snapshot->take($before, $oldPath)),
            $newPath !== null,
            $newPath === null ? null : ($redacted ? Change::REDACTED : $this->snapshot->take($after, $newPath)),
            $aggregate,
            $redacted,
        );
    }
}
