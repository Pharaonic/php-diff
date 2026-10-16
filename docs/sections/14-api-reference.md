## API Reference

All classes live in the `Pharaonic\Diff` namespace, and the exceptions in `Pharaonic\Diff\Exceptions`. Options are immutable: every method that configures them returns new options. Results and their parts are read-only.

### Diff

| Method | Description | Returns |
| --- | --- | --- |
| `between(mixed $before, mixed $after, ?DiffOptions $options = null)` | Compares two structured values | `DiffResult` |
| `text(string $before, string $after, ?TextDiffOptions $options = null)` | Compares two texts line by line | `TextDiffResult` |

### DiffOptions

| Method | Description |
| --- | --- |
| `create()` | New default options |
| `ignore(string\|array $paths)` | Leaves out these paths and everything below them |
| `only(string\|array $paths)` | Reports only these paths and everything below them |
| `redact(string\|array $paths)` | Stores `Change::REDACTED` instead of these values |
| `maxDepth(int $depth)` | How deep values may nest (64 by default) |
| `floatTolerance(float $tolerance)` | Treats floats within this absolute amount as equal (0 by default) |
| `listStrategy(string $path, string $strategy)` | Compares the lists at a path with a `ListStrategy` constant |
| `identify(string $path, int\|string\|array $keys)` | Compares the lists at a path as records identified by one or more keys |
| `detectMoves()` | Reports identified records that changed their relative order as moved |
| `normalize(string $class, callable $normalizer)` | Turns instances of a class or interface into a comparable value: `fn (object $value): mixed` |
| `compare(string $class, callable $equals)` | Decides whether two instances are equal: `fn (object $a, object $b): bool` |

### DiffResult

Implements `Countable`, `IteratorAggregate` and `JsonSerializable`. Methods that take a path accept dot notation or a `Path`.

| Method | Description | Returns |
| --- | --- | --- |
| `hasChanges()` | Whether anything changed | `bool` |
| `hasChanged(string\|Path $path)` | Whether anything at or below the path changed, at its old or new path | `bool` |
| `wasAdded(string\|Path $path)` | Whether a value was added at exactly this path | `bool` |
| `wasRemoved(string\|Path $path)` | Whether a value was removed from exactly this path | `bool` |
| `wasModified(string\|Path $path)` | Whether the value at exactly this path was modified, directly or through its descendants | `bool` |
| `wasMoved(string\|Path $path)` | Whether a value moved from or to exactly this path | `bool` |
| `changes()` | Every change, parents before their descendants | `list<Change>` |
| `added()` / `removed()` / `modified()` / `moved()` | The changes of one type | `list<Change>` |
| `at(string\|Path $path)` | The changes whose canonical path is exactly this path | `list<Change>` |
| `under(string\|Path $path)` | The changes at or below this path, at their old or new path | `list<Change>` |
| `count()` | The number of changes | `int` |
| `stats()` | `total`, `added`, `removed`, `modified` and `moved` counts | `array` |
| `toArray()` / `jsonSerialize()` | Every change as an array | `array` |
| `toJson(int $flags = 0)` | The changes as JSON, always with `JSON_THROW_ON_ERROR` | `string` |

### Change

| Method | Description | Returns |
| --- | --- | --- |
| `type()` | A `ChangeType` constant | `string` |
| `path()` | The old path for a removed value, the new path otherwise | `Path` |
| `oldPath()` / `newPath()` | Where the value was / is, or `null` | `?Path` |
| `hasOldValue()` / `hasNewValue()` | Whether the value existed before / exists after | `bool` |
| `oldValue()` / `newValue()` | The value before / after, or `null` | `mixed` |
| `isAggregate()` | Whether it is a container reported because of changes inside it | `bool` |
| `isRedacted()` | Whether the values were replaced with `Change::REDACTED` as a whole | `bool` |
| `toArray()` | `type`, `path`, `old_path`, `new_path`, `old_exists`, `old`, `new_exists`, `new`, `aggregate`, `redacted` | `array` |

`Change::REDACTED` is `'[REDACTED]'`.

### Path

| Method | Description | Returns |
| --- | --- | --- |
| `fromSegments(array $segments)` | A path from keys and indexes | `Path` |
| `fromDot(string $path)` | Parses dot notation; `''` is the root | `Path` |
| `segments()` | The keys and indexes | `list<int\|string>` |
| `isRoot()` | Whether it is the empty path | `bool` |
| `toDot()` | Escaped dot notation | `string` |
| `toJsonPointer()` | RFC 6901 JSON Pointer | `string` |

### TextDiffOptions

| Method | Description |
| --- | --- |
| `create()` | New default options |
| `context(int $lines)` | Unchanged lines around each change in a hunk (3 by default) |
| `pairModifiedLines(bool $pair = true)` | Whether alike removed and added lines are paired (on by default) |
| `similarityThreshold(float $threshold)` | How similar, from 0 to 1, paired lines must be (0.55 by default) |
| `inline(string $mode)` | An `InlineMode` constant (`word` by default) |
| `ignoreWhitespaceChanges()` | Lines that differ only in the amount of whitespace are equal |
| `ignoreTrailingWhitespace()` | Lines that differ only in trailing whitespace are equal |
| `maxLines(int $lines)` | The most lines either text may have (20,000 by default) |
| `maxInputBytes(int $bytes)` | The most bytes either text may have (4 MiB by default) |
| `maxInlineLength(int $bytes)` | The longest line that gets inline highlighting (1,000 bytes by default) |

### TextDiffResult

Implements `JsonSerializable`.

| Method | Description | Returns |
| --- | --- | --- |
| `hasChanges()` | Whether the texts differ | `bool` |
| `lines()` | Every line in reading order | `list<DiffLine>` |
| `hunks()` | The changes with their context | `list<Hunk>` |
| `addedLines()` / `removedLines()` | The lines of one type | `list<DiffLine>` |
| `modifiedPairs()` | Alike removed and added lines | `list<ModifiedLinePair>` |
| `stats()` | `added`, `removed`, `modified` and `unchanged` line counts | `array` |
| `toUnified(string $oldLabel = 'before', string $newLabel = 'after')` | A unified diff that `git apply` and `patch` accept | `string` |
| `toHtml()` | A GitHub-style HTML table | `string` |
| `toArray()` / `jsonSerialize()` | Stats, final newlines, hunks and modified pairs | `array` |
| `toJson(int $flags = 0)` | `toArray()` as JSON, always with `JSON_THROW_ON_ERROR` | `string` |

### DiffLine, Hunk, ModifiedLinePair and InlineSegment

| Class | Methods |
| --- | --- |
| `DiffLine` | `type()`, `content()`, `oldLineNumber()`, `newLineNumber()`, `inlineSegments()`, `toArray()` |
| `Hunk` | `oldStart()`, `oldLength()`, `newStart()`, `newLength()`, `lines()`, `toArray()` |
| `ModifiedLinePair` | `removed()`, `added()`, `toArray()` |
| `InlineSegment` | `type()`, `text()`, `toArray()` |

### Constants

| Class | Constants |
| --- | --- |
| `ChangeType` | `ADDED`, `REMOVED`, `MODIFIED`, `MOVED`, `UNCHANGED` |
| `ListStrategy` | `SEQUENCE` (default), `POSITION`, `UNORDERED` |
| `InlineMode` | `NONE`, `WORD` (default), `CHARACTER` |

Their values are the lowercase names, such as `'added'` and `'unordered'`, so they are safe to store.

### Exceptions

| Class | Extends |
| --- | --- |
| `DiffException` | Interface implemented by all of them |
| `UnsupportedValueException` | `InvalidArgumentException` |
| `MaximumDepthExceededException` | `UnsupportedValueException` |
| `IdentityException` | `UnexpectedValueException` |
| `InputLimitExceededException` | `LengthException` |

Classes outside this list, and methods marked `@internal`, are not part of the public API and may change in any release.
