<?php

namespace Tests\Feature;

use App\Jobs\SyncVicidialCallHistoryJob;
use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\VicidialCallHistorySyncState;
use App\Models\VicidialServer;
use App\Services\Telephony\CrmCampaignVicidialScopeResolver;
use App\Services\Telephony\HistoricalCallProviderResult;
use App\Services\Telephony\LocalCallHistoryQueryService;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialCallHistorySyncService;
use App\Services\Telephony\VicidialHistoricalCallProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CallHistorySyncReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_history_job_queue_matches_horizon_and_local_worker_configuration(): void
    {
        $job = new SyncVicidialCallHistoryJob(123);
        $horizonSupervisor = config('horizon.defaults.supervisor-telephony');
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('telephony', $job->queue);
        $this->assertSame(['telephony'], $horizonSupervisor['queue']);
        $this->assertSame(120, $horizonSupervisor['timeout']);
        $this->assertStringContainsString('--queue=telephony,default', (string) $composer['scripts']['dev'][1]);
        $this->assertStringContainsString('--queue=telephony,default', (string) file_get_contents(base_path('start-dev.sh')));
        $this->assertStringContainsString('--queue=telephony,default', (string) file_get_contents(base_path('start-dev.bat')));
        $this->assertStringContainsString('--queue=telephony,default', (string) file_get_contents(base_path('README.md')));
    }

    public function test_scheduler_registers_recent_call_history_dispatch(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('vicidial:sync-call-history --recent')
            ->assertExitCode(0);
    }

    public function test_future_checkpoint_uses_a_bounded_recent_window_and_recovers_health(): void
    {
        config(['vicidial.campaign_scope_cache_seconds' => 0]);
        $campaign = Campaign::factory()->create(['code' => 'future-cursor']);
        $server = VicidialServer::factory()->create(['campaign_code' => 'future-cursor']);
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => $campaign->id,
            'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'FUTURE_CURSOR',
            'is_enabled' => true,
            'status' => CampaignVicidialMapping::STATUS_ACTIVE,
        ]);
        VicidialCallHistorySyncState::factory()->create([
            'vicidial_server_id' => $server->id,
            'crm_campaign_id' => $campaign->id,
            'status' => VicidialCallHistorySyncState::STATUS_HEALTHY,
            'last_call_at' => '2026-05-18 10:00:00',
            'last_successful_sync_at' => '2026-05-18 08:59:00',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-05-18 09:00:00'));

        try {
            $provider = new RecordingSyncProvider;
            $service = new VicidialCallHistorySyncService(
                $provider,
                $this->app->make(CrmCampaignVicidialScopeResolver::class),
                $this->app->make(TelephonyLogger::class),
            );

            $result = $service->sync($campaign);
            $state = VicidialCallHistorySyncState::query()->forScope($server->id, $campaign->id)->firstOrFail();
            $health = $this->app->make(LocalCallHistoryQueryService::class)->syncHealth($campaign->code);

            $this->assertTrue($result->success);
            $this->assertTrue($provider->from?->equalTo(Carbon::parse('2026-05-18 08:45:00')));
            $this->assertTrue($provider->to?->equalTo(Carbon::parse('2026-05-18 09:00:00')));
            $this->assertTrue($state->last_call_at?->equalTo(Carbon::parse('2026-05-18 09:00:00')));
            $this->assertSame(VicidialCallHistorySyncState::STATUS_HEALTHY, $state->status);
            $this->assertSame('healthy', $health['status']);
            $this->assertSame(1, $health['mapped_campaign_count']);
            $this->assertNotNull($health['last_attempted_sync_at']);
            $this->assertArrayHasKey('last_sync_duration_ms', $health);
            $this->assertArrayHasKey('current_window_start', $health);
            $this->assertArrayHasKey('current_window_end', $health);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_job_dispatch_is_targeted_to_the_telephony_queue(): void
    {
        Queue::fake();

        SyncVicidialCallHistoryJob::dispatch(123);

        Queue::assertPushed(SyncVicidialCallHistoryJob::class, function (SyncVicidialCallHistoryJob $job): bool {
            return $job->queue === 'telephony';
        });
    }

    public function test_job_middleware_can_be_constructed_by_the_queue_worker(): void
    {
        $middleware = (new SyncVicidialCallHistoryJob(123))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }
}

final class RecordingSyncProvider extends VicidialHistoricalCallProvider
{
    public ?Carbon $from = null;

    public ?Carbon $to = null;

    public function fetchRange(
        VicidialServer $server,
        Campaign $campaign,
        array $campaignCodes,
        Carbon $from,
        Carbon $to,
    ): HistoricalCallProviderResult {
        $this->from = $from;
        $this->to = $to;

        return HistoricalCallProviderResult::success([], 0);
    }
}
