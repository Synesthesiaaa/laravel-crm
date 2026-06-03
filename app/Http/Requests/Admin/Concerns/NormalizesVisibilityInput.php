<?php

namespace App\Http\Requests\Admin\Concerns;

trait NormalizesVisibilityInput
{
    protected function prepareForValidation(): void
    {
        $visibility = $this->input('visibility');
        if (! is_array($visibility)) {
            return;
        }

        $rawValues = $visibility['values'] ?? null;
        if ($rawValues === null) {
            return;
        }

        $visibility['values'] = $this->coerceVisibilityValuesForValidation($rawValues);

        $this->merge(['visibility' => $visibility]);
    }

    /**
     * @return list<string>
     */
    protected function coerceVisibilityValuesForValidation(mixed $rawValues): array
    {
        if (is_string($rawValues)) {
            return [$rawValues];
        }

        if (! is_array($rawValues)) {
            return [];
        }

        $strings = [];
        foreach ($rawValues as $value) {
            if (is_array($value)) {
                foreach ($this->coerceVisibilityValuesForValidation($value) as $nested) {
                    $strings[] = $nested;
                }

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (is_scalar($value)) {
                $strings[] = (string) $value;
            }
        }

        return $strings;
    }
}
