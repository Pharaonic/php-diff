<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffOptions;

it('compares scalars strictly', function (mixed $before, mixed $after, bool $changed) {
    expect(Diff::between(['v' => $before], ['v' => $after])->hasChanges())->toBe($changed);
})->with([
    'int equal' => [1, 1, false],
    'int changed' => [1, 2, true],
    'int vs string' => [1, '1', true],
    'int vs float' => [1, 1.0, true],
    'bool vs int' => [true, 1, true],
    'null vs null' => [null, null, false],
    'null vs string' => [null, '', true],
    'null vs false' => [null, false, true],
    'string equal' => ['a', 'a', false],
    'string case' => ['a', 'A', true],
    'numeric strings' => ['1.0', '1', true],
    'float equal' => [0.1, 0.1, false],
    'float changed' => [0.1, 0.2, true],
    'NAN vs NAN' => [NAN, NAN, false],
    'NAN vs number' => [NAN, 1.0, true],
    'INF vs INF' => [INF, INF, false],
    '-INF vs -INF' => [-INF, -INF, false],
    'INF vs -INF' => [INF, -INF, true],
    '-0.0 vs 0.0' => [-0.0, 0.0, false],
]);

it('compares root scalars with one direct modification at the root', function () {
    $result = Diff::between(1, 2);

    expect(summary($result))->toBe(['modified'])
        ->and($result->changes()[0]->path()->isRoot())->toBeTrue()
        ->and($result->changes()[0]->isAggregate())->toBeFalse()
        ->and($result->wasModified(''))->toBeTrue();
});

it('treats floats within the tolerance as equal', function (float $before, float $after, bool $changed) {
    $options = DiffOptions::create()->floatTolerance(0.001);

    expect(Diff::between($before, $after, $options)->hasChanges())->toBe($changed);
})->with([
    'within' => [1.0, 1.0005, false],
    'at the edge' => [1.0, 1.0009, false],
    'outside' => [1.0, 1.01, true],
    'INF vs large' => [INF, PHP_FLOAT_MAX, true],
    'NAN vs zero' => [NAN, 0.0, true],
]);

it('never makes an int equal to a float with a tolerance', function () {
    expect(Diff::between(1, 1.0, DiffOptions::create()->floatTolerance(1.0))->hasChanges())->toBeTrue();
});

it('keeps both values of a modification', function () {
    $change = Diff::between(['age' => 32], ['age' => 33])->changes()[0];

    expect($change->oldValue())->toBe(32)
        ->and($change->newValue())->toBe(33)
        ->and($change->hasOldValue())->toBeTrue()
        ->and($change->hasNewValue())->toBeTrue()
        ->and($change->oldPath()?->toDot())->toBe('age')
        ->and($change->newPath()?->toDot())->toBe('age');
});
