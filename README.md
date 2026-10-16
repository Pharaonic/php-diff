<p align="center"><a href="https://pharaonic.dev/packages/php/diff" target="_blank"><img src="https://raw.githubusercontent.com/Pharaonic/php-diff/develop/docs/cover.webp"></a></p>

<p align="center">
  <a href="https://php.net" target="_blank"><img src="https://img.shields.io/static/v1?label=PHP&message=8.0&color=blue&style=flat-square" alt="PHP Version : 8.0"></a>
  <img src="https://img.shields.io/static/v1?label=License&message=MIT&color=brightgreen&style=flat-square" alt="License">
  <img src="https://github.com/Pharaonic/php-diff/actions/workflows/build.yml/badge.svg" alt="Tests">
  <br>
  <a href="https://packagist.org/packages/pharaonic/php-diff" target="_blank"><img src="https://img.shields.io/static/v1?label=Packagist&message=pharaonic/php-diff&color=blue&logo=packagist&logoColor=white" alt="Source"></a>
  <a href="https://packagist.org/packages/pharaonic/php-diff" target="_blank"><img src="https://poser.pugx.org/pharaonic/php-diff/v" alt="Packagist Version"></a>
  <a href="https://packagist.org/packages/pharaonic/php-diff" target="_blank"><img src="https://poser.pugx.org/pharaonic/php-diff/downloads" alt="Packagist Downloads"></a>
</p>

<h3 align="center">Know exactly what changed in PHP data and text.</h3>
<h5 align="center">Added, removed, modified and moved values at their exact paths, records matched by ID, redacted secrets, and GitHub-style line diffs.</h5>
<br>

## Installation

```bash
composer require pharaonic/php-diff
```

## Usage

```php
use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffOptions;

$result = Diff::between($before, $after, DiffOptions::create()
    ->identify('addresses', 'id')
    ->ignore('updated_at')
    ->redact('password'));

$result->wasModified('email');      // true when the email changed
$result->hasChanged('addresses.0'); // true when anything in that address changed
$result->toJson();                  // every change, with "[REDACTED]" instead of the password
```

For text, `Diff::text($old, $new)` gives the changed lines in hunks, with `toUnified()` for a patch that `git apply` accepts and `toHtml()` for a GitHub-style table.

## Documentation

Full documentation: [PHP Diff Documentation](https://pharaonic.dev/packages/php/diff).

## Support & Security

See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
