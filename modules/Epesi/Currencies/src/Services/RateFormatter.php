<?php

namespace Epesi\Modules\Currencies\Services;

/**
 * An exchange rate for display, by significant digits rather than a fixed
 * number of decimals: 2 dp shows HUF -> EUR (0.00257) as "0.00", and even 5 dp
 * is 4% out on IDR -> EUR. Rates are stored unrounded and rounded only here.
 */
class RateFormatter
{
    public static function format(float|int|string|null $rate, int $significant = 6): string
    {
        if ($rate === null || $rate === '' || ! is_numeric($rate)) {
            return '';
        }

        $rate = (float) $rate;

        if ($rate == 0.0) {
            return '0.00';
        }

        // Decimals that keep $significant digits whatever the magnitude:
        // 25.34 -> 4, 1.0856 -> 5, 0.0000578 -> 10. Never fewer than 2, never
        // more than a double holds.
        $decimals = max(2, min(12, $significant - 1 - (int) floor(log10(abs($rate)))));

        $out = rtrim(number_format($rate, $decimals, '.', ''), '0');

        // Keep at least 2 decimals, so 1 reads "1.00".
        return number_format((float) $out, max(2, strlen($out) - strpos($out, '.') - 1), '.', '');
    }
}
