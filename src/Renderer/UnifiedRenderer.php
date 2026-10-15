<?php

declare(strict_types=1);

namespace Pharaonic\Diff\Renderer;

use InvalidArgumentException;
use Pharaonic\Diff\ChangeType;
use Pharaonic\Diff\TextDiffResult;

/**
 * Renders a text diff as a unified diff that `git apply` and `patch` accept
 *
 * Lines end with "\n". A side's last line without a line ending is followed by
 * "\ No newline at end of file". Equal texts render as an empty string.
 *
 * @internal
 */
final class UnifiedRenderer
{
    private const NO_NEWLINE = "\\ No newline at end of file\n";

    public static function render(TextDiffResult $result, string $oldLabel, string $newLabel): string
    {
        foreach ([$oldLabel, $newLabel] as $label) {
            if (strpbrk($label, "\r\n") !== false) {
                throw new InvalidArgumentException('A unified diff label cannot contain a line ending.');
            }
        }

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
        $markers = [ChangeType::UNCHANGED => ' ', ChangeType::REMOVED => '-', ChangeType::ADDED => '+'];
        $output = "--- {$oldLabel}\n+++ {$newLabel}\n";

        foreach ($hunks as $hunk) {
            $output .= sprintf(
                "@@ -%s +%s @@\n",
                self::range($hunk->oldStart(), $hunk->oldLength()),
                self::range($hunk->newStart(), $hunk->newLength()),
            );

            foreach ($hunk->lines() as $line) {
                $output .= $markers[$line->type()] . $line->content() . "\n";

                if (
                    ($oldMissing && $line->type() !== ChangeType::ADDED && $line->oldLineNumber() === $oldLast)
                    || ($newMissing && $line->type() === ChangeType::ADDED && $line->newLineNumber() === $newLast)
                ) {
                    $output .= self::NO_NEWLINE;
                }
            }
        }

        return $output;
    }

    /**
     * "start,length", leaving out a length of 1 as Git and GNU diff do
     */
    private static function range(int $start, int $length): string
    {
        return $length === 1 ? (string) $start : "{$start},{$length}";
    }
}
