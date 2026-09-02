<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\CrmCallHistory;
use App\Models\User;
use App\Models\VicidialServer;
use App\Services\Telephony\VicidialHistoricalCallProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class RecordsListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vicidial.campaign_scope_cache_seconds' => 0]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'full_name' => 'Admin User']);
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

    public function test_default_tab_keeps_submitted_records_local_and_separate(): void
    {
        CrmCallHistory::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'loan_application',
            'record_id' => 10,
            'agent' => 'agent_submit',
            'phone_number' => '639111111111',
            'status' => 'RECORDED',
        ]);

        $this->actingAs($this->admin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.records.index'))
            ->assertOk()
            ->assertSee('Submitted CRM records')
            ->assertSee('loan_application')
            ->assertSee('639111111111');
    }

    public function test_call_history_tab_is_an_async_shell_and_does_not_invoke_remote_history(): void
    {
        $provider = Mockery::mock(VicidialHistoricalCallProvider::class);
        $provider->shouldNotReceive('fetch');
        $provider->shouldNotReceive('fetchRange');
        $this->app->instance(VicidialHistoricalCallProvider::class, $provider);

        $this->actingAs($this->admin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.records.index', ['tab' => 'calls']))
            ->assertOk()
            ->assertSee('Historical VICIdial calls')
            ->assertSee('callHistoryPage', false)
            ->assertSee('records-tab-calls');
    }
}
