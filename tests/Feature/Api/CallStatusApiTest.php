<?php

namespace Tests\Feature\Api;

use App\Models\CallSession;
use App\Models\User;
use App\Services\Telephony\CallOrchestrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('call.lead_id', 456);
    }
}
