<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Algorithm;

/**
 * Myers' O(ND) shortest edit script, in linear space
 *
 * Tokens are compared with ===. Common prefixes and suffixes are stripped first, and inside
 * every run of edits the deletions come before the insertions, as in Git.
 *
 * @internal
 */
final class Myers
{
    public const EQUAL = 0;

    public const DELETE = 1;

    public const INSERT = 2;

    /**
     * @var list<int|string>
     */
    private array $a;

    /**
     * @var list<int|string>
     */
    private array $b;

    /**
     * @param list<int|string> $a
     * @param list<int|string> $b
     */
    private function __construct(array $a, array $b)
    {
        $this->a = $a;
        $this->b = $b;
    }

    /**
     * The edits that turn $a into $b, as [operation, index in $a, index in $b]; the index of the
     * side an edit does not touch is where it would be inserted.
     *
     * @param list<int|string> $a
     * @param list<int|string> $b
     *
     * @return list<array{int, int, int}>
     */
    public static function diff(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $start = 0;

        while ($start < $n && $start < $m && $a[$start] === $b[$start]) {
            $start++;
        }

        $end = 0;

        while ($end < $n - $start && $end < $m - $start && $a[$n - 1 - $end] === $b[$m - 1 - $end]) {
            $end++;
        }

        $edits = [];

        for ($i = 0; $i < $start; $i++) {
            $edits[] = [self::EQUAL, $i, $i];
        }

        $myers = new self(array_slice($a, $start, $n - $start - $end), array_slice($b, $start, $m - $start - $end));

        foreach ($myers->edits() as [$operation, $x, $y]) {
            $edits[] = [$operation, $x + $start, $y + $start];
        }

        for ($i = $end; $i > 0; $i--) {
            $edits[] = [self::EQUAL, $n - $i, $m - $i];
        }

        return self::deletionsFirst($edits);
    }

    /**
     * @return list<array{int, int, int}>
     */
    private function edits(): array
    {
        $n = count($this->a);
        $m = count($this->b);

        if ($n === 0 || $m === 0) {
            $edits = [];

            for ($x = 0; $x < $n; $x++) {
                $edits[] = [self::DELETE, $x, 0];
            }

            for ($y = 0; $y < $m; $y++) {
                $edits[] = [self::INSERT, $n, $y];
            }

            return $edits;
        }

        $path = $this->path(0, 0, $n, $m) ?? [[0, 0], [$n, $m]];
        $edits = [];

        for ($i = 1, $count = count($path); $i < $count; $i++) {
            [$x1, $y1] = $path[$i - 1];
            [$x2, $y2] = $path[$i];

            [$x1, $y1] = $this->diagonal($x1, $y1, $x2, $y2, $edits);

            if ($x2 - $x1 < $y2 - $y1) {
                $edits[] = [self::INSERT, $x1, $y1];
                $y1++;
            } elseif ($x2 - $x1 > $y2 - $y1) {
                $edits[] = [self::DELETE, $x1, $y1];
                $x1++;
            }

            $this->diagonal($x1, $y1, $x2, $y2, $edits);
        }

        return $edits;
    }

    /**
     * @param list<array{int, int, int}> $edits
     *
     * @return array{int, int}
     */
    private function diagonal(int $x1, int $y1, int $x2, int $y2, array &$edits): array
    {
        while ($x1 < $x2 && $y1 < $y2 && $this->a[$x1] === $this->b[$y1]) {
            $edits[] = [self::EQUAL, $x1, $y1];
            $x1++;
            $y1++;
        }

        return [$x1, $y1];
    }

    /**
     * The points of the shortest path through a box, split at its middle snake
     *
     * @return list<array{int, int}>|null
     */
    private function path(int $left, int $top, int $right, int $bottom): ?array
    {
        $snake = $this->midpoint($left, $top, $right, $bottom);

        if ($snake === null) {
            return null;
        }

        [$start, $finish] = $snake;

        $head = $this->path($left, $top, $start[0], $start[1]);
        $tail = $this->path($finish[0], $finish[1], $right, $bottom);

        return [...($head ?? [$start]), ...($tail ?? [$finish])];
    }

    /**
     * @return array{array{int, int}, array{int, int}}|null
     */
    private function midpoint(int $left, int $top, int $right, int $bottom): ?array
    {
        $width = $right - $left;
        $height = $bottom - $top;
        $size = $width + $height;

        if ($size === 0) {
            return null;
        }

        $delta = $width - $height;
        $max = intdiv($size + 1, 2);
        $forward = [1 => $left];
        $backward = [1 => $bottom];

        for ($d = 0; $d <= $max; $d++) {
            for ($k = $d; $k >= -$d; $k -= 2) {
                $c = $k - $delta;

                if ($k === -$d || ($k !== $d && $forward[$k - 1] < $forward[$k + 1])) {
                    $px = $x = $forward[$k + 1];
                } else {
                    $px = $forward[$k - 1];
                    $x = $px + 1;
                }

                $y = $top + ($x - $left) - $k;
                $py = ($d === 0 || $x !== $px) ? $y : $y - 1;

                while ($x < $right && $y < $bottom && $this->a[$x] === $this->b[$y]) {
                    $x++;
                    $y++;
                }

                $forward[$k] = $x;

                if ($delta % 2 !== 0 && $c >= -($d - 1) && $c <= $d - 1 && $y >= $backward[$c]) {
                    return [[$px, $py], [$x, $y]];
                }
            }

            for ($c = $d; $c >= -$d; $c -= 2) {
                $k = $c + $delta;

                if ($c === -$d || ($c !== $d && $backward[$c - 1] > $backward[$c + 1])) {
                    $py = $y = $backward[$c + 1];
                } else {
                    $py = $backward[$c - 1];
                    $y = $py - 1;
                }

                $x = $left + ($y - $top) + $k;
                $px = ($d === 0 || $y !== $py) ? $x : $x + 1;

                while ($x > $left && $y > $top && $this->a[$x - 1] === $this->b[$y - 1]) {
                    $x--;
                    $y--;
                }

                $backward[$c] = $y;

                if ($delta % 2 === 0 && $k >= -$d && $k <= $d && $x <= $forward[$k]) {
                    return [[$x, $y], [$px, $py]];
                }
            }
        }

        return null;
    }

    /**
     * Put the deletions of every run of edits before its insertions
     *
     * @param list<array{int, int, int}> $edits
     *
     * @return list<array{int, int, int}>
     */
    private static function deletionsFirst(array $edits): array
    {
        $sorted = [];
        $run = [];

        foreach ($edits as $edit) {
            if ($edit[0] === self::EQUAL) {
                self::flush($run, $sorted);
                $sorted[] = $edit;
            } else {
                $run[] = $edit;
            }
        }

        self::flush($run, $sorted);

        return $sorted;
    }

    /**
     * @param list<array{int, int, int}> $run
     * @param list<array{int, int, int}> $sorted
     */
    private static function flush(array &$run, array &$sorted): void
    {
        if ($run === []) {
            return;
        }

        [, $x, $y] = $run[0];
        $insertions = [];

        foreach ($run as [$operation, $oldIndex, $newIndex]) {
            if ($operation === self::DELETE) {
                $sorted[] = [self::DELETE, $oldIndex, $y];
                $x++;
            } else {
                $insertions[] = $newIndex;
            }
        }

        foreach ($insertions as $newIndex) {
            $sorted[] = [self::INSERT, $x, $newIndex];
        }

        $run = [];
    }
}
