<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Log;

class TelephonyLogger
{
    public function debug(string $component, string $message, array $context = []): void
    {
        $this->write('debug', $component, $message, $context);
    }

    public function info(string $component, string $message, array $context = []): void
    {
        $this->write('info', $component, $message, $context);
    }

    public function warning(string $component, string $message, array $context = []): void
    {
        $this->write('warning', $component, $message, $context);
    }

    public function error(string $component, string $message, array $context = []): void
    {
        $this->write('error', $component, $message, $context);
    }

    public function event(string $component, string $eventType, string $message, array $context = []): void
    {
        $payload = $this->sanitizeContext(array_merge($context, ['event_type' => $eventType]));
        $message = $this->sanitizeString($message);
        $this->write('info', $component, $message, $payload);
        Log::channel('telephony-events')->info($message, array_merge([
            'component' => $component,
            'timestamp' => now()->toIso8601String(),
        ], $payload));
    }

    private function write(string $level, string $component, string $message, array $context): void
    {
        $message = $this->sanitizeString($message);
        $payload = array_merge([
            'component' => $component,
            'timestamp' => now()->toIso8601String(),
        ], $this->sanitizeContext($context));

        Log::channel('telephony')->{$level}($message, $payload);
        if ($level === 'error') {
            Log::channel('telephony-errors')->error($message, $payload);
        }
    }

    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $normalizedKey = strtolower(str_replace(['-', '_'], '', (string) $key));
            $isCredential = preg_match('/(?:pass|password|secret|token|credentials?|authorization|apikey)$/', $normalizedKey) === 1;
            $isIdentityOrCustomerData = in_array($normalizedKey, [
                'user', 'username', 'agentuser', 'viciuser', 'apiuser', 'leadid', 'phone',
                'phonenumber', 'email', 'clientname',
            ], true);
            if ($isCredential || $isIdentityOrCustomerData) {
                $sanitized[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function sanitizeString(string $value): string
    {
        $value = preg_replace(
            '/(^|[?&\\s])((?:user|username|agent_user|vici_user|api_user|pass|password|vici_pass|api_pass|secret|webhook_secret|token|access_token|refresh_token|api_key|authorization|credentials|phone|phone_number|email|lead_id)=)[^&\\s"<>]*/i',
            '$1$2[redacted]',
            $value,
        ) ?? $value;

        return preg_replace('/(authorization\\s*:\\s*bearer\\s+)[^\\s,;]+/i', '$1[redacted]', $value) ?? $value;
    }
}
