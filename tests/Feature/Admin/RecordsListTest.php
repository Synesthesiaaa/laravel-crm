<?php

namespace Tests\Feature\Admin;

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

class RecordsListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'full_name' => 'Admin User',
        ]);

        $this->campaign = Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
            'color' => '#3b82f6',
        ]);
        Campaign::factory()->create([
            'code' => 'othercamp',
            'name' => 'Other Camp',
            'color' => '#ef4444',
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

    private function campaignSession(): array
    {
        return ['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'];
    }

    public function test_default_tab_shows_submitted_records(): void
    {
        CrmCallHistory::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'loan_application',
            'record_id' => 10,
            'agent' => 'agent_submit',
            'phone_number' => '639111111111',
            'status' => 'RECORDED',
        ]);
        CallSession::factory()->for($this->admin)->completed()->create([
            'campaign_code' => 'mbsales',
            'phone_number' => '639222222222',
        ]);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.records.index'))
            ->assertOk()
            ->assertSee('Submitted CRM records')
            ->assertSee('loan_application')
            ->assertSee('639111111111')
            ->assertDontSee('639222222222');
    }

    public function test_call_sessions_tab_shows_campaign_call_sessions(): void
    {
        $agent = User::factory()->create([
            'full_name' => 'Agent Caller',
            'username' => 'agent_caller',
            'vici_user' => 'agent_caller',
        ]);
        $otherCampaignAgent = User::factory()->create();

        $this->mockHistoricalProvider([
            $this->historicalRecord('call-one', $agent, '639333333333', 'SALE', 303),
        ]);
        CallSession::factory()->for($otherCampaignAgent)->completed()->create([
            'campaign_code' => 'othercamp',
            'lead_id' => 404,
            'phone_number' => '639444444444',
        ]);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.records.index', ['tab' => 'calls']))
            ->assertOk()
            ->assertSee('Historical VICIdial call records')
            ->assertSee('Agent Caller')
            ->assertSee('639333333333')
            ->assertSee('Sale')
            ->assertDontSee('639444444444');
    }

    public function test_call_sessions_tab_filters_and_tab_links_preserve_filters(): void
    {
        $agent = User::factory()->create([
            'full_name' => 'Agent Filter',
            'username' => 'agent_filter',
            'vici_user' => 'agent_filter',
        ]);
        $wrongAgent = User::factory()->create([
            'full_name' => 'Agent Hidden',
            'username' => 'agent_hidden',
            'vici_user' => 'agent_hidden',
        ]);

        $match = $this->copyRecord(
            $this->historicalRecord('match', $agent, '639555123456', 'SALE', 505),
            Carbon::parse('2026-05-18 09:00:00'),
        );
        $hidden = $this->copyRecord(
            $this->historicalRecord('hidden', $wrongAgent, '639555999999', 'SALE', 506),
            Carbon::parse('2026-05-18 10:00:00'),
        );
        $this->mockHistoricalProvider([$match, $hidden]);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.records.index', [
                'tab' => 'calls',
                'start_date' => '2026-05-17',
                'end_date' => '2026-05-19',
                'agent' => 'Filter',
                'phone' => '123456',
                'status' => 'SALE',
            ]))
            ->assertOk()
            ->assertSee('639555123456')
            ->assertDontSee('639555999999')
            ->assertSee('tab=submissions', false)
            ->assertSee('agent=Filter', false);
    }

    /**
     * @param  array<int, HistoricalCallRecord>  $records
     */
    private function mockHistoricalProvider(array $records): void
    {
        $provider = Mockery::mock(VicidialHistoricalCallProvider::class);
        $provider->shouldReceive('fetch')->andReturnUsing(
            function ($server, $campaign, array $campaignCodes, array $filters, int $page, int $perPage) use ($records): HistoricalCallProviderResult {
                $filtered = collect($records)->filter(function (HistoricalCallRecord $record) use ($filters): bool {
                    if (! empty($filters['agent']) && ! str_contains(strtolower((string) $record->vicidialUser), strtolower((string) $filters['agent']))) {
                        return false;
                    }
                    if (! empty($filters['statuses']) && ! in_array($record->status, $filters['statuses'], true)) {
                        return false;
                    }
                    if (! empty($filters['phone']) && ! str_contains((string) $record->phoneNumber, (string) $filters['phone'])) {
                        return false;
                    }
                    if (! empty($filters['start_date']) && $record->callDate?->lt(Carbon::parse($filters['start_date'])->startOfDay())) {
                        return false;
                    }
                    if (! empty($filters['end_date']) && $record->callDate?->gt(Carbon::parse($filters['end_date'])->endOfDay())) {
                        return false;
                    }

                    return true;
                })->values();

                return HistoricalCallProviderResult::success(
                    $filtered->forPage($page, $perPage)->all(),
                    $filtered->count(),
                    [
                        'agents' => collect($records)->pluck('vicidialUser')->filter()->unique()->values()->all(),
                        'statuses' => collect($records)->pluck('status')->filter()->unique()->values()->all(),
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

    private function copyRecord(HistoricalCallRecord $record, Carbon $callDate): HistoricalCallRecord
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
            callDate: $callDate,
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
}
