## Quick Start

Everything starts from the `Diff` class. It has two methods, one for each kind of comparison.

### Compare Two Values

`Diff::between()` compares two values and returns a `DiffResult`:

```php
use Pharaonic\Diff\Diff;

$before = ['name' => 'Raggi', 'email' => 'old@example.com', 'roles' => ['admin'], 'nickname' => 'R'];
$after = ['name' => 'Raggi', 'email' => 'new@example.com', 'roles' => ['admin', 'editor'], 'age' => 33];

$result = Diff::between($before, $after);

$result->hasChanges();           // true
$result->wasModified('email');   // true
$result->wasAdded('roles.1');    // true
$result->wasRemoved('nickname'); // true
$result->hasChanged('name');     // false
```

Each change is a `Change` with a type, a path, and the old and new values:

```php
foreach ($result as $change) {
    echo $change->type(), ' ', $change->path()->toDot(), PHP_EOL;
}

// modified email
// modified roles
// added roles.1
// added age
// removed nickname
```

`roles` is reported as modified because something inside it changed. Such a change is an *aggregate*: `$change->isAggregate()` returns `true`.

### Compare Two Texts

`Diff::text()` compares two texts line by line and returns a `TextDiffResult`:

```php
$result = Diff::text("alpha\nbeta\ngamma\n", "alpha\nbeta changed\ngamma\ndelta");

$result->stats(); // ['added' => 2, 'removed' => 1, 'modified' => 1, 'unchanged' => 2]

echo $result->toUnified('a/notes.txt', 'b/notes.txt');
```

```diff title="Output" no-copy
--- a/notes.txt
+++ b/notes.txt
@@ -1,3 +1,4 @@
 alpha
-beta
+beta changed
 gamma
+delta
\ No newline at end of file
```

`echo $result->toHtml()` renders the same hunks as a GitHub-style HTML table instead.

### Add Options

Both methods take optional settings. `DiffOptions` and `TextDiffOptions` are immutable: each method returns new options.

```php
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\TextDiffOptions;

$options = DiffOptions::create()
    ->ignore('updated_at')
    ->redact('password')
    ->identify('addresses', 'id');

Diff::between($before, $after, $options);

Diff::text($old, $new, TextDiffOptions::create()->context(5)->ignoreTrailingWhitespace());
```
