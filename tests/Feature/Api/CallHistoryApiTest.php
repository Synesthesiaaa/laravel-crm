<?php

namespace Tests\Feature\Api;

use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\DispositionCode;
use App\Models\User;
use App\Models\VicidialServer;
use App\Services\CallHistoryService;
use App\Services\Telephony\HistoricalCallProviderResult;
use App\Services\Telephony\HistoricalCallRecord;
use App\Services\Telephony\VicidialHistoricalCallProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

final class CallHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campaign = Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
        ]);
        $server = VicidialServer::factory()->create(['campaign_code' => 'mbsales']);
        CampaignVicidialMapping::create([
            'campaign_id' => $this->campaign->id,
            'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'CAMP_A',
            'is_enabled' => true,
            'status' => CampaignVicidialMapping::STATUS_ACTIVE,
        ]);
        DispositionCode::create([
            'campaign_code' => '',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
        ]);
    }

    public function test_agent_api_returns_normalized_rows_and_personal_scope(): void
    {
        $agent = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'vici_user' => 'agent_api',
            'full_name' => 'API Agent',
        ]);
        $record = $this->record('api-1', 'agent_api', '639121234567', 'SALE');
        $this->mockProvider(function (array $filters) use ($record): HistoricalCallProviderResult {
            $this->assertSame('agent_api', $filters['agent']);

            return HistoricalCallProviderResult::success([$record], 1, [
                'agents' => ['agent_api'],
                'statuses' => ['SALE'],
                'campaigns' => ['CAMP_A'],
            ], ['source' => 'vicidial_database', 'server_id' => 1]);
        });

        $this->actingAs($agent)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->getJson(route('api.call-history', [
                'per_page' => 15,
                'page' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('state', 'data')
            ->assertJsonPath('data.0.phone_number', '639121234567')
            ->assertJsonPath('data.0.duration_seconds', 127)
            ->assertJsonPath('data.0.disposition.label', 'Sale')
            ->assertJsonPath('data.0.agent.crm_user_available', true)
            ->assertJsonPath('data.0.call_direction', 'OUTBOUND')
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('scope.server.id', 1)
            ->assertJsonPath('source_health.status', 'healthy');
    }

    public function test_api_maps_soft_deleted_users_by_vicidial_login_not_display_name(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $deletedUser = User::factory()->create([
            'full_name' => 'Shared Name',
            'vici_user' => 'deleted_login',
        ]);
        User::factory()->create([
            'full_name' => 'Shared Name',
            'vici_user' => 'other_login',
        ]);
        $deletedUser->delete();
        $this->mockProvider(fn (array $filters): HistoricalCallProviderResult => HistoricalCallProviderResult::success([
            $this->record('deleted-1', 'deleted_login', '639100000001', 'SALE'),
        ], 1, [
            'agents' => ['deleted_login'],
            'statuses' => ['SALE'],
            'campaigns' => ['CAMP_A'],
        ], ['source' => 'vicidial_database', 'server_id' => 1]));

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.call-history'))
            ->assertOk()
            ->assertJsonPath('data.0.agent.crm_user_id', $deletedUser->id)
            ->assertJsonPath('data.0.agent.name', 'Shared Name')
            ->assertJsonPath('data.0.agent.crm_user_available', true);
    }

    public function test_api_preserves_unknown_agent_and_distinguishes_empty_from_failure(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $record = $this->record('legacy-1', 'legacy_agent', '09121234567', 'LEGACY');
        $first = HistoricalCallProviderResult::success([$record], 1, [
            'agents' => ['legacy_agent'],
            'statuses' => ['LEGACY'],
            'campaigns' => ['CAMP_A'],
        ], ['source' => 'vicidial_database', 'server_id' => 1]);
        $empty = HistoricalCallProviderResult::success([], 0, [
            'agents' => [],
            'statuses' => [],
            'campaigns' => ['CAMP_A'],
        ], ['source' => 'vicidial_database', 'server_id' => 1]);
        $failure = HistoricalCallProviderResult::failure(
            'VICIdial call history is currently unavailable. Please try again.',
            ['classification' => 'REMOTE_DATABASE_ERROR', 'source' => 'vicidial_database', 'server_id' => 1],
        );
        $this->mockProviderResults([$first, $empty, $failure]);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.call-history'))
            ->assertOk()
            ->assertJsonPath('data.0.agent.vicidial_user', 'legacy_agent')
            ->assertJsonPath('data.0.agent.crm_user_available', false)
            ->assertJsonPath('data.0.disposition.label', 'Unmapped');

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.call-history'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('state', 'confirmed_empty')
            ->assertJsonPath('source_health.status', 'healthy');

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.call-history'))
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('state', 'unavailable')
            ->assertJsonPath('source_health.status', 'unavailable')
            ->assertJsonPath('pagination.total', 0);
    }

    public function test_unmapped_secondary_campaign_does_not_query_provider(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $provider = Mockery::mock(VicidialHistoricalCallProvider::class);
        $provider->shouldNotReceive('fetch');
        $this->app->instance(VicidialHistoricalCallProvider::class, $provider);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.call-history', ['vicidial_campaign' => 'NOT_MAPPED']))
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('source_health.classification', 'UNAUTHORIZED_SCOPE');
    }

    /**
     * @param  callable(array<string, mixed>): HistoricalCallProviderResult  $callback
     */
    private function mockProvider(callable $callback): void
    {
        $provider = Mockery::mock(VicidialHistoricalCallProvider::class);
        $provider->shouldReceive('fetch')->once()->andReturnUsing(
            function ($server, $campaign, array $campaignCodes, array $filters, int $page, int $perPage) use ($callback): HistoricalCallProviderResult {
                return $callback($filters);
            },
        );
        $this->app->instance(VicidialHistoricalCallProvider::class, $provider);
        $this->app->forgetInstance(CallHistoryService::class);
    }

    /**
     * @param  array<int, HistoricalCallProviderResult>  $results
     */
    private function mockProviderResults(array $results): void
    {
        $provider = Mockery::mock(VicidialHistoricalCallProvider::class);
        $provider->shouldReceive('fetch')->times(count($results))->andReturn(...$results);
        $this->app->instance(VicidialHistoricalCallProvider::class, $provider);
        $this->app->forgetInstance(CallHistoryService::class);
    }

    private function record(string $id, string $agent, string $phone, string $status): HistoricalCallRecord
    {
        return new HistoricalCallRecord(
            id: 'vicidial_log:'.$id,
            uniqueCallId: $id,
            crmCampaignId: $this->campaign->id,
            crmCampaignCode: 'mbsales',
            vicidialCampaignId: 'CAMP_A',
            vicidialListId: 'LIST_A',
            leadId: 99,
            vicidialUser: $agent,
            crmUserId: null,
            crmUserName: null,
            agentDisplayName: $agent,
            phoneNumber: $phone,
            callDate: Carbon::parse('2026-06-23 10:00:00'),
            callStartedAt: Carbon::parse('2026-06-23 09:57:53'),
            callEndedAt: Carbon::parse('2026-06-23 10:00:00'),
            callDirection: 'OUTBOUND',
            status: $status,
            dispositionCode: null,
            dispositionLabel: 'Unmapped',
            durationSeconds: 127,
            talkSeconds: null,
            waitSeconds: null,
            rawEndReason: 'HANGUP',
            sourceTable: 'vicidial_log',
        );
    }
}
