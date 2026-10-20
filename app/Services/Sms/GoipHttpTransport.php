<?php

namespace App\Services\Sms;

use App\Models\SmsChannel;
use App\Models\SmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GoipHttpTransport
{
    public function assertDestination(SmsGateway $gateway): void
    {
        if (! filter_var($gateway->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! in_array($gateway->host, config('sms.allowed_ips', []), true)
            || $gateway->transport !== 'legacy_http' || $gateway->port < 1 || $gateway->port > 65535) {
            throw new RuntimeException('Gateway IPv4 must be explicitly listed in SMS_ALLOWED_IPS.');
        }
    }

    public function assertReady(SmsGateway $gateway): void
    {
        $this->assertDestination($gateway);
        if (! config('sms.live_enabled') || config('sms.emergency_stop') || ! $gateway->enabled || ! $gateway->verified_at) {
            throw new RuntimeException('Live SMS is disabled, stopped, or firmware is unverified.');
        }
    }

    public function send(SmsChannel $channel, string $phone, string $body): array
    {
        $gateway = $channel->gateway;
        $this->assertReady($gateway);
        SmsText::assertSupported($body);

        // No retries: a timeout after transmission is an ambiguous submission.
        try {
            $response = Http::connectTimeout(2)->timeout(5)
                ->withOptions(['allow_redirects' => false, 'proxy' => '', 'on_headers' => static function ($response): void {
                    if ((int) $response->getHeaderLine('Content-Length') > 4096) {
                        throw new RuntimeException('Oversized gateway response.');
                    }
                }])
                ->get("http://{$gateway->host}:{$gateway->port}/default/en_US/send.html", [
                    'u' => $gateway->username, 'p' => $gateway->password,
                    'l' => $channel->number, 'n' => SmsText::phone($phone), 'm' => $body,
                ]);

            return $this->interpret($response->status(), substr($response->body(), 0, 4097), $channel->number, $phone);
        } catch (Throwable) {
            // Never persist exceptions: HTTP exceptions can include credentials and SMS text.
            return ['status' => 'unknown', 'reason' => 'transport_outcome_unknown', 'gateway_message_id' => null];
        }
    }

    public function interpret(int $status, string $response, int $channel, string $phone): array
    {
        $result = ['status' => 'unknown', 'reason' => 'unrecognized_gateway_response', 'gateway_message_id' => null];
        if (strlen($response) <= 4096 && $status === 200
            && preg_match('/^Sending,L([0-9]+) Send SMS to:(\+?[0-9]+); ID:([a-zA-Z0-9_-]{1,100})$/D', trim($response), $matches)
            && (int) $matches[1] === $channel && ltrim($matches[2], '+') === ltrim($phone, '+')) {
            return ['status' => 'submitted', 'reason' => null, 'gateway_message_id' => $matches[3]];
        }
        if (in_array($status, [401, 403], true)) {
            $result['status'] = 'failed';
            $result['reason'] = 'gateway_authentication_failed';
        }

        return $result;
    }

    public function connectivity(SmsGateway $gateway): string
    {
        $this->assertDestination($gateway);
        $socket = @stream_socket_client("tcp://{$gateway->host}:{$gateway->port}", $code, $error, 2);
        if (! $socket) {
            return 'Network connection failed. Authentication and SMS were not tested.';
        }
        fclose($socket);

        return 'Network reachable. Authentication, protocol readiness and SMS delivery were not tested.';
    }
}
