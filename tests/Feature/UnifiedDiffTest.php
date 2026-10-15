<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\TextDiffOptions;

it('renders the brief example', function () {
    expect(Diff::text("apple\nbanana\norange\n", "apple\nbanana split\norange\nmango\n")->toUnified('before.php', 'after.php'))->toBe(<<<'DIFF'
        --- before.php
        +++ after.php
        @@ -1,3 +1,4 @@
         apple
        -banana
        +banana split
         orange
        +mango

        DIFF);
});

it('renders nothing for equal texts', function () {
    expect(Diff::text("a\n", "a\n")->toUnified())->toBe('')
        ->and(Diff::text('', '')->toUnified())->toBe('');
});

it('labels the sides "before" and "after" by default', function () {
    expect(Diff::text('a', 'b')->toUnified())->toStartWith("--- before\n+++ after\n");
});

it('leaves out lengths of one and keeps lengths of zero', function () {
    expect(Diff::text("a\n", "b\n")->toUnified())->toContain("@@ -1 +1 @@\n")
        ->and(Diff::text('', "a\nb\n")->toUnified())->toContain("@@ -0,0 +1,2 @@\n")
        ->and(Diff::text("a\nb\n", '')->toUnified())->toContain("@@ -1,2 +0,0 @@\n");
});

it('marks a last line without a line ending', function (string $old, string $new, string $body) {
    expect(Diff::text($old, $new, TextDiffOptions::create()->context(1))->toUnified())->toEndWith($body);
})->with([
    'old only' => ["a\nb", "a\nb\n", "-b\n\\ No newline at end of file\n+b\n"],
    'new only' => ["a\nb\n", "a\nb", "-b\n+b\n\\ No newline at end of file\n"],
    'both, changed' => ["a\nb", "a\nc", "-b\n\\ No newline at end of file\n+c\n\\ No newline at end of file\n"],
    'both, unchanged context' => ["x\na\nb", "y\na\nb", " a\n"],
    'both, unchanged last line in the hunk' => ["a\nb", "A\nb", "-a\n+A\n b\n\\ No newline at end of file\n"],
]);

it('writes every line ending as "\n"', function () {
    expect(Diff::text("a\r\nb\r\n", "a\r\nc\r\n")->toUnified())->not->toContain("\r");
});

it('rejects labels with a line ending', function () {
    Diff::text('a', 'b')->toUnified("a\nb");
})->throws(InvalidArgumentException::class, 'A unified diff label cannot contain a line ending.');

it('serializes hunks to arrays and JSON', function () {
    $result = Diff::text("a\nb", "a\nc\n");
    $array = $result->toArray();

    expect($array)->toMatchArray([
        'stats' => ['added' => 1, 'removed' => 1, 'modified' => 0, 'unchanged' => 1],
        'old_ends_with_newline' => false,
        'new_ends_with_newline' => true,
    ])
        ->and($array['hunks'][0])->toMatchArray(['old_start' => 1, 'old_length' => 2, 'new_start' => 1, 'new_length' => 2])
        ->and(json_decode($result->toJson(), true))->toBe($array)
        ->and(json_encode($result))->toBe($result->toJson())
        ->and(Diff::text('', '')->toArray()['hunks'])->toBe([]);
});

it('throws on text JSON cannot encode unless the flags allow it', function () {
    $result = Diff::text("a\n", "\xB1\n");

    expect(fn () => $result->toJson())->toThrow(JsonException::class)
        ->and($result->toJson(JSON_INVALID_UTF8_SUBSTITUTE))->toBeString();
});
