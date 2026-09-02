<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\CrmCallHistory;
use App\Models\User;
use App\Models\VicidialServer;
use App\Services\Telephony\HistoricalCallProviderResult;
use App\Services\Telephony\HistoricalCallRecord;
use App\Services\Telephony\VicidialHistoricalCallProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class RecordsTest extends TestCase
{
    use RefreshDatabase;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaign = Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
            'color' => '#3b82f6',
        ]);
        $server = VicidialServer::factory()->create(['campaign_code' => 'mbsales']);
        CampaignVicidialMapping::create([
            'campaign_id' => $this->campaign->id,
            'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'CAMP_A',
            'is_enabled' => true,
            'status' => CampaignVicidialMapping::STATUS_ACTIVE,
        ]);
    }

    protected function tearDown(): void
    {
        config(['vicidial.events_webhook_secret' => '']);

        parent::tearDown();
    }

    public function test_call_history_lists_only_authenticated_agents_historical_calls(): void
    {
        $agent = User::factory()->create(['full_name' => 'Agent One', 'vici_user' => 'agent_one']);
        $otherAgent = User::factory()->create(['full_name' => 'Agent Two', 'vici_user' => 'agent_two']);

        $this->mockHistoricalProvider([
            $this->historicalRecord('call-one', $agent, '639111111111', 'SALE', 101),
            $this->historicalRecord('call-two', $otherAgent, '639222222222', 'SALE', 202),
        ]);

        $this->actingAs($agent)
            ->withSession($this->campaignSession())
            ->get(route('records.index'))
            ->assertOk()
            ->assertSee('639111111111')
            ->assertSee('Sale')
            ->assertDontSee('639222222222')
            ->assertDontSee('Agent Two');
    }

    public function test_call_history_filters_by_date_status_and_phone(): void
    {
        $agent = User::factory()->create(['vici_user' => 'agent_filter']);
        $match = $this->historicalRecord('match', $agent, '639121234567', 'SALE', 111);
        $match = $this->copyRecord($match, callDate: Carbon::parse('2026-05-18 09:00:00'));
        $wrongStatus = $this->historicalRecord('wrong-status', $agent, '639121234567', 'DROP', 112);
        $wrongStatus = $this->copyRecord($wrongStatus, callDate: Carbon::parse('2026-05-18 10:00:00'));
        $wrongDate = $this->historicalRecord('wrong-date', $agent, '639129999999', 'SALE', 113);
        $wrongDate = $this->copyRecord($wrongDate, callDate: Carbon::parse('2026-05-10 09:00:00'));

        $this->mockHistoricalProvider([$match, $wrongStatus, $wrongDate]);

        $response = $this->actingAs($agent)
            ->withSession($this->campaignSession())
            ->get(route('records.index', [
                'start_date' => '2026-05-17',
                'end_date' => '2026-05-19',
                'phone' => '1234567',
                'status' => 'SALE',
            ]))
            ->assertOk()
            ->assertSee('639121234567')
            ->assertDontSee('639129999999');
        $response->assertDontSee('wrong-status');
    }

    public function test_call_history_does_not_use_crm_submission_history(): void
    {
        $agent = User::factory()->create(['vici_user' => 'agent_submission']);

        CrmCallHistory::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'loan_application',
            'record_id' => 55,
            'agent' => $agent->full_name,
            'phone_number' => '639133333333',
            'status' => 'RECORDED',
        ]);
        $this->mockHistoricalProvider([]);

        $this->actingAs($agent)
            ->withSession($this->campaignSession())
            ->get(route('records.index'))
            ->assertOk()
            ->assertSee('No calls were found.')
            ->assertDontSee('loan_application')
            ->assertDontSee('639133333333');
    }

    public function test_call_history_shows_unavailable_state_when_vicidial_fails(): void
    {
        $agent = User::factory()->create(['vici_user' => 'agent_unavailable']);
        $this->mockHistoricalProvider([], HistoricalCallProviderResult::failure(
            'VICIdial call history is currently unavailable. Please try again.',
            ['classification' => 'REMOTE_DATABASE_ERROR'],
        ));

        $this->actingAs($agent)
            ->withSession($this->campaignSession())
            ->get(route('records.index'))
            ->assertOk()
            ->assertSee('Call History unavailable')
            ->assertSee('Retry')
            ->assertDontSee('No calls were found.');
    }

    public function test_live_webhook_updates_call_session_without_making_it_historical_call_history(): void
    {
        config(['vicidial.events_webhook_secret' => 'vicidial-secret']);

        $this->travelTo(Carbon::parse('2026-06-23 12:00:00'), function (): void {
            $agent = User::factory()->create([
                'username' => 'agent_vici',
                'vici_user' => 'agent_vici',
                'full_name' => 'Agent Vici',
            ]);
            $this->mockHistoricalProvider([]);

            $session = CallSession::factory()
                ->for($agent)
                ->ringing()
                ->create([
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
            ], [
                'X-Webhook-Secret' => 'vicidial-secret',
            ])
                ->assertOk()
                ->assertJsonPath('received', true)
                ->assertJsonPath('processed', true);

            $this->postJson(route('api.webhooks.vicidial-events'), [
                'user' => 'agent_vici',
                'event' => 'call_answered',
            ], [
                'X-Webhook-Secret' => 'vicidial-secret',
            ])
                ->assertOk()
                ->assertJsonPath('received', true)
                ->assertJsonPath('processed', true);

            $session->refresh();
            $this->assertSame(CallSession::STATUS_COMPLETED, $session->status);
            $this->assertSame(120, $session->call_duration_seconds);

            $this->actingAs($agent)
                ->withSession($this->campaignSession())
                ->get(route('records.index'))
                ->assertOk()
                ->assertSee('No calls were found.');
        });
    }

    /**
     * @param  array<int, HistoricalCallRecord>  $records
     */
    private function mockHistoricalProvider(array $records, ?HistoricalCallProviderResult $result = null): void
    {
        $provider = Mockery::mock(VicidialHistoricalCallProvider::class);
        $provider->shouldReceive('fetch')->andReturnUsing(
            function ($server, $campaign, array $campaignCodes, array $filters, int $page, int $perPage) use ($records, $result): HistoricalCallProviderResult {
                $filtered = collect($records)->filter(function (HistoricalCallRecord $record) use ($filters): bool {
                    if (! empty($filters['agent']) && strcasecmp((string) $record->vicidialUser, (string) $filters['agent']) !== 0) {
                        return false;
                    }
                    if (! empty($filters['statuses']) && ! in_array($record->status, $filters['statuses'], true)) {
                        return false;
                    }
                    if (! empty($filters['start_date']) && $record->callDate?->lt(Carbon::parse($filters['start_date'])->startOfDay())) {
                        return false;
                    }
                    if (! empty($filters['end_date']) && $record->callDate?->gt(Carbon::parse($filters['end_date'])->endOfDay())) {
                        return false;
                    }
                    if (! empty($filters['phone']) && ! str_contains(preg_replace('/\D+/', '', (string) $record->phoneNumber), preg_replace('/\D+/', '', (string) $filters['phone']))) {
                        return false;
                    }

                    return true;
                })->values();

                $metadata = collect($records);

                return $result ?? HistoricalCallProviderResult::success(
                    $filtered->forPage($page, $perPage)->all(),
                    $filtered->count(),
                    [
                        'agents' => $metadata->pluck('vicidialUser')->filter()->unique()->values()->all(),
                        'statuses' => $metadata->pluck('status')->filter()->unique()->values()->all(),
                        'campaigns' => $campaignCodes,
                    ],
                    ['source' => 'vicidial_database', 'server_id' => $server->id],
                );
            },
        );
        $this->app->instance(VicidialHistoricalCallProvider::class, $provider);
    }

    private function historicalRecord(string $id, User $user, string $phone, string $status, int $leadId): HistoricalCallRecord
    {
        return new HistoricalCallRecord(
            id: 'vicidial_log:'.$id,
            uniqueCallId: $id,
            crmCampaignId: $this->campaign->id,
            crmCampaignCode: 'mbsales',
            vicidialCampaignId: 'CAMP_A',
            vicidialListId: 'LIST_A',
            leadId: $leadId,
            vicidialUser: $user->vici_user,
            crmUserId: null,
            crmUserName: null,
            agentDisplayName: $user->vici_user,
            phoneNumber: $phone,
            callDate: Carbon::parse('2026-06-23 10:00:00'),
            callStartedAt: Carbon::parse('2026-06-23 09:58:00'),
            callEndedAt: Carbon::parse('2026-06-23 10:00:00'),
            callDirection: 'OUTBOUND',
            status: $status,
            dispositionCode: null,
            dispositionLabel: 'Unmapped',
            durationSeconds: 120,
            talkSeconds: null,
            waitSeconds: null,
            rawEndReason: 'HANGUP',
            sourceTable: 'vicidial_log',
        );
    }

    private function copyRecord(HistoricalCallRecord $record, ?Carbon $callDate = null): HistoricalCallRecord
    {
        return new HistoricalCallRecord(
            id: $record->id,
            uniqueCallId: $record->uniqueCallId,
            crmCampaignId: $record->crmCampaignId,
            crmCampaignCode: $record->crmCampaignCode,
            vicidialCampaignId: $record->vicidialCampaignId,
            vicidialListId: $record->vicidialListId,
            leadId: $record->leadId,
            vicidialUser: $record->vicidialUser,
            crmUserId: $record->crmUserId,
            crmUserName: $record->crmUserName,
            agentDisplayName: $record->agentDisplayName,
            phoneNumber: $record->phoneNumber,
            callDate: $callDate ?? $record->callDate,
            callStartedAt: $record->callStartedAt,
            callEndedAt: $record->callEndedAt,
            callDirection: $record->callDirection,
            status: $record->status,
            dispositionCode: $record->dispositionCode,
            dispositionLabel: $record->dispositionLabel,
            durationSeconds: $record->durationSeconds,
            talkSeconds: $record->talkSeconds,
            waitSeconds: $record->waitSeconds,
            rawEndReason: $record->rawEndReason,
            sourceTable: $record->sourceTable,
        );
    }

    private function campaignSession(): array
    {
        return ['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'];
    }
}
