## Ignoring, Selecting & Redacting

Three options control which values the result reports and what it stores. Each takes one path or a list of paths, with `*` wildcards. Calling one again adds paths; it never replaces the earlier ones.

### Ignore

`ignore()` leaves out the values at these paths and everything below them. They are not compared or even traversed:

```php
$options = DiffOptions::create()->ignore(['updated_at', 'items.*.cached_at']);

Diff::between(
    ['name' => 'a', 'updated_at' => 1],
    ['name' => 'a', 'updated_at' => 2],
    $options,
)->hasChanges(); // false
```

### Only

`only()` reports only the changes at these paths and below them:

```php
$options = DiffOptions::create()->only('items.*.price');

$result = Diff::between(
    ['items' => [['sku' => 'A', 'price' => 1, 'name' => 'Pen']]],
    ['items' => [['sku' => 'A', 'price' => 5, 'name' => 'Blue pen']]],
    $options,
);

$result->wasModified('items.0.price'); // true
$result->hasChanged('items.0.name');   // false
$result->wasModified('items');         // false: containers above the selected paths are never reported
```

Branches that cannot lead to the selected paths are skipped. The containers above them are traversed but never reported, not even when a whole container was added, removed or changed type. `ignore()` still wins over `only()`.

### Redact

`redact()` compares the values at these paths as usual, but stores `Change::REDACTED` (`'[REDACTED]'`) in the result instead of them:

```php
$options = DiffOptions::create()->redact('user.password');

$result = Diff::between(
    ['user' => ['name' => 'a', 'password' => 'old-secret']],
    ['user' => ['name' => 'b', 'password' => 'new-secret']],
    $options,
);

$result->wasModified('user.password');                // true: the change is still detected
$result->at('user.password')[0]->newValue();         // '[REDACTED]'
$result->at('user.password')[0]->isRedacted();       // true
$result->at('user')[0]->newValue()['password'];      // '[REDACTED]' inside the parent too
```

The secret never reaches the result: it is replaced inside the values of every parent, and inside added or removed containers. In those, the container itself has `isRedacted()` set to `false`, because only part of it was replaced.

:::warning Redaction Covers Values, Not Keys
Redaction replaces values. A key name that is itself sensitive still appears in paths. Keep secrets out of keys, or `ignore()` those branches instead.
:::

### Combining Them

```php
$options = DiffOptions::create()
    ->only(['profile', 'settings'])
    ->ignore('profile.updated_at')
    ->redact(['profile.api_token', 'settings.*.secret']);
```

The order of the calls does not matter: a path is ignored when any `ignore()` pattern matches it, and redacted when any `redact()` pattern matches it.
