<?php

declare(strict_types=1);

use Pharaonic\Diff\Change;
use Pharaonic\Diff\ChangeType;
use Pharaonic\Diff\Path;

it('uses the new path as the canonical path except for removals', function (string $type, string $expected) {
    $change = new Change($type, Path::fromDot('users.3'), Path::fromDot('users.1'), true, 'a', true, 'b');

    expect($change->path()->toDot())->toBe($expected);
})->with([
    'added' => [ChangeType::ADDED, 'users.1'],
    'modified' => [ChangeType::MODIFIED, 'users.1'],
    'moved' => [ChangeType::MOVED, 'users.1'],
    'removed' => [ChangeType::REMOVED, 'users.3'],
]);

it('requires its canonical path', function () {
    new Change(ChangeType::REMOVED, null, Path::fromDot('a'), true, 1, false, null);
})->throws(LogicException::class, 'A removed change needs its canonical path.');

it('tells a null value apart from a missing one', function () {
    $change = new Change(ChangeType::ADDED, null, Path::fromDot('middle_name'), false, null, true, null);

    expect($change->hasOldValue())->toBeFalse()
        ->and($change->hasNewValue())->toBeTrue()
        ->and($change->oldValue())->toBeNull()
        ->and($change->newValue())->toBeNull()
        ->and($change->oldPath())->toBeNull();
});

it('exposes whether it is aggregate or redacted', function () {
    $change = new Change(ChangeType::MODIFIED, Path::fromDot('user'), Path::fromDot('user'), true, [], true, [], true, false);
    $redacted = new Change(ChangeType::MODIFIED, Path::fromDot('password'), Path::fromDot('password'), true, Change::REDACTED, true, Change::REDACTED, false, true);

    expect($change->isAggregate())->toBeTrue()
        ->and($change->isRedacted())->toBeFalse()
        ->and($redacted->isAggregate())->toBeFalse()
        ->and($redacted->isRedacted())->toBeTrue()
        ->and($redacted->oldValue())->toBe('[REDACTED]');
});

it('serializes to an array with dot paths', function () {
    $change = new Change(ChangeType::MODIFIED, Path::fromDot('user.age'), Path::fromDot('user.age'), true, 32, true, 33);

    expect($change->type())->toBe('modified')
        ->and($change->toArray())->toBe([
            'type' => 'modified',
            'path' => 'user.age',
            'old_path' => 'user.age',
            'new_path' => 'user.age',
            'old_exists' => true,
            'old' => 32,
            'new_exists' => true,
            'new' => 33,
            'aggregate' => false,
            'redacted' => false,
        ]);
});

it('serializes a missing path as null', function () {
    $change = new Change(ChangeType::REMOVED, Path::fromDot('nickname'), null, true, 'R', false, null);

    expect($change->toArray())->toMatchArray(['path' => 'nickname', 'old_path' => 'nickname', 'new_path' => null, 'new_exists' => false]);
});
