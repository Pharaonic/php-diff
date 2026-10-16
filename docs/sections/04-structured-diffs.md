## How Values Are Compared

`Diff::between()` accepts scalars, `null`, arrays and objects at any depth. It follows the rules below, and the same input always gives the same result, in the same order.

### Scalars Are Compared Strictly

Two scalars are equal only when they have the same type and value. There is no loose comparison:

```php
Diff::between(['qty' => '1'], ['qty' => 1])->wasModified('qty');   // true
Diff::between(['total' => 1.0], ['total' => 1])->hasChanges();     // true
Diff::between(['flag' => 0], ['flag' => false])->hasChanges();     // true
```

Floats have two exceptions, so that a value is always equal to itself: `NAN` equals `NAN`, and `-0.0` equals `0.0`. To accept small rounding differences, set an absolute tolerance:

```php
$options = DiffOptions::create()->floatTolerance(0.001);

Diff::between(['total' => 10.0], ['total' => 10.0000001], $options)->hasChanges(); // false
```

### Maps and Lists

An array whose keys are `0, 1, 2…` in order is a **list**. Any other array, including a sparse one such as `[0 => 'a', 2 => 'b']`, is a **map**.

- **Maps** are compared key by key, and the order of their keys is ignored.
- **Lists** are aligned like lines of text by default, so an insertion or a removal does not shift every later item. Other strategies can be chosen per path.

An empty array matches the kind of the array on the other side, so `[]` becoming `['k' => 1]` reports one added key, not a type change.

### A Missing Key Is Not Null

A key that does not exist is different from a key whose value is `null`:

```php
Diff::between(['middle_name' => null], [])->wasRemoved('middle_name'); // true
```

`Change::hasOldValue()` and `hasNewValue()` tell the two cases apart, because `oldValue()` and `newValue()` return `null` for both.

### Type Changes

When a value changes type, for example from an array to a string, the diff reports one direct `modified` change at that path and does not look inside the old or new value:

```php
foreach (Diff::between(['a' => [1]], ['a' => '1']) as $change) {
    echo $change->type(), ' ', $change->path()->toDot(); // modified a
}
```

### Aggregate Changes

When something inside an array changes, every array above it is also reported as `modified`, with its full old and new values, and `isAggregate()` returns `true`. Parents always come before their descendants:

```php
$result = Diff::between(['user' => ['email' => 'a']], ['user' => ['email' => 'b']]);

// modified user        (aggregate)
// modified user.email
```

The root itself is never reported as an aggregate, so `Diff::between([], ['a' => 1])` returns a single `added a` change. Comparing two different scalars reports one change at the root, whose path is empty.

:::tip
To list only the values that really changed, skip the aggregates: `array_filter($result->modified(), fn ($change) => ! $change->isAggregate())`.
:::

### Supported Values

| Value | How it is compared |
| --- | --- |
| `null`, `bool`, `int`, `float`, `string` | Strictly, with the float rules above |
| Arrays | As maps or lists, recursively |
| `DateTimeInterface` | Equal when they are the same instant, to the microsecond, whatever their time zones |
| `JsonSerializable` | Through `jsonSerialize()` |
| `stdClass` | As a map of its public properties |
| Other objects | Through a comparator or normalizer you register |
| Resources, closures, generators | Not supported: an `UnsupportedValueException` is thrown |

Values may nest up to 64 levels by default.
