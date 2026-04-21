<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class TelephonyPreflightCommand extends Command
{
    protected $signature = 'telephony:preflight {--timeout=3 : TCP timeout seconds for AMI check}';

    protected $description = 'Validate CRM -> Asterisk telephony integration prerequisites';

    public function handle(): int
    {
        $timeout = max(1, (int) $this->option('timeout'));

        $this->info('Telephony preflight checks');
        $this->line('');

        $rows = [];

        $amiHost = (string) config('asterisk.host');
        $amiPort = (int) config('asterisk.port');
        $amiUser = (string) config('asterisk.username');
        $amiSecret = (string) config('asterisk.secret');
        $goipTrunk = (string) config('asterisk.goip_trunk', 'goip-trunk');
        $agentChannel = (string) config('asterisk.agent_channel', 'SIP');

        $rows[] = ['ASTERISK_AMI_HOST', $amiHost !== '' ? 'OK' : 'MISSING', $amiHost ?: '-'];
        $rows[] = ['ASTERISK_AMI_PORT', $amiPort > 0 ? 'OK' : 'MISSING', (string) $amiPort];
        $rows[] = ['ASTERISK_AMI_USERNAME', $amiUser !== '' ? 'OK' : 'MISSING', $amiUser ?: '-'];
        $rows[] = ['ASTERISK_AMI_SECRET', $amiSecret !== '' ? 'OK' : 'MISSING', $amiSecret !== '' ? '***set***' : '-'];
        $rows[] = ['ASTERISK_GOIP_TRUNK', $goipTrunk !== '' ? 'OK' : 'MISSING', $goipTrunk ?: '-'];
        $rows[] = ['ASTERISK_AGENT_CHANNEL', strtoupper($agentChannel) === 'SIP' ? 'OK' : 'INVALID', $agentChannel];

        $broadcastConnection = (string) config('broadcasting.default');
        $rows[] = ['BROADCAST_CONNECTION', $broadcastConnection === 'reverb' ? 'OK' : 'WARN', $broadcastConnection];

        $webhookRouteExists = Route::has('api.webhooks.ami');
        $callDialRouteExists = Route::has('api.call.dial');
        $callPredictiveRouteExists = Route::has('api.call.predictive-dial');
        $sipCredsRouteExists = Route::has('api.sip.credentials');

        $rows[] = ['Route api.webhooks.ami', $webhookRouteExists ? 'OK' : 'MISSING', '/api/webhooks/ami'];
        $rows[] = ['Route api.call.dial', $callDialRouteExists ? 'OK' : 'MISSING', '/api/call/dial'];
        $rows[] = ['Route api.call.predictive-dial', $callPredictiveRouteExists ? 'OK' : 'MISSING', '/api/call/predictive-dial'];
        $rows[] = ['Route api.sip.credentials', $sipCredsRouteExists ? 'OK' : 'MISSING', '/api/sip/credentials'];

        [$tcpOk, $tcpError] = $this->checkTcp($amiHost, $amiPort, $timeout);
        $rows[] = ['AMI TCP connectivity', $tcpOk ? 'OK' : 'FAIL', $tcpOk ? "{$amiHost}:{$amiPort}" : $tcpError];

        $this->table(['Check', 'Status', 'Details'], $rows);

        $failed = collect($rows)->contains(fn ($row) => in_array($row[1], ['MISSING', 'INVALID', 'FAIL'], true));
        if ($failed) {
            $this->error('Preflight failed. Fix failing checks before production dial tests.');

            return self::FAILURE;
        }

        $this->info('Preflight passed. Ready for direct dial smoke tests.');

        return self::SUCCESS;
    }

    /**
     * @return array{bool,string}
     */
    private function checkTcp(string $host, int $port, int $timeout): array
    {
        if ($host === '' || $port <= 0) {
            return [false, 'AMI host/port not configured'];
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (! is_resource($socket)) {
            return [false, trim("{$errstr} ({$errno})")];
        }

        fclose($socket);

        return [true, ''];
    }
}
