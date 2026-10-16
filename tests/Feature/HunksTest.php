<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\Hunk;
use Pharaonic\Diff\TextDiffOptions;

/**
 * The hunk ranges as Git writes them, e.g. "-7,7 +7,7"
 *
 * @return list<string>
 */
function ranges(string $old, string $new, int $context = 3): array
{
    return array_map(
        fn(Hunk $hunk) => sprintf('-%d,%d +%d,%d', $hunk->oldStart(), $hunk->oldLength(), $hunk->newStart(), $hunk->newLength()),
        Diff::text($old, $new, TextDiffOptions::create()->context($context))->hunks(),
    );
}

/**
 * Lines 1 to 20, with the given line numbers replaced by "X"
 */
function numbered(int ...$replaced): string
{
    return implode('', array_map(fn(int $i) => (in_array($i, $replaced, true) ? 'X' : (string) $i) . "\n", range(1, 20)));
}

// Expected ranges were taken from `git diff --no-index`.
it('builds hunks with the same ranges as Git', function (string $old, string $new, int $context, array $expected) {
    expect(ranges($old, $new, $context))->toBe($expected);
})->with([
    'middle' => [numbered(), numbered(10), 3, ['-7,7 +7,7']],
    'at the start' => [numbered(), numbered(1), 3, ['-1,4 +1,4']],
    'at the end' => [numbered(), numbered(20), 3, ['-17,4 +17,4']],
    'merged at a gap of twice the context' => [numbered(), numbered(3, 10), 3, ['-1,13 +1,13']],
    'split at a larger gap' => [numbered(), numbered(3, 11), 3, ['-1,6 +1,6', '-8,7 +8,7']],
    'insertion without context' => ["1\n2\n3\n4\n5\n", "1\n2\n3\nA\nB\n4\n5\n", 0, ['-3,0 +4,2']],
    'deletion without context' => ["1\n2\n3\n4\n5\n6\n", "1\n2\n3\n6\n", 0, ['-4,2 +3,0']],
    'from an empty text' => ['', "a\nb\n", 3, ['-0,0 +1,2']],
    'to an empty text' => ["a\nb\n", '', 3, ['-1,2 +0,0']],
    'replacement without context' => ["a\nb\n", "c\n", 0, ['-1,2 +1,1']],
    'insertion at the start without context' => ["1\n2\n", "0\n1\n2\n", 0, ['-0,0 +1,1']],
]);

it('has no hunks when nothing changed', function () {
    expect(Diff::text("a\nb\n", "a\nb\n")->hunks())->toBe([]);
});

it('keeps the lines of each hunk in order', function () {
    $hunk = Diff::text(numbered(), numbered(10))->hunks()[0];

    expect(array_map(fn($line) => $line->content(), $hunk->lines()))->toBe(['7', '8', '9', '10', 'X', '11', '12', '13'])
        ->and($hunk->toArray())->toMatchArray(['old_start' => 7, 'old_length' => 7, 'new_start' => 7, 'new_length' => 7])
        ->and($hunk->toArray()['lines'][3])->toBe(['type' => 'removed', 'content' => '10', 'old_line' => 10, 'new_line' => null, 'inline' => []]);
});

it('uses the configured context, as Git does', function () {
    expect(ranges(numbered(), numbered(10), 1))->toBe(['-9,3 +9,3'])
        ->and(ranges(numbered(), numbered(3, 6), 1))->toBe(['-2,6 +2,6'])
        ->and(ranges(numbered(), numbered(3, 7), 1))->toBe(['-2,3 +2,3', '-6,3 +6,3'])
        ->and(ranges(numbered(), numbered(3, 5), 1))->toBe(['-2,5 +2,5'])
        ->and(ranges(numbered(), numbered(10), 100))->toBe(['-1,20 +1,20']);
});
