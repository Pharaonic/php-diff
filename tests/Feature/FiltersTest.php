<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\Exceptions\MaximumDepthExceededException;

it('leaves out ignored paths and everything below them', function () {
    $before = ['name' => 'a', 'updated_at' => 1, 'meta' => ['x' => 1]];
    $after = ['name' => 'b', 'updated_at' => 2, 'meta' => ['x' => 2]];

    expect(summary(Diff::between($before, $after, DiffOptions::create()->ignore(['updated_at', 'meta']))))
        ->toBe(['modified name']);
});

it('matches one segment with a wildcard', function () {
    $before = ['users' => [['name' => 'a', 'age' => 1, 'updated_at' => 1], ['name' => 'b', 'age' => 1, 'updated_at' => 1]]];
    $after = ['users' => [['name' => 'a', 'age' => 1, 'updated_at' => 2], ['name' => 'b', 'age' => 2, 'updated_at' => 2]]];

    expect(summary(Diff::between($before, $after, DiffOptions::create()->ignore('users.*.updated_at'))))
        ->toBe(['modified users [aggregate]', 'modified users.1 [aggregate]', 'modified users.1.age']);
});

it('does not mark a parent as modified when only ignored children changed', function () {
    $result = Diff::between(['user' => ['updated_at' => 1]], ['user' => ['updated_at' => 2]], DiffOptions::create()->ignore('user.updated_at'));

    expect($result->hasChanges())->toBeFalse();
});

it('ignores added and removed values', function () {
    $result = Diff::between(['gone' => 1], ['new' => 1], DiffOptions::create()->ignore(['gone', 'new']));

    expect($result->hasChanges())->toBeFalse();
});

it('matches escaped keys literally', function () {
    $before = ['a.b' => 1, 'a' => ['b' => 1], '*' => 1, 'x' => 1];
    $after = ['a.b' => 2, 'a' => ['b' => 2], '*' => 2, 'x' => 2];

    expect(summary(Diff::between($before, $after, DiffOptions::create()->ignore(['a\.b', '\*']))))
        ->toBe(['modified a [aggregate]', 'modified a.b', 'modified x']);
});

it('matches numeric keys given as strings', function () {
    expect(Diff::between([1, 2], [1, 3], DiffOptions::create()->ignore('1'))->hasChanges())->toBeFalse();
});

it('ignores everything with the root path', function () {
    expect(Diff::between(1, 2, DiffOptions::create()->ignore(''))->hasChanges())->toBeFalse();
});

it('reports only changes at or below the only() paths', function () {
    $before = ['user' => ['email' => 'a', 'name' => 'a'], 'other' => 1];
    $after = ['user' => ['email' => 'b', 'name' => 'b'], 'other' => 2];

    expect(summary(Diff::between($before, $after, DiffOptions::create()->only('user'))))
        ->toBe(['modified user [aggregate]', 'modified user.email', 'modified user.name'])
        ->and(summary(Diff::between($before, $after, DiffOptions::create()->only('user.email'))))
        ->toBe(['modified user.email'])
        ->and(summary(Diff::between($before, $after, DiffOptions::create()->only(['other', 'user.name']))))
        ->toBe(['modified user.name', 'modified other']);
});

it('does not report a parent above the only() paths, even when its type changed', function () {
    $options = DiffOptions::create()->only('user.email');

    expect(Diff::between(['user' => 'Raggi'], ['user' => ['email' => 'r@example.com']], $options)->hasChanges())->toBeFalse()
        ->and(Diff::between([], ['user' => ['email' => 'r@example.com']], $options)->hasChanges())->toBeFalse();
});

it('lets ignore() win over only()', function () {
    $options = DiffOptions::create()->only('user')->ignore('user.password');

    expect(summary(Diff::between(['user' => ['password' => 'a', 'name' => 'a']], ['user' => ['password' => 'b', 'name' => 'b']], $options)))
        ->toBe(['modified user [aggregate]', 'modified user.name']);
});

it('does not traverse ignored branches', function () {
    $before = ['ok' => 1, 'skip' => ['resource' => STDIN]];
    $after = ['ok' => 2, 'skip' => ['resource' => STDOUT]];

    expect(summary(Diff::between($before, $after, DiffOptions::create()->ignore('skip'))))->toBe(['modified ok']);
});

it('does not traverse branches that cannot reach an only() path', function () {
    $before = ['ok' => 1, 'skip' => STDIN];
    $after = ['ok' => 2, 'skip' => STDOUT];

    expect(summary(Diff::between($before, $after, DiffOptions::create()->only('ok'))))->toBe(['modified ok']);
});

it('does not count pruned branches toward the maximum depth', function () {
    $deep = ['a' => ['b' => ['c' => ['d' => 1]]]];
    $before = ['ok' => 1, 'deep' => $deep];
    $after = ['ok' => 2, 'deep' => ['a' => ['b' => ['c' => ['d' => 2]]]]];
    $options = DiffOptions::create()->maxDepth(2);

    expect(summary(Diff::between($before, $after, $options->ignore('deep'))))->toBe(['modified ok'])
        ->and(summary(Diff::between($before, $after, $options->only('ok'))))->toBe(['modified ok'])
        ->and(fn () => Diff::between($before, $after, $options))->toThrow(MaximumDepthExceededException::class);
});
