<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Support;

use Pharaonic\Diff\Change;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\Exceptions\MaximumDepthExceededException;
use Pharaonic\Diff\Exceptions\UnsupportedValueException;
use Pharaonic\Diff\Path;

/**
 * Prepares a value before a change stores it: values at redacted paths are replaced with
 * Change::REDACTED, objects are normalized all the way down, except those a comparator claims,
 * and values at ignored paths are kept as they are without being traversed, unless a redacted
 * path lies inside them. An array is only copied when it holds arrays or objects.
 *
 * @internal
 */
final class Snapshot
{
    /**
     * @var list<list<string|null>>
     */
    private array $redact;

    public function __construct(private DiffOptions $options, private PathFilter $filter, private Objects $objects)
    {
        $this->redact = $options->redactedPatterns();
    }

    /**
     * Whether the value at the path is redacted as a whole
     *
     * @param list<int|string> $path
     */
    public function redacts(array $path): bool
    {
        foreach ($this->redact as $pattern) {
            if (Pattern::covers($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<int|string> $path
     */
    public function take(mixed $value, array $path): mixed
    {
        return $this->redacts($path) ? Change::REDACTED : $this->prepare($value, $path, []);
    }

    /**
     * @param list<int|string> $path
     * @param list<object> $ancestors
     */
    private function prepare(mixed $value, array $path, array $ancestors): mixed
    {
        if (is_object($value) && ! $this->objects->isCompared($value)) {
            foreach ($ancestors as $ancestor) {
                if ($ancestor === $value) {
                    throw UnsupportedValueException::circular(Path::fromSegments($path), $value);
                }
            }

            $ancestors[] = $value;
            $value = $this->objects->normalize($value, $path);
        }

        if ($value === null || is_scalar($value) || is_object($value)) {
            return $value;
        }

        if (! is_array($value)) {
            throw UnsupportedValueException::at(Path::fromSegments($path), Values::describe($value));
        }

        if ($value !== [] && count($path) >= $this->options->depthLimit()) {
            throw MaximumDepthExceededException::limit(Path::fromSegments($path), $this->options->depthLimit());
        }

        $prepared = $value;
        $changed = false;

        foreach ($value as $key => $item) {
            $child = [...$path, $key];

            if ($this->redacts($child)) {
                $prepared[$key] = Change::REDACTED;
                $changed = true;

                continue;
            }

            if ($item === null || is_scalar($item) || ($this->filter->state($child) === PathFilter::EXCLUDED && ! $this->leadsToRedaction($child))) {
                continue;
            }

            $prepared[$key] = $this->prepare($item, $child, $ancestors);
            $changed = true;
        }

        return $changed ? $prepared : $value;
    }

    /**
     * @param list<int|string> $path
     */
    private function leadsToRedaction(array $path): bool
    {
        foreach ($this->redact as $pattern) {
            if (Pattern::leadsTo($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
