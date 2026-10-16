<?php

declare(strict_types=1);

/*
 * Times the cases from the package brief and reports wall time and peak memory for each.
 *
 *     php benchmarks/run.php [filter]
 *
 * Each case runs in a fresh child process so peak memory is its own.
 */

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\DiffOptions;
use Pharaonic\Diff\TextDiffOptions;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @return array<string, Closure(): void>
 */
function cases(): array
{
    $map = static fn (int $n, string $suffix = ''): array => array_combine(
        array_map(static fn (int $i): string => "key{$i}", range(1, $n)),
        array_map(static fn (int $i): string => "value {$i}{$suffix}", range(1, $n)),
    );
    $deep = static function (int $depth, mixed $leaf): array {
        $value = $leaf;

        for ($i = 0; $i < $depth; $i++) {
            $value = ['level' => $value, 'sibling' => $i];
        }

        return $value;
    };
    $records = static fn (int $n, string $name = 'user'): array => array_map(
        static fn (int $i): array => ['id' => $i, 'name' => "{$name} {$i}", 'email' => "u{$i}@example.com", 'tags' => ['a', 'b']],
        range(1, $n),
    );
    $text = static fn (int $lines, string $word = 'line'): string => implode("\n", array_map(
        static fn (int $i): string => "{$word} {$i}: lorem ipsum dolor sit amet",
        range(1, $lines),
    )) . "\n";
    $edit = static function (string $text, int $every): string {
        $lines = explode("\n", $text);

        foreach ($lines as $i => $line) {
            if ($i % $every === 0 && $line !== '') {
                $lines[$i] = $line . ' changed';
            }
        }

        return implode("\n", $lines);
    };
    $identity = DiffOptions::create()->identify('', 'id')->detectMoves();

    return [
        'structured: 100 keys, 10 changed' => fn () => Diff::between($map(100), array_replace($map(100), array_slice($map(10, '!'), 0, 10))),
        'structured: 1,000 keys, all changed' => fn () => Diff::between($map(1000), $map(1000, '!')),
        'structured: 1,000 keys, unchanged' => fn () => Diff::between($map(1000), $map(1000)),
        'structured: depth 60, leaf changed' => fn () => Diff::between($deep(60, 1), $deep(60, 2)),
        'structured: 1,000 scalars, head insertion' => fn () => Diff::between(range(1, 1000), [0, ...range(1, 1000)]),
        'structured: 1,000 scalars, middle insertion' => fn () => Diff::between(range(1, 1000), [...range(1, 500), 0, ...range(501, 1000)]),
        'structured: 1,000 records, head insertion' => fn () => Diff::between($records(1000), [['id' => 0, 'name' => 'x', 'email' => 'x', 'tags' => []], ...$records(1000)]),
        'structured: 1,000 records, all modified, no identity' => fn () => Diff::between($records(1000), $records(1000, 'person')),
        'structured: 1,000 identity records, head insertion' => fn () => Diff::between($records(1000), [['id' => 0, 'name' => 'x', 'email' => 'x', 'tags' => []], ...$records(1000)], $identity),
        'structured: 10,000 records that never pair' => fn () => Diff::between(
            array_map(static fn (int $i): array => ['id' => $i, 'name' => "n{$i}"], range(1, 10000)),
            array_map(static fn (int $i): array => ['sku' => "s{$i}", 'price' => $i], range(1, 10000)),
        ),
        'structured: 1,000 identity records, reversed' => fn () => Diff::between($records(1000), array_reverse($records(1000)), $identity),
        'text: 100 lines, every 10th changed' => fn () => Diff::text($text(100), $edit($text(100), 10))->toHtml(),
        'text: 1,000 lines, every 10th changed' => fn () => Diff::text($text(1000), $edit($text(1000), 10))->toHtml(),
        'text: 10,000 lines, every 100th changed' => fn () => Diff::text($text(10000), $edit($text(10000), 100))->toUnified(),
        'text: 10,000 lines, identical' => fn () => Diff::text($text(10000), $text(10000))->toUnified(),
        'text: 2,000 lines, totally different' => fn () => Diff::text($text(2000), $text(2000, 'other'))->toUnified(),
        'text: 5,000 lines, totally different' => fn () => Diff::text($text(5000), $text(5000, 'other'))->toUnified(),
        'text: 20,000 lines, totally different (default limit)' => fn () => Diff::text($text(20000), $text(20000, 'other'))->toUnified(),
        'text: 4 MiB in 20,000 different lines (default limits)' => fn () => Diff::text(
            implode("\n", array_map(static fn (int $i): string => "a{$i} " . str_repeat('lorem ', 33), range(1, 20000))) . "\n",
            implode("\n", array_map(static fn (int $i): string => "b{$i} " . str_repeat('ipsum ', 33), range(1, 20000))) . "\n",
        )->toHtml(),
        'text: 10,000 repeated lines, shuffled' => function () {
            mt_srand(1);
            $old = implode("\n", array_map(static fn () => ['{', '}', '', 'return;'][mt_rand(0, 3)], range(1, 10000)));
            $new = implode("\n", array_map(static fn () => ['{', '}', '', 'return;'][mt_rand(0, 3)], range(1, 10000)));
            Diff::text($old, $new)->toUnified();
        },
        'text: long lines (100 KB), one changed' => fn () => Diff::text(str_repeat('x', 100_000) . "\n", str_repeat('x', 99_999) . "y\n")->toHtml(),
        'text: 1,000 Unicode lines, character mode' => fn () => Diff::text(
            str_repeat("مرحبا بالعالم 👩‍💻 café\n", 1000),
            str_repeat("مرحبا يا عالم 👩‍💻 cafe\n", 1000),
            TextDiffOptions::create()->inline('character'),
        )->toHtml(),
        'text: 500 inline-heavy lines (1,000 bytes)' => fn () => Diff::text(
            str_repeat(str_repeat('word ', 200) . "\n", 500),
            str_repeat(str_repeat('word ', 199) . "drow \n", 500),
        )->toHtml(),
    ];
}

if (($argv[1] ?? '') === '--case') {
    $case = cases()[$argv[2]];
    $start = hrtime(true);
    $case();
    printf("%.1f %.1f\n", (hrtime(true) - $start) / 1e6, memory_get_peak_usage(true) / 1048576);

    exit(0);
}

printf("PHP %s\n\n%-55s %10s %10s\n", PHP_VERSION, 'case', 'ms', 'peak MB');

foreach (array_keys(cases()) as $name) {
    if (isset($argv[1]) && stripos($name, $argv[1]) === false) {
        continue;
    }

    $output = shell_exec(sprintf('%s %s --case %s', escapeshellarg(PHP_BINARY), escapeshellarg(__FILE__), escapeshellarg($name)));
    [$ms, $mb] = explode(' ', trim((string) $output)) + ['?', '?'];
    printf("%-55s %10s %10s\n", $name, $ms, $mb);
}
