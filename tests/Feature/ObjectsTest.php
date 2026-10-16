<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\Exceptions\UnsupportedValueException;
use Pharaonic\Diff\ListStrategy;
use Pharaonic\Diff\Tests\Fixtures\Money;
use Pharaonic\Diff\Tests\Fixtures\Payload;

it('compares JsonSerializable objects by what they serialize to', function () {
    $result = Diff::between(['p' => new Payload(['a' => 1, 'b' => 1])], ['p' => new Payload(['b' => 1, 'a' => 2])]);

    expect(summary($result))->toBe(['modified p [aggregate]', 'modified p.a'])
        ->and($result->at('p')[0]->oldValue())->toBe(['a' => 1, 'b' => 1]);
});

it('compares stdClass objects as maps', function () {
    $before = json_decode('{"user": {"name": "Raggi", "tags": ["a"]}}');
    $after = json_decode('{"user": {"name": "Raggi", "tags": ["a", "b"]}}');

    expect(summary(Diff::between($before, $after)))->toBe(['modified user [aggregate]', 'modified user.tags [aggregate]', 'added user.tags.1']);
});

it('normalizes objects with a registered normalizer', function () {
    $options = DiffOptions::create()->normalize(Money::class, fn(Money $m) => ['cents' => $m->cents, 'currency' => $m->currency]);

    expect(summary(Diff::between(['price' => new Money(100, 'EGP')], ['price' => new Money(150, 'EGP')], $options)))
        ->toBe(['modified price [aggregate]', 'modified price.cents']);
});

it('uses the last normalizer registered for a matching class', function () {
    $options = DiffOptions::create()
        ->normalize(Money::class, fn(Money $m) => $m->cents)
        ->normalize(Money::class, fn(Money $m) => $m->currency);

    expect(Diff::between(new Money(1, 'EGP'), new Money(2, 'EGP'), $options)->hasChanges())->toBeFalse();
});

it('prefers a registered normalizer over jsonSerialize()', function () {
    $options = DiffOptions::create()->normalize(Payload::class, fn() => 'same');

    expect(Diff::between(new Payload(1), new Payload(2), $options)->hasChanges())->toBeFalse();
});

it('normalizes again when a normalizer returns another object', function () {
    expect(summary(Diff::between(new Payload(new Payload(['a' => 1])), new Payload(new Payload(['a' => 2])))))->toBe(['modified a']);
});

it('compares traversables only through an explicit normalizer', function () {
    $options = DiffOptions::create()->normalize(Traversable::class, fn(Traversable $t) => iterator_to_array($t));

    expect(summary(Diff::between(new ArrayIterator([1, 2]), new ArrayIterator([1, 3]), $options)))->toBe(['modified 1'])
        ->and(fn() => Diff::between(new ArrayIterator([1]), new ArrayIterator([1])))
        ->toThrow(UnsupportedValueException::class, 'An object of class ArrayIterator cannot be compared without a normalizer at the root.');
});

it('rejects generators', function () {
    $generator = (function () {
        yield 1;
    })();

    expect(fn() => Diff::between(['g' => $generator], ['g' => $generator]))
        ->toThrow(UnsupportedValueException::class, 'A generator cannot be compared at path "g".');
});

it('lets exceptions from a normalizer pass through', function () {
    $options = DiffOptions::create()->normalize(Money::class, function () {
        throw new RuntimeException('boom');
    });

    Diff::between(new Money(1, 'EGP'), new Money(1, 'EGP'), $options);
})->throws(RuntimeException::class, 'boom');

it('stops at an object that contains itself', function () {
    $before = new stdClass();
    $before->self = $before;
    $after = new stdClass();
    $after->self = $after;

    Diff::between($before, $after);
})->throws(UnsupportedValueException::class, 'The stdClass object contains itself at path "self".');

it('stops at a normalizer that returns its own object', function () {
    $options = DiffOptions::create()->normalize(Money::class, fn(Money $m) => $m);

    Diff::between(new Money(1, 'EGP'), new Money(2, 'EGP'), $options);
})->throws(UnsupportedValueException::class, 'The Pharaonic\Diff\Tests\Fixtures\Money object contains itself at the root.');

it('accepts the same object in separate branches', function () {
    $shared = new Payload(['a' => 1]);

    expect(Diff::between(['x' => $shared, 'y' => $shared], ['x' => $shared, 'y' => new Payload(['a' => 2])])->wasModified('y.a'))->toBeTrue();
});

it('stores normalized values in changes', function () {
    $result = Diff::between([], ['p' => new Payload(['when' => new Payload('now'), 'list' => [new Payload(1)]])]);

    expect($result->changes()[0]->newValue())->toBe(['when' => 'now', 'list' => [1]])
        ->and($result->toJson())->toBe('[{"type":"added","path":"p","old_path":null,"new_path":"p","old_exists":false,"old":null,"new_exists":true,"new":{"when":"now","list":[1]},"aggregate":false,"redacted":false}]');
});

it('rejects unsupported values inside added objects', function () {
    Diff::between([], ['p' => new Payload(['r' => STDIN])]);
})->throws(UnsupportedValueException::class, 'at path "p.r"');

it('treats dates at the same instant as equal whatever their time zone', function () {
    $utc = new DateTimeImmutable('2026-10-15 12:00:00.000000', new DateTimeZone('UTC'));
    $cairo = new DateTime('2026-10-15 15:00:00.000000', new DateTimeZone('Africa/Cairo'));

    expect(Diff::between(['at' => $utc], ['at' => $cairo])->hasChanges())->toBeFalse();
});

it('compares dates to the microsecond', function () {
    $result = Diff::between(
        ['at' => new DateTimeImmutable('2026-10-15 12:00:00.000001')],
        ['at' => new DateTimeImmutable('2026-10-15 12:00:00.000002')],
    );

    expect(summary($result))->toBe(['modified at'])
        ->and($result->changes()[0]->newValue())->toBeInstanceOf(DateTimeImmutable::class);
});

it('reports a date replaced by another type as modified', function () {
    expect(summary(Diff::between(['at' => new DateTimeImmutable('2026-10-15')], ['at' => '2026-10-15'])))->toBe(['modified at']);
});

it('aligns lists of dates by instant', function () {
    $before = [new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-02')];
    $after = [new DateTimeImmutable('2025-12-31'), new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-02')];

    expect(summary(Diff::between($before, $after)))->toBe(['added 0']);
});

it('lets a comparator decide alone, without traversing', function () {
    $options = DiffOptions::create()->compare(Money::class, fn(Money $a, Money $b): bool => $a->currency === $b->currency);

    expect(Diff::between(['p' => new Money(1, 'EGP')], ['p' => new Money(2, 'EGP')], $options)->hasChanges())->toBeFalse()
        ->and(summary(Diff::between(['p' => new Money(1, 'EGP')], ['p' => new Money(1, 'USD')], $options)))->toBe(['modified p']);
});

it('applies a comparator only when both values are instances of its class', function () {
    $options = DiffOptions::create()->compare(Money::class, fn() => true);

    expect(summary(Diff::between(['p' => new Money(1, 'EGP')], ['p' => 1], $options)))->toBe(['modified p']);
});

it('lets a comparator override the built-in date comparison', function () {
    $options = DiffOptions::create()->compare(DateTimeInterface::class, fn(DateTimeInterface $a, DateTimeInterface $b): bool => $a->format('Y-m-d') === $b->format('Y-m-d'));

    expect(Diff::between(new DateTimeImmutable('2026-10-15 01:00'), new DateTimeImmutable('2026-10-15 23:00'), $options)->hasChanges())->toBeFalse();
});

it('treats only a comparator that returns true as equal', function () {
    expect(Diff::between(new Money(1, 'EGP'), new Money(1, 'EGP'), DiffOptions::create()->compare(Money::class, fn() => 1))->hasChanges())->toBeTrue();
});

it('uses the last comparator registered for a matching class', function () {
    $options = DiffOptions::create()->compare(Money::class, fn() => false)->compare(Money::class, fn() => true);

    expect(Diff::between(new Money(1, 'EGP'), new Money(2, 'EGP'), $options)->hasChanges())->toBeFalse();
});

it('matches identities inside normalized records', function () {
    $before = [new Payload(['id' => 1, 'v' => 1]), new Payload(['id' => 2, 'v' => 1])];
    $after = [new Payload(['id' => 2, 'v' => 1]), new Payload(['id' => 1, 'v' => 2])];

    expect(summary(Diff::between($before, $after, DiffOptions::create()->identify('', 'id'))))->toBe(['modified 1 [aggregate]', 'modified 1.v']);
});

it('aligns lists of objects a comparator finds equal', function () {
    $options = DiffOptions::create()->compare(Money::class, fn(Money $a, Money $b) => $a->cents === $b->cents);

    $result = Diff::between(
        ['prices' => [new Money(100, 'EGP'), new Money(200, 'EGP')]],
        ['prices' => [new Money(50, 'EGP'), new Money(100, 'USD'), new Money(200, 'USD')]],
        $options,
    );

    expect(summary($result))->toBe(['modified prices [aggregate]', 'added prices.0']);
});

it('matches objects a comparator finds equal in unordered lists', function () {
    $options = DiffOptions::create()
        ->compare(Money::class, fn(Money $a, Money $b) => $a->cents === $b->cents)
        ->listStrategy('prices', ListStrategy::UNORDERED);

    $result = Diff::between(
        ['prices' => [new Money(100, 'EGP'), new Money(200, 'EGP')]],
        ['prices' => [new Money(200, 'EGP'), new Money(100, 'EGP')]],
        $options,
    );

    expect($result->hasChanges())->toBeFalse();
});

it('matches identities that a comparator finds equal', function () {
    $options = DiffOptions::create()
        ->compare(Money::class, fn(Money $a, Money $b) => $a->cents === $b->cents)
        ->identify('rows', 'price');

    $result = Diff::between(
        ['rows' => [['price' => new Money(100, 'EGP'), 'qty' => 1]]],
        ['rows' => [['price' => new Money(100, 'EGP'), 'qty' => 2]]],
        $options,
    );

    expect(summary($result))->toBe(['modified rows [aggregate]', 'modified rows.0 [aggregate]', 'modified rows.0.qty']);
});

it('applies a date comparator when aligning lists', function () {
    $options = DiffOptions::create()
        ->compare(DateTimeInterface::class, fn(DateTimeInterface $a, DateTimeInterface $b) => $a->format('Y-m-d') === $b->format('Y-m-d'))
        ->listStrategy('days', ListStrategy::UNORDERED);

    $result = Diff::between(
        ['days' => [new DateTime('2026-01-01 10:00'), new DateTime('2026-01-02 10:00')]],
        ['days' => [new DateTime('2026-01-02 11:00'), new DateTime('2026-01-01 11:00')]],
        $options,
    );

    expect($result->hasChanges())->toBeFalse();
});

it('lets a normalizer override the built-in date comparison', function () {
    $options = DiffOptions::create()->normalize(DateTimeInterface::class, fn(DateTimeInterface $date) => $date->format('Y-m-d'));

    $result = Diff::between(['at' => new DateTime('2026-01-01 10:00')], ['at' => new DateTime('2026-01-02 11:00')], $options);

    expect(Diff::between(new DateTime('2026-01-01 10:00'), new DateTime('2026-01-01 11:00'), $options)->hasChanges())->toBeFalse()
        ->and($result->at('at')[0]->newValue())->toBe('2026-01-02');
});
