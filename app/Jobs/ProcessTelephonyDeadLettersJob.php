<?php

namespace App\Jobs;

use App\Services\Telephony\TelephonyAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Monitors failed_jobs for telephony queue and logs them as alerts.
 * Runs periodically to surface dead telephony jobs.
 */
class ProcessTelephonyDeadLettersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array<int, string>
     */
    public const APPLICATION_QUEUES = ['imports', 'asterisk', 'telephony'];

    /**
     * @var array<int, class-string>
     */
    public const APPLICATION_JOB_CLASSES = [
        ReconcileCallStateJob::class,
        CallNoAnswerTimeoutJob::class,
        AsteriskOriginateJob::class,
        ImportLeadsCsvJob::class,
    ];

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(TelephonyAlertService $alerts): void
    {
        $failed = $this->getFailedTelephonyJobs();
        $alertedUuids = $this->getRecentlyAlertedUuids();

        foreach ($failed as $job) {
            $uuid = $job->uuid ?? (string) $job->id;
            if (in_array($uuid, $alertedUuids, true)) {
                continue;
            }
            $alerts->deadLetter(
                $uuid,
                $job->queue ?? 'telephony',
                strlen((string) $job->exception) > 500
                    ? substr((string) $job->exception, 0, 500).'...'
                    : (string) ($job->exception ?? 'Unknown error'),
            );
        }
    }

    protected function getRecentlyAlertedUuids(): array
    {
        $alerts = \App\Models\TelephonyAlert::where('type', 'dead_letter')
            ->where('created_at', '>=', now()->subHours(24))
            ->get('context');
        $uuids = [];
        foreach ($alerts as $a) {
            $ctx = $a->context;
            if (is_array($ctx) && ! empty($ctx['uuid'])) {
                $uuids[] = $ctx['uuid'];
            }
        }

        return array_unique($uuids);
    }

    /**
     * @return \Illuminate\Support\Collection<object{uuid: string, queue: string, exception: string, id: int}>
     */
    protected function getFailedTelephonyJobs()
    {
        try {
            return DB::table('failed_jobs')
                ->where(fn (Builder $query) => self::scopeApplicationFailedJobs($query))
                ->where('failed_at', '>=', now()->subHours(24))
                ->select('id', 'uuid', 'queue', 'exception', 'payload')
                ->limit(50)
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    public static function scopeApplicationFailedJobs(Builder $query): Builder
    {
        return $query
            ->whereIn('queue', self::APPLICATION_QUEUES)
            ->orWhere(function (Builder $payloadQuery): void {
                foreach (self::APPLICATION_JOB_CLASSES as $jobClass) {
                    $payloadQuery->orWhere('payload', 'like', '%'.class_basename($jobClass).'%');
                }
            });
    }
}
