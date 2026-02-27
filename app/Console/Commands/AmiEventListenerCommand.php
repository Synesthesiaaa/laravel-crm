<?php

namespace App\Console\Commands;

use App\Services\Telephony\TelephonyLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\IncomingMessage;

/**
 * Long-running AMI event listener. Connects to Asterisk AMI, subscribes to
 * Hangup, Bridge, DialEnd events, and forwards them to the Laravel webhook.
 *
 * Run as: php artisan ami:listen
 * Use Supervisor or systemd to keep it running. Stop with Ctrl+C.
 */
class AmiEventListenerCommand extends Command
{
    public function __construct(
        protected TelephonyLogger $telephonyLogger
    ) {
        parent::__construct();
    }

    protected $signature = 'ami:listen
                            {--reconnect-delay=5 : Seconds to wait before reconnecting after disconnect}
                            {--webhook-url= : Override webhook URL (default: APP_URL/api/webhooks/ami)}';

    protected $description = 'Listen to Asterisk AMI events and forward to webhook';

    protected const FORWARD_EVENTS = [
        'Hangup',
        'HangupRequest',
        'SoftHangupRequest',
        'Bridge',
        'BridgeEnter',
        'DialEnd',
    ];

    protected ?ClientImpl $client = null;

    public function handle(): int
    {
        if (! config('asterisk.secret')) {
            $this->error('ASTERISK_AMI_SECRET is not configured. Cannot connect to AMI.');

            return self::FAILURE;
        }

        $webhookUrl = $this->option('webhook-url') ?: rtrim(config('app.url'), '/') . '/api/webhooks/ami';
        $reconnectDelay = (int) $this->option('reconnect-delay');

        $this->info("AMI Event Listener starting. Webhook: {$webhookUrl}");
        $this->info('Press Ctrl+C to stop.');

        while (true) {
            try {
                $this->runListener($webhookUrl);
            } catch (\Throwable $e) {
                $this->telephonyLogger->error('AmiEventListenerCommand', 'AMI listener crashed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("Connection lost: {$e->getMessage()}. Reconnecting in {$reconnectDelay}s...");
                sleep($reconnectDelay);
            }
        }
    }

    protected function runListener(string $webhookUrl): void
    {
        $options = $this->clientOptions();
        $this->client = new ClientImpl($options);
        $this->client->open(); // Logs in automatically

        $this->info('Connected to Asterisk AMI. Listening for events...');

        $listenerId = $this->client->registerEventListener(
            fn (IncomingMessage $event) => $this->forwardToWebhook($event, $webhookUrl),
            fn (IncomingMessage $event) => $this->shouldForward($event)
        );

        try {
            while (true) {
                $this->client->process();
                usleep(10000); // 10ms – avoid busy loop when no events
            }
        } finally {
            $this->client->unregisterEventListener($listenerId);
            $this->client->close();
            $this->client = null;
        }
    }

    protected function shouldForward(IncomingMessage $event): bool
    {
        $name = $event->getKey('Event') ?? '';

        return in_array($name, self::FORWARD_EVENTS, true);
    }

    protected function forwardToWebhook(IncomingMessage $event, string $webhookUrl): void
    {
        $payload = $this->eventToPayload($event);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $secret = config('asterisk.webhook_secret');
        if ($secret !== '') {
            $headers['X-Webhook-Secret'] = $secret;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders($headers)
                ->post($webhookUrl, $payload);

            if (! $response->successful()) {
                $this->telephonyLogger->warning('AmiEventListenerCommand', 'AMI webhook returned non-2xx', [
                    'event' => $payload['event'] ?? $payload['Event'] ?? 'unknown',
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->telephonyLogger->error('AmiEventListenerCommand', 'AMI webhook POST failed', [
                'event' => $payload['event'] ?? $payload['Event'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Convert PAMI IncomingMessage to webhook payload (AmiWebhookController format).
     */
    protected function eventToPayload(IncomingMessage $event): array
    {
        $payload = [];

        foreach ($event->getKeys() as $key => $value) {
            if ($value !== null && $value !== '') {
                $payload[$key] = $value;
            }
        }

        foreach ($event->getVariables() as $key => $value) {
            if ($value !== null && $value !== '') {
                $payload['variable_' . $key] = $value;
            }
        }

        // Ensure event/Event and linkedid/Linkedid for controller compatibility
        if (! isset($payload['Event']) && isset($payload['event'])) {
            $payload['Event'] = $payload['event'];
        } elseif (! isset($payload['event']) && isset($payload['Event'])) {
            $payload['event'] = $payload['Event'];
        }

        return $payload;
    }

    protected function clientOptions(): array
    {
        $timeout = config('asterisk.timeout', 5);
        $readTimeout = config('asterisk.read_timeout', 5000);

        return [
            'host' => config('asterisk.host'),
            'port' => config('asterisk.port'),
            'username' => config('asterisk.username'),
            'secret' => config('asterisk.secret'),
            'connect_timeout' => $timeout,
            'read_timeout' => $readTimeout,
            'scheme' => 'tcp://',
        ];
    }
}
