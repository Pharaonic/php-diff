<?php

declare(strict_types=1);

use Pharaonic\Diff\Support\Arrays;
use Pharaonic\Diff\Support\Missing;

it('has one missing value that differs from null', function () {
    expect(Missing::value())->toBe(Missing::value())
        ->and(Missing::is(Missing::value()))->toBeTrue()
        ->and(Missing::is(null))->toBeFalse();
});

it('treats only arrays keyed 0 to n-1 in order as lists', function (array $value, bool $isList) {
    expect(Arrays::isList($value))->toBe($isList);
})->with([
    'empty' => [[], true],
    'sequential' => [['a', 'b'], true],
    'reordered keys' => [[1 => 'b', 0 => 'a'], false],
    'sparse' => [[0 => 'a', 2 => 'c'], false],
    'starts at one' => [[1 => 'a'], false],
    'string key' => [['a' => 1], false],
]);
