<?php

namespace App\Services;

use Illuminate\Contracts\Support\Arrayable;
use Traversable;

class ActivityLogSanitizer
{
    public const REDACTED = '[REDACTED]';

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public function sanitize(array $properties): array
    {
        /** @var array<string, mixed> $sanitized */
        $sanitized = $this->sanitizeValue($properties);

        return $sanitized;
    }

    private function sanitizeValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof Traversable) {
            $value = iterator_to_array($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];
        foreach ($value as $childKey => $childValue) {
            $sanitized[$childKey] = $this->sanitizeValue($childValue, (string) $childKey);
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key));

        return preg_match(
            '/(^|_)(?:password|pass|passwd|passphrase|secret|token|credential|credentials|authorization|api[_-]?key|private[_-]?key|access[_-]?key|client[_-]?secret)(_|$)/',
            $normalized,
        ) === 1;
    }
}
