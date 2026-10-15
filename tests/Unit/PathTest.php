<?php

declare(strict_types=1);

use Pharaonic\Diff\Path;

it('has an empty root', function () {
    $root = Path::fromDot('');

    expect($root->isRoot())->toBeTrue()
        ->and($root->segments())->toBe([])
        ->and($root->toDot())->toBe('')
        ->and($root->toJsonPointer())->toBe('')
        ->and(Path::fromSegments([])->isRoot())->toBeTrue()
        ->and(Path::fromDot('users')->isRoot())->toBeFalse();
});

it('reads dot notation into segments', function () {
    expect(Path::fromDot('users.4.email')->segments())->toBe(['users', 4, 'email']);
});

it('stores segments the way PHP stores array keys', function (array $segments, array $expected) {
    expect(Path::fromSegments($segments)->segments())->toBe($expected);
})->with([
    'numeric string' => [['4'], [4]],
    'negative numeric string' => [['-4'], [-4]],
    'leading zero' => [['04'], ['04']],
    'decimal' => [['4.5'], ['4.5']],
    'int' => [[4], [4]],
    'keyed input' => [['a' => 'x', 'b' => 'y'], ['x', 'y']],
]);

it('rejects segments that are not keys', function () {
    Path::fromSegments([1.5]);
})->throws(InvalidArgumentException::class, 'A path segment must be an int or a string, float given.');

it('escapes dots, stars and backslashes in dot notation', function (array $segments, string $dot) {
    $path = Path::fromSegments($segments);

    expect($path->toDot())->toBe($dot)
        ->and(Path::fromDot($dot)->segments())->toBe($path->segments());
})->with([
    'dot' => [['a.b', 'c'], 'a\.b.c'],
    'star' => [['*'], '\*'],
    'backslash' => [['a\\b'], 'a\\\\b'],
    'trailing backslash' => [['a\\'], 'a\\\\'],
    'numeric' => [['items', 0], 'items.0'],
]);

it('reads an unescaped star in a path as the key "*"', function () {
    expect(Path::fromDot('users.*')->segments())->toBe(['users', '*']);
});

it('keeps a backslash that escapes nothing', function () {
    expect(Path::fromDot('a\b')->segments())->toBe(['a\b']);
});

it('rejects empty segments in dot notation', function (string $dot) {
    Path::fromDot($dot);
})->with(['a..b', '.a', 'a.'])->throws(InvalidArgumentException::class, 'has an empty segment');

it('renders RFC 6901 JSON pointers', function (array $segments, string $pointer) {
    expect(Path::fromSegments($segments)->toJsonPointer())->toBe($pointer);
})->with([
    'plain' => [['users', 4, 'email'], '/users/4/email'],
    'slash' => [['a/b'], '/a~1b'],
    'tilde' => [['a~b'], '/a~0b'],
    'tilde then one' => [['~1'], '/~01'],
    'empty key' => [[''], '/'],
]);
