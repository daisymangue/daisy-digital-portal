<?php

namespace App\Support;

class Money
{
    /** Format whole cents as rand, e.g. 450000 => "R4 500". */
    public static function format(?int $cents): string
    {
        if ($cents === null) {
            return 'Quote';
        }
        $decimals = $cents % 100 === 0 ? 0 : 2;

        return 'R'.number_format($cents / 100, $decimals, '.', ' ');
    }

    /** Price label with "From" and unit, e.g. "From R1 500 / month". */
    public static function label(?int $cents, bool $isFrom = false, ?string $unit = null): string
    {
        if ($cents === null) {
            return 'Quote on request';
        }

        return ($isFrom ? 'From ' : '').self::format($cents).($unit ? ' / '.$unit : '');
    }

    /** Turn typed rand input ("1 500", "R450.50") into cents. Empty input means "quote". */
    public static function toCents(?string $input): ?int
    {
        $clean = preg_replace('/[^\d.]/', '', (string) $input);
        if ($clean === '' || $clean === null) {
            return null;
        }

        return (int) round(((float) $clean) * 100);
    }
}
