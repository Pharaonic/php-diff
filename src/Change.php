<?php

declare(strict_types=1);

namespace Pharaonic\Diff;

use LogicException;

/**
 * One change reported by a structured diff
 *
 * A change at a list item may know both the item's old and new paths. Its canonical path() is the
 * old path for a removed value and the new path otherwise.
 */
final class Change
{
    /**
     * Stored instead of a value matched by DiffOptions::redact()
     */
    public const REDACTED = '[REDACTED]';

    private Path $path;

    /**
     * @internal Changes are created by Diff::between().
     */
    public function __construct(
        private string $type,
        private ?Path $oldPath,
        private ?Path $newPath,
        private bool $hasOldValue,
        private mixed $oldValue,
        private bool $hasNewValue,
        private mixed $newValue,
        private bool $aggregate = false,
        private bool $redacted = false,
    ) {
        $path = $type === ChangeType::REMOVED ? $oldPath : $newPath;

        if ($path === null) {
            throw new LogicException(sprintf('A %s change needs its canonical path.', $type));
        }

        $this->path = $path;
    }

    /**
     * One of the ChangeType constants
     */
    public function type(): string
    {
        return $this->type;
    }

    public function path(): Path
    {
        return $this->path;
    }

    /**
     * Where the value was, or null when it did not exist before
     */
    public function oldPath(): ?Path
    {
        return $this->oldPath;
    }

    /**
     * Where the value is, or null when it no longer exists
     */
    public function newPath(): ?Path
    {
        return $this->newPath;
    }

    public function hasOldValue(): bool
    {
        return $this->hasOldValue;
    }

    public function hasNewValue(): bool
    {
        return $this->hasNewValue;
    }

    /**
     * The value before the change; null both when it was null and when it did not exist, which
     * hasOldValue() tells apart
     */
    public function oldValue(): mixed
    {
        return $this->oldValue;
    }

    /**
     * The value after the change; null both when it is null and when it does not exist, which
     * hasNewValue() tells apart
     */
    public function newValue(): mixed
    {
        return $this->newValue;
    }

    /**
     * Whether this is a container reported as modified because something inside it changed
     */
    public function isAggregate(): bool
    {
        return $this->aggregate;
    }

    /**
     * Whether the values were replaced with Change::REDACTED as a whole; redacted values inside a
     * container are replaced without setting this flag
     */
    public function isRedacted(): bool
    {
        return $this->redacted;
    }

    /**
     * @return array{type: string, path: string, old_path: string|null, new_path: string|null, old_exists: bool, old: mixed, new_exists: bool, new: mixed, aggregate: bool, redacted: bool}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'path' => $this->path->toDot(),
            'old_path' => $this->oldPath?->toDot(),
            'new_path' => $this->newPath?->toDot(),
            'old_exists' => $this->hasOldValue,
            'old' => $this->oldValue,
            'new_exists' => $this->hasNewValue,
            'new' => $this->newValue,
            'aggregate' => $this->aggregate,
            'redacted' => $this->redacted,
        ];
    }
}
