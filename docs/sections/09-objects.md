## Objects

Objects are compared in one of two ways. A **comparator** decides whether two objects are equal. A **normalizer** turns an object into a scalar, `null` or an array, which is then compared like any other value.

### Built-in Rules

| Object | Compared |
| --- | --- |
| `DateTimeInterface` | By instant, to the microsecond, whatever the time zones |
| `JsonSerializable` | As the value `jsonSerialize()` returns |
| `stdClass` | As a map of its public properties |

```php
Diff::between(
    ['at' => new DateTimeImmutable('2026-01-01 12:00 UTC')],
    ['at' => new DateTime('2026-01-01 14:00 +02:00')],
)->hasChanges(); // false: the same instant

Diff::between((object) ['a' => 1, 'b' => (object) ['c' => 1]], (object) ['a' => 1, 'b' => (object) ['c' => 2]]);
// modified b     (aggregate)
// modified b.c
```

Any other object throws an `UnsupportedValueException` until you register a rule for its class:

```text title="Message" no-copy no-line-numbers
An object of class ArrayObject cannot be compared without a normalizer at the root.
```

### Normalizers

`normalize()` registers a callback that turns instances of a class, or of any class implementing an interface, into a comparable value. The diff then looks inside the result, so you get changes at nested paths:

```php
$options = DiffOptions::create()
    ->normalize(Money::class, fn (Money $money) => [
        'amount' => $money->amount,
        'currency' => $money->currency,
    ]);

Diff::between(['price' => new Money(100, 'EGP')], ['price' => new Money(150, 'EGP')], $options);
// modified price           (aggregate)
// modified price.amount    (100 -> 150)
```

A normalizer may return another object; that object is normalized in turn. The result stores the normalized values, not the objects. Objects handled by a comparator, such as dates, are stored as they are.

### Comparators

`compare()` registers a callback that decides whether two instances are equal. Only `true` means equal. Use it for objects whose inside you do not want to report, or that define their own equality:

```php
$options = DiffOptions::create()
    ->compare(Money::class, fn (Money $a, Money $b) => $a->amount === $b->amount && $a->currency === $b->currency);

Diff::between(['price' => new Money(100, 'EGP')], ['price' => new Money(150, 'EGP')], $options);
// modified price   (one change; the objects are stored as they are)
```

A pair that is not equal is reported as one `modified` change and is never compared further. Both objects must be instances of the comparator's class; otherwise it does not apply.

### Which Rule Applies

For each object, the diff uses the first rule that matches:

1. A comparator you registered, the last one added first.
2. The built-in `DateTimeInterface` comparator.
3. A normalizer you registered, the last one added first.
4. `jsonSerialize()` for `JsonSerializable`.
5. Public properties for `stdClass`.

So a comparator for `DateTimeInterface` replaces the built-in date rule, and a normalizer for a `JsonSerializable` class replaces `jsonSerialize()`. A normalizer for `DateTimeInterface` has no effect, because the built-in comparator handles dates first. To compare dates by day, register a comparator:

```php
DiffOptions::create()->compare(
    DateTimeInterface::class,
    fn (DateTimeInterface $a, DateTimeInterface $b) => $a->format('Y-m-d') === $b->format('Y-m-d'),
);
```

:::warning Objects That Contain Themselves
An object that contains itself, directly or through its children, throws an `UnsupportedValueException` instead of recursing forever. The same applies to a normalizer chain that comes back to an object it already visited.
:::

### Laravel Models and Collections

Diff has no Laravel dependency. Compare `$model->toArray()` or `$collection->all()`, or register a normalizer:

```php
use Illuminate\Database\Eloquent\Model;

$options = DiffOptions::create()
    ->normalize(Model::class, fn (Model $model) => $model->attributesToArray())
    ->ignore(['updated_at', 'created_at']);

Diff::between($original, $post->fresh(), $options);
```

Models implement `JsonSerializable`, so without a normalizer they are compared through `jsonSerialize()`, which includes loaded relations and appended attributes.
