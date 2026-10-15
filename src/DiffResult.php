<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * The changes between two structured values, parents before their descendants
 *
 * Methods that take a path accept dot notation or a Path.
 *
 * @implements IteratorAggregate<int, Change>
 */
final class DiffResult implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param list<Change> $changes
     *
     * @internal Results are created by Diff::between().
     */
    public function __construct(private array $changes)
    {
    }

    public function hasChanges(): bool
    {
        return $this->changes !== [];
    }

    /**
     * Whether the value at the path or anything below it changed, at its old or its new path
     */
    public function hasChanged(string|Path $path): bool
    {
        return $this->under($path) !== [];
    }

    /**
     * Whether a value was added at exactly this path
     */
    public function wasAdded(string|Path $path): bool
    {
        return $this->exactly(ChangeType::ADDED, $path);
    }

    /**
     * Whether a value was removed from exactly this path
     */
    public function wasRemoved(string|Path $path): bool
    {
        return $this->exactly(ChangeType::REMOVED, $path);
    }

    /**
     * Whether the value at exactly this path was modified, directly or through its descendants
     */
    public function wasModified(string|Path $path): bool
    {
        return $this->exactly(ChangeType::MODIFIED, $path);
    }

    /**
     * Whether a value moved from or to exactly this path
     */
    public function wasMoved(string|Path $path): bool
    {
        $segments = self::path($path)->segments();

        foreach ($this->moved() as $change) {
            if ($change->oldPath()?->segments() === $segments || $change->newPath()?->segments() === $segments) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<Change>
     */
    public function changes(): array
    {
        return $this->changes;
    }

    /**
     * @return list<Change>
     */
    public function added(): array
    {
        return $this->ofType(ChangeType::ADDED);
    }

    /**
     * @return list<Change>
     */
    public function removed(): array
    {
        return $this->ofType(ChangeType::REMOVED);
    }

    /**
     * Direct and aggregate modifications
     *
     * @return list<Change>
     */
    public function modified(): array
    {
        return $this->ofType(ChangeType::MODIFIED);
    }

    /**
     * @return list<Change>
     */
    public function moved(): array
    {
        return $this->ofType(ChangeType::MOVED);
    }

    /**
     * The changes whose canonical path is exactly this path
     *
     * @return list<Change>
     */
    public function at(string|Path $path): array
    {
        $segments = self::path($path)->segments();

        return array_values(array_filter(
            $this->changes,
            static fn (Change $change): bool => $change->path()->segments() === $segments,
        ));
    }

    /**
     * The changes at this path or below it, at their old or their new path
     *
     * @return list<Change>
     */
    public function under(string|Path $path): array
    {
        $segments = self::path($path)->segments();

        return array_values(array_filter(
            $this->changes,
            static fn (Change $change): bool => self::within($change->oldPath(), $segments)
                || self::within($change->newPath(), $segments),
        ));
    }

    public function count(): int
    {
        return count($this->changes);
    }

    /**
     * @return array{total: int, added: int, removed: int, modified: int, moved: int}
     */
    public function stats(): array
    {
        return [
            'total' => count($this->changes),
            'added' => count($this->added()),
            'removed' => count($this->removed()),
            'modified' => count($this->modified()),
            'moved' => count($this->moved()),
        ];
    }

    /**
     * @return ArrayIterator<int, Change>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->changes);
    }

    /**
     * @return list<array{type: string, path: string, old_path: string|null, new_path: string|null, old_exists: bool, old: mixed, new_exists: bool, new: mixed, aggregate: bool, redacted: bool}>
     */
    public function toArray(): array
    {
        return array_map(static fn (Change $change): array => $change->toArray(), $this->changes);
    }

    /**
     * @return list<array{type: string, path: string, old_path: string|null, new_path: string|null, old_exists: bool, old: mixed, new_exists: bool, new: mixed, aggregate: bool, redacted: bool}>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Encode the changes as JSON; JSON_THROW_ON_ERROR is always added
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<Change>
     */
    private function ofType(string $type): array
    {
        return array_values(array_filter($this->changes, static fn (Change $change): bool => $change->type() === $type));
    }

    private function exactly(string $type, string|Path $path): bool
    {
        $segments = self::path($path)->segments();

        foreach ($this->changes as $change) {
            if ($change->type() === $type && $change->path()->segments() === $segments) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<int|string> $ancestor
     */
    private static function within(?Path $path, array $ancestor): bool
    {
        return $path !== null && array_slice($path->segments(), 0, count($ancestor)) === $ancestor;
    }

    private static function path(string|Path $path): Path
    {
        return $path instanceof Path ? $path : Path::fromDot($path);
    }
}
