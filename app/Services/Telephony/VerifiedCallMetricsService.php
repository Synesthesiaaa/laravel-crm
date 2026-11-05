<?php

namespace App\Services\Telephony;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The only standardized rate calculation path. Raw dialer exports are not evidence. */
class VerifiedCallMetricsService
{
    public const DEFINITIONS = [
        'answer_rate' => 'Verified answered call attempts ÷ total valid call attempts × 100. Includes verified human and machine answers on the customer leg.',
        'contact_rate' => 'Unique leads with a verified live agent–customer conversation ÷ unique attempted leads × 100. Repeated attempts count once per lead.',
        'abandoned_rate' => 'Live answered calls disconnected before agent connection ÷ total live answered calls × 100. Machines and unanswered calls are excluded.',
    ];

    public function __construct(private CrmCampaignVicidialScopeResolver $scopeResolver) {}

    public function forCampaign(string $campaign, CarbonInterface $from, CarbonInterface $until, array $filters = []): array
    {
        return $this->forScope($this->scopeResolver->resolve($campaign), $from, $until, $filters);
    }

    public function forScope(VicidialCampaignScope $scope, CarbonInterface $from, CarbonInterface $until, array $filters = []): array
    {
        $from = CarbonImmutable::instance($from)->utc();
        $until = CarbonImmutable::instance($until)->utc();
        $result = $this->unavailable('source_not_configured');
        $result['scope'] = ['crm_campaign' => $scope->campaign->code, 'server_id' => $scope->server?->getKey(),
            'start' => $from->toIso8601String(), 'end_exclusive' => $until->toIso8601String(),
            'timezone' => $filters['timezone'] ?? config('app.timezone'), 'agent' => $filters['agent'] ?? null,
            'cohort' => 'attempt_started_at', 'lead_identity' => 'server-local lead ID',
            'disposition_scope' => 'not applied to standardized metrics'];
        $codes = $scope->narrowCampaignCodes($filters['campaigns'] ?? null, (bool) ($filters['historical'] ?? true));
        if (! $scope->campaign->exists || $scope->server === null || $codes === []) {
            return $result;
        }
        if ($from->gte($until) || trim((string) ($filters['ingroups'] ?? '')) !== '') {
            return array_replace($result, $this->unavailable($from->gte($until) ? 'invalid_range' : 'ingroup_evidence_unavailable'));
        }
        if (! Schema::hasTable('telephony_metric_attempts') || ! Schema::hasTable('telephony_metric_coverage')) {
            return array_replace($result, $this->unavailable('evidence_storage_not_ready'));
        }

        // Scope this isolation level to the next read transaction; do not change the session default.
        if (DB::transactionLevel() === 0 && in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        return DB::transaction(function () use ($scope, $from, $until, $filters, $codes, $result): array {
            $coverageRows = DB::table('telephony_metric_coverage')->where('crm_campaign_id', $scope->campaign->getKey())
                ->where('vicidial_server_id', $scope->server->getKey())->whereIn('campaign', $codes)
                ->where('definition_version', 'verified-v1')->where('start_at', '<', $until->format('Y-m-d H:i:s.u'))
                ->where('end_at', '>', $from->format('Y-m-d H:i:s.u'))->orderBy('start_at')->get();
            if (($filters['historical'] ?? true) === false && $coverageRows->isNotEmpty()) {
                $effectiveUntil = $until;
                foreach ($codes as $code) {
                    $latest = $coverageRows->whereStrict('campaign', $code)->max('end_at');
                    if ($latest !== null) {
                        $effectiveUntil = $effectiveUntil->min(CarbonImmutable::parse($latest, 'UTC'));
                    }
                }
                $lag = max(0, (int) $effectiveUntil->diffInSeconds($until));
                $result['freshness'] = ['lag_seconds' => $lag, 'observed_through' => $effectiveUntil->toIso8601String(), 'status' => $lag > 60 ? 'stale' : 'current'];
                if ($lag > 60 || $effectiveUntil->lte($from)) {
                    return array_replace($result, $this->unavailable('stale_source_coverage'));
                }
                $result['scope']['requested_end_exclusive'] = $until->toIso8601String();
                $until = $effectiveUntil;
                $result['scope']['end_exclusive'] = $until->toIso8601String();
            }
            $agent = trim((string) ($filters['agent'] ?? ''));
            $base = DB::table('telephony_metric_attempts as t')
                ->where('t.crm_campaign_id', $scope->campaign->getKey())->where('t.vicidial_server_id', $scope->server->getKey())
                ->whereIn('t.campaign', $codes)->where('t.definition_version', 'verified-v1')
                ->where('t.started_at', '>=', $from->format('Y-m-d H:i:s.u'))
                ->where('t.started_at', '<', $until->format('Y-m-d H:i:s.u'));
            if ($agent !== '') {
                $base->join('telephony_metric_agents as a', function ($join) use ($agent): void {
                    $join->on('a.attempt_id', '=', 't.id')->where('a.login', $agent);
                });
            }
            $overall = $this->aggregate(clone $base, $agent !== '')->first();
            $byCampaign = $this->aggregate(clone $base, $agent !== '')->addSelect('t.campaign')->groupBy('t.campaign')->get()->keyBy('campaign');
            $complete = [];
            foreach ($codes as $code) {
                foreach (array_keys(self::DEFINITIONS) as $metric) {
                    $cursor = $from;
                    foreach ($coverageRows->whereStrict('campaign', $code)->where('metric', $metric) as $coverage) {
                        $start = CarbonImmutable::parse($coverage->start_at, 'UTC');
                        $end = CarbonImmutable::parse($coverage->end_at, 'UTC');
                        if ($start->gt($cursor) || CarbonImmutable::parse($coverage->observed_through, 'UTC')->lt($end)) {
                            continue;
                        }
                        if ($end->gt($cursor)) {
                            $cursor = $end;
                        }
                    }
                    $complete[$code][$metric] = $cursor->gte($until);
                }
                $result['campaigns'][$code] = $this->format($byCampaign[$code] ?? null, $complete[$code], $agent !== '');
            }
            $allComplete = [];
            foreach (array_keys(self::DEFINITIONS) as $metric) {
                $allComplete[$metric] = collect($complete)->every(fn (array $entry): bool => $entry[$metric]);
            }
            $result = array_replace($result, $this->format($overall, $allComplete, $agent !== ''));
            $result['coverage'] = $coverageRows->map(fn ($row): array => [
                'campaign' => $row->campaign, 'metric' => $row->metric, 'start' => $row->start_at,
                'end_exclusive' => $row->end_at, 'observed_through' => $row->observed_through,
                'source_reference' => $row->source_reference,
            ])->all();
            $result['source'] = 'verified_call_evidence';
            $result['generated_at'] = now()->toIso8601String();

            return $result;
        });
    }

    private function aggregate(Builder $query, bool $agent): Builder
    {
        $lead = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(t.vicidial_server_id AS TEXT) || ':' || t.lead_key"
            : "CONCAT(t.vicidial_server_id, ':', t.lead_key)";
        $conversation = $agent ? 'a.conversation_verified' : 't.conversation_verified';

        return $query->selectRaw("COUNT(*) as rows_count,
            COALESCE(SUM(CASE WHEN t.valid_attempt = 1 THEN 1 ELSE 0 END), 0) as valid_attempts,
            COALESCE(SUM(CASE WHEN t.valid_attempt = 1 AND t.answered = 1 THEN 1 ELSE 0 END), 0) as answered,
            COALESCE(SUM(CASE WHEN t.valid_attempt = 1 AND t.live_answer = 1 THEN 1 ELSE 0 END), 0) as live,
            COALESCE(SUM(CASE WHEN t.valid_attempt = 1 AND t.live_answer = 1 AND t.agent_connected = 0 AND t.ended_at IS NOT NULL THEN 1 ELSE 0 END), 0) as abandoned,
            COUNT(DISTINCT CASE WHEN t.valid_attempt = 1 THEN {$lead} END) as attempted_leads,
            COUNT(DISTINCT CASE WHEN t.valid_attempt = 1 AND {$conversation} = 1 THEN {$lead} END) as contacted_leads,
            COALESCE(SUM(CASE WHEN t.valid_attempt IS NULL THEN 1 ELSE 0 END), 0) as unknown_valid,
            COALESCE(SUM(CASE WHEN t.valid_attempt = 1 AND t.answered IS NULL THEN 1 ELSE 0 END), 0) as unknown_answer,
            COALESCE(SUM(CASE WHEN t.valid_attempt = 1 AND (t.lead_key IS NULL OR t.lead_key = '' OR {$conversation} IS NULL) THEN 1 ELSE 0 END), 0) as unknown_contact,
            COALESCE(SUM(CASE WHEN t.valid_attempt = 1 AND (t.answered IS NULL OR (t.answered = 1 AND t.live_answer IS NULL) OR (t.live_answer = 1 AND (t.agent_connected IS NULL OR (t.agent_connected = 0 AND t.ended_at IS NULL)))) THEN 1 ELSE 0 END), 0) as unknown_abandon");
    }

    private function format(?object $counts, array $complete, bool $agent): array
    {
        $counts = (array) $counts;
        $result = ['rates' => [], 'metrics' => []];
        foreach (self::DEFINITIONS as $key => $definition) {
            [$numeratorKey, $denominatorKey, $unknownKey] = match ($key) {
                'answer_rate' => ['answered', 'valid_attempts', 'unknown_answer'],
                'contact_rate' => ['contacted_leads', 'attempted_leads', 'unknown_contact'],
                default => ['abandoned', 'live', 'unknown_abandon'],
            };
            $reason = ! ($complete[$key] ?? false) ? 'incomplete_source_coverage' : null;
            if (($counts['unknown_valid'] ?? 0) > 0 || ($counts[$unknownKey] ?? 0) > 0) {
                $reason = 'unverified_or_pending_call_evidence';
            }
            if ($agent && $key === 'abandoned_rate') {
                $reason = 'agent_attribution_unavailable';
            }
            $numerator = (int) ($counts[$numeratorKey] ?? 0);
            $denominator = (int) ($counts[$denominatorKey] ?? 0);
            $rate = $reason === null ? ($denominator > 0 ? round($numerator / $denominator * 100, 2) : 0.0) : null;
            $result['rates'][$key] = $rate;
            $result['metrics'][$key] = ['rate' => $rate, 'numerator' => $reason === null ? $numerator : null,
                'denominator' => $reason === null ? $denominator : null,
                'state' => $reason !== null ? 'unavailable' : ($denominator === 0 ? 'confirmed_zero' : 'verified'),
                'reason' => $reason, 'definition' => $definition];
        }

        return $result;
    }

    public function unavailable(string $reason): array
    {
        $result = $this->format(null, [], false);
        foreach ($result['metrics'] as &$metric) {
            $metric['reason'] = $reason;
        }
        unset($metric);

        return [...$result, 'source' => 'verified_call_evidence', 'campaigns' => [], 'coverage' => [], 'definition_version' => 'verified-v1'];
    }
}
