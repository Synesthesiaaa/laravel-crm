<?php

namespace App\Console\Commands;

use App\Services\Sms\GoipReceiver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ReceiveGoipSms extends Command
{
    protected $signature = 'sms:receive {--once : Process one packet for supervised diagnostics}';

    protected $description = 'Run the authenticated private-network GoIP UDP SMS receiver';

    public function handle(GoipReceiver $receiver): int
    {
        $host = config('sms.receiver_host');
        if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ($host !== '127.0.0.1' && ! preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $host))) {
            $this->error('Bind the receiver to loopback or an RFC1918 IPv4 address.');

            return self::FAILURE;
        }
        $socket = @stream_socket_server('udp://'.$host.':'.config('sms.receiver_port'), $code, $error, STREAM_SERVER_BIND);
        if (! $socket) {
            $this->error('Unable to bind SMS receiver. Check the private address, port and firewall.');

            return self::FAILURE;
        }
        stream_set_timeout($socket, 5);
        do {
            Cache::put('sms:receiver-heartbeat', now()->toIso8601String(), 30);
            $packet = stream_socket_recvfrom($socket, 4097, 0, $peer);
            if ($packet !== false && $peer) {
                try {
                    $source = explode(':', $peer, 2)[0];
                    $ack = $receiver->receive($packet, $source);
                    if ($ack !== null) {
                        stream_socket_sendto($socket, $ack, 0, $peer);
                    }
                } catch (Throwable) {
                    // Persistence failed: no ACK. Never log packet contents or exception details.
                    $this->warn('SMS packet persistence failed; no acknowledgment sent.');
                }
            }
        } while (! $this->option('once'));
        fclose($socket);

        return self::SUCCESS;
    }
}
