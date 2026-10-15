# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Answer "what changed between the old value and the new value?" for structured PHP data (`Diff::between()`) and for multi-line text (`Diff::text()`). Git repository operations, JSON Patch, patch application, three-way merge, binary and AST diffs are out of scope; Laravel integration belongs to a future `laravel-diff`.

## Supported PHP lines

One branch per PHP minor, each requiring exactly that minor (`"php": ">=8.0 <8.1"`):

```text
8.0.x  8.1.x  8.2.x  8.3.x  8.4.x  8.5.x
```

## Public API invariants

- The public API is identical on every line. `ChangeType`, `ListStrategy` and `InlineMode` stay string-constant classes on every line, including the ones that have enums.
- Scalar comparison is strict, map key order is ignored, list order matters by default, and a missing value differs from `null`.
- Every existing ancestor of a changed value is reported as an aggregate `modified` change; the root never is. A wholly added or removed subtree is one change.
- `hasChanged()` matches the path and its descendants by segment; `wasAdded()`, `wasRemoved()` and `wasModified()` match the exact path; `wasMoved()` matches the old or the new path.
- Options are immutable. Redaction happens before a value is stored in a result, including inside aggregate and subtree values.
- Text diffs keep the low-level `added`/`removed`/`unchanged` lines; modified-line pairing is a layer on top. Unified output must apply with `git apply` and `patch`.
- Every exception implements `Exceptions\DiffException`. Everything marked `@internal` may change in any release.

## Package-specific constraints

- No runtime dependencies and no required extension beyond what PHP always ships (JSON, PCRE).
- No filesystem, network or process IO in comparison or rendering code.
- HTML output escapes every caller-controlled string.

## Branch notes

Tooling follows the line's PHP: Pest 1 on `8.0.x` and `8.1.x`, Pest 3 on `8.2.x`, Pest 4 from `8.3.x`; Pint uses the `psr12` preset on `8.0.x` and `per` on the other lines.

## Documentation

https://pharaonic.dev/packages/php/diff

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
