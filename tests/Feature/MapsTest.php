<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;

it('finds no changes between identical maps', function () {
    expect(Diff::between(['a' => 1, 'b' => ['c' => 2]], ['a' => 1, 'b' => ['c' => 2]])->hasChanges())->toBeFalse();
});

it('ignores map key order', function () {
    expect(Diff::between(['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1])->hasChanges())->toBeFalse();
});

it('reports an added key, keeping a null value apart from a missing one', function () {
    $result = Diff::between([], ['middle_name' => null]);
    $change = $result->changes()[0];

    expect(summary($result))->toBe(['added middle_name'])
        ->and($change->hasOldValue())->toBeFalse()
        ->and($change->hasNewValue())->toBeTrue()
        ->and($change->newValue())->toBeNull()
        ->and($change->oldPath())->toBeNull()
        ->and($change->toArray())->toMatchArray(['old_exists' => false, 'new_exists' => true, 'new' => null]);
});

it('reports a removed key', function () {
    $result = Diff::between(['name' => 'Raggi', 'nickname' => 'R'], ['name' => 'Raggi']);
    $change = $result->changes()[0];

    expect(summary($result))->toBe(['removed nickname'])
        ->and($change->oldValue())->toBe('R')
        ->and($change->hasNewValue())->toBeFalse()
        ->and($change->newPath())->toBeNull();
});

it('reports a key that became null as modified, not removed', function () {
    expect(summary(Diff::between(['a' => 1], ['a' => null])))->toBe(['modified a']);
});

it('reports a whole added or removed subtree as one change', function () {
    $user = ['name' => 'Raggi', 'profile' => ['age' => 33]];

    $added = Diff::between([], ['user' => $user]);
    $removed = Diff::between(['user' => $user], []);

    expect(summary($added))->toBe(['added user'])
        ->and($added->changes()[0]->newValue())->toBe($user)
        ->and(summary($removed))->toBe(['removed user'])
        ->and($removed->changes()[0]->oldValue())->toBe($user);
});

it('reports a container type change as one direct modification', function (mixed $before, mixed $after) {
    $result = Diff::between(['user' => $before], ['user' => $after]);

    expect(summary($result))->toBe(['modified user'])
        ->and($result->changes()[0]->oldValue())->toBe($before)
        ->and($result->changes()[0]->newValue())->toBe($after);
})->with([
    'scalar to map' => ['Raggi', ['name' => 'Raggi']],
    'map to scalar' => [['name' => 'Raggi'], 'Raggi'],
    'scalar to list' => ['a', ['a']],
    'map to list' => [['a' => 1], [1]],
    'list to map' => [[1], ['a' => 1]],
    'null to map' => [null, ['a' => 1]],
]);

it('lets an empty array take the kind of the other side', function (array $before, array $after, array $expected) {
    expect(summary(Diff::between(['v' => $before], ['v' => $after])))->toBe($expected);
})->with([
    'empty to map' => [[], ['name' => 'Raggi'], ['modified v [aggregate]', 'added v.name']],
    'map to empty' => [['name' => 'Raggi'], [], ['modified v [aggregate]', 'removed v.name']],
    'empty to list' => [[], [1, 2], ['modified v [aggregate]', 'added v.0', 'added v.1']],
    'list to empty' => [[1], [], ['modified v [aggregate]', 'removed v.0']],
    'empty to empty' => [[], [], []],
]);

it('treats sparse arrays as maps without reindexing them', function () {
    expect(summary(Diff::between([0 => 'a', 2 => 'c'], [0 => 'a', 2 => 'd'])))->toBe(['modified 2'])
        ->and(summary(Diff::between([0 => 'a', 2 => 'c'], [0 => 'a', 3 => 'c'])))->toBe(['added 3', 'removed 2'])
        ->and(summary(Diff::between([0 => 'a', 2 => 'c'], ['a', 'c'])))->toBe(['modified']);
});

it('orders changes by the new keys, then the removed keys in their old order', function () {
    $before = ['a' => 1, 'gone1' => 1, 'b' => 1, 'gone2' => 1];
    $after = ['new' => 1, 'b' => 2, 'a' => 2];

    expect(summary(Diff::between($before, $after)))->toBe(['added new', 'modified b', 'modified a', 'removed gone1', 'removed gone2']);
});

it('compares nested maps', function () {
    $before = ['user' => ['name' => 'Raggi', 'nickname' => 'R', 'age' => 32]];
    $after = ['user' => ['name' => 'Raggi', 'age' => 33, 'email' => 'r@example.com']];

    expect(summary(Diff::between($before, $after)))->toBe([
        'modified user [aggregate]',
        'modified user.age',
        'added user.email',
        'removed user.nickname',
    ]);
});

it('produces the same result for the same input', function () {
    $before = ['a' => [1, 2], 'b' => ['c' => NAN], 'd' => 1];
    $after = ['d' => 2, 'a' => [1, 3], 'b' => ['c' => NAN, 'e' => 1]];

    expect(serialize(Diff::between($before, $after)->changes()))->toBe(serialize(Diff::between($before, $after)->changes()));
});
