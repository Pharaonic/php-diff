# Changelog

All notable changes to this project are documented here.

## 8.3.0 - 2026-10-16

The `8.3.x` line targets PHP 8.3. Its public API and its output are identical to `8.2.0`.

### Changed

- **Requires PHP `>=8.3 <8.4`.** Use the `8.2.x` line on PHP 8.2.

## 8.2.0 - 2026-10-16

The `8.2.x` line targets PHP 8.2. Its public API and its output are identical to `8.1.0`.

### Changed

- **Requires PHP `>=8.2 <8.3`.** Use the `8.1.x` line on PHP 8.1.

## 8.1.0 - 2026-10-16

The `8.1.x` line targets PHP 8.1. Its public API and its output are identical to `8.0.0`.

### Changed

- **Requires PHP `>=8.1 <8.2`.** Use the `8.0.x` line on PHP 8.0.

## 8.0.0 - 2026-10-16

### Added

- `Diff::between()`: structured diffs of `null`, scalars, arrays and objects, reported as `added`, `removed`, `modified` and `moved` changes with their paths and old and new values. Scalars are compared strictly, `NAN` equals `NAN` and `-0.0` equals `0.0`, map key order is ignored, and a missing key is not `null`.
- Containers above a change are reported as aggregate `modified` changes, parents before their descendants; the root is never an aggregate.
- `DiffResult`: `hasChanges()`, `hasChanged()`, `wasAdded()`, `wasRemoved()`, `wasModified()`, `wasMoved()`, `changes()`, `added()`, `removed()`, `modified()`, `moved()`, `at()`, `under()`, `stats()`, `count()`, iteration, `toArray()` and `toJson()`.
- `Path` with dot notation (`\.`, `\*` and `\\` escape a literal character) and RFC 6901 JSON Pointers.
- `DiffOptions`: `ignore()`, `only()` and `redact()` with `*` wildcards, `maxDepth()` (64 by default) and `floatTolerance()`.
- Lists: the `sequence` strategy (the default) aligns items like lines of text and pairs replaced items conservatively; `position` and `unordered` strategies through `listStrategy()`; records identified by one or more keys through `identify()`; and `detectMoves()` for identified records that changed their relative order.
- Objects: dates are compared by instant, `JsonSerializable` objects through `jsonSerialize()` and `stdClass` as a map; `normalize()` and `compare()` register rules for any class or interface. Unsupported values and objects that contain themselves throw an `UnsupportedValueException` that names the path.
- `Diff::text()`: line diffs with the Myers algorithm, `"\n"`, `"\r\n"` and `"\r"` treated alike, final newlines compared, hunks with `context()` lines, and `ignoreWhitespaceChanges()` and `ignoreTrailingWhitespace()`.
- Modified line pairing with `similarityThreshold()`, and inline highlighting by word or grapheme cluster through `inline()`.
- `TextDiffResult::toUnified()`, which `git apply` and `patch` accept, and `toHtml()`, an escaped GitHub-style table styled by the optional `resources/diff.css`.
- Text limits: `maxLines()` (20,000 by default), `maxInputBytes()` (4 MiB by default) and `maxInlineLength()` (1,000 bytes by default).
- Every exception implements `DiffException`: `UnsupportedValueException`, `MaximumDepthExceededException`, `IdentityException` and `InputLimitExceededException`.
