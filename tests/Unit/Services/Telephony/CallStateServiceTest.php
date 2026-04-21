<?php

namespace Tests\Unit\Services\Telephony;

use App\Models\CallSession;
use App\Services\Telephony\CallStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CallStateServiceTest extends TestCase
{
    use RefreshDatabase;

    private CallStateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CallStateService::class);
        Event::fake([\App\Events\CallStateChanged::class]);
    }

    public function test_valid_transition_dialing_to_ringing(): void
    {
        $session = CallSession::factory()->dialing()->create();
        $result = $this->service->transition($session, CallSession::STATUS_RINGING);
        $this->assertTrue($result->success);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_RINGING, $session->status);
        $this->assertNotNull($session->ringing_at);
    }

    public function test_valid_transition_ringing_to_answered(): void
    {
        $session = CallSession::factory()->ringing()->create();
        $result = $this->service->transition($session, CallSession::STATUS_ANSWERED);
        $this->assertTrue($result->success);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_ANSWERED, $session->status);
        $this->assertNotNull($session->answered_at);
    }

    public function test_valid_transition_in_call_to_completed(): void
    {
        $session = CallSession::factory()->inCall()->create();
        $result = $this->service->transition($session, CallSession::STATUS_COMPLETED, ['end_reason' => 'hangup']);
        $this->assertTrue($result->success);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->ended_at);
        $this->assertGreaterThan(0, $session->call_duration_seconds);
    }

    public function test_invalid_transition_rejected(): void
    {
        // DIALING -> ON_HOLD is never valid; on-hold requires an answered call.
        // (Note: DIALING -> COMPLETED IS allowed now because agent hangup must
        // always be able to terminate a session; see VALID_TRANSITIONS comment.)
        $session = CallSession::factory()->dialing()->create();
        $result = $this->service->transition($session, CallSession::STATUS_ON_HOLD);
        $this->assertFalse($result->success);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_DIALING, $session->status);
    }

    public function test_idempotent_same_state_returns_success(): void
    {
        $session = CallSession::factory()->inCall()->create();
        $result = $this->service->transition($session, CallSession::STATUS_IN_CALL);
        $this->assertTrue($result->success);
        $this->assertEquals('Already in state', $result->message);
    }

    public function test_terminal_state_ignores_transition(): void
    {
        $session = CallSession::factory()->completed()->create();
        $result = $this->service->transition($session, CallSession::STATUS_RINGING);
        $this->assertTrue($result->success);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_COMPLETED, $session->status);
    }

    public function test_record_hangup_transitions_to_completed(): void
    {
        $session = CallSession::factory()->inCall()->create();
        $result = $this->service->recordHangup($session, ['end_reason' => 'agent_hangup']);
        $this->assertTrue($result->success);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_COMPLETED, $session->status);
        $this->assertEquals('agent_hangup', $session->end_reason);
    }

    public function test_record_hangup_idempotent_on_terminal(): void
    {
        $session = CallSession::factory()->completed()->create();
        $result = $this->service->recordHangup($session);
        $this->assertTrue($result->success);
        $this->assertEquals('Already ended', $result->message);
    }

    public function test_force_stale_to_terminal(): void
    {
        $session = CallSession::factory()->inCall()->create();
        $result = $this->service->forceStaleToTerminal($session, CallSession::STATUS_FAILED);
        $this->assertTrue($result->success);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_FAILED, $session->status);
        $this->assertEquals('reconciliation_timeout', $session->end_reason);
    }

    public function test_force_correction_only_allows_terminal_states(): void
    {
        // Force transitions are restricted to terminal states (COMPLETED/FAILED/ABANDONED).
        // Attempting to force to a non-terminal state must fail.
        $session = CallSession::factory()->inCall()->create();
        $result = $this->service->transition($session, CallSession::STATUS_RINGING, [], true);
        $this->assertFalse($result->success);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_IN_CALL, $session->status);
    }

    public function test_is_valid_transition_helper(): void
    {
        $this->assertTrue($this->service->isValidTransition(CallSession::STATUS_DIALING, CallSession::STATUS_RINGING));
        $this->assertTrue($this->service->isValidTransition(CallSession::STATUS_IN_CALL, CallSession::STATUS_COMPLETED));
        // DIALING -> ON_HOLD is not valid (must go through ANSWERED/IN_CALL first).
        $this->assertFalse($this->service->isValidTransition(CallSession::STATUS_DIALING, CallSession::STATUS_ON_HOLD));
        // No transitions out of terminal states.
        $this->assertFalse($this->service->isValidTransition(CallSession::STATUS_COMPLETED, CallSession::STATUS_RINGING));
    }

    public function test_event_fired_on_transition(): void
    {
        $session = CallSession::factory()->ringing()->create();
        $this->service->transition($session, CallSession::STATUS_ANSWERED);
        Event::assertDispatched(\App\Events\CallStateChanged::class, function ($event) use ($session) {
            return $event->session->id === $session->id
                && $event->fromStatus === CallSession::STATUS_RINGING
                && $event->toStatus === CallSession::STATUS_ANSWERED;
        });
    }
}
