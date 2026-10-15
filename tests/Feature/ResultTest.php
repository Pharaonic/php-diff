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

    expect(array_map(fn (Change $c) => $c->path()->toDot(), $result->added()))->toBe(['user.age'])
        ->and(array_map(fn (Change $c) => $c->path()->toDot(), $result->removed()))->toBe(['user.nickname'])
        ->and(array_map(fn (Change $c) => $c->path()->toDot(), $result->modified()))->toBe(['user', 'user.email', 'tags', 'tags.a\.b'])
        ->and($result->moved())->toBe([]);
});

it('returns changes at a path or under it', function () {
    $result = sampleResult();

    expect(array_map(fn (Change $c) => $c->path()->toDot(), $result->at('user')))->toBe(['user'])
        ->and(array_map(fn (Change $c) => $c->path()->toDot(), $result->under('user')))->toBe(['user', 'user.email', 'user.age', 'user.nickname'])
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

    expect(fn () => $result->toJson())->toThrow(JsonException::class)
        ->and(json_decode($result->toJson(JSON_INVALID_UTF8_SUBSTITUTE), true)[0]['new'])->toBe("\u{FFFD}")
        ->and(fn () => Diff::between(1.0, NAN)->toJson())->toThrow(JsonException::class);
});
