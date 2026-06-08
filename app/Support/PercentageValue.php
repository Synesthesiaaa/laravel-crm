<?php

namespace App\Support;

class PercentageValue
{
    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return self::hasSuffix($text) ? $text : $text.'%';
    }

    public static function display(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return self::normalize($value) ?? '';
    }

    private static function hasSuffix(string $value): bool
    {
        return str_ends_with(rtrim($value), '%');
    }
}
