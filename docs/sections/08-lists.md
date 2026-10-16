## Lists & Identity

How a list should be compared depends on what it holds. Choose a strategy for each path with `listStrategy()`, or compare records by key with `identify()`. When several rules match the same path, the one added last wins.

### Sequence (the Default)

The diff aligns both lists the way `git diff` aligns lines, so inserting an item does not shift every later one:

```php
Diff::between(['a', 'b', 'c'], ['x', 'a', 'b', 'c']);
// added 0
```

A replaced item is reported as `modified` when it can be paired with the item it replaced. Scalars pair when as many items were removed as added. Records pair when they share at least half of their keys and at least one value, and are then compared field by field:

```php
Diff::between(['a', 'b', 'c'], ['a', 'x', 'c']);
// modified 1   ("b" -> "x")

Diff::between(
    [['sku' => 'A', 'qty' => 1], ['sku' => 'B', 'qty' => 1]],
    [['sku' => 'A', 'qty' => 2], ['sku' => 'B', 'qty' => 1]],
);
// modified 0       (aggregate)
// modified 0.qty   (1 -> 2)
```

Items that cannot be paired are reported as one `removed` and one `added` change.

:::warning Selected Paths Inside Replaced Records
A record reported as removed and added is a container above any path you select with `only()`, so `only()` hides it. Compare such lists by identity, below, so that each record is matched by its key.
:::

### Position

`ListStrategy::POSITION` compares items index by index. Use it when the position is the item's meaning, such as coordinates or the days of a week:

```php
use Pharaonic\Diff\ListStrategy;

$options = DiffOptions::create()->listStrategy('point', ListStrategy::POSITION);

Diff::between(['point' => [1, 2]], ['point' => [2, 2]], $options);
// modified point     (aggregate)
// modified point.0   (1 -> 2)
```

### Unordered

`ListStrategy::UNORDERED` ignores the order but keeps counts, so the list behaves like a multiset. Use it for tags, roles or permissions:

```php
$options = DiffOptions::create()->listStrategy('tags', ListStrategy::UNORDERED);

Diff::between(['tags' => ['php', 'laravel', 'php']], ['tags' => ['laravel', 'php', 'go']], $options);
// modified tags      (aggregate)
// removed tags.2     ("php", the second one)
// added tags.2       ("go")
```

A reordered list with the same items reports nothing.

### Identity

`identify()` compares a list of records by one key, or by several keys together. Records with the same identity are compared with each other wherever they are, and reordering them reports nothing:

```php
$before = ['users' => [['id' => 1, 'name' => 'Ahmed'], ['id' => 2, 'name' => 'Sara'], ['id' => 3, 'name' => 'Omar']]];
$after = ['users' => [['id' => 2, 'name' => 'Sara'], ['id' => 1, 'name' => 'Ahmed M.'], ['id' => 4, 'name' => 'Mona']]];

$result = Diff::between($before, $after, DiffOptions::create()->identify('users', 'id'));

// modified users           (aggregate)
// modified users.1         (aggregate, was users.0)
// modified users.1.name    ("Ahmed" -> "Ahmed M.", was users.0.name)
// added users.2            (id 4)
// removed users.2          (id 3)
```

The record with `id` 1 was at index 0 and is now at index 1. This is where `oldPath()` and `newPath()` differ: `oldPath()` is `users.0` and `newPath()` is `users.1`. The result's methods find it by either one:

```php
$result->wasModified('users.1.name');  // true: the canonical (new) path
$result->hasChanged('users.0');        // true: through the old path
```

For a composite identity, pass the keys as a list. Records are then identified by all of them together:

```php
DiffOptions::create()->identify('lines', ['order_id', 'sku']);
```

Identity values are compared strictly by type, so `1` and `'1'` are different identities. Each record must have every identity key, and no two records of the same list may share an identity; otherwise an `IdentityException` is thrown.

### Detecting Moves

By default, records that only changed order report nothing. Add `detectMoves()` to report the records whose order changed relative to the others:

```php
$options = DiffOptions::create()->identify('users', 'id')->detectMoves();

Diff::between(
    ['users' => [['id' => 1], ['id' => 2], ['id' => 3]]],
    ['users' => [['id' => 3], ['id' => 1], ['id' => 2]]],
    $options,
);
// modified users     (aggregate)
// moved users.0      (id 3, was users.2)
```

Only the record with `id` 3 moved: records 1 and 2 kept their order relative to each other, even though their indexes changed. The diff keeps the largest group of records in their original order and reports the rest as moved, so a single move never produces a cascade of changes.

A moved record that also changed has a `moved` change and its own `modified` changes. `wasMoved()` finds it by its old or its new path.

:::info
`detectMoves()` applies to lists compared by identity. Under the other strategies, a changed order is either an insertion and a removal (sequence), a modification (position) or nothing (unordered).
:::

### Root Lists and Nested Lists

Use the empty path for a list at the root, and wildcards for lists inside lists:

```php
DiffOptions::create()
    ->identify('', 'id')
    ->identify('*.items', 'sku')
    ->listStrategy('*.tags', ListStrategy::UNORDERED);
```

:::tip
A rule for a nested list applies only when the records around it are compared with each other. If the outer list is a sequence and the outer records share no value, they are reported as removed and added, and the inner list is never compared. Identify the outer list too, as above.
:::
