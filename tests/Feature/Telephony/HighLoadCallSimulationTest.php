<?php

namespace Tests\Feature\Telephony;

use App\Models\CallSession;
use App\Models\User;
use App\Services\Telephony\CallStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Simulates high load: many concurrent call sessions and state transitions.
 * Validates system remains consistent under load.
 */
class HighLoadCallSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_many_sessions_complete_full_lifecycle(): void
    {
        Event::fake([\App\Events\CallStateChanged::class]);
        $service = app(CallStateService::class);
        $user = User::factory()->create();

        $count = 50;
        $sessions = [];
        for ($i = 0; $i < $count; $i++) {
            $sessions[] = CallSession::factory()
                ->for($user)
                ->dialing()
                ->create();
        }

        foreach ($sessions as $session) {
            $service->transition($session, CallSession::STATUS_RINGING);
        }
        foreach ($sessions as $session) {
            $session->refresh();
            $service->transition($session, CallSession::STATUS_ANSWERED);
        }
        foreach ($sessions as $session) {
            $session->refresh();
            $service->transition($session, CallSession::STATUS_IN_CALL);
        }
        foreach ($sessions as $session) {
            $session->refresh();
            $service->recordHangup($session);
        }

        $completed = CallSession::where('status', CallSession::STATUS_COMPLETED)->count();
        $this->assertEquals($count, $completed);
    }

    public function test_mixed_states_no_cross_contamination(): void
    {
        Event::fake([\App\Events\CallStateChanged::class]);
        $service = app(CallStateService::class);
        $user = User::factory()->create();

        $dialing = CallSession::factory()->for($user)->dialing()->create();
        $ringing = CallSession::factory()->for($user)->ringing()->create();
        $inCall = CallSession::factory()->for($user)->inCall()->create();

        $service->transition($dialing, CallSession::STATUS_RINGING);
        $service->transition($ringing, CallSession::STATUS_ANSWERED);
        $service->transition($inCall, CallSession::STATUS_COMPLETED);

        $dialing->refresh();
        $ringing->refresh();
        $inCall->refresh();

        $this->assertEquals(CallSession::STATUS_RINGING, $dialing->status);
        $this->assertEquals(CallSession::STATUS_ANSWERED, $ringing->status);
        $this->assertEquals(CallSession::STATUS_COMPLETED, $inCall->status);
    }
}
