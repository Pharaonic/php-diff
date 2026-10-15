<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\Exceptions\IdentityException;

it('matches records by identity regardless of order', function () {
    $before = [['id' => 10, 'name' => 'A'], ['id' => 20, 'name' => 'B']];
    $after = [['id' => 20, 'name' => 'B2'], ['id' => 10, 'name' => 'A']];
    $result = Diff::between($before, $after, DiffOptions::create()->identify('', 'id'));

    expect(moves($result))->toBe(['modified 1->0 [aggregate]', 'modified 1.name->0.name'])
        ->and($result->changes()[1]->oldValue())->toBe('B')
        ->and($result->changes()[1]->newValue())->toBe('B2');
});

it('reports added and removed records, the removed ones last', function () {
    $before = [['id' => 1], ['id' => 2]];
    $after = [['id' => 3], ['id' => 1]];

    expect(moves(Diff::between($before, $after, DiffOptions::create()->identify('', 'id'))))
        ->toBe(['added 0', 'removed 1']);
});

it('matches composite identities', function () {
    $before = ['items' => [['order_id' => 1, 'product_id' => 1, 'qty' => 1], ['order_id' => 1, 'product_id' => 2, 'qty' => 1]]];
    $after = ['items' => [['order_id' => 1, 'product_id' => 2, 'qty' => 5], ['order_id' => 1, 'product_id' => 1, 'qty' => 1]]];
    $options = DiffOptions::create()->identify('items', ['order_id', 'product_id']);

    expect(moves(Diff::between($before, $after, $options)))
        ->toBe(['modified items [aggregate]', 'modified items.1->items.0 [aggregate]', 'modified items.1.qty->items.0.qty']);
});

it('keeps identity types apart', function () {
    $result = Diff::between([['id' => 1]], [['id' => '1']], DiffOptions::create()->identify('', 'id'));

    expect(summary($result))->toBe(['added 0', 'removed 0']);
});

it('never confuses composite identities that would concatenate the same way', function () {
    $before = [['a' => '1:2', 'b' => '3'], ['a' => '1', 'b' => '2:3']];
    $after = [['a' => '1', 'b' => '2:3'], ['a' => '1:2', 'b' => '3']];

    expect(Diff::between($before, $after, DiffOptions::create()->identify('', ['a', 'b']))->hasChanges())->toBeFalse();
});

it('matches identity rules with wildcards', function () {
    $before = ['orders' => [['items' => [['id' => 1, 'q' => 1], ['id' => 2, 'q' => 1]]]]];
    $after = ['orders' => [['items' => [['id' => 2, 'q' => 1], ['id' => 1, 'q' => 1]]]]];

    expect(Diff::between($before, $after, DiffOptions::create()->identify('orders.*.items', 'id'))->hasChanges())->toBeFalse();
});

it('throws on a duplicate identity', function () {
    Diff::between(['users' => [['id' => 10], ['id' => 10]]], ['users' => []], DiffOptions::create()->identify('users', 'id'));
})->throws(IdentityException::class, 'The items at indexes 0 and 1 of the list at path "users" have the same identity.');

it('throws on a missing identity', function (array $items) {
    Diff::between(['users' => [['id' => 1]]], ['users' => $items], DiffOptions::create()->identify('users', 'id'));
})->with([
    'missing key' => [[['id' => 1], ['name' => 'x']]],
    'scalar item' => [[['id' => 1], 'x']],
])->throws(IdentityException::class, 'The item at index 1 of the list at path "users" has no identity key "id".');

it('treats a null identity value as present', function () {
    expect(Diff::between([['id' => null, 'v' => 1]], [['id' => null, 'v' => 2]], DiffOptions::create()->identify('', 'id'))->wasModified('0.v'))->toBeTrue();
});

it('does not report moves unless asked', function () {
    $before = [['id' => 1], ['id' => 2]];
    $after = [['id' => 2], ['id' => 1]];

    expect(Diff::between($before, $after, DiffOptions::create()->identify('', 'id'))->hasChanges())->toBeFalse();
});

it('does not report records whose index only shifted as moved', function () {
    $options = DiffOptions::create()->identify('', 'id')->detectMoves();
    $before = [['id' => 1], ['id' => 2], ['id' => 3]];
    $after = [['id' => 0], ['id' => 1], ['id' => 2], ['id' => 3]];

    expect(summary(Diff::between($before, $after, $options)))->toBe(['added 0'])
        ->and(summary(Diff::between($after, $before, $options)))->toBe(['removed 0']);
});

it('reports records that changed their relative order as moved', function () {
    $options = DiffOptions::create()->identify('', 'id')->detectMoves();
    $before = [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]];
    $after = [['id' => 1], ['id' => 3], ['id' => 4], ['id' => 2]];
    $result = Diff::between($before, $after, $options);

    expect(moves($result))->toBe(['moved 1->3'])
        ->and($result->wasMoved('1'))->toBeTrue()
        ->and($result->wasMoved('3'))->toBeTrue()
        ->and($result->wasMoved('2'))->toBeFalse()
        ->and($result->moved()[0]->oldValue())->toBe(['id' => 2]);
});

it('reports a record as both moved and modified', function () {
    $options = DiffOptions::create()->identify('users', 'id')->detectMoves();
    $before = ['users' => [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]];
    $after = ['users' => [['id' => 2, 'name' => 'B2'], ['id' => 1, 'name' => 'A'], ['id' => 3, 'name' => 'C']]];

    expect(moves(Diff::between($before, $after, $options)))->toBe([
        'modified users [aggregate]',
        'modified users.1->users.0 [aggregate]',
        'modified users.1.name->users.0.name',
        'moved users.0->users.1',
        'added users.2',
    ]);
});

it('does not explode into moves when a record is inserted at the head of a long list', function () {
    $before = array_map(fn (int $id) => ['id' => $id, 'name' => "user {$id}"], range(1, 1000));
    $after = [['id' => 0, 'name' => 'new'], ...$before];
    $result = Diff::between($before, $after, DiffOptions::create()->identify('', 'id')->detectMoves());

    expect(summary($result))->toBe(['added 0']);
});

it('does not explode into changes when an item is inserted at the head of a long list', function () {
    $before = array_map(fn (int $id) => ['id' => $id, 'name' => "user {$id}"], range(1, 1000));

    expect(summary(Diff::between($before, [['id' => 0], ...$before])))->toBe(['added 0']);
});

it('ignores filtered records', function () {
    $options = DiffOptions::create()->identify('', 'id')->detectMoves()->ignore('0');
    $before = [['id' => 1, 'v' => 1], ['id' => 2, 'v' => 1]];
    $after = [['id' => 2, 'v' => 1], ['id' => 1, 'v' => 2]];

    expect(Diff::between($before, $after, $options)->hasChanges())->toBeFalse();
});
