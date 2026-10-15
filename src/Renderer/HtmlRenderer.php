<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Renderer;

use Pharaonic\Diff\ChangeType;
use Pharaonic\Diff\DiffLine;
use Pharaonic\Diff\Hunk;
use Pharaonic\Diff\TextDiffResult;

/**
 * Renders a text diff as a GitHub-style HTML table, styled by the ph-diff-* classes
 *
 * Line numbers and +/- markers live in data-line-number and data-marker attributes, which
 * resources/diff.css shows with generated content, so copying the code does not copy them.
 * Changed pieces of modified lines are wrapped in <del> and <ins>. Every piece of text is escaped.
 * Equal texts render as an empty string.
 *
 * @internal
 */
final class HtmlRenderer
{
    private const KINDS = [
        ChangeType::UNCHANGED => ['context', ' '],
        ChangeType::REMOVED => ['removed', '-'],
        ChangeType::ADDED => ['added', '+'],
    ];

    public static function render(TextDiffResult $result): string
    {
        $hunks = $result->hunks();

        if ($hunks === []) {
            return '';
        }

        $oldLast = 0;
        $newLast = 0;

        foreach ($result->lines() as $line) {
            $oldLast = $line->oldLineNumber() ?? $oldLast;
            $newLast = $line->newLineNumber() ?? $newLast;
        }

        $oldMissing = ! $result->oldEndsWithNewline() && $oldLast > 0;
        $newMissing = ! $result->newEndsWithNewline() && $newLast > 0;
        $html = '<table class="ph-diff">';

        foreach ($hunks as $hunk) {
            $html .= '<tbody class="ph-diff-hunk">' . self::header($hunk);

            foreach ($hunk->lines() as $line) {
                $html .= self::line($line);

                if (
                    ($oldMissing && $line->type() !== ChangeType::ADDED && $line->oldLineNumber() === $oldLast)
                    || ($newMissing && $line->type() === ChangeType::ADDED && $line->newLineNumber() === $newLast)
                ) {
                    $html .= '<tr class="ph-diff-no-newline"><td class="ph-diff-number"></td><td class="ph-diff-number"></td>'
                        . '<td class="ph-diff-code">No newline at end of file</td></tr>';
                }
            }

            $html .= '</tbody>';
        }

        return $html . '</table>';
    }

    private static function header(Hunk $hunk): string
    {
        $range = static fn (int $start, int $length): string => $length === 1 ? (string) $start : "{$start},{$length}";

        return '<tr class="ph-diff-hunk-header"><td class="ph-diff-hunk-header-text" colspan="3">'
            . sprintf('@@ -%s +%s @@', $range($hunk->oldStart(), $hunk->oldLength()), $range($hunk->newStart(), $hunk->newLength()))
            . '</td></tr>';
    }

    private static function line(DiffLine $line): string
    {
        [$kind, $marker] = self::KINDS[$line->type()];

        return sprintf('<tr class="ph-diff-line ph-diff-line--%s">', $kind)
            . self::number($line->oldLineNumber())
            . self::number($line->newLineNumber())
            . sprintf('<td class="ph-diff-code"><span class="ph-diff-marker" data-marker="%s"></span>', $marker)
            . self::content($line)
            . '</td></tr>';
    }

    private static function number(?int $number): string
    {
        return $number === null
            ? '<td class="ph-diff-number"></td>'
            : sprintf('<td class="ph-diff-number" data-line-number="%d"></td>', $number);
    }

    private static function content(DiffLine $line): string
    {
        $segments = $line->inlineSegments();

        if ($segments === []) {
            return self::escape($line->content());
        }

        $html = '';

        foreach ($segments as $segment) {
            $text = self::escape($segment->text());

            if ($segment->type() === ChangeType::REMOVED) {
                $html .= '<del class="ph-diff-inline--removed">' . $text . '</del>';
            } elseif ($segment->type() === ChangeType::ADDED) {
                $html .= '<ins class="ph-diff-inline--added">' . $text . '</ins>';
            } else {
                $html .= $text;
            }
        }

        return $html;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
