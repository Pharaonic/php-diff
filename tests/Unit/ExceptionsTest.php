<?php

declare(strict_types=1);

use Pharaonic\Diff\Exceptions\DiffException;
use Pharaonic\Diff\Exceptions\IdentityException;
use Pharaonic\Diff\Exceptions\InputLimitExceededException;
use Pharaonic\Diff\Exceptions\MaximumDepthExceededException;
use Pharaonic\Diff\Exceptions\UnsupportedValueException;
use Pharaonic\Diff\Path;

it('implements the package exception interface and extends SPL', function (Throwable $exception, string $spl) {
    expect($exception)->toBeInstanceOf(DiffException::class)->toBeInstanceOf($spl);
})->with([
    'unsupported value' => [UnsupportedValueException::at(Path::fromDot(''), 'A resource cannot be compared'), InvalidArgumentException::class],
    'maximum depth' => [MaximumDepthExceededException::limit(Path::fromDot('a'), 64), UnsupportedValueException::class],
    'identity' => [IdentityException::missing(Path::fromDot('users'), 0, 'id'), UnexpectedValueException::class],
    'input limit' => [InputLimitExceededException::lines('old', 10, 5), LengthException::class],
]);

it('names the path in its message', function (Throwable $exception, string $message) {
    expect($exception->getMessage())->toBe($message);
})->with([
    'root' => [UnsupportedValueException::at(Path::fromDot(''), 'A resource cannot be compared'), 'A resource cannot be compared at the root.'],
    'path' => [UnsupportedValueException::at(Path::fromDot('a.b'), 'A closure cannot be compared'), 'A closure cannot be compared at path "a.b".'],
    'circular' => [UnsupportedValueException::circular(Path::fromDot('a'), new stdClass()), 'The stdClass object contains itself at path "a".'],
    'depth' => [MaximumDepthExceededException::limit(Path::fromDot('a'), 2), 'The value is nested deeper than the maximum depth of 2 at path "a"; it may reference itself.'],
    'duplicate identity' => [IdentityException::duplicate(Path::fromDot('users'), 3, 1), 'The items at indexes 1 and 3 of the list at path "users" have the same identity.'],
    'missing identity' => [IdentityException::missing(Path::fromDot(''), 2, 'id'), 'The item at index 2 of the list at the root has no identity key "id".'],
    'lines' => [InputLimitExceededException::lines('new', 10, 5), 'The new text has 10 lines, more than the maximum of 5.'],
    'bytes' => [InputLimitExceededException::bytes('old', 10, 5), 'The old text has 10 bytes, more than the maximum of 5.'],
]);
