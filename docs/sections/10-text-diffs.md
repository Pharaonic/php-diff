## Text Diffs

`Diff::text()` compares two strings line by line, like `git diff`, and returns a `TextDiffResult`.

```php
$result = Diff::text($old, $new);

$result->hasChanges(); // true
$result->stats();      // ['added' => 2, 'removed' => 1, 'modified' => 1, 'unchanged' => 2]
```

### Line Endings

A line ends with `"\n"`, `"\r\n"` or `"\r"`, and all three are treated alike, so a file converted from Windows to Unix line endings reports no change. Line content never includes the ending.

Whether a text ends with a line ending is compared too. When only one side has a final newline, the last line is reported as changed, and the unified diff adds `\ No newline at end of file`, as Git does.

### Whitespace

```php
TextDiffOptions::create()->ignoreWhitespaceChanges();  // like git diff -b: "a  b" equals "a b"
TextDiffOptions::create()->ignoreTrailingWhitespace(); // like git diff --ignore-space-at-eol
```

Lines that are equal only because whitespace is ignored are unchanged, and keep their old content.

### Lines

`lines()` returns every line in reading order: unchanged, removed and added, with the removed lines of a change before its added lines. Each one is a `DiffLine`:

```php
foreach ($result->lines() as $line) {
    $line->type();          // ChangeType::ADDED, REMOVED or UNCHANGED
    $line->content();       // the text, without its line ending
    $line->oldLineNumber(); // 1-based, or null for an added line
    $line->newLineNumber(); // 1-based, or null for a removed line
}

$result->addedLines();   // only added lines
$result->removedLines(); // only removed lines
```

### Hunks

`hunks()` groups the changes with the unchanged lines around them, as in a unified diff. Each `Hunk` has `oldStart()`, `oldLength()`, `newStart()`, `newLength()` and `lines()`. When a side has no lines in the hunk, its start is the line the hunk comes after, or `0` at the start of the text, as Git writes it.

Set how many unchanged lines surround each change with `context()` (3 by default). Two changes closer than twice the context share one hunk:

```php
use Pharaonic\Diff\TextDiffOptions;

// Lines 5 and 15 of a 20-line file changed.
count(Diff::text($old, $new)->hunks());                                        // 2
count(Diff::text($old, $new, TextDiffOptions::create()->context(1))->hunks()); // 2, with 4 lines each
count(Diff::text($old, $new, TextDiffOptions::create()->context(5))->hunks()); // 1
```

Equal texts have no hunks.

### Modified Lines

A removed line and an added line that look alike are paired as a modified line, the way GitHub shows a changed line. `modifiedPairs()` returns them as `ModifiedLinePair` objects with `removed()` and `added()`. Both lines still appear on their own in `lines()` and in the hunks.

```php
$result = Diff::text("The quick brown fox\n", "The quick red fox\n");

$pair = $result->modifiedPairs()[0];
$pair->removed()->content(); // 'The quick brown fox'
$pair->added()->content();   // 'The quick red fox'
```

Two lines pair when their word similarity is at least 0.55. Change it with `similarityThreshold()`, between 0 and 1, or turn pairing off with `pairModifiedLines(false)`.

### Inline Highlighting

The lines of a modified pair are split into segments, so you can highlight what changed inside them. `inlineSegments()` returns `InlineSegment` objects with `type()` and `text()`, which join back into the line's content:

```php
foreach ($pair->added()->inlineSegments() as $segment) {
    echo $segment->type(), ': ', $segment->text(), PHP_EOL;
}

// unchanged: The quick
// added: red
// unchanged:  fox
```

On a removed line, the changed segments are `removed`; on an added line, they are `added`.

Choose the granularity with `inline()`:

| Mode | Splits by |
| --- | --- |
| `InlineMode::WORD` (default) | Words, runs of whitespace and single punctuation characters |
| `InlineMode::CHARACTER` | Characters: grapheme clusters for valid UTF-8, bytes otherwise |
| `InlineMode::NONE` | Nothing: modified lines have no segments |

```php
use Pharaonic\Diff\InlineMode;

$result = Diff::text("The colour of the sky\n", "The color of the sky\n", TextDiffOptions::create()->inline(InlineMode::CHARACTER));

// The removed line splits into "The colo", "u" (removed) and "r of the sky".
```

Lines are paired by word similarity in every mode, so two one-word lines such as `colour` and `color` are not paired, and have no segments.

`inlineSegments()` is empty for unchanged lines, unpaired lines, and lines longer than `maxInlineLength()` (1,000 bytes by default), which count as changed as a whole.

### Arrays and JSON

`toArray()`, `jsonSerialize()` and `toJson()` give the stats, whether each side ends with a newline, the hunks with their lines and inline segments, and the line numbers of the modified pairs:

```php
Diff::text("alpha\nbeta\n", "alpha\nbeta changed\n")->toArray();

// [
//     'stats' => ['added' => 1, 'removed' => 1, 'modified' => 1, 'unchanged' => 1],
//     'old_ends_with_newline' => true,
//     'new_ends_with_newline' => true,
//     'hunks' => [[
//         'old_start' => 1, 'old_length' => 2, 'new_start' => 1, 'new_length' => 2,
//         'lines' => [
//             ['type' => 'unchanged', 'content' => 'alpha', 'old_line' => 1, 'new_line' => 1, 'inline' => []],
//             ['type' => 'removed', 'content' => 'beta', 'old_line' => 2, 'new_line' => null, 'inline' => [...]],
//             ['type' => 'added', 'content' => 'beta changed', 'old_line' => null, 'new_line' => 2, 'inline' => [...]],
//         ],
//     ]],
//     'modified' => [['old_line' => 2, 'new_line' => 2]],
// ]
```

Only the lines inside hunks are included. Use `lines()` for all of them.
