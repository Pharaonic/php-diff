<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\ModifiedLinePair;
use Pharaonic\Diff\Text\Similarity;
use Pharaonic\Diff\TextDiffOptions;

/**
 * Each pair as "old line number->new line number"
 *
 * @return list<string>
 */
function pairs(string $old, string $new, ?TextDiffOptions $options = null): array
{
    return array_map(
        fn (ModifiedLinePair $pair) => $pair->removed()->oldLineNumber() . '->' . $pair->added()->newLineNumber(),
        Diff::text($old, $new, $options)->modifiedPairs(),
    );
}

it('pairs the brief example as a modified line and an added line', function () {
    $result = Diff::text("alpha\nbeta\ngamma\n", "alpha\nbeta changed\ngamma\ndelta\n");
    $pair = $result->modifiedPairs()[0];

    expect(pairs("alpha\nbeta\ngamma\n", "alpha\nbeta changed\ngamma\ndelta\n"))->toBe(['2->2'])
        ->and($pair->removed()->content())->toBe('beta')
        ->and($pair->added()->content())->toBe('beta changed')
        ->and($pair->toArray())->toBe(['old_line' => 2, 'new_line' => 2])
        ->and(textLines($result))->toBe([' alpha', '-beta', '+beta changed', ' gamma', '+delta'])
        ->and($result->stats())->toBe(['added' => 2, 'removed' => 1, 'modified' => 1, 'unchanged' => 2]);
});

it('does not pair clearly unrelated lines', function () {
    expect(pairs("alpha\n", "omega\n"))->toBe([])
        ->and(pairs("return \$user;\n", "throw new Exception();\n"))->toBe([]);
});

it('pairs each removed line with the most similar added line, without crossing pairs', function () {
    $old = "foo(1);\nbar(1);\n";
    $new = "unrelated\nbar(2);\nfoo(2);\n";

    // Both pairs score 0.8; the earliest removed line wins the tie, and pairs cannot cross.
    expect(pairs($old, $new))->toBe(['1->3'])
        ->and(pairs("a = 1;\nb = 2;\n", "x\na = 10;\nb = 20;\n"))->toBe(['1->2', '2->3']);
});

it('pairs inside each change separately', function () {
    $old = "one = 1\nkeep\ntwo = 2\n";
    $new = "one = 11\nkeep\ntwo = 22\n";

    expect(pairs($old, $new))->toBe(['1->1', '3->3']);
});

it('can be turned off', function () {
    $result = Diff::text("beta\n", "beta changed\n", TextDiffOptions::create()->pairModifiedLines(false));

    expect($result->modifiedPairs())->toBe([])
        ->and($result->stats()['modified'])->toBe(0)
        ->and(textLines($result))->toBe(['-beta', '+beta changed']);
});

it('pairs at the threshold and not below it', function () {
    // "a b" and "a c" share one of two words on each side: a ratio of exactly 0.5.
    expect(Similarity::ratio('a b', 'a c', 1000))->toBe(0.5)
        ->and(pairs("a b\n", "a c\n", TextDiffOptions::create()->similarityThreshold(0.5)))->toBe(['1->1'])
        ->and(pairs("a b\n", "a c\n", TextDiffOptions::create()->similarityThreshold(0.51)))->toBe([])
        ->and(pairs("x\n", "y\n", TextDiffOptions::create()->similarityThreshold(0.0)))->toBe(['1->1']);
});

it('scores similarity by words and punctuation', function (string $a, string $b, float $ratio) {
    expect(round(Similarity::ratio($a, $b, 1000), 4))->toBe($ratio);
})->with([
    'equal' => ['same', 'same', 1.0],
    'one empty' => ['', 'x', 0.0],
    'appended word' => ['beta', 'beta changed', 0.6667],
    'changed argument' => ['foo(1);', 'foo(2);', 0.8],
    'unrelated' => ['alpha', 'omega', 0.0],
    'unicode words' => ['مرحبا يا عالم', 'مرحبا يا صديق', 0.6667],
    'punctuation only' => ['---', '--', 0.8],
    'malformed bytes' => ["\xB1 a", "\xB1 b", 0.5],
]);

it('scores lines above the inline limit by their common prefix and suffix', function () {
    $long = str_repeat('x', 50);

    expect(Similarity::ratio($long . 'a', $long . 'b', 10))->toBe(100 / 102)
        ->and(Similarity::ratio('abc', 'xyz', 1))->toBe(0.0)
        ->and(Similarity::ratio('aXa', 'aYa', 1))->toBe(4 / 6);
});

it('stays fast and deterministic on large changes', function () {
    $old = implode("\n", array_map(fn ($i) => "value {$i} = old", range(1, 300))) . "\n";
    $new = implode("\n", array_map(fn ($i) => "value {$i} = new", range(1, 300))) . "\n";
    $start = microtime(true);
    $result = pairs($old, $new);

    expect($result)->toHaveCount(300)
        ->and($result[0])->toBe('1->1')
        ->and($result[299])->toBe('300->300')
        ->and(pairs($old, $new))->toBe($result)
        ->and(microtime(true) - $start)->toBeLessThan(10.0);
});
