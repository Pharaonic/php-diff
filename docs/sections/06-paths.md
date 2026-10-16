## Paths & Wildcards

A path is the location of a value inside the compared data: a list of keys and list indexes. You write it in dot notation, and the package gives it back as a `Path` object.

### Dot Notation

| Path | Points to |
| --- | --- |
| `''` (empty string) | The root value |
| `user.email` | `$data['user']['email']` |
| `users.4.email` | `$data['users'][4]['email']` |
| `tags.a\.b` | `$data['tags']['a.b']`, a key with a dot in it |
| `files.\*` | `$data['files']['*']`, a key that is a star |
| `items.*.price` | `price` in every item of `items` (options only) |

Use `\.`, `\*` and `\\` for a literal dot, star or backslash inside a key. In PHP single-quoted strings, write them as `'tags.a\.b'` and `'path\\\\to'`.

A path with an empty segment, such as `users..1`, is rejected with an `InvalidArgumentException`.

### Wildcards

In options, `*` matches any one key or list index:

```php
DiffOptions::create()
    ->ignore('items.*.cached_at')
    ->redact('accounts.*.token')
    ->identify('orders.*.lines', 'sku');
```

Result queries such as `hasChanged()` and `at()` take exact paths, without wildcards.

### Keys and Indexes

Segments are stored the way PHP stores array keys, so the string `"4"` and the integer `4` are the same segment:

```php
Path::fromSegments(['users', '4'])->toDot() === Path::fromSegments(['users', 4])->toDot(); // true
```

### The Path Class

```php
use Pharaonic\Diff\Path;

$path = Path::fromDot('users.4.email');

$path->segments();      // ['users', 4, 'email']
$path->toDot();         // 'users.4.email'
$path->toJsonPointer(); // '/users/4/email'
$path->isRoot();        // false

Path::fromSegments(['tags', 'a.b'])->toDot(); // 'tags.a\.b'
```

The `path`, `old_path` and `new_path` keys of `toArray()` use the same escaped dot notation, so `Path::fromDot()` reads them back. Use `toJsonPointer()` when you need RFC 6901 pointers instead.
