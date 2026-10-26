<?php

namespace App\Support;

final class CsvCellEncoder
{
    public static function encode(mixed $value, bool $allowNumeric = false): string
    {
        if ($value === null) {
            return '';
        }

        $text = is_scalar($value) ? (string) $value : (string) json_encode($value);
        if ($allowNumeric && is_numeric($text)) {
            return $text;
        }

        $probe = preg_replace('/^[\x00-\x20\x{FEFF}]+/u', '', $text);
        if ($probe !== null && preg_match('/^[=+\-@]/u', $probe) === 1) {
            return "'".$text;
        }

        return $text;
    }
}
