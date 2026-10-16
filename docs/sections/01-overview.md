:::badges
- PHP Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# Diff

Diff tells you what changed between two PHP values. It does two kinds of comparison:

- **Structured diffs** compare arrays, scalars and objects, and report each value that was added, removed, modified or moved, at its exact path.
- **Text diffs** compare two texts line by line, the way Git and GitHub show them: hunks, highlighted words, unified patches and HTML.

```php
use Pharaonic\Diff\Diff;

$result = Diff::between(
    ['name' => 'Raggi', 'email' => 'old@example.com'],
    ['name' => 'Raggi', 'email' => 'new@example.com'],
);

$result->wasModified('email'); // true

echo Diff::text("alpha\nbeta\n", "alpha\nbeta changed\n")->toUnified();
```

:::features
### Changes at Exact Paths {icon="code-brackets"}
Each change has a type, a path such as user.email, and its old and new values, so you can query, store or display it.

### Records Matched by ID {icon="users"}
Lists of records can be compared by one or more keys, and records that changed order can be reported as moved.

### Redaction Built In {icon="lock"}
Secrets are still compared, but the result stores [REDACTED] instead of them, even inside parent and removed values.

### Strict and Predictable {icon="switch"}
1, "1" and 1.0 differ, a missing key is not null, map key order is ignored, and the same input always gives the same result.

### GitHub-Style Text Diffs {icon="lines"}
Line diffs with hunks, word or character highlighting, and unified patches that git apply and patch accept.

### HTML Ready {icon="browser"}
An escaped HTML table of the hunks, styled by an optional stylesheet with light and dark colors.
:::

:::info Quick Tip
Fields such as `updated_at` change on every save. Leave them out with `DiffOptions::create()->ignore('updated_at')`.
:::
