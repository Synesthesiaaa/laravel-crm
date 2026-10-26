<?php

namespace App\Support;

final class FormFieldVisibility
{
    /**
     * Match the scalar and array operators used by resources/js/form-visibility.js.
     * An absent or incomplete rule is visible, preserving existing field behavior.
     *
     * @param  array<string, mixed>  $values
     */
    public static function isVisible(mixed $rule, array $values): bool
    {
        if (! is_array($rule)) {
            return true;
        }

        $sourceField = trim((string) ($rule['field'] ?? ''));
        $operator = trim((string) ($rule['operator'] ?? ''));
        $targets = self::splitValues($rule['values'] ?? []);

        if ($sourceField === '' || $operator === '' || $targets === []) {
            return true;
        }

        $currentValue = $values[$sourceField] ?? null;
        $currentValueString = is_array($currentValue) ? '' : self::stringValue($currentValue);
        $currentValues = is_array($currentValue) ? self::splitValues($currentValue) : [];

        return match ($operator) {
            'equals' => is_array($currentValue)
                ? in_array($targets[0], $currentValues, true)
                : $currentValueString === $targets[0],
            'not_equals' => is_array($currentValue)
                ? ! in_array($targets[0], $currentValues, true)
                : $currentValueString !== $targets[0],
            'in' => is_array($currentValue)
                ? array_intersect($targets, $currentValues) !== []
                : in_array($currentValueString, $targets, true),
            'not_in' => is_array($currentValue)
                ? array_intersect($targets, $currentValues) === []
                : ! in_array($currentValueString, $targets, true),
            default => true,
        };
    }

    /**
     * @return list<string>
     */
    private static function splitValues(mixed $value): array
    {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                array_push($items, ...self::splitValues($item));
            }

            return array_values(array_unique(array_filter($items, static fn (string $item): bool => $item !== '')));
        }

        if (! is_scalar($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', self::stringValue($value)) ?: []),
            static fn (string $item): bool => $item !== '',
        ));
    }

    private static function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return trim((string) $value);
    }
}
