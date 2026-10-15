<?php

declare(strict_types=1);

use Pharaonic\Diff\DiffLine;
use Pharaonic\Diff\Diff;
use Pharaonic\Diff\Exceptions\InputLimitExceededException;
use Pharaonic\Diff\TextDiffOptions;

it('aligns lines', function (string $old, string $new, array $expected) {
    expect(textLines(Diff::text($old, $new)))->toBe($expected);
})->with([
    'empty vs empty' => ['', '', []],
    'empty vs text' => ['', "a\nb\n", ['+a', '+b']],
    'text vs empty' => ["a\nb\n", '', ['-a', '-b']],
    'identical' => ["a\nb\n", "a\nb\n", [' a', ' b']],
    'one-line add' => ["a\nc\n", "a\nb\nc\n", [' a', '+b', ' c']],
    'one-line remove' => ["a\nb\nc\n", "a\nc\n", [' a', '-b', ' c']],
    'one-line modify' => ["a\nb\nc\n", "a\nB\nc\n", [' a', '-b', '+B', ' c']],
    'repeated lines' => ["x\nx\nx\n", "x\nx\n", [' x', ' x', '-x']],
    'blank lines' => ["a\n\nb\n", "a\nb\n", [' a', '-', ' b']],
]);

it('numbers lines from 1 on each side', function () {
    $lines = Diff::text("alpha\nbeta\ngamma\n", "alpha\nbeta changed\ngamma\ndelta\n")->lines();

    expect(array_map(fn (DiffLine $l) => [$l->type(), $l->content(), $l->oldLineNumber(), $l->newLineNumber()], $lines))->toBe([
        ['unchanged', 'alpha', 1, 1],
        ['removed', 'beta', 2, null],
        ['added', 'beta changed', null, 2],
        ['unchanged', 'gamma', 3, 3],
        ['added', 'delta', null, 4],
    ]);
});

it('treats every line ending alike and keeps none in the content', function (string $old, string $new) {
    $result = Diff::text($old, $new);

    expect($result->hasChanges())->toBeFalse()
        ->and(textLines($result))->toBe([' a', ' b']);
})->with([
    'LF' => ["a\nb\n", "a\nb\n"],
    'CRLF' => ["a\r\nb\r\n", "a\r\nb\r\n"],
    'CR' => ["a\rb\r", "a\rb\r"],
    'CRLF vs LF' => ["a\r\nb\r\n", "a\nb\n"],
    'CR vs LF' => ["a\rb\r", "a\nb\n"],
    'mixed' => ["a\r\nb\r", "a\nb\n"],
]);

it('reads "\r\r\n" as an empty line between two lines', function () {
    expect(textLines(Diff::text("a\r\r\nb", "a\r\r\nb")))->toBe([' a', ' ', ' b']);
});

it('compares the line ending at the end of the text', function (string $old, string $new, array $expected) {
    expect(textLines(Diff::text($old, $new)))->toBe($expected);
})->with([
    'both end with one' => ["a\nb\n", "a\nb\n", [' a', ' b']],
    'neither ends with one' => ["a\nb", "a\nb", [' a', ' b']],
    'added' => ["a\nb", "a\nb\n", [' a', '-b', '+b']],
    'removed' => ["a\nb\n", "a\nb", [' a', '-b', '+b']],
    'only an empty line' => ["\n", '', ['-']],
    'last line changed without one' => ["a\nb", "a\nc", [' a', '-b', '+c']],
    'line added after a last line without one' => ['a', "a\nb", ['-a', '+a', '+b']],
]);

it('reports whether a text ends with a line ending', function () {
    $result = Diff::text("a\n", 'a');

    expect($result->oldEndsWithNewline())->toBeTrue()
        ->and($result->newEndsWithNewline())->toBeFalse()
        ->and(Diff::text('', '')->oldEndsWithNewline())->toBeFalse();
});

it('keeps Unicode, emoji and malformed UTF-8 bytes as they are', function () {
    $old = "مرحبا\n👩‍💻 code\n\xB1\xFF\n";
    $new = "مرحبا\n👩‍💻 code!\n\xB1\xFF\n";

    expect(textLines(Diff::text($old, $new)))->toBe([' مرحبا', '-👩‍💻 code', '+👩‍💻 code!', " \xB1\xFF"]);
});

it('keeps a very long line whole', function () {
    $line = str_repeat('x', 100_000);

    expect(textLines(Diff::text("{$line}\n", "{$line}y\n")))->toBe(["-{$line}", "+{$line}y"]);
});

it('can ignore trailing whitespace', function () {
    $options = TextDiffOptions::create()->ignoreTrailingWhitespace();

    expect(Diff::text("a  \nb\t\n", "a\nb\n", $options)->hasChanges())->toBeFalse()
        ->and(Diff::text("a b\n", "a  b\n", $options)->hasChanges())->toBeTrue()
        ->and(Diff::text("a\n", " a\n", $options)->hasChanges())->toBeTrue();
});

it('can ignore changes in the amount of whitespace', function () {
    $options = TextDiffOptions::create()->ignoreWhitespaceChanges();

    expect(Diff::text("a b  \n", "a \t  b\n", $options)->hasChanges())->toBeFalse()
        ->and(Diff::text("ab\n", "a b\n", $options)->hasChanges())->toBeTrue()
        ->and(Diff::text("  a\n", "\ta\n", $options)->hasChanges())->toBeFalse()
        ->and(Diff::text("a\n", " a\n", $options)->hasChanges())->toBeTrue();
});

it('keeps the old content of lines that only differ in ignored whitespace', function () {
    expect(textLines(Diff::text("a  \n", "a\n", TextDiffOptions::create()->ignoreTrailingWhitespace())))->toBe([' a  ']);
});

it('returns added and removed lines and stats', function () {
    $result = Diff::text("a\nb\nc\n", "a\nB\nc\nd\n");

    expect(array_map(fn (DiffLine $l) => $l->content(), $result->addedLines()))->toBe(['B', 'd'])
        ->and(array_map(fn (DiffLine $l) => $l->content(), $result->removedLines()))->toBe(['b'])
        ->and($result->stats())->toBe(['added' => 2, 'removed' => 1, 'modified' => 0, 'unchanged' => 2])
        ->and($result->hasChanges())->toBeTrue()
        ->and($result->lines()[1]->toArray())->toBe(['type' => 'removed', 'content' => 'b', 'old_line' => 2, 'new_line' => null, 'inline' => []]);
});

it('rejects texts above the limits on either side', function (string $old, string $new, string $message) {
    expect(fn () => Diff::text($old, $new, TextDiffOptions::create()->maxLines(2)->maxInputBytes(10)))
        ->toThrow(InputLimitExceededException::class, $message);
})->with([
    'old lines' => ["a\nb\nc", 'a', 'The old text has 3 lines, more than the maximum of 2.'],
    'new lines' => ['a', "a\nb\nc\n", 'The new text has 3 lines, more than the maximum of 2.'],
    'old bytes' => [str_repeat('x', 11), 'a', 'The old text has 11 bytes, more than the maximum of 10.'],
    'new bytes' => ['a', str_repeat('x', 11), 'The new text has 11 bytes, more than the maximum of 10.'],
]);

it('accepts texts exactly at the limits', function () {
    expect(Diff::text("a\nb\n", "abcdefghij", TextDiffOptions::create()->maxLines(2)->maxInputBytes(10))->hasChanges())->toBeTrue();
});

it('produces the same lines for the same input', function () {
    $old = implode("\n", array_map(fn ($i) => "line {$i}", range(1, 300)));
    $new = str_replace(['line 5', 'line 77'], ['line five', 'gone'], $old);

    expect(textLines(Diff::text($old, $new)))->toBe(textLines(Diff::text($old, $new)));
});
