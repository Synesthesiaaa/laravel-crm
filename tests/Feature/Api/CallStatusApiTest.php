<?php

namespace Tests\Feature\Api;

use App\Models\CallSession;
use App\Models\User;
use App\Services\Telephony\CallOrchestrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class CallStatusApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_status_response_includes_lead_id_for_active_call(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);

        $session = new CallSession([
            'user_id' => $user->id,
            'campaign_code' => 'mbsales',
            'lead_id' => 456,
            'phone_number' => '15551234567',
            'status' => CallSession::STATUS_IN_CALL,
            'answered_at' => now()->subSeconds(10),
        ]);
        $session->id = 777;

        $orchestration = Mockery::mock(CallOrchestrationService::class);
        $orchestration->shouldReceive('getActiveSession')
            ->once()
            ->andReturn($session);
        $orchestration->shouldReceive('getPendingDispositionSession')
            ->once()
            ->andReturn(null);
        $this->instance(CallOrchestrationService::class, $orchestration);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->getJson('/api/call/status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('active', true)
            ->assertJsonPath('call.lead_id', 456)
            ->assertJsonPath('call.remote_dial_outcome', null)
            ->assertJsonPath('call.outcome_unknown', false);
    }

    public function test_status_exposes_unknown_outbound_dial_outcome(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);
        $session = new CallSession([
            'user_id' => $user->id,
            'campaign_code' => 'mbsales',
            'phone_number' => '15551234567',
            'status' => CallSession::STATUS_DIALING,
            'remote_dial_outcome' => 'unknown',
        ]);
        $session->id = 779;

        $orchestration = Mockery::mock(CallOrchestrationService::class);
        $orchestration->shouldReceive('getActiveSession')->once()->andReturn($session);
        $orchestration->shouldReceive('getPendingDispositionSession')->once()->andReturn(null);
        $this->instance(CallOrchestrationService::class, $orchestration);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB'])
            ->getJson('/api/call/status')
            ->assertOk()
            ->assertJsonPath('call.session_id', 779)
            ->assertJsonPath('call.status', CallSession::STATUS_DIALING)
            ->assertJsonPath('call.remote_dial_outcome', 'unknown')
            ->assertJsonPath('call.outcome_unknown', true);
    }

    public function test_status_duration_clamps_future_answer_timestamp_to_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 12:00:00'));

        try {
            $user = User::factory()->create(['role' => 'Agent']);
            $session = new CallSession([
                'user_id' => $user->id,
                'campaign_code' => 'mbsales',
                'phone_number' => '15551234567',
                'status' => CallSession::STATUS_IN_CALL,
                'answered_at' => now()->addMinute(),
            ]);
            $session->id = 778;

            $orchestration = Mockery::mock(CallOrchestrationService::class);
            $orchestration->shouldReceive('getActiveSession')->once()->andReturn($session);
            $orchestration->shouldReceive('getPendingDispositionSession')->once()->andReturn(null);
            $this->instance(CallOrchestrationService::class, $orchestration);

            $this->actingAs($user)
                ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
                ->getJson('/api/call/status')
                ->assertOk()
                ->assertJsonPath('call.duration_seconds', 0);
        } finally {
            Carbon::setTestNow();
        }
    }
}
