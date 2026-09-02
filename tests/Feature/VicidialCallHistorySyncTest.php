<?php

namespace Tests\Feature;

use App\Jobs\SyncVicidialCallHistoryJob;
use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\TelephonyCallHistory;
use App\Models\VicidialCallHistorySyncState;
use App\Models\VicidialServer;
use App\Services\Telephony\CrmCampaignVicidialScopeResolver;
use App\Services\Telephony\HistoricalCallProviderResult;
use App\Services\Telephony\HistoricalCallRecord;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialCallHistorySyncService;
use App\Services\Telephony\VicidialHistoricalCallProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class VicidialCallHistorySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_sync_upserts_rows_and_creates_a_healthy_checkpoint(): void
    {
        [$campaign, $server] = $this->mappedScope('sync-initial');
        $provider = new FakeSyncProvider(HistoricalCallProviderResult::success([
            $this->record($campaign, 'call-1', '2026-05-18 09:01:00'),
        ], 1));
        $service = $this->service($provider);

        $result = $service->sync(
            $campaign,
            Carbon::parse('2026-05-18 09:00:00'),
            Carbon::parse('2026-05-18 09:15:00'),
        );

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->rowsInserted);
        $this->assertSame(0, $result->rowsUpdated);
        $this->assertDatabaseHas('telephony_call_histories', [
            'vicidial_server_id' => $server->id,
            'crm_campaign_id' => $campaign->id,
            'source_unique_id' => 'call-1',
        ]);
        $this->assertDatabaseHas('vicidial_call_history_sync_states', [
            'vicidial_server_id' => $server->id,
            'crm_campaign_id' => $campaign->id,
            'status' => VicidialCallHistorySyncState::STATUS_HEALTHY,
            'last_rows_received' => 1,
            'last_rows_inserted' => 1,
        ]);
    }

    public function test_overlapping_sync_is_idempotent_and_advances_the_checkpoint(): void
    {
        [$campaign] = $this->mappedScope('sync-overlap');
        $provider = new FakeSyncProvider(HistoricalCallProviderResult::success([
            $this->record($campaign, 'call-1', '2026-05-18 09:01:00'),
        ], 1));
        $service = $this->service($provider);

        $first = $service->sync($campaign, Carbon::parse('2026-05-18 09:00:00'), Carbon::parse('2026-05-18 09:05:00'));
        $provider->result = HistoricalCallProviderResult::success([
            $this->record($campaign, 'call-1', '2026-05-18 09:01:00'),
            $this->record($campaign, 'call-2', '2026-05-18 09:09:00'),
        ], 2);
        $second = $service->sync($campaign, Carbon::parse('2026-05-18 09:04:00'), Carbon::parse('2026-05-18 09:10:00'));

        $this->assertTrue($first->success);
        $this->assertTrue($second->success);
        $this->assertSame(1, $second->rowsInserted);
        $this->assertSame(1, $second->rowsUpdated);
        $this->assertSame(2, TelephonyCallHistory::query()->where('crm_campaign_id', $campaign->id)->count());
        $this->assertSame('call-2', VicidialCallHistorySyncState::query()->where('crm_campaign_id', $campaign->id)->value('last_unique_id'));
    }

    public function test_failed_sync_preserves_the_last_successful_checkpoint(): void
    {
        [$campaign] = $this->mappedScope('sync-failure');
        $provider = new FakeSyncProvider(HistoricalCallProviderResult::success([
            $this->record($campaign, 'call-1', '2026-05-18 09:01:00'),
        ], 1));
        $service = $this->service($provider);
        $service->sync($campaign, Carbon::parse('2026-05-18 09:00:00'), Carbon::parse('2026-05-18 09:05:00'));
        $checkpoint = VicidialCallHistorySyncState::query()->where('crm_campaign_id', $campaign->id)->firstOrFail()->last_call_at;

        $provider->result = HistoricalCallProviderResult::failure('remote unavailable', [
            'classification' => 'REMOTE_DATABASE_TIMEOUT',
            'retryable' => true,
        ]);
        $result = $service->sync($campaign, Carbon::parse('2026-05-18 09:04:00'), Carbon::parse('2026-05-18 09:10:00'));
        $state = VicidialCallHistorySyncState::query()->where('crm_campaign_id', $campaign->id)->firstOrFail();

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
        $this->assertSame(VicidialCallHistorySyncState::STATUS_FAILED, $state->status);
        $this->assertTrue($state->last_call_at->equalTo($checkpoint));
        $this->assertSame('REMOTE_DATABASE_TIMEOUT', $state->last_error_classification);
    }

    public function test_sync_command_dispatches_recent_and_chunked_backfill_jobs(): void
    {
        Queue::fake();
        [$campaign] = $this->mappedScope('sync-command');

        $this->artisan('vicidial:sync-call-history', ['--campaign' => $campaign->code, '--recent' => true])
            ->assertExitCode(0);
        Queue::assertPushed(SyncVicidialCallHistoryJob::class, function (SyncVicidialCallHistoryJob $job) use ($campaign): bool {
            return $job->crmCampaignId === $campaign->id && $job->from === null && $job->to === null;
        });

        Queue::fake();
        $this->artisan('vicidial:sync-call-history', [
            '--campaign' => $campaign->code,
            '--from' => '2026-05-01',
            '--to' => '2026-05-02',
        ])->expectsOutput('Dispatched 2 call-history synchronization job(s).')->assertExitCode(0);
        Queue::assertPushed(SyncVicidialCallHistoryJob::class, 1);
    }

    /**
     * @return array{0: Campaign, 1: VicidialServer}
     */
    private function mappedScope(string $code): array
    {
        config(['vicidial.campaign_scope_cache_seconds' => 0]);
        $campaign = Campaign::factory()->create(['code' => $code]);
        $server = VicidialServer::factory()->create(['campaign_code' => $code]);
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => $campaign->id,
            'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'VICI_'.strtoupper($code),
            'is_enabled' => true,
            'status' => CampaignVicidialMapping::STATUS_ACTIVE,
        ]);

        return [$campaign, $server];
    }

    private function service(FakeSyncProvider $provider): VicidialCallHistorySyncService
    {
        return new VicidialCallHistorySyncService(
            $provider,
            $this->app->make(CrmCampaignVicidialScopeResolver::class),
            $this->app->make(TelephonyLogger::class),
        );
    }

    private function record(Campaign $campaign, string $id, string $callDate): HistoricalCallRecord
    {
        $date = Carbon::parse($callDate);

        return new HistoricalCallRecord(
            id: 'vicidial_log:'.$id,
            uniqueCallId: $id,
            crmCampaignId: $campaign->id,
            crmCampaignCode: $campaign->code,
            vicidialCampaignId: 'VICI_'.strtoupper($campaign->code),
            vicidialListId: 'LIST_1',
            leadId: 101,
            vicidialUser: 'agent_one',
            crmUserId: null,
            crmUserName: null,
            agentDisplayName: 'agent_one',
            phoneNumber: '639121234567',
            callDate: $date,
            callStartedAt: $date,
            callEndedAt: $date->copy()->addMinute(),
            callDirection: 'OUTBOUND',
            status: 'SALE',
            dispositionCode: null,
            dispositionLabel: 'Unmapped',
            durationSeconds: 60,
            talkSeconds: null,
            waitSeconds: null,
            rawEndReason: 'HANGUP',
            sourceTable: 'vicidial_log',
        );
    }
}

final class FakeSyncProvider extends VicidialHistoricalCallProvider
{
    public function __construct(public HistoricalCallProviderResult $result) {}

    public function fetchRange(
        VicidialServer $server,
        Campaign $campaign,
        array $campaignCodes,
        Carbon $from,
        Carbon $to,
    ): HistoricalCallProviderResult {
        return $this->result;
    }
}
