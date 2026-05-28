<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Telephony\VicidialProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CallControllerHangupTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'testpass',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_hangup_without_call_session_still_hangs_up_vicidial_leg(): void
    {
        $proxyMock = Mockery::mock(VicidialProxyService::class);
        $proxyMock->shouldReceive('execute')
            ->once()
            ->with($this->agent, 'testcamp', 'external_pause', ['value' => 'PAUSE'])
            ->andReturnUsing(static fn () => ['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);
        $proxyMock->shouldReceive('execute')
            ->once()
            ->with($this->agent, 'testcamp', 'external_hangup', ['value' => '1'])
            ->andReturnUsing(static fn () => ['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);
        $this->instance(VicidialProxyService::class, $proxyMock);

        $response = $this->actingAs($this->agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Camp'])
            ->postJson('/api/call/hangup', [
                'campaign' => 'testcamp',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
