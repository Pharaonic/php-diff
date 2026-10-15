<?php

declare(strict_types=1);

use Pharaonic\Diff\Change;
use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\ListStrategy;

/**
 * Each change as "type path", with "old->new" when a list item changed its index
 *
 * @return list<string>
 */
function moves(Pharaonic\Diff\DiffResult $result): array
{
    return array_map(static function (Change $change): string {
        $old = $change->oldPath()?->toDot();
        $new = $change->newPath()?->toDot();
        $where = $old !== null && $new !== null && $old !== $new ? "{$old}->{$new}" : $change->path()->toDot();

        return $change->type() . ' ' . $where . ($change->isAggregate() ? ' [aggregate]' : '');
    }, $result->changes());
}

it('aligns sequences so an insertion or removal does not cascade', function (array $before, array $after, array $expected) {
    expect(summary(Diff::between($before, $after)))->toBe($expected);
})->with([
    'identical' => [['a', 'b', 'c'], ['a', 'b', 'c'], []],
    'append' => [['a', 'b'], ['a', 'b', 'c'], ['added 2']],
    'prepend' => [['a', 'b'], ['x', 'a', 'b'], ['added 0']],
    'insert middle' => [['A', 'B', 'C'], ['A', 'X', 'B', 'C'], ['added 1']],
    'remove beginning' => [['a', 'b', 'c'], ['b', 'c'], ['removed 0']],
    'remove middle' => [['a', 'b', 'c'], ['a', 'c'], ['removed 1']],
    'duplicates' => [['a', 'a', 'b'], ['a', 'a', 'a', 'b'], ['added 2']],
    'replace one' => [['a', 'b', 'c'], ['a', 'x', 'c'], ['modified 1']],
    'replace with more' => [['a', 'b', 'c'], ['a', 'x', 'y', 'c'], ['removed 1', 'added 1', 'added 2']],
    'type change of an item' => [['a', 1], ['a', '1'], ['modified 1']],
]);

it('reports an insertion into a nested list with its parent', function () {
    $result = Diff::between(['tags' => ['php', 'laravel']], ['tags' => ['php', 'diff', 'laravel']]);

    expect(summary($result))->toBe(['modified tags [aggregate]', 'added tags.1'])
        ->and($result->added()[0]->newValue())->toBe('diff');
});

it('compares nested lists', function () {
    expect(summary(Diff::between([[1, 2], [3, 4]], [[1, 2], [3, 5]])))
        ->toBe(['modified 1 [aggregate]', 'modified 1.1']);
});

it('keeps old and new indexes of items that shifted', function () {
    $result = Diff::between(['x', ['n' => 'a', 'v' => 1]], [['n' => 'a', 'v' => 2]]);

    expect(moves($result))->toBe(['removed 0', 'modified 1->0 [aggregate]', 'modified 1.v->0.v'])
        ->and($result->wasModified('0.v'))->toBeTrue()
        ->and($result->hasChanged('1'))->toBeTrue();
});

it('compares by position when the position is the identity', function () {
    $options = DiffOptions::create()->listStrategy('slots', ListStrategy::POSITION);

    expect(summary(Diff::between(['slots' => ['a', 'b', 'c']], ['slots' => ['x', 'a', 'b']], $options)))
        ->toBe(['modified slots [aggregate]', 'modified slots.0', 'modified slots.1', 'modified slots.2'])
        ->and(summary(Diff::between(['slots' => ['a', 'b']], ['slots' => ['a']], $options)))
        ->toBe(['modified slots [aggregate]', 'removed slots.1'])
        ->and(summary(Diff::between(['slots' => ['a']], ['slots' => ['a', 'b']], $options)))
        ->toBe(['modified slots [aggregate]', 'added slots.1']);
});

it('ignores order but keeps counts in unordered lists', function (array $before, array $after, array $expected) {
    $options = DiffOptions::create()->listStrategy('', ListStrategy::UNORDERED);

    expect(moves(Diff::between($before, $after, $options)))->toBe($expected);
})->with([
    'reordered' => [['a', 'b', 'c'], ['c', 'a', 'b'], []],
    'removed duplicate' => [['php', 'php', 'laravel'], ['php', 'laravel'], ['removed 1']],
    'added duplicate' => [['php', 'laravel'], ['laravel', 'php', 'php'], ['added 2']],
    'different scalars are never paired' => [['a', 'b'], ['b', 'c'], ['removed 0', 'added 1']],
    'reordered records' => [[['k' => 1], ['k' => 2]], [['k' => 2], ['k' => 1]], []],
    'paired records' => [[['k' => 1, 'v' => 1], ['k' => 2, 'v' => 1]], [['k' => 2, 'v' => 1], ['k' => 1, 'v' => 2]], ['modified 0->1 [aggregate]', 'modified 0.v->1.v']],
]);

it('picks the list strategy per path, with the last matching rule winning', function () {
    $before = ['a' => ['x', 'y'], 'b' => ['x', 'y']];
    $after = ['a' => ['y', 'x'], 'b' => ['y', 'x']];
    $options = DiffOptions::create()
        ->listStrategy('*', ListStrategy::UNORDERED)
        ->listStrategy('b', ListStrategy::SEQUENCE);

    expect(summary(Diff::between($before, $after, $options)))->toBe(['modified b [aggregate]', 'removed b.0', 'added b.1']);
});

it('treats records that differ only in ignored fields as equal when aligning', function () {
    $before = [['id' => 1, 'at' => 1], ['id' => 2, 'at' => 1]];
    $after = [['id' => 0, 'at' => 2], ['id' => 1, 'at' => 2], ['id' => 2, 'at' => 2]];

    expect(summary(Diff::between($before, $after, DiffOptions::create()->ignore('*.at'))))->toBe(['added 0']);
});

it('rejects unsupported values inside list items', function () {
    Diff::between([['a' => 1]], [['a' => STDIN]]);
})->throws(Pharaonic\Diff\Exceptions\UnsupportedValueException::class, 'at path "0.a"');

it('stops self-referencing items at the maximum depth', function () {
    $item = ['a' => 1];
    $item['self'] = &$item;

    Diff::between([1], [$item]);
})->throws(Pharaonic\Diff\Exceptions\MaximumDepthExceededException::class);
