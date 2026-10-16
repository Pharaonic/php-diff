<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffLine;
use Pharaonic\Diff\InlineMode;
use Pharaonic\Diff\InlineSegment;
use Pharaonic\Diff\TextDiffOptions;

/**
 * A line's pieces with changed ones in brackets, e.g. "beta[ changed]"
 */
function marked(DiffLine $line): string
{
    return implode('', array_map(
        fn(InlineSegment $s) => $s->type() === 'unchanged' ? $s->text() : "[{$s->text()}]",
        $line->inlineSegments(),
    ));
}

/**
 * The removed and added line of the first pair, marked
 *
 * @return array{string, string}
 */
function inline(string $old, string $new, string $mode = InlineMode::WORD, ?TextDiffOptions $options = null): array
{
    $pair = Diff::text($old, $new, ($options ?? TextDiffOptions::create())->inline($mode)->similarityThreshold(0.0))->modifiedPairs()[0];

    return [marked($pair->removed()), marked($pair->added())];
}

it('highlights changed words', function (string $old, string $new, array $expected) {
    expect(inline($old, $new))->toBe($expected);
})->with([
    'appended word' => ['beta', 'beta changed', ['beta', 'beta[ changed]']],
    'changed argument' => ['foo(1, 2);', 'foo(1, 3);', ['foo(1, [2]);', 'foo(1, [3]);']],
    'replaced word' => ['the quick fox', 'the slow fox', ['the [quick] fox', 'the [slow] fox']],
    'whitespace' => ['a b', 'a  b', ['a[ ]b', 'a[  ]b']],
    'arabic words' => ['مرحبا يا عالم', 'مرحبا يا صديق', ['مرحبا يا [عالم]', 'مرحبا يا [صديق]']],
]);

it('highlights changed characters, keeping grapheme clusters whole', function (string $old, string $new, array $expected) {
    expect(inline($old, $new, InlineMode::CHARACTER))->toBe($expected);
})->with([
    'one letter' => ['colour', 'color', ['colo[u]r', 'color']],
    'digits' => ['v1.2.3', 'v1.2.4', ['v1.2.[3]', 'v1.2.[4]']],
    'emoji with a skin tone' => ['hi 👋🏽', 'hi 👋🏿', ['hi [👋🏽]', 'hi [👋🏿]']],
    'joined emoji' => ['👩‍💻 x', '👩‍💻 y', ['👩‍💻 [x]', '👩‍💻 [y]']],
    'combining accent' => ["cafe\u{0301} x", "cafe\u{0301} y", ["cafe\u{0301} [x]", "cafe\u{0301} [y]"]],
]);

it('falls back to bytes for malformed UTF-8', function () {
    expect(inline("a\xB1b", "a\xB2b", InlineMode::CHARACTER))->toBe(["a[\xB1]b", "a[\xB2]b"])
        ->and(inline("x \xB1", "x \xB2"))->toBe(["x [\xB1]", "x [\xB2]"]);
});

it('splits each line into pieces that join back into it', function (string $mode) {
    mt_srand(7);
    $alphabet = ['a', 'b', ' ', '  ', '.', '(', ')', 'é', '👍', "\t", 'word'];

    for ($round = 0; $round < 100; $round++) {
        $old = implode('', array_map(fn() => $alphabet[mt_rand(0, 10)], range(0, mt_rand(1, 12))));
        $new = implode('', array_map(fn() => $alphabet[mt_rand(0, 10)], range(0, mt_rand(1, 12))));

        if ($old === $new) {
            continue;
        }

        $pair = Diff::text("{$old}\n", "{$new}\n", TextDiffOptions::create()->inline($mode)->similarityThreshold(0.0))->modifiedPairs()[0];

        foreach ([[$pair->removed(), 'removed'], [$pair->added(), 'added']] as [$line, $changed]) {
            expect(implode('', array_map(fn(InlineSegment $s) => $s->text(), $line->inlineSegments())))->toBe($line->content());

            foreach ($line->inlineSegments() as $segment) {
                expect($segment->type())->toBeIn(['unchanged', $changed]);
            }
        }
    }
})->with([InlineMode::WORD, InlineMode::CHARACTER]);

it('merges neighbouring pieces of the same kind', function () {
    $segments = Diff::text("abc\n", "xyz\n", TextDiffOptions::create()->inline(InlineMode::CHARACTER)->similarityThreshold(0.0))
        ->modifiedPairs()[0]->added()->inlineSegments();

    expect(array_map(fn(InlineSegment $s) => $s->toArray(), $segments))->toBe([['type' => 'added', 'text' => 'xyz']]);
});

it('highlights by word by default', function () {
    $pair = Diff::text("beta\n", "beta changed\n")->modifiedPairs()[0];

    expect(marked($pair->added()))->toBe('beta[ changed]')
        ->and($pair->added()->toArray()['inline'])->toBe([['type' => 'unchanged', 'text' => 'beta'], ['type' => 'added', 'text' => ' changed']]);
});

it('leaves lines without pieces when they count as changed as a whole', function (string $old, string $new, TextDiffOptions $options) {
    foreach (Diff::text($old, $new, $options)->lines() as $line) {
        expect($line->inlineSegments())->toBe([]);
    }
})->with([
    'no inline mode' => ["beta\n", "beta changed\n", TextDiffOptions::create()->inline(InlineMode::NONE)],
    'pairing off' => ["beta\n", "beta changed\n", TextDiffOptions::create()->pairModifiedLines(false)],
    'unpaired lines' => ["alpha\n", "omega\n", TextDiffOptions::create()],
    'unchanged lines' => ["same\n", "same\n", TextDiffOptions::create()],
    'old line above the inline limit' => [str_repeat('a ', 20) . "\n", str_repeat('a ', 5) . "\n", TextDiffOptions::create()->maxInlineLength(20)],
    'new line above the inline limit' => [str_repeat('a ', 5) . "\n", str_repeat('a ', 20) . "\n", TextDiffOptions::create()->maxInlineLength(20)],
]);

it('highlights lines exactly at the inline limit', function () {
    expect(inline(str_repeat('a', 9) . 'b', str_repeat('a', 9) . 'c', InlineMode::CHARACTER, TextDiffOptions::create()->maxInlineLength(10)))
        ->toBe(['aaaaaaaaa[b]', 'aaaaaaaaa[c]']);
});

it('keeps the same lines in pairs, lines and hunks', function () {
    $result = Diff::text("alpha\nbeta\n", "alpha\nbeta changed\n");
    $pair = $result->modifiedPairs()[0];

    expect($result->lines()[1])->toBe($pair->removed())
        ->and($result->lines()[2])->toBe($pair->added())
        ->and($result->hunks()[0]->lines()[2])->toBe($pair->added());
});
