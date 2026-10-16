<p align="center"><a href="https://pharaonic.dev/packages/php/diff" target="_blank"><img src="https://raw.githubusercontent.com/Pharaonic/php-diff/develop/docs/cover.webp"></a></p>

<p align="center">
  <a href="https://php.net" target="_blank"><img src="https://img.shields.io/static/v1?label=PHP&message=8.1&color=blue&style=flat-square" alt="PHP Version : 8.1"></a>
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

### Compare two values

```php
use Pharaonic\Diff\Diff;

$before = ['name' => 'Ali', 'age' => 30, 'phone' => '0100'];
$after  = ['name' => 'Ali', 'age' => 31, 'email' => 'ali@example.com'];

$result = Diff::between($before, $after);

$result->wasModified('age');   // true
$result->wasAdded('email');    // true
$result->wasRemoved('phone');  // true
$result->hasChanged('name');   // false
```

### Compare two texts

```php
echo Diff::text("apple\nbanana\ncherry\n", "apple\nblueberry\ncherry\n")->toUnified();
```

```diff
--- before
+++ after
@@ -1,3 +1,3 @@
 apple
-banana
+blueberry
 cherry
```

## Documentation

Full documentation: [PHP Diff Documentation](https://pharaonic.dev/packages/php/diff).

## Support & Security

See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
