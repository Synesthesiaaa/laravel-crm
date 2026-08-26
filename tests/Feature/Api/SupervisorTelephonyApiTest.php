<?php

namespace Tests\Feature\Api;

use App\Models\Campaign;
use App\Models\User;
use App\Services\Telephony\VicidialProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorTelephonyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_action_uses_the_active_crm_campaign_when_payload_campaign_is_missing(): void
    {
        Campaign::factory()->create(['code' => 'campaign-b', 'name' => 'Campaign B']);
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'default_campaign' => 'campaign-a',
        ]);
        $agent = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'vici_user' => 'agent-b',
        ]);

        $this->mock(VicidialProxyService::class, function ($mock) use ($supervisor): void {
            $mock->shouldReceive('execute')
                ->once()
                ->withArgs(function (User $user, string $campaign, string $action, array $params) use ($supervisor): bool {
                    return $user->id === $supervisor->id
                        && $campaign === 'campaign-b'
                        && $action === 'blind_monitor'
                        && $params === [
                            'value' => '',
                            'query' => ['agent_user' => 'agent-b', 'stage' => 'MONITOR'],
                        ];
                })
                ->andReturn([
                    'success' => true,
                    'message' => null,
                    'raw_response' => 'SUCCESS',
                    'failure_code' => null,
                ]);
        });

        $this->actingAs($supervisor)
            ->withSession(['campaign' => 'campaign-b', 'campaign_name' => 'Campaign B'])
            ->postJson(route('api.supervisor.monitor'), ['agent_user_id' => $agent->id])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_supervisor_action_fails_with_campaign_specific_error_when_server_is_unmapped(): void
    {
        Campaign::factory()->create(['code' => 'campaign-b', 'name' => 'Campaign B']);
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'default_campaign' => 'campaign-b',
        ]);
        $agent = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'vici_user' => 'agent-b',
            'vici_pass' => 'agent-pass',
        ]);

        $this->actingAs($supervisor)
            ->withSession(['campaign' => 'campaign-b', 'campaign_name' => 'Campaign B'])
            ->postJson(route('api.supervisor.monitor'), ['agent_user_id' => $agent->id])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No VICIdial server configured for this campaign.');
    }
}
