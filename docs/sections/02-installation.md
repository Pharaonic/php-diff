## Installation

Install the package with Composer. There is nothing to register or configure.

### Requirements

- PHP 8.0

There are no runtime dependencies and no required PHP extensions.

### Composer Installation

```bash title="Terminal" no-line-numbers
composer require pharaonic/php-diff
```

Composer autoloads the `Pharaonic\Diff` namespace.

:::success Installation Complete
You're all set! Try `Pharaonic\Diff\Diff::between(['a' => 1], ['a' => 2])->wasModified('a')`, which returns `true`.
:::
