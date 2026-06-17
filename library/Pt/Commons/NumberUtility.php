<?php

final class Pt_Commons_NumberUtility
{
    /**
     * Round a value only when it is actually numeric.
     *
     * Guards against PHP 8 round() TypeErrors on null / '' / non-numeric
     * strings by returning $default instead of calling round(). A genuine 0
     * is numeric and is rounded normally, so is_numeric() is used rather
     * than !empty().
     */
    public static function safeRound(mixed $value, int $decimals = 2, mixed $default = ''): mixed
    {
        return is_numeric($value) ? round((float) $value, $decimals) : $default;
    }

    /**
     * Format a value as a fixed-decimal string for report cells (keeps
     * trailing zeros, e.g. "3.50"). Non-numeric input returns $placeholder.
     */
    public static function decimal(mixed $value, int $decimals = 2, string $placeholder = '-'): string
    {
        if (!is_numeric($value)) {
            return $placeholder;
        }

        return number_format(round((float) $value, $decimals), $decimals, '.', '');
    }
}
