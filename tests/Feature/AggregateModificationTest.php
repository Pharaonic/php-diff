<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;

it('marks every existing ancestor of a change as an aggregate modification', function () {
    $result = Diff::between(['a' => ['b' => ['c' => 1]]], ['a' => ['b' => ['c' => 2]]]);

    expect(summary($result))->toBe(['modified a [aggregate]', 'modified a.b [aggregate]', 'modified a.b.c']);
});

it('never reports the root as an aggregate modification', function () {
    $result = Diff::between(['a' => 1, 'b' => 1], ['a' => 2, 'b' => 1]);

    expect(summary($result))->toBe(['modified a'])
        ->and($result->wasModified(''))->toBeFalse();
});

it('gives aggregate modifications the whole old and new containers', function () {
    $result = Diff::between(['user' => ['name' => 'Raggi', 'age' => 32]], ['user' => ['name' => 'Raggi', 'age' => 33]]);
    $user = $result->at('user')[0];

    expect($user->isAggregate())->toBeTrue()
        ->and($user->oldValue())->toBe(['name' => 'Raggi', 'age' => 32])
        ->and($user->newValue())->toBe(['name' => 'Raggi', 'age' => 33])
        ->and($result->at('user.age')[0]->isAggregate())->toBeFalse();
});

it('marks a parent of an added or removed key as modified', function () {
    expect(summary(Diff::between(['user' => ['name' => 'Raggi']], ['user' => ['name' => 'Raggi', 'age' => 1]])))
        ->toBe(['modified user [aggregate]', 'added user.age'])
        ->and(summary(Diff::between(['user' => ['name' => 'Raggi', 'age' => 1]], ['user' => ['name' => 'Raggi']])))
        ->toBe(['modified user [aggregate]', 'removed user.age']);
});

it('reports a parent once with its changed children after it', function () {
    $before = ['company' => ['a' => 1, 'b' => ['c' => 1, 'd' => 1]]];
    $after = ['company' => ['a' => 2, 'b' => ['c' => 2, 'd' => 2]]];

    expect(summary(Diff::between($before, $after)))->toBe([
        'modified company [aggregate]',
        'modified company.a',
        'modified company.b [aggregate]',
        'modified company.b.c',
        'modified company.b.d',
    ]);
});

it('does not mark unchanged siblings', function () {
    $result = Diff::between(['a' => ['x' => 1], 'b' => ['y' => 1]], ['a' => ['x' => 2], 'b' => ['y' => 1]]);

    expect($result->hasChanged('b'))->toBeFalse()
        ->and(summary($result))->toBe(['modified a [aggregate]', 'modified a.x']);
});
