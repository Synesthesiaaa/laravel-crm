<?php

namespace Tests\Feature;

use App\Jobs\CallNoAnswerTimeoutJob;
use App\Jobs\ImportLeadsCsvJob;
use App\Jobs\ProcessTelephonyDeadLettersJob;
use App\Jobs\ReconcileCallStateJob;
use App\Models\TelephonyAlert;
use App\Services\Telephony\TelephonyAlertService;
use App\Services\Telephony\TelephonyHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueStabilizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_horizon_config_supervises_all_application_queues(): void
    {
        config([
            'queue.default' => 'redis',
        ]);

        $this->assertSame('redis', config('queue.default'));
        $this->assertSame(900, config('queue.connections.redis.retry_after'));

        $supervisors = config('horizon.defaults');

        $this->assertSame(['default'], $supervisors['supervisor-default']['queue']);
        $this->assertSame(['imports'], $supervisors['supervisor-imports']['queue']);
        $this->assertSame(['asterisk'], $supervisors['supervisor-asterisk']['queue']);
        $this->assertSame(['telephony'], $supervisors['supervisor-telephony']['queue']);

        $this->assertGreaterThan(600, $supervisors['supervisor-imports']['timeout']);
        $this->assertSame(1, $supervisors['supervisor-imports']['maxProcesses']);
        $this->assertSame([10, 30, 60], $supervisors['supervisor-asterisk']['backoff']);
        $this->assertSame([60, 180, 300], $supervisors['supervisor-telephony']['backoff']);
    }

    public function test_scheduler_lists_reconciliation_horizon_snapshot_and_dead_letter_processing(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('reconcile-call-state')
            ->expectsOutputToContain('horizon:snapshot')
            ->expectsOutputToContain('process-telephony-dead-letters')
            ->assertExitCode(0);
    }

    public function test_call_no_answer_timeout_job_does_not_define_constructor_delay(): void
    {
        $job = new CallNoAnswerTimeoutJob(123);

        $this->assertNull($job->delay);
        $this->assertSame('default', $job->queue);
    }

    public function test_reconcile_call_state_job_prevents_overlapping_runs(): void
    {
        $middleware = (new ReconcileCallStateJob)->middleware();

        $this->assertNotEmpty($middleware);
    }

    public function test_dead_letter_processing_alerts_once_for_application_failed_jobs(): void
    {
        $this->insertFailedJob('imports', ImportLeadsCsvJob::class, 'import-uuid');

        $job = new ProcessTelephonyDeadLettersJob;
        $job->handle(app(TelephonyAlertService::class));
        $job->handle(app(TelephonyAlertService::class));

        $this->assertDatabaseCount('telephony_alerts', 1);
        $this->assertDatabaseHas('telephony_alerts', [
            'type' => TelephonyAlert::TYPE_DEAD_LETTER,
            'message' => 'Telephony job failed: imports',
        ]);
    }

    public function test_telephony_health_counts_failed_application_jobs_across_queues(): void
    {
        $this->insertFailedJob('asterisk', \App\Jobs\AsteriskOriginateJob::class, 'asterisk-uuid');
        $this->insertFailedJob('default', CallNoAnswerTimeoutJob::class, 'timeout-uuid');

        $metrics = app(TelephonyHealthService::class)->getMetrics();

        $this->assertSame(2, $metrics['failed_telephony_jobs_24h']);
    }

    private function insertFailedJob(string $queue, string $displayName, string $uuid): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'redis',
            'queue' => $queue,
            'payload' => json_encode([
                'displayName' => $displayName,
                'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            ], JSON_THROW_ON_ERROR),
            'exception' => 'RuntimeException: test failure',
            'failed_at' => now(),
        ]);
    }
}
