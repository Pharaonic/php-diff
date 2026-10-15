<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;

it('turns an obvious record replacement into a modification', function () {
    expect(summary(Diff::between([['name' => 'A', 'age' => 1]], [['name' => 'A', 'age' => 2]])))
        ->toBe(['modified 0 [aggregate]', 'modified 0.age']);
});

it('pairs a removed record with a later added one without crossing earlier pairs', function () {
    $before = [['n' => 'a', 'v' => 1], ['n' => 'b', 'v' => 1]];
    $after = [['n' => 'a', 'v' => 2], ['k' => 'other'], ['n' => 'b', 'v' => 2]];

    expect(summary(Diff::between($before, $after)))->toBe([
        'modified 0 [aggregate]',
        'modified 0.v',
        'added 1',
        'modified 2 [aggregate]',
        'modified 2.v',
    ]);
});

it('keeps weakly related records as a removal and an addition', function (array $before, mixed $after) {
    expect(summary(Diff::between([$before], [$after])))->toBe(['removed 0', 'added 0']);
})->with([
    'different keys' => [['name' => 'A', 'age' => 1], ['sku' => 'X', 'price' => 2]],
    'few shared keys' => [['a' => 1, 'b' => 1, 'c' => 1], ['a' => 1, 'x' => 1, 'y' => 1]],
    'no shared value' => [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']],
    'map and list' => [['a' => 1], [1]],
    'empty record' => [['a' => 1], []],
    'record and scalar' => [['a' => 1], 'a'],
]);

it('pairs lists that share an item', function () {
    expect(summary(Diff::between([[1, 2]], [[1, 3]])))->toBe(['modified 0 [aggregate]', 'modified 0.1'])
        ->and(summary(Diff::between([[1, 2]], [[3, 4]])))->toBe(['removed 0', 'added 0']);
});
