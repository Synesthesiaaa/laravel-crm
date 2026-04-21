<?php

namespace App\Console\Commands;

use App\Events\InboundCallReceived;
use App\Models\CallSession;
use App\Services\Telephony\CallStateService;
use App\Services\Telephony\CallUuidMappingService;
use App\Services\Telephony\TelephonyLogger;
use Illuminate\Console\Command;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Event\EventMessage;

class AmiListenerCommand extends Command
{
    protected $signature = 'ami:listen';

    protected $description = 'Persistent AMI event listener — streams Asterisk events into the CRM event bus';

    private bool $shouldRun = true;

    private int $reconnectDelay;

    private int $maxReconnectDelay;

    private int $eventsProcessed = 0;

    private const HANDLED_EVENTS = [
        'Newchannel',
        'Dial',
        'Bridge',
        'BridgeEnter',
        'Hangup',
        'HangupRequest',
        'SoftHangupRequest',
        'DialEnd',
        'QueueMemberStatus',
        'AgentConnect',
        'AgentComplete',
    ];

    public function handle(
        CallStateService $callStateService,
        CallUuidMappingService $mapping,
        TelephonyLogger $logger,
    ): int {
        $this->reconnectDelay = (int) config('asterisk.reconnect_delay', 5);
        $this->maxReconnectDelay = (int) config('asterisk.max_reconnect_delay', 60);

        if (config('asterisk.secret', '') === '') {
            $this->error('ASTERISK_AMI_SECRET is not configured. Exiting.');

            return self::FAILURE;
        }

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldRun = false);
            pcntl_signal(SIGTERM, fn () => $this->shouldRun = false);
        }

        $this->info('AMI listener starting (Ctrl+C to stop)');
        $logger->info('AmiListenerCommand', 'AMI listener starting');

        $currentDelay = $this->reconnectDelay;

        while ($this->shouldRun) {
            try {
                $this->runEventLoop($callStateService, $mapping, $logger);
                $currentDelay = $this->reconnectDelay;
            } catch (\Throwable $e) {
                $logger->warning('AmiListenerCommand', 'AMI connection lost', [
                    'error' => $e->getMessage(),
                    'reconnect_in' => $currentDelay,
                ]);
                $this->warn("AMI connection lost: {$e->getMessage()}. Reconnecting in {$currentDelay}s...");

                $this->sleepInterruptible($currentDelay);
                $currentDelay = min($currentDelay * 2, $this->maxReconnectDelay);
            }
        }

        $logger->info('AmiListenerCommand', 'AMI listener stopped', [
            'events_processed' => $this->eventsProcessed,
        ]);
        $this->info("AMI listener stopped. Events processed: {$this->eventsProcessed}");

        return self::SUCCESS;
    }

    private function runEventLoop(
        CallStateService $callStateService,
        CallUuidMappingService $mapping,
        TelephonyLogger $logger,
    ): void {
        $client = new ClientImpl($this->amiOptions());
        $client->open();
        $this->info('Connected to AMI at '.config('asterisk.host').':'.config('asterisk.port'));

        $client->registerEventListener(function (EventMessage $event) use ($callStateService, $mapping, $logger) {
            $this->dispatchEvent($event, $callStateService, $mapping, $logger);
        });

        while ($this->shouldRun) {
            $client->process();
            usleep(50_000); // 50ms = ~20 events/sec throughput
        }

        try {
            $client->close();
        } catch (\Throwable $e) {
            // Closing may fail if connection is already dead
        }
    }

    private function dispatchEvent(
        EventMessage $event,
        CallStateService $callStateService,
        CallUuidMappingService $mapping,
        TelephonyLogger $logger,
    ): void {
        $name = $event->getName();

        if (! in_array($name, self::HANDLED_EVENTS, true)) {
            return;
        }

        $this->eventsProcessed++;
        $linkedid = $event->getKey('Linkedid') ?? $event->getKey('Uniqueid');
        $channel = $event->getKey('Channel');

        $logger->event('AmiListenerCommand', $name, 'AMI event received', [
            'linkedid' => $linkedid,
            'channel' => $channel,
        ]);

        if ($name === 'Hangup' || $name === 'HangupRequest' || $name === 'SoftHangupRequest') {
            $this->handleHangup($linkedid, $channel, $event, $callStateService, $mapping, $logger);

            return;
        }

        if ($name === 'Bridge' || $name === 'BridgeEnter') {
            $this->handleBridge($linkedid, $channel, $event, $callStateService, $mapping, $logger);

            return;
        }

        if ($name === 'DialEnd') {
            $this->handleDialEnd($linkedid, $channel, $event, $callStateService, $mapping, $logger);

            return;
        }

        if ($name === 'AgentConnect') {
            $this->handleAgentConnect($event, $mapping, $logger);

            return;
        }
    }

    private function handleHangup(
        ?string $linkedid, ?string $channel, EventMessage $event,
        CallStateService $callStateService, CallUuidMappingService $mapping, TelephonyLogger $logger,
    ): void {
        $session = $mapping->findSessionForHangup($linkedid, $channel, $this->eventToArray($event));
        if (! $session || $session->isTerminal()) {
            return;
        }

        $mapping->attachAsteriskIdentifiers($session, $linkedid, $channel);
        $callStateService->recordHangup($session, [
            'end_reason' => 'ami_hangup',
            'linkedid' => $linkedid,
            'channel' => $channel,
        ]);
    }

    private function handleBridge(
        ?string $linkedid, ?string $channel, EventMessage $event,
        CallStateService $callStateService, CallUuidMappingService $mapping, TelephonyLogger $logger,
    ): void {
        $session = $mapping->findSessionForHangup($linkedid, $channel, $this->eventToArray($event));
        if (! $session || $session->isTerminal()) {
            return;
        }

        $mapping->attachAsteriskIdentifiers($session, $linkedid, $channel);
        $callStateService->transition($session, CallSession::STATUS_IN_CALL, [
            'linkedid' => $linkedid,
            'channel' => $channel,
        ]);
    }

    private function handleDialEnd(
        ?string $linkedid, ?string $channel, EventMessage $event,
        CallStateService $callStateService, CallUuidMappingService $mapping, TelephonyLogger $logger,
    ): void {
        $dialStatus = $event->getKey('DialStatus') ?? '';
        $session = $mapping->findSessionForHangup($linkedid, $channel, $this->eventToArray($event));
        if (! $session || $session->isTerminal()) {
            return;
        }

        $mapping->attachAsteriskIdentifiers($session, $linkedid, $channel);

        if ($dialStatus === 'ANSWER') {
            $callStateService->transition($session, CallSession::STATUS_IN_CALL, [
                'linkedid' => $linkedid,
                'channel' => $channel,
            ]);
        } else {
            $endReason = match ($dialStatus) {
                'NOANSWER' => 'no_answer',
                'BUSY' => 'busy',
                'CANCEL' => 'cancelled',
                'CONGESTION' => 'congestion',
                default => 'dial_failed_'.strtolower($dialStatus ?: 'unknown'),
            };
            $callStateService->transition($session, CallSession::STATUS_FAILED, [
                'end_reason' => $endReason,
            ], true);
        }
    }

    /**
     * AgentConnect: Asterisk connected agent to a queue caller.
     * Fires InboundCallReceived for screen-pop if we can identify the agent.
     */
    private function handleAgentConnect(
        EventMessage $event, CallUuidMappingService $mapping, TelephonyLogger $logger,
    ): void {
        $memberChannel = $event->getKey('MemberName') ?? $event->getKey('Interface') ?? '';
        $extension = $mapping->extractExtensionFromChannel($memberChannel);
        if (! $extension) {
            return;
        }

        $user = $mapping->findUserByExtension($extension);
        if (! $user) {
            return;
        }

        $callerIdNum = $event->getKey('CallerIDNum') ?? '';
        if ($callerIdNum) {
            event(new InboundCallReceived(
                userId: $user->id,
                phoneNumber: $callerIdNum,
            ));
        }
    }

    private function eventToArray(EventMessage $event): array
    {
        $data = [];
        $keys = $event->getKeys();
        foreach ($keys as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    private function amiOptions(): array
    {
        return [
            'host' => config('asterisk.host'),
            'port' => config('asterisk.port'),
            'username' => config('asterisk.username'),
            'secret' => config('asterisk.secret'),
            'connect_timeout' => config('asterisk.timeout', 5),
            'read_timeout' => config('asterisk.read_timeout', 5000),
            'scheme' => 'tcp://',
        ];
    }

    private function sleepInterruptible(int $seconds): void
    {
        $until = time() + $seconds;
        while ($this->shouldRun && time() < $until) {
            usleep(500_000);
        }
    }
}
