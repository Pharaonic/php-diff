## Troubleshooting

### A value is reported as modified, but it looks the same

Comparison is strict: `1`, `'1'`, `1.0` and `true` are all different. This usually comes from a database or form returning strings where your other side has numbers. Cast both sides the same way before comparing, or use `floatTolerance()` for floats that differ by rounding.

### Every item after an insertion is reported as changed

That happens with `ListStrategy::POSITION`, which compares index by index. Use the default sequence strategy, or `identify()` for records.

### A record is reported as removed and added instead of modified

In a sequence list, two records are paired only when they share at least half of their keys and at least one value. A record whose every value changed is reported as removed and added. Use `identify()` to match records by their key.

### Reordering a list reports changes

Lists keep their order by default. Use `ListStrategy::UNORDERED` for sets of values such as tags, or `identify()` for records. With `identify()`, order changes are reported only when you add `detectMoves()`.

### A change I expected is missing from an only() result

`only()` never reports the containers above the selected paths. When a whole record was added, removed or replaced, its change is at the record's path, which is above the selected one.

### UnsupportedValueException for an object

The object is not a date, `JsonSerializable` or `stdClass`. Register a normalizer or a comparator for its class or one of its interfaces.

### MaximumDepthExceededException on normal data

The data nests deeper than 64 levels, or an array references itself. Raise the limit with `maxDepth()` if the data is really that deep.

### IdentityException

Every record of an identified list needs every identity key, and identities must be unique in that list. Check for records without the key, duplicates, or `1` and `'1'` mixed when you expected them to match: identities are compared strictly.

### InputLimitExceededException for a text

The text has more than 20,000 lines or 4 MiB. Raise `maxLines()` or `maxInputBytes()` if you trust the input and can afford the time.

### git apply rejects the patch

Check that the patch is applied to the same old text it was made from, and that the labels match the file path, such as `a/src/File.php`. For patches made with `context(0)`, run `git apply --unidiff-zero`.

### The HTML has no colors

`toHtml()` returns markup only. Include `resources/diff.css`, or write rules for the `ph-diff-*` classes.

### Two lines are not paired as modified

Lines pair when their word similarity is at least 0.55. Short lines that share few words do not pair. Lower `similarityThreshold()`, or check that `pairModifiedLines()` was not turned off.
