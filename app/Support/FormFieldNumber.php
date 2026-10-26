<?php

namespace App\Support;

final class FormFieldNumber
{
    /** DECIMAL(65, 2) allows 63 integer digits and two fractional digits. */
    private const DECIMAL_PATTERN = '/^[+-]?\d{1,63}(?:\.\d{1,2})?$/D';

    public static function isIdentifierLike(string $name): bool
    {
        return preg_match('/(^|_)(phone|mobile|telephone)($|_)/i', $name) === 1
            || preg_match('/(^|_)(id|code|number|no|num)$/i', $name) === 1
            || in_array(strtolower($name), ['email', 'zip', 'postal_code'], true);
    }

    public static function isValidDecimal(mixed $value): bool
    {
        return (is_string($value) || is_int($value) || is_float($value))
            && preg_match(self::DECIMAL_PATTERN, trim((string) $value)) === 1;
    }
}
