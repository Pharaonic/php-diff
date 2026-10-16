<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Support;

use Closure;
use DateTimeInterface;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\Exceptions\MaximumDepthExceededException;
use Pharaonic\Diff\ListStrategy;
use Pharaonic\Diff\Exceptions\UnsupportedValueException;
use Pharaonic\Diff\Path;

/**
 * Encodes a value as a string that two values share exactly when no change would be reported
 * between them: map key order is ignored, NAN equals NAN, -0.0 equals 0.0, values at ignored
 * paths are left out, and so is the order of unordered lists and of identified lists when moves
 * are not detected. Objects a comparator claims share a string when the comparator finds them
 * equal, which assumes it treats equality as symmetric and transitive. Used to align list items
 * and to compare identities.
 *
 * @internal
 */
final class Canonical
{
    /**
     * Objects seen so far that no earlier one equals, by comparator position
     *
     * @var array<int, list<object>>
     */
    private array $representatives = [];

    public function __construct(private DiffOptions $options, private PathFilter $filter, private Objects $objects)
    {
    }

    /**
     * The list strategy for a path and its identity keys; null as the strategy means identity.
     *
     * @param list<int|string> $path
     *
     * @return array{string|null, list<int|string>}
     */
    public function listRule(array $path): array
    {
        foreach (array_reverse($this->options->listRules()) as [$pattern, $strategy, $keys]) {
            if (Pattern::matches($pattern, $path)) {
                return [$strategy, $keys];
            }
        }

        return [ListStrategy::SEQUENCE, []];
    }

    /**
     * @param list<int|string> $path
     * @param list<object> $ancestors The objects being encoded around this value
     */
    public function encode(mixed $value, array $path, array $ancestors = []): string
    {
        if (is_object($value)) {
            $claim = $this->objects->claim($value);

            if ($claim !== null) {
                return $this->encodeCompared($value, ...$claim);
            }

            foreach ($ancestors as $ancestor) {
                if ($ancestor === $value) {
                    throw UnsupportedValueException::circular(Path::fromSegments($path), $value);
                }
            }

            $ancestors[] = $value;

            return $this->encode($this->objects->normalize($value, $path), $path, $ancestors);
        }

        if ($value === null) {
            return 'n;';
        }

        if (is_bool($value)) {
            return $value ? 'b1;' : 'b0;';
        }

        if (is_int($value)) {
            return 'i' . $value . ';';
        }

        if (is_float($value)) {
            return 'f' . self::float($value) . ';';
        }

        if (is_string($value)) {
            return 's' . strlen($value) . ':' . $value . ';';
        }

        if (! is_array($value)) {
            throw UnsupportedValueException::at(Path::fromSegments($path), Values::describe($value));
        }

        if ($value === []) {
            return 'e;';
        }

        if (count($path) >= $this->options->depthLimit()) {
            throw MaximumDepthExceededException::limit(Path::fromSegments($path), $this->options->depthLimit());
        }

        return Arrays::isList($value) ? $this->encodeList($value, $path, $ancestors) : $this->encodeMap($value, $path, $ancestors);
    }

    /**
     * @param list<mixed> $value
     * @param list<int|string> $path
     * @param list<object> $ancestors
     */
    private function encodeList(array $value, array $path, array $ancestors): string
    {
        $items = [];

        foreach ($value as $index => $item) {
            $child = [...$path, $index];

            if ($this->filter->state($child) !== PathFilter::EXCLUDED) {
                $items[] = $this->encode($item, $child, $ancestors);
            }
        }

        [$strategy] = $this->listRule($path);

        if ($strategy === ListStrategy::UNORDERED || ($strategy === null && ! $this->options->movesDetected())) {
            sort($items, SORT_STRING);

            return 'u[' . implode('', $items) . ']';
        }

        return 'l[' . implode('', $items) . ']';
    }

    /**
     * @param array<mixed> $value
     * @param list<int|string> $path
     * @param list<object> $ancestors
     */
    private function encodeMap(array $value, array $path, array $ancestors): string
    {
        uksort($value, static fn (int|string $a, int|string $b): int => strcmp((string) $a, (string) $b));
        $encoded = 'm{';

        foreach ($value as $key => $item) {
            $child = [...$path, $key];

            if ($this->filter->state($child) !== PathFilter::EXCLUDED) {
                $encoded .= (is_int($key) ? 'i' . $key : 's' . strlen($key) . ':' . $key) . '=' . $this->encode($item, $child, $ancestors);
            }
        }

        return $encoded . '}';
    }

    /**
     * Encode an object a comparator claims: dates of the built-in comparator by instant, other
     * objects by the first object seen before that the comparator finds equal to them
     */
    private function encodeCompared(object $value, int $index, Closure $equals): string
    {
        if ($value instanceof DateTimeInterface && $this->objects->isBuiltInDate($index)) {
            return 'd' . $value->format('U.u') . ';';
        }

        $representatives = $this->representatives[$index] ?? [];

        foreach ($representatives as $id => $representative) {
            if ($equals($representative, $value) === true) {
                return 'o' . $index . ':' . $id . ';';
            }
        }

        $this->representatives[$index][] = $value;

        return 'o' . $index . ':' . count($representatives) . ';';
    }

    private static function float(float $value): string
    {
        if (is_nan($value)) {
            return 'nan';
        }

        if (is_infinite($value)) {
            return $value > 0 ? 'inf' : '-inf';
        }

        if ($value == 0.0) {
            return '0';
        }

        return sprintf('%.17g', $value);
    }
}
