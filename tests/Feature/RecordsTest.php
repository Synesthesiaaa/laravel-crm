<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\CrmCallHistory;
use App\Models\User;
use App\Models\VicidialServer;
use App\Services\Telephony\VicidialHistoricalCallProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class RecordsTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vicidial.campaign_scope_cache_seconds' => 0]);
        $this->campaign = Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);
        $server = VicidialServer::factory()->create(['campaign_code' => 'mbsales']);
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => $this->campaign->id,
            'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'CAMP_A',
            'status' => CampaignVicidialMapping::STATUS_ACTIVE,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_call_history_page_renders_a_lightweight_async_shell_without_remote_reads(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT, 'vici_user' => 'agent_one']);
        $provider = Mockery::mock(VicidialHistoricalCallProvider::class);
        $provider->shouldNotReceive('fetch');
        $provider->shouldNotReceive('fetchRange');
        $this->app->instance(VicidialHistoricalCallProvider::class, $provider);

        $this->actingAs($agent)
            ->withSession(['campaign' => 'mbsales'])
            ->get(route('records.index'))
            ->assertOk()
            ->assertSee('Historical VICIdial calls')
            ->assertSee('callHistoryPage', false)
            ->assertSee('Locally synchronized');
    }

    public function test_crm_submission_history_is_not_rendered_as_telephony_history(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT, 'vici_user' => 'agent_submission']);
        CrmCallHistory::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'loan_application',
            'record_id' => 55,
            'agent' => 'agent_submission',
            'phone_number' => '639133333333',
            'status' => 'RECORDED',
        ]);

        $this->actingAs($agent)
            ->withSession(['campaign' => 'mbsales'])
            ->get(route('records.index'))
            ->assertOk()
            ->assertSee('Historical VICIdial calls')
            ->assertDontSee('loan_application')
            ->assertDontSee('639133333333');
    }

    public function test_live_webhook_updates_call_session_without_making_it_historical_call_history(): void
    {
        config(['vicidial.events_webhook_secret' => 'vicidial-secret']);
        $agent = User::factory()->create([
            'username' => 'agent_vici',
            'vici_user' => 'agent_vici',
            'full_name' => 'Agent Vici',
        ]);
        $session = CallSession::factory()->for($agent)->ringing()->create([
            'campaign_code' => 'mbsales',
            'lead_id' => 3030,
            'phone_number' => '639155500000',
            'vicidial_call_id' => 'VICI-RACE-1',
            'dialed_at' => now()->subMinutes(2),
            'ringing_at' => now()->subMinutes(2),
        ]);

        $this->postJson(route('api.webhooks.vicidial-events'), [
            'user' => 'agent_vici',
            'event' => 'agent_hangup',
        ], ['X-Webhook-Secret' => 'vicidial-secret'])
            ->assertOk()
            ->assertJsonPath('processed', true);

        $session->refresh();
        $this->assertSame(CallSession::STATUS_COMPLETED, $session->status);
        $this->assertSame(120, $session->call_duration_seconds);
    }
}
