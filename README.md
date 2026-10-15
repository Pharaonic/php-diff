# PHP Diff

Semantic structured diffs and GitHub-style text diffs for PHP, with no runtime dependencies.

## Installation

```bash
composer require pharaonic/php-diff
```

## Usage

```php
use Pharaonic\Diff\Diff;

$result = Diff::between(
    ['user' => ['name' => 'Raggi', 'age' => 32]],
    ['user' => ['name' => 'Raggi', 'age' => 33]],
);

$result->hasChanged('user');     // true
$result->wasModified('user.age'); // true

echo Diff::text("alpha\nbeta\n", "alpha\nbeta changed\n")->toUnified();
```

## Documentation

Full documentation: https://pharaonic.dev/packages/php/diff

## Support & Security

See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
