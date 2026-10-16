<?php

declare(strict_types=1);

use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\ListStrategy;

it('returns new options from every method and leaves the original unchanged', function (string $method, array $arguments) {
    $original = DiffOptions::create();

    $configured = $original->{$method}(...$arguments);

    expect($configured)->not->toBe($original)->not->toEqual($original)
        ->and($original)->toEqual(DiffOptions::create());
})->with([
    'ignore' => ['ignore', ['a']],
    'only' => ['only', [['a']]],
    'redact' => ['redact', ['password']],
    'maxDepth' => ['maxDepth', [3]],
    'floatTolerance' => ['floatTolerance', [0.1]],
    'listStrategy' => ['listStrategy', ['tags', ListStrategy::UNORDERED]],
    'identify' => ['identify', ['users', 'id']],
    'detectMoves' => ['detectMoves', []],
    'normalize' => ['normalize', [DateTimeInterface::class, fn() => 1]],
    'compare' => ['compare', [stdClass::class, fn() => true]],
]);

it('has documented defaults', function () {
    $options = DiffOptions::create();

    expect($options->depthLimit())->toBe(64)
        ->and($options->tolerance())->toBe(0.0)
        ->and($options->movesDetected())->toBeFalse()
        ->and($options->ignoredPatterns())->toBe([])
        ->and($options->listRules())->toBe([]);
});

it('parses path filters into patterns and accumulates them', function () {
    $options = DiffOptions::create()
        ->ignore('users.*.updated_at')
        ->ignore(['a\.b', 'x'])
        ->only('profile')
        ->redact(['*.token']);

    expect($options->ignoredPatterns())->toBe([['users', null, 'updated_at'], ['a.b'], ['x']])
        ->and($options->onlyPatterns())->toBe([['profile']])
        ->and($options->redactedPatterns())->toBe([[null, 'token']]);
});

it('keeps list rules in the order they were added', function () {
    $options = DiffOptions::create()
        ->listStrategy('tags', ListStrategy::UNORDERED)
        ->identify('orders.*.items', ['order_id', 'product_id'])
        ->identify('users', 'id');

    expect($options->listRules())->toBe([
        [['tags'], 'unordered', []],
        [['orders', null, 'items'], null, ['order_id', 'product_id']],
        [['users'], null, ['id']],
    ]);
});

it('keeps normalizers and comparators as closures', function () {
    $options = DiffOptions::create()
        ->normalize(DateTimeInterface::class, 'get_object_vars')
        ->compare(stdClass::class, fn(object $a, object $b): bool => true);

    expect($options->normalizers()[0][0])->toBe(DateTimeInterface::class)
        ->and($options->normalizers()[0][1])->toBeInstanceOf(Closure::class)
        ->and($options->comparators()[0][0])->toBe(stdClass::class);
});

it('rejects invalid configuration', function (string $method, array $arguments, string $message) {
    expect(fn() => DiffOptions::create()->{$method}(...$arguments))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'empty segment' => ['ignore', ['a..b'], 'The path "a..b" has an empty segment.'],
    'non-string path' => ['only', [[1]], 'A path must be a string, int given.'],
    'depth' => ['maxDepth', [0], 'The maximum depth must be at least 1, 0 given.'],
    'negative tolerance' => ['floatTolerance', [-0.1], 'The float tolerance must be a finite number of at least 0.'],
    'NAN tolerance' => ['floatTolerance', [NAN], 'The float tolerance must be a finite number of at least 0.'],
    'INF tolerance' => ['floatTolerance', [INF], 'The float tolerance must be a finite number of at least 0.'],
    'strategy' => ['listStrategy', ['tags', 'set'], 'Unknown list strategy "set"; use a ListStrategy constant.'],
    'no identity keys' => ['identify', ['users', []], 'Identity keys must be a key or a non-empty list of keys.'],
    'keyed identity keys' => ['identify', ['users', ['a' => 'id']], 'Identity keys must be a key or a non-empty list of keys.'],
    'invalid identity key' => ['identify', ['users', [1.5]], 'An identity key must be an int or a string, float given.'],
    'unknown normalizer class' => ['normalize', ['NoSuchClass', fn() => 1], 'The class or interface "NoSuchClass" does not exist.'],
    'unknown comparator class' => ['compare', ['NoSuchClass', fn() => true], 'The class or interface "NoSuchClass" does not exist.'],
]);
