<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Support;

use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\Exceptions\MaximumDepthExceededException;
use Pharaonic\Diff\Exceptions\UnsupportedValueException;
use Pharaonic\Diff\Path;

/**
 * Prepares a value before a change stores it: objects are normalized all the way down, except
 * those a comparator claims, and values at ignored paths are kept as they are without being
 * traversed. An array is only copied when it holds arrays or objects.
 *
 * @internal
 */
final class Snapshot
{
    public function __construct(private DiffOptions $options, private PathFilter $filter, private Objects $objects)
    {
    }

    /**
     * @param list<int|string> $path
     */
    public function take(mixed $value, array $path): mixed
    {
        return $this->prepare($value, $path, []);
    }

    /**
     * @param list<int|string> $path
     * @param list<object> $ancestors
     */
    private function prepare(mixed $value, array $path, array $ancestors): mixed
    {
        if (is_object($value) && $this->objects->comparedAs($value) === null) {
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

            if ($this->filter->state($child) === PathFilter::EXCLUDED || ($item === null || is_scalar($item))) {
                continue;
            }

            $prepared[$key] = $this->prepare($item, $child, $ancestors);
            $changed = true;
        }

        return $changed ? $prepared : $value;
    }
}
