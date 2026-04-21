<?php

namespace App\Services\Telephony;

use App\Models\CallSession;
use App\Models\TelephonyAlert;
use App\Models\UnmatchedAmiEvent;
use App\Models\VicidialServer;
use App\Repositories\VicidialServerRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Provides telephony health metrics for monitoring and alerting.
 */
class TelephonyHealthService
{
    public function __construct(
        protected VicidialServerRepository $vicidialServers,
    ) {}

    /**
     * Collect all telephony health metrics.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $staleAt = now()->subMinutes(CallStateService::STALE_CALL_MINUTES);

        $activeCalls = CallSession::active()->count();
        $staleCalls = CallSession::active()->where('dialed_at', '<', $staleAt)->count();
        $unmatchedAmi24h = UnmatchedAmiEvent::unprocessed()
            ->whereIn('event', ['Hangup', 'HangupRequest', 'SoftHangupRequest'])
            ->where('received_at', '>=', now()->subHours(24))
            ->count();

        $failedTelephonyJobs24h = $this->getFailedTelephonyJobsCount();

        $vicidialReachable = $this->checkVicidialReachability();

        $alerts24h = TelephonyAlert::recent(24)->count();
        $alertsByType = TelephonyAlert::recent(24)
            ->selectRaw('type, severity, COUNT(*) as count')
            ->groupBy('type', 'severity')
            ->get()
            ->groupBy('type')
            ->map(fn ($g) => $g->pluck('count', 'severity')->all())
            ->all();

        return [
            'active_calls' => $activeCalls,
            'stale_calls' => $staleCalls,
            'unmatched_ami_events_24h' => $unmatchedAmi24h,
            'failed_telephony_jobs_24h' => $failedTelephonyJobs24h,
            'vicidial_reachable' => $vicidialReachable,
            'alerts_24h' => $alerts24h,
            'alerts_by_type' => $alertsByType,
            'db_connection' => $this->checkDbConnection(),
        ];
    }

    /**
     * Determine overall status: ok, degraded, critical.
     */
    public function getStatus(?array $metrics = null): string
    {
        $m = $metrics ?? $this->getMetrics();

        if ($m['stale_calls'] > 0 || $m['failed_telephony_jobs_24h'] > 10 || ! $m['db_connection']) {
            return 'critical';
        }

        if ($m['unmatched_ami_events_24h'] > 20 || $m['alerts_24h'] > 50 || ! $m['vicidial_reachable']) {
            return 'degraded';
        }

        return 'ok';
    }

    protected function getFailedTelephonyJobsCount(): int
    {
        try {
            return DB::table('failed_jobs')
                ->where('queue', 'telephony')
                ->where('failed_at', '>=', now()->subHours(24))
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function checkVicidialReachability(): bool
    {
        $server = VicidialServer::active()->whereNotNull('api_url')->where('api_url', '!=', '')->first();
        if (! $server || empty($server->api_url)) {
            return true; // No server configured = not applicable
        }

        try {
            $url = rtrim((string) $server->api_url, '?&');
            $response = Http::when(! config('vicidial.verify_ssl', true), fn ($h) => $h->withoutVerifying())
                ->connectTimeout(3)->timeout(5)->get($url);

            return $response->status() < 500;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function checkDbConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
