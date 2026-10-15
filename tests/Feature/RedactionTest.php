<?php

declare(strict_types=1);

use Pharaonic\Diff\Change;
use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\Tests\Fixtures\Payload;

/**
 * Assert that a secret appears nowhere in a result, however it is read
 */
function expectNoSecret(Pharaonic\Diff\DiffResult $result, string $secret): void
{
    expect(serialize($result))->not->toContain($secret)
        ->and(var_export($result->toArray(), true))->not->toContain($secret)
        ->and($result->toJson())->not->toContain($secret)
        ->and(print_r($result, true))->not->toContain($secret);
}

it('redacts a modified value', function () {
    $result = Diff::between(['password' => 'old-s3cret'], ['password' => 'new-s3cret'], DiffOptions::create()->redact('password'));
    $change = $result->changes()[0];

    expect(summary($result))->toBe(['modified password'])
        ->and($change->oldValue())->toBe(Change::REDACTED)
        ->and($change->newValue())->toBe('[REDACTED]')
        ->and($change->isRedacted())->toBeTrue()
        ->and($change->toArray()['redacted'])->toBeTrue();
    expectNoSecret($result, 's3cret');
});

it('still detects that a redacted value changed or stayed the same', function () {
    $options = DiffOptions::create()->redact('password');

    expect(Diff::between(['password' => 'a'], ['password' => 'a'], $options)->hasChanges())->toBeFalse()
        ->and(Diff::between(['password' => 'a'], ['password' => 'b'], $options)->wasModified('password'))->toBeTrue();
});

it('redacts added and removed values', function () {
    $options = DiffOptions::create()->redact(['*.token', 'secret']);
    $added = Diff::between(['api' => []], ['api' => ['token' => 'tok-s3cret']], $options);
    $removed = Diff::between(['secret' => 'gone-s3cret'], [], $options);

    expect($added->at('api.token')[0]->newValue())->toBe(Change::REDACTED)
        ->and($added->at('api.token')[0]->hasOldValue())->toBeFalse()
        ->and($removed->changes()[0]->oldValue())->toBe(Change::REDACTED)
        ->and($removed->changes()[0]->newValue())->toBeNull();
    expectNoSecret($added, 's3cret');
    expectNoSecret($removed, 's3cret');
});

it('redacts inside the values of parents', function () {
    $result = Diff::between(
        ['user' => ['name' => 'a', 'password' => 'old-s3cret']],
        ['user' => ['name' => 'b', 'password' => 'new-s3cret']],
        DiffOptions::create()->redact('user.password'),
    );
    $user = $result->at('user')[0];

    expect($user->oldValue())->toBe(['name' => 'a', 'password' => Change::REDACTED])
        ->and($user->newValue())->toBe(['name' => 'b', 'password' => Change::REDACTED])
        ->and($user->isRedacted())->toBeFalse();
    expectNoSecret($result, 's3cret');
});

it('redacts nested wildcard paths inside added subtrees and list items', function () {
    $after = ['users' => [['name' => 'a', 'secret' => 'one-s3cret'], ['name' => 'b', 'secret' => 'two-s3cret']]];
    $result = Diff::between([], $after, DiffOptions::create()->redact('users.*.secret'));

    expect($result->changes()[0]->newValue())->toBe([['name' => 'a', 'secret' => Change::REDACTED], ['name' => 'b', 'secret' => Change::REDACTED]]);
    expectNoSecret($result, 's3cret');
});

it('redacts whole containers', function () {
    $result = Diff::between(['credentials' => ['key' => 'a-s3cret']], ['credentials' => ['key' => 'b-s3cret']], DiffOptions::create()->redact('credentials'));

    expect(summary($result))->toBe(['modified credentials [aggregate]', 'modified credentials.key'])
        ->and($result->changes()[0]->oldValue())->toBe(Change::REDACTED)
        ->and($result->changes()[0]->isRedacted())->toBeTrue()
        ->and($result->changes()[1]->isRedacted())->toBeTrue();
    expectNoSecret($result, 's3cret');
});

it('redacts inside ignored branches that a stored value contains', function () {
    $result = Diff::between(
        ['user' => ['name' => 'a', 'meta' => ['token' => 'old-s3cret', 'note' => 'kept']]],
        ['user' => ['name' => 'b', 'meta' => ['token' => 'new-s3cret', 'note' => 'kept']]],
        DiffOptions::create()->ignore('user.meta')->redact('user.meta.token'),
    );

    expect(summary($result))->toBe(['modified user [aggregate]', 'modified user.name'])
        ->and($result->at('user')[0]->newValue())->toBe(['name' => 'b', 'meta' => ['token' => Change::REDACTED, 'note' => 'kept']]);
    expectNoSecret($result, 's3cret');
});

it('redacts inside normalized objects', function () {
    $result = Diff::between([], ['p' => new Payload(['token' => 'obj-s3cret'])], DiffOptions::create()->redact('p.token'));

    expect($result->changes()[0]->newValue())->toBe(['token' => Change::REDACTED]);
    expectNoSecret($result, 's3cret');
});

it('redacts moved records when either path is redacted', function () {
    $options = DiffOptions::create()->identify('', 'id')->detectMoves()->redact('0');
    $before = [['id' => 1, 'pin' => 'a-s3cret'], ['id' => 2, 'pin' => 'b-s3cret']];
    $after = [['id' => 2, 'pin' => 'b-s3cret'], ['id' => 1, 'pin' => 'a-s3cret']];
    $result = Diff::between($before, $after, $options);

    expect($result->moved()[0]->isRedacted())->toBeTrue()
        ->and($result->moved()[0]->oldValue())->toBe(Change::REDACTED)
        ->and($result->moved()[0]->newValue())->toBe(Change::REDACTED);
    expectNoSecret($result, 'a-s3cret');
});

it('redacts everything with the root path', function () {
    $result = Diff::between(['a' => 'x-s3cret'], ['a' => 'y-s3cret'], DiffOptions::create()->redact(''));

    expect($result->changes()[0]->isRedacted())->toBeTrue();
    expectNoSecret($result, 's3cret');
});
