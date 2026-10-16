## Unified & HTML Output

A `TextDiffResult` renders itself as a unified diff or as an HTML table. Both are empty strings when the texts are equal.

### Unified Diff

`toUnified()` writes a standard unified diff, with the hunks and the context you set:

```php
echo Diff::text($old, $new)->toUnified('a/config/app.php', 'b/config/app.php');
```

```diff title="Output" no-copy
--- a/config/app.php
+++ b/config/app.php
@@ -1,3 +1,4 @@
 alpha
-beta
+beta changed
 gamma
+delta
\ No newline at end of file
```

The two labels default to `before` and `after`. Every line of the output ends with `"\n"`, whatever line endings the texts used.

The output is a valid patch: `git apply` and `patch` apply it to the old text and give back the new one, including the `\ No newline at end of file` markers. With `context(0)`, pass `--unidiff-zero` to `git apply`, as Git requires for patches without context.

```bash title="Terminal" no-line-numbers
git apply changes.patch
```

### HTML

`toHtml()` returns a GitHub-style `<table>` of the hunks, with old and new line numbers, `+` and `-` markers, and the changed words of modified lines highlighted:

```php
echo Diff::text($old, $new)->toHtml();
```

Every piece of text is escaped with `htmlspecialchars()`, so you can render a diff of user content safely. Line numbers and markers are in `data-line-number` and `data-marker` attributes, displayed by CSS, so copying the code from the page does not copy them.

### Styling

The package ships an optional GitHub-style stylesheet at `vendor/pharaonic/php-diff/resources/diff.css`. Copy it into your assets, or write your own rules for its classes:

```bash title="Terminal" no-line-numbers
cp vendor/pharaonic/php-diff/resources/diff.css public/css/diff.css
```

`diff.css` follows GitHub's colors, switches to dark colors with `prefers-color-scheme`, and is themed through custom properties on `.ph-diff`:

```css title="public/css/app.css"
.ph-diff {
  --ph-diff-font: "JetBrains Mono", monospace;
  --ph-diff-added-background: #e6ffec;
  --ph-diff-removed-background: #ffebe9;
}
```

| Class | Element |
| --- | --- |
| `ph-diff` | The `<table>` |
| `ph-diff-hunk` | A `<tbody>` per hunk |
| `ph-diff-hunk-header`, `ph-diff-hunk-header-text` | The `@@ -1,3 +1,4 @@` row and its cell |
| `ph-diff-line` | Every line row, with `ph-diff-line--context`, `ph-diff-line--added` or `ph-diff-line--removed` |
| `ph-diff-number` | The old and new line number cells |
| `ph-diff-code` | The code cell |
| `ph-diff-marker` | The `+`, `-` or space marker inside the code cell |
| `ph-diff-inline--added`, `ph-diff-inline--removed` | The `<ins>` and `<del>` around changed words |
| `ph-diff-no-newline` | The "No newline at end of file" row |

The custom properties are `--ph-diff-font`, `--ph-diff-text`, `--ph-diff-muted`, `--ph-diff-background`, `--ph-diff-border`, `--ph-diff-hunk-background`, and `--ph-diff-added-*` / `--ph-diff-removed-*` for `background`, `number-background` and `inline`.

### Your Own Renderer

For any other layout, such as a side-by-side view or a terminal, build it from `hunks()`, each line's `type()`, `content()` and line numbers, and `inlineSegments()`. `toArray()` gives the same data as plain arrays for JavaScript front ends.
