<?php

namespace App\Services\Telephony;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Ingests attested normalized facts, never guesses classifications from disposition labels. */
class CallMetricEvidenceService
{
    public function __construct(private CrmCampaignVicidialScopeResolver $scopeResolver) {}

    public function import(array $input): array
    {
        $date = ['string', 'date', 'regex:/T.*(?:Z|[+-]\d{2}:\d{2})$/'];
        $rules = [
            'crm_campaign' => 'required|string|max:50', 'server_id' => 'required|integer',
            'campaign' => 'required|string|max:50', 'definition_version' => 'required|in:verified-v1',
            'source_reference' => 'required|string|max:255',
            'observed_at' => ['required_without:coverage', ...$date],
            'coverage' => 'sometimes|array', 'coverage.start' => ['required_with:coverage', ...$date],
            'coverage.end' => ['required_with:coverage', ...$date],
            'coverage.observed_through' => ['required_with:coverage', ...$date],
            'coverage.metrics' => 'required_with:coverage|array|min:1',
            'coverage.metrics.*' => 'required|distinct|in:answer_rate,contact_rate,abandoned_rate',
            'attempts' => 'present|array|max:5000', 'attempts.*.attempt_key' => 'required|string|max:100|distinct',
            'attempts.*.lead_key' => 'present|nullable|string|max:100',
            'attempts.*.started_at' => ['required', ...$date],
            'attempts.*.outcome' => 'required|string|max:50',
            'attempts.*.agents' => 'present|array|max:100',
            'attempts.*.agents.*.login' => 'required|string|max:100',
            'attempts.*.agents.*.conversation_verified' => 'present|nullable|boolean',
        ];
        foreach (['answered_at', 'agent_connected_at', 'ended_at'] as $field) {
            $rules['attempts.*.'.$field] = ['present', 'nullable', ...$date];
        }
        foreach (['valid_attempt', 'answered', 'live_answer', 'agent_connected', 'conversation_verified'] as $field) {
            $rules['attempts.*.'.$field] = 'present|nullable|boolean';
        }
        $data = Validator::make($input, $rules)->validate();
        $scope = $this->scopeResolver->resolve($data['crm_campaign']);
        if (! $scope->campaign->exists || (int) $scope->server?->getKey() !== (int) $data['server_id']
            || ! in_array($data['campaign'], $scope->historicalCampaignCodes(), true)) {
            throw ValidationException::withMessages(['campaign' => 'The server and campaign must belong to the configured CRM scope.']);
        }
        $observed = CarbonImmutable::parse($data['observed_at'] ?? $data['coverage']['observed_through'])->utc();
        if ($observed->gt(CarbonImmutable::now()->addMinute())) {
            throw ValidationException::withMessages(['observed_at' => 'The source watermark is in the future. Verify the source clock and timezone.']);
        }
        if (isset($data['coverage'])) {
            $coverageStart = CarbonImmutable::parse($data['coverage']['start'])->utc();
            $coverageEnd = CarbonImmutable::parse($data['coverage']['end'])->utc();
            $through = CarbonImmutable::parse($data['coverage']['observed_through'])->utc();
            if ($coverageStart->gte($coverageEnd) || $through->lt($coverageEnd) || $observed->lt($through)) {
                throw ValidationException::withMessages(['coverage' => 'Coverage must have a positive range fully observed by the batch watermark.']);
            }
        }
        foreach ($data['attempts'] as $index => $attempt) {
            $this->validateAttempt($attempt, $observed, $index);
        }

        return DB::transaction(function () use ($data, $scope, $observed): array {
            $scopeKey = ['crm_campaign_id' => $scope->campaign->getKey(), 'vicidial_server_id' => $scope->server->getKey()];
            $written = 0;
            foreach ($data['attempts'] as $attempt) {
                $identity = [...$scopeKey, 'attempt_key' => $attempt['attempt_key']];
                $existing = DB::table('telephony_metric_attempts')->where($identity)->lockForUpdate()->first();
                if ($existing && CarbonImmutable::parse($existing->observed_at, 'UTC')->gt($observed)) {
                    continue;
                }
                $row = array_intersect_key($attempt, array_flip(['lead_key', 'outcome', 'valid_attempt', 'answered', 'live_answer', 'agent_connected', 'conversation_verified']));
                foreach (['started_at', 'answered_at', 'agent_connected_at', 'ended_at'] as $field) {
                    $row[$field] = $attempt[$field] === null ? null : CarbonImmutable::parse($attempt[$field])->utc()->format('Y-m-d H:i:s.u');
                }
                foreach (['valid_attempt', 'answered', 'live_answer', 'agent_connected', 'conversation_verified'] as $flag) {
                    $row[$flag] = $row[$flag] === null ? null : (int) (bool) $row[$flag];
                }
                $row += ['campaign' => $data['campaign'], 'source_reference' => $data['source_reference'],
                    'definition_version' => $data['definition_version'], 'observed_at' => $observed->format('Y-m-d H:i:s.u')];
                $agents = collect($attempt['agents'])->sortBy('login')->map(fn ($agent) => [
                    'login' => $agent['login'], 'conversation_verified' => $agent['conversation_verified'] === null ? null : (int) (bool) $agent['conversation_verified'],
                ])->values()->all();
                $row['evidence_hash'] = hash('sha256', json_encode([$row, $agents], JSON_THROW_ON_ERROR));
                if ($existing && ($existing->campaign !== $data['campaign']
                    || ! CarbonImmutable::parse($existing->started_at, 'UTC')->equalTo(CarbonImmutable::parse($row['started_at'], 'UTC'))
                    || ($existing->lead_key !== null && $existing->lead_key !== $row['lead_key'])
                    || (CarbonImmutable::parse($existing->observed_at, 'UTC')->equalTo($observed) && $existing->evidence_hash !== $row['evidence_hash']))) {
                    throw ValidationException::withMessages(['attempts' => 'An original attempt cannot be reassigned or have conflicting evidence at the same watermark.']);
                }
                $row['updated_at'] = now();
                if ($existing) {
                    DB::table('telephony_metric_attempts')->where('id', $existing->id)->update($row);
                    $id = $existing->id;
                } else {
                    $id = DB::table('telephony_metric_attempts')->insertGetId([...$identity, ...$row, 'created_at' => now()]);
                }
                DB::table('telephony_metric_agents')->where('attempt_id', $id)->delete();
                foreach ($attempt['agents'] as $agent) {
                    DB::table('telephony_metric_agents')->updateOrInsert(['attempt_id' => $id, 'login' => $agent['login']], ['conversation_verified' => $agent['conversation_verified']]);
                }
                $written++;
            }
            if (isset($data['coverage'])) {
                foreach ($data['coverage']['metrics'] as $metric) {
                    $key = [...$scopeKey, 'campaign' => $data['campaign'], 'metric' => $metric,
                        'start_at' => CarbonImmutable::parse($data['coverage']['start'])->utc()->format('Y-m-d H:i:s.u'),
                        'end_at' => CarbonImmutable::parse($data['coverage']['end'])->utc()->format('Y-m-d H:i:s.u')];
                    $existing = DB::table('telephony_metric_coverage')->where($key)->lockForUpdate()->first();
                    if (! $existing || CarbonImmutable::parse($existing->observed_through, 'UTC')->lte($observed)) {
                        DB::table('telephony_metric_coverage')->updateOrInsert($key, [
                            'observed_through' => $observed->format('Y-m-d H:i:s.u'),
                            'source_reference' => $data['source_reference'], 'definition_version' => $data['definition_version'],
                            'created_at' => $existing?->created_at ?? now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }

            activity('telephony_metrics')->event('imported')->withProperties([
                'crm_campaign' => $data['crm_campaign'], 'server_id' => $data['server_id'],
                'campaign' => $data['campaign'], 'source_reference' => $data['source_reference'],
                'observed_at' => $observed->toIso8601String(), 'attempts_written' => $written,
                'batch_sha256' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)),
            ])->log('Imported verified call metric evidence');
            event(new \App\Events\VerifiedCallMetricsUpdated($data['crm_campaign']));

            return ['attempts_written' => $written, 'observed_at' => $observed->toIso8601String()];
        });
    }

    private function validateAttempt(array $attempt, CarbonImmutable $observed, int $index): void
    {
        $start = CarbonImmutable::parse($attempt['started_at']);
        $answer = $attempt['answered_at'] === null ? null : CarbonImmutable::parse($attempt['answered_at']);
        $connect = $attempt['agent_connected_at'] === null ? null : CarbonImmutable::parse($attempt['agent_connected_at']);
        $end = $attempt['ended_at'] === null ? null : CarbonImmutable::parse($attempt['ended_at']);
        $invalid = $start->gt($observed);
        foreach ([$attempt['attempt_key'], $attempt['lead_key'], ...array_column($attempt['agents'], 'login')] as $identity) {
            $invalid = $invalid || ($identity !== null && ($identity === '' || trim($identity) !== $identity));
        }
        $invalid = $invalid || count(array_unique(array_column($attempt['agents'], 'login'))) !== count($attempt['agents']);
        foreach ([$answer, $connect, $end] as $time) {
            $invalid = $invalid || ($time !== null && ($time->lt($start) || $time->gt($observed)));
        }
        $invalid = $invalid || ($answer && $end && $answer->gt($end)) || ($connect && $end && $connect->gt($end))
            || ($answer && $connect && $connect->lt($answer))
            || ((bool) $attempt['answered'] !== ($answer !== null))
            || ((bool) $attempt['agent_connected'] !== ($connect !== null))
            || ($attempt['live_answer'] && ! $attempt['answered'])
            || ($attempt['agent_connected'] && ! $attempt['answered'])
            || ($attempt['valid_attempt'] !== null && ! $attempt['valid_attempt'] && ($attempt['answered'] || $attempt['live_answer'] || $attempt['agent_connected'] || $attempt['conversation_verified']))
            || ($attempt['conversation_verified'] && (! $attempt['live_answer'] || ! $attempt['agent_connected']
                || ($end ?? $observed)->lte($connect) || ! collect($attempt['agents'])->contains(fn ($agent) => (bool) $agent['conversation_verified'])))
            || (collect($attempt['agents'])->contains(fn ($agent) => (bool) $agent['conversation_verified']) && ! $attempt['conversation_verified']);
        if ($invalid) {
            throw ValidationException::withMessages(['attempts.'.$index => 'Contradictory flags, missing conversation evidence, or invalid lifecycle timestamps.']);
        }
    }
}
