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
        $payload = array_merge($context, ['event_type' => $eventType]);
        $this->write('info', $component, $message, $payload);
        Log::channel('telephony-events')->info($message, array_merge([
            'component' => $component,
            'timestamp' => now()->toIso8601String(),
        ], $payload));
    }

    private function write(string $level, string $component, string $message, array $context): void
    {
        $payload = array_merge([
            'component' => $component,
            'timestamp' => now()->toIso8601String(),
        ], $context);

        Log::channel('telephony')->{$level}($message, $payload);
        if ($level === 'error') {
            Log::channel('telephony-errors')->error($message, $payload);
        }
    }
}
