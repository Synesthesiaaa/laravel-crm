<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Log;
use PAMI\Client\Exception\ClientException;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\OriginateAction;

class AsteriskAmiService
{
    /**
     * Build PAMI client options from config.
     */
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

    /**
     * Originate a call via Asterisk AMI.
     *
     * @return array{success: bool, message: ?string}
     */
    public function originate(string $channel, string $number, string $callerId = ''): array
    {
        if (config('asterisk.secret') === '') {
            Log::warning('AsteriskAmiService: AMI secret not configured');
            return ['success' => false, 'message' => 'AMI not configured'];
        }

        $callerId = $callerId ?: 'Web Call ' . $number;

        try {
            $client = new ClientImpl($this->clientOptions());
            $client->open();

            $action = new OriginateAction($channel);
            $action->setExtension($number);
            $action->setContext('from-internal');
            $action->setPriority('1');
            $action->setCallerId($callerId);

            $response = $client->send($action);
            $client->close();

            $success = $response->isSuccess();
            $message = $success ? null : $response->getMessage();

            if (!$success) {
                Log::warning('AsteriskAmiService: Originate failed', [
                    'channel' => $channel,
                    'number' => $number,
                    'response' => $message,
                ]);
            }

            return ['success' => $success, 'message' => $message];
        } catch (ClientException $e) {
            Log::warning('AsteriskAmiService: AMI error', [
                'error' => $e->getMessage(),
                'channel' => $channel,
                'number' => $number,
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
