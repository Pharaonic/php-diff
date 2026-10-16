<?php

declare(strict_types=1);

use Pharaonic\Diff\Change;
use Pharaonic\Diff\ChangeType;
use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffResult;
use Pharaonic\Diff\Path;

function sampleResult(): DiffResult
{
    return Diff::between(
        ['user' => ['email' => 'a', 'nickname' => 'R'], 'username' => 'x', 'tags' => ['a.b' => 1]],
        ['user' => ['email' => 'b', 'age' => 1], 'username' => 'x', 'tags' => ['a.b' => 2]],
    );
}

it('reports whether anything changed', function () {
    expect(sampleResult()->hasChanges())->toBeTrue()
        ->and(Diff::between(['a' => 1], ['a' => 1])->hasChanges())->toBeFalse();
});

it('checks a path and its descendants by segment', function () {
    $result = sampleResult();

    expect($result->hasChanged('user'))->toBeTrue()
        ->and($result->hasChanged('user.email'))->toBeTrue()
        ->and($result->hasChanged('username'))->toBeFalse()
        ->and($result->hasChanged('use'))->toBeFalse()
        ->and($result->hasChanged('tags.a\.b'))->toBeTrue()
        ->and($result->hasChanged(Path::fromSegments(['tags', 'a.b'])))->toBeTrue()
        ->and($result->hasChanged('tags.a'))->toBeFalse()
        ->and($result->hasChanged(''))->toBeTrue();
});

it('checks change types at exact paths', function () {
    $result = sampleResult();

    expect($result->wasModified('user.email'))->toBeTrue()
        ->and($result->wasModified('user'))->toBeTrue()
        ->and($result->wasAdded('user.age'))->toBeTrue()
        ->and($result->wasRemoved('user.nickname'))->toBeTrue()
        ->and($result->wasAdded('user'))->toBeFalse()
        ->and($result->wasRemoved('user.email'))->toBeFalse()
        ->and($result->wasModified('user.email.x'))->toBeFalse()
        ->and($result->wasMoved('user.email'))->toBeFalse();
});

it('returns changes by type', function () {
    $result = sampleResult();

    expect(array_map(fn(Change $c) => $c->path()->toDot(), $result->added()))->toBe(['user.age'])
        ->and(array_map(fn(Change $c) => $c->path()->toDot(), $result->removed()))->toBe(['user.nickname'])
        ->and(array_map(fn(Change $c) => $c->path()->toDot(), $result->modified()))->toBe(['user', 'user.email', 'tags', 'tags.a\.b'])
        ->and($result->moved())->toBe([]);
});

it('returns changes at a path or under it', function () {
    $result = sampleResult();

    expect(array_map(fn(Change $c) => $c->path()->toDot(), $result->at('user')))->toBe(['user'])
        ->and(array_map(fn(Change $c) => $c->path()->toDot(), $result->under('user')))->toBe(['user', 'user.email', 'user.age', 'user.nickname'])
        ->and($result->at('missing'))->toBe([])
        ->and($result->under(''))->toBe($result->changes());
});

it('counts, iterates and summarizes its changes', function () {
    $result = sampleResult();

    expect($result)->toHaveCount(6)
        ->and(iterator_to_array($result))->toBe($result->changes())
        ->and($result->stats())->toBe(['total' => 6, 'added' => 1, 'removed' => 1, 'modified' => 4, 'moved' => 0])
        ->and(Diff::between(1, 1)->stats())->toBe(['total' => 0, 'added' => 0, 'removed' => 0, 'modified' => 0, 'moved' => 0]);
});

it('serializes to arrays and JSON', function () {
    $result = Diff::between(['a' => 1], ['a' => 2]);
    $expected = [[
        'type' => ChangeType::MODIFIED,
        'path' => 'a',
        'old_path' => 'a',
        'new_path' => 'a',
        'old_exists' => true,
        'old' => 1,
        'new_exists' => true,
        'new' => 2,
        'aggregate' => false,
        'redacted' => false,
    ]];

    expect($result->toArray())->toBe($expected)
        ->and($result->jsonSerialize())->toBe($expected)
        ->and(json_decode($result->toJson(), true))->toBe($expected)
        ->and(json_encode($result))->toBe($result->toJson())
        ->and($result->toJson(JSON_PRETTY_PRINT))->toContain("\n");
});

it('throws on values JSON cannot encode unless the flags allow them', function () {
    $result = Diff::between(['a' => 1], ['a' => "\xB1"]);

    expect(fn() => $result->toJson())->toThrow(JsonException::class)
        ->and(json_decode($result->toJson(JSON_INVALID_UTF8_SUBSTITUTE), true)[0]['new'])->toBe("\u{FFFD}")
        ->and(fn() => Diff::between(1.0, NAN)->toJson())->toThrow(JsonException::class);
});

function listResult(): DiffResult
{
    $before = ['users' => [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B'], ['id' => 4, 'name' => 'D']]];
    $after = ['users' => [['id' => 2, 'name' => 'B2'], ['id' => 1, 'name' => 'A'], ['id' => 3, 'name' => 'C']]];

    return Diff::between($before, $after, Pharaonic\Diff\DiffOptions::create()->identify('users', 'id')->detectMoves());
}

it('answers queries on lists by canonical, old and new paths', function () {
    $result = listResult();

    expect(moves($result))->toBe([
        'modified users [aggregate]',
        'modified users.1->users.0 [aggregate]',
        'modified users.1.name->users.0.name',
        'moved users.0->users.1',
        'added users.2',
        'removed users.2',
    ])
        ->and($result->wasModified('users.0.name'))->toBeTrue()
        ->and($result->wasModified('users.1.name'))->toBeFalse()
        ->and($result->wasMoved('users.0'))->toBeTrue()
        ->and($result->wasMoved('users.1'))->toBeTrue()
        ->and($result->wasAdded('users.2'))->toBeTrue()
        ->and($result->wasRemoved('users.2'))->toBeTrue()
        ->and($result->stats())->toBe(['total' => 6, 'added' => 1, 'removed' => 1, 'modified' => 3, 'moved' => 1]);
});

it('returns every change at a path, including a move and a modification', function () {
    $result = Diff::between(
        [['id' => 1, 'v' => 1], ['id' => 2, 'v' => 1]],
        [['id' => 2, 'v' => 2], ['id' => 1, 'v' => 1]],
        Pharaonic\Diff\DiffOptions::create()->identify('', 'id')->detectMoves(),
    );

    expect(array_map(fn(Change $c) => $c->type(), $result->at('0')))->toBe(['modified'])
        ->and(array_map(fn(Change $c) => $c->type(), $result->at('1')))->toBe(['moved'])
        ->and(array_map(fn(Change $c) => $c->type(), $result->under('1')))->toBe(['modified', 'modified', 'moved']);
});

it('finds changes under a path through their old or new path', function () {
    $result = listResult();

    expect($result->hasChanged('users.1'))->toBeTrue()
        ->and(count($result->under('users.1.name')))->toBe(1)
        ->and($result->under('users.1.name')[0]->path()->toDot())->toBe('users.0.name');
});

it('accepts paths as strings, segments or Path objects alike', function () {
    $result = listResult();

    expect($result->wasAdded(Path::fromSegments(['users', 2])))->toBe($result->wasAdded('users.2'))
        ->and($result->wasAdded(Path::fromSegments(['users', '2'])))->toBeTrue()
        ->and($result->at(Path::fromDot('users.0.name')))->toBe($result->at('users.0.name'));
});

it('rejects malformed query paths', function () {
    listResult()->hasChanged('users..1');
})->throws(InvalidArgumentException::class, 'The path "users..1" has an empty segment.');

it('serializes old and new paths of list changes', function () {
    $moved = listResult()->moved()[0]->toArray();

    expect($moved)->toMatchArray(['type' => 'moved', 'path' => 'users.1', 'old_path' => 'users.0', 'new_path' => 'users.1', 'old' => ['id' => 1, 'name' => 'A']]);
});

it('produces identical output for identical input', function () {
    expect(listResult()->toJson(JSON_PRETTY_PRINT))->toBe(listResult()->toJson(JSON_PRETTY_PRINT))
        ->and(serialize(listResult()))->toBe(serialize(listResult()));
});

it('keeps its changes when the returned list is modified', function () {
    $result = listResult();
    $changes = $result->changes();
    array_pop($changes);

    expect($result)->toHaveCount(6);
});

it('passes JSON flags through', function () {
    $result = Diff::between(['name' => 'رجي'], ['name' => 'Raggi/x']);

    expect($result->toJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->toContain('"old":"رجي","new_exists":true,"new":"Raggi/x"');
});
