<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks;

/**
 * Generates hierarchical numbering strings for agenda blocks.
 *
 * Depth 0 → "1.0", "2.0", ...
 * Depth 1 → "1.1", "1.2", ...
 * Depth 2 → "i)", "ii)", ...
 * Depth 3 → "a)", "b)", ...
 */
final class Numbering
{
    /** @var array<int, int> */
    private array $counters = [];

    public function next(int $depth): string
    {
        // Reset all counters deeper than the current depth.
        foreach (array_keys($this->counters) as $d) {
            if ($d > $depth) {
                unset($this->counters[$d]);
            }
        }

        $this->counters[$depth] = ($this->counters[$depth] ?? 0) + 1;
        $n = $this->counters[$depth];

        return match ($depth) {
            0 => $n.'.0',
            1 => ($this->counters[0] ?? 1).'.'.$n,
            2 => self::toRoman($n).')',
            3 => self::toLetter($n).')',
            default => str_repeat(' ', $depth - 3).'-',
        };
    }

    public function reset(): void
    {
        $this->counters = [];
    }

    private static function toRoman(int $n): string
    {
        static $map = [
            1000 => 'm', 900 => 'cm', 500 => 'd', 400 => 'cd',
            100 => 'c', 90 => 'xc', 50 => 'l', 40 => 'xl',
            10 => 'x', 9 => 'ix', 5 => 'v', 4 => 'iv', 1 => 'i',
        ];

        $result = '';
        foreach ($map as $value => $numeral) {
            while ($n >= $value) {
                $result .= $numeral;
                $n -= $value;
            }
        }

        return $result;
    }

    private static function toLetter(int $n): string
    {
        $letters = '';
        while ($n > 0) {
            $n--;
            $letters = chr(97 + ($n % 26)).$letters;
            $n = intdiv($n, 26);
        }

        return $letters;
    }
}
