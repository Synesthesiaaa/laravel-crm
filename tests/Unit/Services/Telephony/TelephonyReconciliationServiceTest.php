<?php

namespace Tests\Unit\Services\Telephony;

use App\Jobs\ReconcileCallStateJob;
use App\Models\CallSession;
use App\Models\UnmatchedAmiEvent;
use App\Models\User;
use App\Services\Telephony\CallStateService;
use App\Services\Telephony\CallUuidMappingService;
use App\Services\Telephony\TelephonyAlertService;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\TelephonyReconciliationService;
use App\Support\OperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TelephonyReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_call_transition_keeps_ami_event_unprocessed_with_retry_metadata(): void
    {
        $user = User::factory()->create();
        $session = CallSession::factory()->ringing()->for($user)->create(['dialed_at' => now()]);
        $event = $this->unmatchedHangupEvent();

        $mapping = Mockery::mock(CallUuidMappingService::class);
        $mapping->shouldReceive('findSessionForHangup')->once()->andReturn($session);
        $mapping->shouldReceive('attachAsteriskIdentifiers')->once();
        $state = Mockery::mock(CallStateService::class);
        $state->shouldReceive('recordHangup')->once()->andReturn(OperationResult::failure('transition rejected'));
        $alerts = Mockery::mock(TelephonyAlertService::class);
        $alerts->shouldNotReceive('unmatchedAmiProcessed');
        $logger = Mockery::mock(TelephonyLogger::class)->shouldIgnoreMissing();

        (new TelephonyReconciliationService($state, $mapping, $alerts, $logger))->run();

        $event->refresh();
        $this->assertFalse($event->processed);
        $this->assertSame(1, $event->attempt_count);
        $this->assertSame('CALL_TRANSITION_REJECTED', $event->last_error_code);
        $this->assertNotNull($event->last_attempted_at);
    }

    public function test_matching_event_is_acknowledged_only_after_successful_transition(): void
    {
        $user = User::factory()->create();
        $session = CallSession::factory()->ringing()->for($user)->create(['dialed_at' => now()]);
        $event = $this->unmatchedHangupEvent();

        $mapping = Mockery::mock(CallUuidMappingService::class);
        $mapping->shouldReceive('findSessionForHangup')->once()->andReturn($session);
        $mapping->shouldReceive('attachAsteriskIdentifiers')->once();
        $state = Mockery::mock(CallStateService::class);
        $state->shouldReceive('recordHangup')->once()->andReturn(OperationResult::success());
        $alerts = Mockery::mock(TelephonyAlertService::class);
        $alerts->shouldReceive('unmatchedAmiProcessed')->once()->with($event->id, $session->id);
        $logger = Mockery::mock(TelephonyLogger::class)->shouldIgnoreMissing();

        (new TelephonyReconciliationService($state, $mapping, $alerts, $logger))->run();

        $event->refresh();
        $this->assertTrue($event->processed);
        $this->assertSame(1, $event->attempt_count);
        $this->assertNull($event->last_error_code);
    }

    public function test_reconciliation_does_not_terminalize_stale_calls_with_unknown_dial_outcome(): void
    {
        $user = User::factory()->create();
        $session = CallSession::factory()->create([
            'user_id' => $user->id,
            'status' => CallSession::STATUS_DIALING,
            'remote_dial_outcome' => 'unknown',
            'dialed_at' => now()->subHours(3),
        ]);

        $state = Mockery::mock(CallStateService::class);
        $state->shouldNotReceive('forceStaleToTerminal');
        $mapping = Mockery::mock(CallUuidMappingService::class);
        $alerts = Mockery::mock(TelephonyAlertService::class);
        $logger = Mockery::mock(TelephonyLogger::class)->shouldIgnoreMissing();

        (new TelephonyReconciliationService($state, $mapping, $alerts, $logger))->run();

        $this->assertSame(CallSession::STATUS_DIALING, $session->fresh()->status);
        $this->assertSame('unknown', $session->fresh()->remote_dial_outcome);
    }

    public function test_reconciliation_job_delegates_to_the_shared_service(): void
    {
        $reconciliation = Mockery::mock(TelephonyReconciliationService::class);
        $reconciliation->shouldReceive('run')->once()->andReturn([
            'stale_corrected' => 0,
            'unmatched_matched' => 0,
            'stale_count' => 0,
            'unmatched_count' => 0,
        ]);

        (new ReconcileCallStateJob)->handle($reconciliation);
    }

    private function unmatchedHangupEvent(): UnmatchedAmiEvent
    {
        return UnmatchedAmiEvent::query()->create([
            'event' => 'Hangup',
            'linkedid' => 'synthetic-linked-id',
            'channel' => 'PJSIP/100-00000001',
            'payload' => ['synthetic' => true],
            'processed' => false,
            'received_at' => now(),
        ]);
    }
}
