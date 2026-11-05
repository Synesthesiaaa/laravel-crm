<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telephony\CallMetricEvidenceService;
use App\Services\Telephony\TelephonyCampaignResolver;
use App\Services\Telephony\VerifiedCallMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class VerifiedCallMetricsController extends Controller
{
    public function ingest(Request $request, CallMetricEvidenceService $service)
    {
        $secret = (string) config('asterisk.metrics_webhook_secret');
        if ($secret === '') {
            return response()->json(['message' => 'Verified metric ingestion is not configured.'], 503);
        }
        if (! hash_equals($secret, (string) $request->header('X-Webhook-Secret', ''))) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json(['success' => true, 'data' => $service->import($request->all())]);
    }

    public function show(Request $request, VerifiedCallMetricsService $service)
    {
        return response()->json(['success' => true, 'data' => $this->report($request, $service)]);
    }

    public function export(Request $request, VerifiedCallMetricsService $service)
    {
        $result = $this->report($request, $service);

        return response()->streamDownload(function () use ($result): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['scope', 'metric', 'numerator', 'denominator', 'rate_percent', 'state', 'reason', 'formula', 'start_utc', 'end_exclusive_utc', 'timezone', 'definition_version', 'agent', 'source_watermarks', 'source_references', 'scope_type'], escape: '');
            $entries = [['overall', $result, 'overall']];
            foreach ($result['campaigns'] as $code => $entry) {
                $entries[] = [(string) $code, $entry, 'campaign'];
            }
            foreach ($entries as [$scope, $entry, $scopeType]) {
                foreach ($entry['metrics'] as $key => $metric) {
                    $row = [$scope, $key, $metric['numerator'], $metric['denominator'],
                        $metric['rate'] === null ? 'N/A' : number_format($metric['rate'], 2, '.', ''),
                        $metric['state'], $metric['reason'], $metric['definition'],
                        $result['scope']['start'], $result['scope']['end_exclusive'], $result['scope']['timezone'], 'verified-v1', $result['scope']['agent'],
                        json_encode(array_values(array_unique(array_column($result['coverage'], 'observed_through')))),
                        json_encode(array_values(array_unique(array_column($result['coverage'], 'source_reference')))), $scopeType];
                    $row = array_map(static fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value, $row);
                    fputcsv($file, $row, escape: '');
                }
            }
            fclose($file);
        }, 'verified-call-metrics.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function report(Request $request, VerifiedCallMetricsService $service): array
    {
        $filters = $request->validate([
            'campaign' => 'nullable|string|max:50', 'campaigns' => 'nullable|string|max:255',
            'agent' => 'nullable|string|max:100', 'timezone' => 'nullable|timezone',
            'query_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:query_date',
            'mode' => 'nullable|in:historical,live,today',
            'ingroups' => 'nullable|string|max:255',
        ]);
        $timezone = $filters['timezone'] ?? config('app.timezone');
        $now = CarbonImmutable::now($timezone);
        $mode = $filters['mode'] ?? 'historical';
        $from = match ($mode) {
            'live' => $now->subMinutes(max(1, (int) config('vicidial.supervisor.rolling_window_minutes', 15))),
            'today' => $now->startOfDay(),
            default => CarbonImmutable::parse($filters['query_date'] ?? $now->toDateString(), $timezone)->startOfDay(),
        };
        $until = $mode === 'historical'
            ? CarbonImmutable::parse($filters['end_date'] ?? $filters['query_date'] ?? $now->toDateString(), $timezone)->startOfDay()->addDay()
            : $now;
        $filters['historical'] = $mode === 'historical';
        $campaign = TelephonyCampaignResolver::resolve($request, $filters['campaign'] ?? $request->session()->get('campaign'));

        return $service->forCampaign($campaign, $from, $until, $filters);
    }
}
