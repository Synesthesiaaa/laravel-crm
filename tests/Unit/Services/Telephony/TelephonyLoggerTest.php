<?php

namespace Tests\Unit\Services\Telephony;

use App\Services\Telephony\TelephonyLogger;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class TelephonyLoggerTest extends TestCase
{
    public function test_it_redacts_credential_context_and_values_in_exception_urls(): void
    {
        $secret = 'synthetic-telephony-password';
        $phone = '15551234567';
        $email = 'customer@example.test';
        $channel = Mockery::mock();
        $channel->shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($secret, $phone, $email): bool {
                $encoded = json_encode([$message, $context], JSON_THROW_ON_ERROR);

                return ($context['password'] ?? null) === '[redacted]'
                    && ($context['phone_number'] ?? null) === '[redacted]'
                    && ($context['email'] ?? null) === '[redacted]'
                    && data_get($context, 'nested.api_pass') === '[redacted]'
                    && str_contains((string) ($context['error'] ?? ''), 'user=[redacted]')
                    && str_contains((string) ($context['error'] ?? ''), 'pass=[redacted]')
                    && str_contains((string) ($context['error'] ?? ''), 'phone_number=[redacted]')
                    && str_contains((string) ($context['error'] ?? ''), 'email=[redacted]')
                    && ! str_contains($encoded, $secret)
                    && ! str_contains($encoded, $phone)
                    && ! str_contains($encoded, $email)
                    && ! str_contains($encoded, 'synthetic-api-user');
            });
        Log::shouldReceive('channel')->once()->with('telephony')->andReturn($channel);

        app(TelephonyLogger::class)->warning(
            'VicidialProxyService',
            'Transport failure',
            [
                'password' => $secret,
                'phone_number' => $phone,
                'email' => $email,
                'nested' => ['api_pass' => $secret],
                'error' => 'cURL failure for https://example.test/api.php?user=synthetic-api-user&pass='.$secret.'&phone_number='.$phone.'&email='.$email,
            ],
        );
    }

    public function test_event_logs_apply_the_same_redaction_to_both_channels(): void
    {
        $secret = 'synthetic-event-password';
        $assertRedacted = function (string $message, array $context) use ($secret): bool {
            $encoded = json_encode([$message, $context], JSON_THROW_ON_ERROR);

            return ($context['pass'] ?? null) === '[redacted]'
                && ! str_contains($encoded, $secret);
        };
        $telephonyChannel = Mockery::mock();
        $telephonyChannel->shouldReceive('info')->once()->withArgs($assertRedacted);
        $eventsChannel = Mockery::mock();
        $eventsChannel->shouldReceive('info')->once()->withArgs($assertRedacted);
        Log::shouldReceive('channel')->once()->with('telephony')->andReturn($telephonyChannel);
        Log::shouldReceive('channel')->once()->with('telephony-events')->andReturn($eventsChannel);

        app(TelephonyLogger::class)->event(
            'VicidialEventsWebhook',
            'logged_in',
            'Event received',
            ['pass' => $secret],
        );
    }
}
