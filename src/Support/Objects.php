<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Support;

use Closure;
use DateTimeInterface;
use JsonSerializable;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\Exceptions\UnsupportedValueException;
use Pharaonic\Diff\Path;
use stdClass;

/**
 * How objects are compared: a comparator claims a pair of instances of its class, and every other
 * object is normalized into a scalar, null or array by a registered normalizer, jsonSerialize()
 * or, for stdClass, its public properties.
 *
 * The last comparator or normalizer registered for a matching class wins. The built-in
 * DateTimeInterface comparator treats two dates as equal when they are the same instant, to the
 * microsecond, whatever their time zones.
 *
 * @internal
 */
final class Objects
{
    /**
     * @var list<array{class-string, Closure}>
     */
    private array $comparators;

    /**
     * @var list<array{class-string, Closure}>
     */
    private array $normalizers;

    public function __construct(DiffOptions $options)
    {
        $this->comparators = array_reverse($options->comparators());
        $this->comparators[] = [DateTimeInterface::class, Closure::fromCallable([self::class, 'sameInstant'])];
        $this->normalizers = array_reverse($options->normalizers());
    }

    /**
     * The comparator that claims both values, or null
     */
    public function comparator(mixed $before, mixed $after): ?Closure
    {
        if (! is_object($before) || ! is_object($after)) {
            return null;
        }

        foreach ($this->comparators as [$class, $comparator]) {
            if ($before instanceof $class && $after instanceof $class) {
                return $comparator;
            }
        }

        return null;
    }

    /**
     * The class of the comparator that claims an object, or null when it is normalized
     */
    public function comparedAs(object $value): ?string
    {
        foreach ($this->comparators as [$class]) {
            if ($value instanceof $class) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Turn an object that no comparator claims into a scalar, null, an array or an object a
     * comparator claims; any other value is returned unchanged
     *
     * @param list<int|string> $path
     */
    public function normalize(mixed $value, array $path): mixed
    {
        $chain = [];

        while (is_object($value) && $this->comparedAs($value) === null) {
            foreach ($chain as $previous) {
                if ($previous === $value) {
                    throw UnsupportedValueException::circular(Path::fromSegments($path), $value);
                }
            }

            $chain[] = $value;
            $value = $this->normalizeOnce($value, $path);
        }

        return $value;
    }

    /**
     * @param list<int|string> $path
     */
    private function normalizeOnce(object $value, array $path): mixed
    {
        foreach ($this->normalizers as [$class, $normalizer]) {
            if ($value instanceof $class) {
                return $normalizer($value);
            }
        }

        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        }

        if ($value instanceof stdClass) {
            return get_object_vars($value);
        }

        throw UnsupportedValueException::at(Path::fromSegments($path), Values::describe($value));
    }

    private static function sameInstant(DateTimeInterface $before, DateTimeInterface $after): bool
    {
        return $before->format('U.u') === $after->format('U.u');
    }
}
