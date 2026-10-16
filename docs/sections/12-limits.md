## Limits & Performance

Diffs can take time and memory on large or very different inputs. Every limit has a safe default, and you can change it in the options.

### Limits

| Option | Default | When exceeded |
| --- | --- | --- |
| `DiffOptions::maxDepth()` | 64 levels | `MaximumDepthExceededException` |
| `TextDiffOptions::maxLines()` | 20,000 lines per text | `InputLimitExceededException` |
| `TextDiffOptions::maxInputBytes()` | 4 MiB per text | `InputLimitExceededException` |
| `TextDiffOptions::maxInlineLength()` | 1,000 bytes per line | The line is highlighted as a whole, without an exception |

```php
$options = TextDiffOptions::create()
    ->maxLines(50_000)
    ->maxInputBytes(10 * 1024 * 1024);
```

The text limits are checked before any work starts, on both texts. The depth limit also stops arrays that reference themselves.

:::warning Raise Text Limits With Care
The defaults keep the slowest case, two completely different texts at the limit, to a few seconds and under 100 MB on PHP 8.0. Doubling the limits can more than double the time and the memory. If users can submit the texts, keep the defaults or lower them, and run large diffs in a queued job.
:::

### What to Expect

Measured on PHP 8.0, each case in a separate process. Your numbers depend on your machine:

| Case | Time | Peak memory |
| --- | --- | --- |
| 1,000-key maps, every value changed | about 5 ms | 4 MB |
| 1,000 records, all modified, no identity | about 0.15 s | 8 MB |
| 1,000 identified records, reversed | about 0.14 s | 6 MB |
| 10,000 records that never pair | about 1.8 s | 36 MB |
| 10,000 text lines, every 100th changed | about 0.07 s | 16 MB |
| 1,000 Unicode lines, character mode | about 0.2 s | 8 MB |
| 5,000 completely different lines | about 0.8 s | 20 MB |
| 20,000 completely different lines (default limit) | about 4 s | 73 MB |
| 4 MiB in 20,000 different lines (default limits) | about 3.3 s | 87 MB |

Typical inputs, like an edited file or a changed record, take milliseconds. The time grows with how different the inputs are, not only with their size.

### How the Work Stays Bounded

- **Text alignment** uses the linear-space Myers algorithm, as Git does. On very different texts, it stops searching for the shortest diff after a cost limit, again as Git does, and returns a valid diff that may be slightly longer than the shortest one.
- **Modified line pairing** skips pairs whose shared words already rule them out, before comparing them in full.
- **Objects in lists** that a comparator handles are matched by calling the comparator against each different object already seen in that diff, so a list of 3,000 different objects takes about half a second. For large lists, a normalizer is faster: its values are matched directly.
- **Record pairing** in a sequence list compares every removed record with every added one, up to 10,000 comparisons per replaced run. Larger runs pair records at the same position only.
- **Text lines** are turned into numbers once, one per distinct line, so the alignment compares integers instead of strings, however long the lines are.

:::tip
For lists of records, `identify()` is faster and more precise than the default sequence strategy: each record is found by its key, with no pairing guesswork.
:::
