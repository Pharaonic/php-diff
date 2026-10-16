<?php

declare(strict_types=1);

use Pharaonic\Diff\InlineMode;
use Pharaonic\Diff\TextDiffOptions;

it('has documented defaults', function () {
    $options = TextDiffOptions::create();

    expect($options->contextLines())->toBe(3)
        ->and($options->pairsModifiedLines())->toBeTrue()
        ->and($options->threshold())->toBe(0.55)
        ->and($options->inlineMode())->toBe(InlineMode::WORD)
        ->and($options->ignoresWhitespaceChanges())->toBeFalse()
        ->and($options->ignoresTrailingWhitespace())->toBeFalse()
        ->and($options->lineLimit())->toBe(20_000)
        ->and($options->byteLimit())->toBe(4_194_304)
        ->and($options->inlineLengthLimit())->toBe(1_000);
});

it('returns new options and leaves the original unchanged', function () {
    $original = TextDiffOptions::create();

    $configured = $original
        ->context(0)
        ->pairModifiedLines(false)
        ->similarityThreshold(1.0)
        ->inline(InlineMode::CHARACTER)
        ->ignoreWhitespaceChanges()
        ->ignoreTrailingWhitespace()
        ->maxLines(1)
        ->maxInputBytes(2)
        ->maxInlineLength(3);

    expect($original)->toEqual(TextDiffOptions::create())
        ->and($configured->contextLines())->toBe(0)
        ->and($configured->pairsModifiedLines())->toBeFalse()
        ->and($configured->threshold())->toBe(1.0)
        ->and($configured->inlineMode())->toBe(InlineMode::CHARACTER)
        ->and($configured->ignoresWhitespaceChanges())->toBeTrue()
        ->and($configured->ignoresTrailingWhitespace())->toBeTrue()
        ->and($configured->lineLimit())->toBe(1)
        ->and($configured->byteLimit())->toBe(2)
        ->and($configured->inlineLengthLimit())->toBe(3);
});

it('rejects invalid configuration', function (string $method, array $arguments, string $message) {
    expect(fn () => TextDiffOptions::create()->{$method}(...$arguments))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'context' => ['context', [-1], 'The context must be at least 0, -1 given.'],
    'threshold above 1' => ['similarityThreshold', [1.1], 'The similarity threshold must be between 0 and 1.'],
    'threshold below 0' => ['similarityThreshold', [-0.1], 'The similarity threshold must be between 0 and 1.'],
    'NAN threshold' => ['similarityThreshold', [NAN], 'The similarity threshold must be between 0 and 1.'],
    'inline mode' => ['inline', ['line'], 'Unknown inline mode "line"; use an InlineMode constant.'],
    'max lines' => ['maxLines', [0], 'The maximum number of lines must be at least 1, 0 given.'],
    'max bytes' => ['maxInputBytes', [0], 'The maximum number of bytes must be at least 1, 0 given.'],
    'max inline length' => ['maxInlineLength', [0], 'The maximum inline length must be at least 1, 0 given.'],
]);
