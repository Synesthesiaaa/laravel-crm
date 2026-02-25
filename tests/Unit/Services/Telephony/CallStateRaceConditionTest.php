<?php

namespace Tests\Unit\Services\Telephony;

use App\Models\CallSession;
use App\Services\Telephony\CallStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CallStateRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    private CallStateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CallStateService::class);
        Event::fake([\App\Events\CallStateChanged::class]);
    }

    /**
     * Simulate multiple hangup attempts (e.g. AMI sends duplicate events).
     * All calls should be idempotent; final state must be completed.
     */
    public function test_record_hangup_idempotent_multiple_calls(): void
    {
        $session = CallSession::factory()->inCall()->create();

        $r1 = $this->service->recordHangup($session);
        $r2 = $this->service->recordHangup($session->fresh());
        $r3 = $this->service->recordHangup($session->fresh());

        $this->assertTrue($r1->success);
        $this->assertTrue($r2->success);
        $this->assertTrue($r3->success);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_COMPLETED, $session->status);
    }

    /**
     * Concurrent transitions to same valid state - last writer wins, no corruption.
     */
    public function test_concurrent_same_transition_consistent(): void
    {
        $session = CallSession::factory()->ringing()->create();
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->service->transition($session->fresh(), CallSession::STATUS_ANSWERED);
        }
        foreach ($results as $r) {
            $this->assertTrue($r->success);
        }
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_ANSWERED, $session->status);
    }

    /**
     * Invalid transition after valid one - must be ignored (terminal state).
     */
    public function test_invalid_transition_after_completed_ignored(): void
    {
        $session = CallSession::factory()->inCall()->create();
        $this->service->transition($session, CallSession::STATUS_COMPLETED);
        $session->refresh();

        $invalid = $this->service->transition($session, CallSession::STATUS_RINGING);
        $this->assertTrue($invalid->success);
        $this->assertEquals('Already terminal', $invalid->message);
        $session->refresh();
        $this->assertEquals(CallSession::STATUS_COMPLETED, $session->status);
    }
}
