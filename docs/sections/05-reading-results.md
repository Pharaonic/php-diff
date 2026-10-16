## Reading Results

`Diff::between()` returns a `DiffResult`. You can ask it questions, list its changes, or turn it into an array or JSON.

### Asking Questions

Every method that takes a path accepts dot notation or a `Path` object:

```php
$result = Diff::between(
    ['user' => ['email' => 'a', 'nickname' => 'R'], 'username' => 'x'],
    ['user' => ['email' => 'b', 'age' => 1], 'username' => 'x'],
);

$result->hasChanges();                // true
$result->hasChanged('user');          // true: something at or below user changed
$result->hasChanged('username');      // false
$result->hasChanged('use');           // false: paths match whole segments
$result->wasModified('user.email');   // true
$result->wasModified('user');         // true: aggregate modifications count
$result->wasAdded('user.age');        // true
$result->wasRemoved('user.nickname'); // true
$result->wasMoved('user.email');      // false
```

| Method | True when |
| --- | --- |
| `hasChanges()` | Anything changed |
| `hasChanged($path)` | Something changed at this path or below it, at its old or its new path |
| `wasAdded($path)` | A value was added at exactly this path |
| `wasRemoved($path)` | A value was removed from exactly this path |
| `wasModified($path)` | The value at exactly this path was modified, directly or through its descendants |
| `wasMoved($path)` | A value moved from or to exactly this path |

### Listing Changes

```php
$result->changes();        // every Change, parents before their descendants
$result->added();          // only added changes
$result->removed();
$result->modified();       // direct and aggregate modifications
$result->moved();
$result->at('user');       // changes whose path is exactly user
$result->under('user');    // changes at user or below it
count($result);            // 4
$result->stats();          // ['total' => 4, 'added' => 1, 'removed' => 1, 'modified' => 2, 'moved' => 0]
```

A `DiffResult` is also iterable: `foreach ($result as $change)`. The arrays it returns are copies, so changing them never changes the result.

### The Change Object

| Method | Returns |
| --- | --- |
| `type()` | One of the `ChangeType` constants: `added`, `removed`, `modified` or `moved` |
| `path()` | The canonical `Path`: the old path for a removed value, the new path otherwise |
| `oldPath()` | Where the value was, or `null` when it did not exist before |
| `newPath()` | Where the value is, or `null` when it no longer exists |
| `hasOldValue()` / `hasNewValue()` | Whether the value existed before / exists after |
| `oldValue()` / `newValue()` | The values, or `null` when they did not exist |
| `isAggregate()` | Whether this is a container reported because something inside it changed |
| `isRedacted()` | Whether the values were replaced with `Change::REDACTED` as a whole |
| `toArray()` | The change as an array, shown below |

When a change has both an old and a new path, they are the same path, unless the value is found at another index after the change.

### Arrays and JSON

`toArray()`, `jsonSerialize()` and `toJson()` give you a list of changes in this shape:

```php
Diff::between(['a' => 1], ['a' => 2])->toArray();

// [[
//     'type' => 'modified',
//     'path' => 'a',
//     'old_path' => 'a',
//     'new_path' => 'a',
//     'old_exists' => true,
//     'old' => 1,
//     'new_exists' => true,
//     'new' => 2,
//     'aggregate' => false,
//     'redacted' => false,
// ]]
```

`toJson()` takes the usual `json_encode()` flags and always adds `JSON_THROW_ON_ERROR`, so a value JSON cannot encode, such as invalid UTF-8 or `NAN`, throws a `JsonException`:

```php
$result->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$result->toJson(JSON_INVALID_UTF8_SUBSTITUTE); // replaces invalid UTF-8 instead of throwing
```
