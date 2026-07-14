<?php

namespace App\Support;

/**
 * Convert between major units (rupees) used in the UI and the integer minor
 * units (paise) stored in the database.
 */
class Money
{
    public static function toCents(int|float|string $rupees): int
    {
        return (int) round(((float) $rupees) * 100);
    }

    public static function toRupees(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
