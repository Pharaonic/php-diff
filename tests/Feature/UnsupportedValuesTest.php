<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\Exceptions\MaximumDepthExceededException;
use Pharaonic\Diff\Exceptions\UnsupportedValueException;

it('rejects values it cannot compare', function (mixed $value, string $message) {
    expect(fn() => Diff::between(['a' => ['b' => 1]], ['a' => ['b' => $value]]))
        ->toThrow(UnsupportedValueException::class, $message);
})->with([
    'resource' => [STDIN, 'A value of type resource (stream) cannot be compared at path "a.b".'],
    'closure' => [fn() => fn() => 1, 'A closure cannot be compared at path "a.b".'],
    'generator' => [(fn() => yield 1)(), 'A generator cannot be compared at path "a.b".'],
    'object' => [new ArrayObject(), 'An object of class ArrayObject cannot be compared without a normalizer at path "a.b".'],
]);

it('rejects unsupported added and removed values', function () {
    expect(fn() => Diff::between([], ['a' => STDIN]))->toThrow(UnsupportedValueException::class, 'at path "a"')
        ->and(fn() => Diff::between(['a' => STDIN], []))->toThrow(UnsupportedValueException::class, 'at path "a"');
});

it('rejects values nested deeper than the maximum depth', function () {
    $options = DiffOptions::create()->maxDepth(2);

    expect(Diff::between(['a' => ['b' => 1]], ['a' => ['b' => 2]], $options)->hasChanges())->toBeTrue()
        ->and(fn() => Diff::between(['a' => ['b' => ['c' => 1]]], ['a' => ['b' => ['c' => 2]]], $options))
        ->toThrow(MaximumDepthExceededException::class, 'maximum depth of 2 at path "a.b"');
});

it('stops self-referencing arrays at the maximum depth', function () {
    $before = ['a' => 1];
    $before['self'] = &$before;
    $after = ['a' => 1];
    $after['self'] = &$after;

    expect(fn() => Diff::between($before, $after))->toThrow(MaximumDepthExceededException::class);
});
