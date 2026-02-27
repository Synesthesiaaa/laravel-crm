<?php

namespace App\Services\Telephony;

use PAMI\Client\Exception\ClientException;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\OriginateAction;

class AsteriskAmiService
{
    public function __construct(
        protected TelephonyLogger $telephonyLogger
    ) {}

    /**
     * Enforce SIP-only channel prefix for agent origination.
     */
    protected function getAgentChannelPrefix(): string
    {
        $configured = strtoupper((string) config('asterisk.agent_channel', 'SIP'));
        if ($configured !== 'SIP') {
            $this->telephonyLogger->error('AsteriskAmiService', 'Non-SIP agent channel configured in SIP-only mode', [
                'configured_channel' => $configured,
            ]);
        }

        return 'SIP';
    }

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
     * Check if AMI is configured (non-empty secret).
     */
    public function isConfigured(): bool
    {
        return config('asterisk.secret', '') !== '';
    }

    /**
     * Originate a call via Asterisk AMI (context/dialplan-based).
     *
     * @return array{success: bool, message: ?string}
     */
    public function originate(string $channel, string $number, string $callerId = ''): array
    {
        if (! $this->isConfigured()) {
            $this->telephonyLogger->warning('AsteriskAmiService', 'AMI secret not configured');
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

            if (! $success) {
                $this->telephonyLogger->warning('AsteriskAmiService', 'Originate failed', [
                    'channel' => $channel,
                    'number' => $number,
                    'response' => $message,
                ]);
            }

            return ['success' => $success, 'message' => $message];
        } catch (ClientException $e) {
            $this->telephonyLogger->warning('AsteriskAmiService', 'AMI error', [
                'error' => $e->getMessage(),
                'channel' => $channel,
                'number' => $number,
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Originate a WebRTC call: calls the agent's SIP extension, then bridges to outbound number.
     *
     * Flow: AMI calls SIP/{extension} → SIP.js auto-answers → Asterisk dials SIP/goip-trunk/{number}
     *
     * @param  string  $extension   Agent's SIP extension (e.g. "6001")
     * @param  string  $number      Outbound phone number to dial via GoIP trunk
     * @param  string  $callerName  Caller ID name
     * @param  int     $timeout     Seconds to wait for agent to answer (default 30)
     * @return array{success: bool, message: ?string, linkedid?: string}
     */
    public function originateWebRtc(string $extension, string $number, string $callerName = '', int $timeout = 30): array
    {
        if (! $this->isConfigured()) {
            $this->telephonyLogger->warning('AsteriskAmiService', 'AMI secret not configured');
            return ['success' => false, 'message' => 'AMI not configured'];
        }

        $trunk = config('asterisk.goip_trunk', 'goip-trunk');
        $callerId = $callerName ?: 'Agent ' . $extension;

        try {
            $client = new ClientImpl($this->clientOptions());
            $client->open();

            // SIP-only: always originate to SIP/{extension}.
            // When agent browser answers, Asterisk runs the Application (Dial) to connect to GoIP trunk.
            $channelPrefix = $this->getAgentChannelPrefix();
            $action = new OriginateAction($channelPrefix . '/' . $extension);
            $action->setApplication('Dial');
            $action->setData('SIP/' . $trunk . '/' . $number . ',' . $timeout . ',r');
            $action->setCallerId($callerId);
            $action->setAsync(true);
            $action->setVariable('CRM_AGENT', $extension);
            $action->setVariable('CRM_DEST', $number);
            $action->setTimeout($timeout * 1000);

            $response = $client->send($action);
            $client->close();

            $success = $response->isSuccess();
            $message = $success ? null : $response->getMessage();

            if (! $success) {
                $this->telephonyLogger->warning('AsteriskAmiService', 'WebRTC originate failed', [
                    'extension' => $extension,
                    'number' => $number,
                    'response' => $message,
                ]);
            } else {
                $this->telephonyLogger->info('AsteriskAmiService', 'WebRTC originate sent', [
                    'extension' => $extension,
                    'number' => $number,
                ]);
            }

            return ['success' => $success, 'message' => $message];
        } catch (ClientException $e) {
            $this->telephonyLogger->warning('AsteriskAmiService', 'WebRTC AMI error', [
                'error' => $e->getMessage(),
                'extension' => $extension,
                'number' => $number,
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
