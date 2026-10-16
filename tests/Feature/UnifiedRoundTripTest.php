<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\TextDiffOptions;

/**
 * Run a command in a directory and return its exit code and output
 *
 * @return array{int, string}
 */
function run(string $command, string $directory): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $directory);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return [proc_close($process), (string) $output];
}

/**
 * Apply a unified diff of $old to $new with a tool and return the patched text
 */
function applyWith(string $tool, string $old, string $new, int $context): string
{
    $directory = sys_get_temp_dir() . '/php-diff-' . bin2hex(random_bytes(6));
    mkdir($directory);

    try {
        file_put_contents("{$directory}/file", $old);
        file_put_contents("{$directory}/change.patch", Diff::text($old, $new, TextDiffOptions::create()->context($context))->toUnified('a/file', 'b/file'));

        // git apply only accepts patches without context lines with --unidiff-zero.
        $git = $context === 0 ? 'git apply --unidiff-zero -p1 change.patch' : 'git apply -p1 change.patch';
        [$code, $output] = run($tool === 'git' ? $git : 'patch -s -p1 -i change.patch', $directory);

        expect($code)->toBe(0, "{$tool} rejected the patch: {$output}");

        return is_file("{$directory}/file") ? (string) file_get_contents("{$directory}/file") : '';
    } finally {
        array_map('unlink', glob("{$directory}/*") ?: []);
        rmdir($directory);
    }
}

/**
 * Fixed-seed pairs of LF texts, often sharing lines, with and without a final line ending
 *
 * @return list<array{string, string}>
 */
function generatedTexts(int $seed, int $count): array
{
    mt_srand($seed);
    $words = ['alpha', 'beta', 'gamma', 'delta', '', '  indented', 'x', 'مرحبا', '👩‍💻'];
    $text = function () use ($words): string {
        $lines = [];

        for ($i = 0, $n = mt_rand(0, 25); $i < $n; $i++) {
            $lines[] = $words[mt_rand(0, count($words) - 1)];
        }

        return $lines === [] ? '' : implode("\n", $lines) . (mt_rand(0, 3) === 0 ? '' : "\n");
    };

    $pairs = [];

    for ($i = 0; $i < $count; $i++) {
        $old = $text();
        $new = mt_rand(0, 2) === 0 ? $text() : preg_replace_callback('/^.*$/m', fn($m) => mt_rand(0, 4) === 0 ? $words[mt_rand(0, 8)] : $m[0], $old);
        $pairs[] = [$old, (string) $new];
    }

    return $pairs;
}

$fixtures = [
    'modify' => ["a\nb\nc\n", "a\nB\nc\n"],
    'add at end' => ["a\n", "a\nb\n"],
    'add at start' => ["b\n", "a\nb\n"],
    'from empty' => ['', "a\nb\n"],
    'to empty' => ["a\nb\n", ''],
    'add final line ending' => ["a\nb", "a\nb\n"],
    'remove final line ending' => ["a\nb\n", "a\nb"],
    'change last line without line ending' => ["a\nb", "a\nc"],
    'blank lines' => ["\n\n\n", "\n\nx\n\n"],
    'far apart' => [implode("\n", range(1, 40)) . "\n", str_replace(["\n5\n", "\n35\n"], ["\nfive\n", "\n"], implode("\n", range(1, 40)) . "\n")],
];

it('produces patches that apply with each tool', function (string $tool, int $context, string $old, string $new) {
    expect(applyWith($tool, $old, $new, $context))->toBe($new);
})->with(['git', 'patch'])->with([0, 1, 3])->with($fixtures);

it('produces patches that apply on generated texts', function (string $tool, int $seed) {
    foreach (generatedTexts($seed, 40) as [$old, $new]) {
        if ($old !== $new) {
            expect(applyWith($tool, $old, $new, $seed % 4))->toBe($new);
        }
    }
})->with(['git', 'patch'])->with([11, 12, 13, 14, 15]);

it('counts the same added and removed lines as git', function (int $seed) {
    $directory = sys_get_temp_dir() . '/php-diff-' . bin2hex(random_bytes(6));
    mkdir($directory);

    try {
        foreach (generatedTexts($seed, 40) as [$old, $new]) {
            file_put_contents("{$directory}/a", $old);
            file_put_contents("{$directory}/b", $new);
            [, $output] = run('git diff --no-index --numstat a b', $directory);
            $stats = Diff::text($old, $new)->stats();
            $expected = $output === '' ? '0 0' : implode(' ', array_slice(preg_split('/\s+/', $output) ?: [], 0, 2));

            expect("{$stats['added']} {$stats['removed']}")->toBe($expected);
        }
    } finally {
        array_map('unlink', glob("{$directory}/*") ?: []);
        rmdir($directory);
    }
})->with([21, 22, 23, 24, 25]);
