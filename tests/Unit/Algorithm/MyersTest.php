<?php

declare(strict_types=1);

use Pharaonic\Diff\Algorithm\Myers;

/**
 * @param list<int|string> $a
 * @param list<int|string> $b
 */
function lcsLength(array $a, array $b): int
{
    $previous = array_fill(0, count($b) + 1, 0);

    foreach ($a as $x) {
        $current = [0];

        foreach ($b as $j => $y) {
            $current[] = $x === $y ? $previous[$j] + 1 : max($previous[$j + 1], $current[$j]);
        }

        $previous = $current;
    }

    return $previous[count($b)];
}

/**
 * Check every invariant of an edit script and return it as a compact string
 *
 * @param list<int|string> $a
 * @param list<int|string> $b
 */
function checkedScript(array $a, array $b): string
{
    $edits = Myers::diff($a, $b);
    $rebuilt = [];
    $x = $y = 0;
    $script = '';

    foreach ($edits as [$operation, $oldIndex, $newIndex]) {
        expect([$oldIndex, $newIndex])->toBe([$x, $y]);

        if ($operation === Myers::EQUAL) {
            expect($a[$x])->toBe($b[$y]);
            $rebuilt[] = $a[$x];
            $script .= '=';
            $x++;
            $y++;
        } elseif ($operation === Myers::DELETE) {
            $script .= '-';
            $x++;
        } else {
            $rebuilt[] = $b[$y];
            $script .= '+';
            $y++;
        }
    }

    expect($x)->toBe(count($a))
        ->and($y)->toBe(count($b))
        ->and($rebuilt)->toBe($b)
        ->and(substr_count($script, '='))->toBe(lcsLength($a, $b))
        ->and($script)->not->toMatch('/\+-/');

    return $script;
}

it('produces the shortest edit script', function (string $a, string $b, string $script) {
    expect(checkedScript($a === '' ? [] : str_split($a), $b === '' ? [] : str_split($b)))->toBe($script);
})->with([
    'both empty' => ['', '', ''],
    'insert all' => ['', 'ab', '++'],
    'delete all' => ['ab', '', '--'],
    'equal' => ['abc', 'abc', '==='],
    'insert middle' => ['abc', 'axbc', '=+=='],
    'delete middle' => ['axbc', 'abc', '=-=='],
    'replace' => ['abc', 'axc', '=-+='],
    'prepend' => ['abc', 'xabc', '+==='],
    'append' => ['abc', 'abcx', '===+'],
    'classic' => ['abcabba', 'cbabac', '--=-=+==+'],
]);

it('keeps the invariants on generated sequences', function (int $seed) {
    mt_srand($seed);

    for ($round = 0; $round < 40; $round++) {
        $alphabet = mt_rand(1, 6);
        $a = array_map(fn () => mt_rand(1, $alphabet), range(1, mt_rand(1, 30)));
        $b = array_map(fn () => mt_rand(1, $alphabet), range(1, mt_rand(1, 30)));

        checkedScript(mt_rand(0, 9) === 0 ? [] : $a, $b);
    }
})->with([1, 2, 3, 4, 5]);

it('returns the same script for the same input', function () {
    $a = [1, 2, 1, 2, 1, 2];
    $b = [2, 1, 2, 1];

    expect(Myers::diff($a, $b))->toBe(Myers::diff($a, $b));
});

it('compares tokens strictly', function () {
    expect(checkedScript([1], ['1']))->toBe('-+');
});
