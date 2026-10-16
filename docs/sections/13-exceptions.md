## Exceptions

Every exception thrown while comparing implements `Pharaonic\Diff\Exceptions\DiffException`, so you can catch them all at once. Each one also extends a standard SPL exception, so code that catches those keeps working.

```php
use Pharaonic\Diff\Exceptions\DiffException;

try {
    $result = Diff::between($before, $after, $options);
} catch (DiffException $e) {
    $logger->warning($e->getMessage());
}
```

### While Comparing

| Exception | Extends | Thrown when |
| --- | --- | --- |
| `UnsupportedValueException` | `InvalidArgumentException` | A value cannot be compared: a resource, a closure, a generator, an object without a rule, or an object that contains itself |
| `MaximumDepthExceededException` | `UnsupportedValueException` | A value nests deeper than `maxDepth()`, which includes arrays that reference themselves |
| `IdentityException` | `UnexpectedValueException` | A record of an identified list has no identity key, or shares its identity with another record |
| `InputLimitExceededException` | `LengthException` | A text has more lines than `maxLines()` or more bytes than `maxInputBytes()` |

The messages say where the problem is:

```text title="Messages" no-copy
An object of class ArrayObject cannot be compared without a normalizer at the root.
The items at indexes 0 and 1 of the list at path "users" have the same identity.
The item at index 0 of the list at path "users" has no identity key "id".
The old text has 25000 lines, more than the maximum of 20000.
```

### While Building Options

Invalid options throw a plain `InvalidArgumentException` as soon as you set them, before any comparison. These are programming mistakes, not data problems, so they do not implement `DiffException`:

```php
TextDiffOptions::create()->context(-1);
// InvalidArgumentException: The context must be at least 0, -1 given.

DiffOptions::create()->listStrategy('tags', 'sorted');
// InvalidArgumentException: Unknown list strategy "sorted"; use a ListStrategy constant.

DiffOptions::create()->normalize('App\Missing', fn ($value) => $value);
// InvalidArgumentException: The class or interface "App\Missing" does not exist.
```

Paths with an empty segment, such as `users..1`, also throw an `InvalidArgumentException`, both in options and in result queries. So does a `toUnified()` label with a line ending in it, which would break the patch.

### JSON

`DiffResult::toJson()` and `TextDiffResult::toJson()` always add `JSON_THROW_ON_ERROR`, so a value JSON cannot encode throws a `JsonException`.
