<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVicidialMapping;
use App\Models\VicidialServer;
use App\Services\Telephony\CallMetricEvidenceService;
use App\Services\Telephony\VerifiedCallMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifiedCallMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-06T00:00:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function batch(array $attempts = []): array
    {
        $campaign = Campaign::factory()->create(['code' => 'verified']);
        $server = VicidialServer::factory()->create(['campaign_code' => 'verified']);
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => $campaign->id, 'vicidial_server_id' => $server->id,
            'vicidial_campaign_code' => 'VICI', 'is_enabled' => true,
            'status' => CampaignVicidialMapping::STATUS_ACTIVE,
        ]);

        return [
            'crm_campaign' => 'verified', 'server_id' => $server->id, 'campaign' => 'VICI',
            'definition_version' => 'verified-v1', 'source_reference' => 'fixture:verified-events',
            'coverage' => ['start' => '2026-11-04T16:00:00Z', 'end' => '2026-11-05T16:00:00Z',
                'observed_through' => '2026-11-05T16:00:00Z', 'metrics' => ['answer_rate', 'contact_rate', 'abandoned_rate']],
            'attempts' => $attempts,
        ];
    }

    private function attempt(string $id, string $lead, array $changes = []): array
    {
        return array_replace([
            'attempt_key' => $id, 'lead_key' => $lead, 'started_at' => '2026-11-05T02:00:00Z',
            'answered_at' => null, 'agent_connected_at' => null, 'ended_at' => '2026-11-05T02:00:30Z',
            'valid_attempt' => true, 'answered' => false, 'live_answer' => false,
            'agent_connected' => false, 'conversation_verified' => false,
            'outcome' => 'unanswered', 'agents' => [],
        ], $changes);
    }

    private function report(array $filters = []): array
    {
        return app(VerifiedCallMetricsService::class)->forCampaign('verified',
            CarbonImmutable::parse('2026-11-05T00:00:00+08:00'),
            CarbonImmutable::parse('2026-11-06T00:00:00+08:00'), $filters);
    }

    public function test_standard_formulas_deduplicate_leads_and_attempts_and_ignore_disposition_guesses(): void
    {
        $batch = $this->batch([
            $this->attempt('a1', 'A'),
            $this->attempt('a2', 'A', ['answered' => true, 'live_answer' => true,
                'answered_at' => '2026-11-05T02:00:02Z', 'agent_connected' => true,
                'agent_connected_at' => '2026-11-05T02:00:04Z', 'conversation_verified' => true,
                'agents' => [['login' => 'agent-a', 'conversation_verified' => true]], 'outcome' => 'conversation']),
            $this->attempt('b', 'B', ['answered' => true, 'answered_at' => '2026-11-05T02:00:02Z', 'outcome' => 'machine']),
            $this->attempt('c', 'C', ['answered' => true, 'live_answer' => true,
                'answered_at' => '2026-11-05T02:00:02Z', 'outcome' => 'pre_agent_disconnect']),
            $this->attempt('d', 'D', ['outcome' => 'busy']),
            $this->attempt('e', 'E', ['outcome' => 'disconnected_number']),
        ]);
        $ingest = app(CallMetricEvidenceService::class);
        $ingest->import($batch);
        $ingest->import($batch); // duplicate deliveries/transferred log aliases retain original attempt key
        $result = $this->report();
        $this->assertSame(50.0, $result['rates']['answer_rate']);
        $this->assertSame(20.0, $result['rates']['contact_rate']);
        $this->assertSame(50.0, $result['rates']['abandoned_rate']);
        $this->assertSame(6, $result['metrics']['answer_rate']['denominator']);
        $this->assertSame(5, $result['metrics']['contact_rate']['denominator']);
        $this->assertSame(2, $result['metrics']['abandoned_rate']['denominator']);
        $this->assertDatabaseCount('telephony_metric_attempts', 6);
        $this->assertSame($result['rates'], $result['campaigns']['VICI']['rates']);
        $agent = $this->report(['agent' => 'agent-a']);
        $this->assertSame(100.0, $agent['rates']['contact_rate']);
        $this->assertNull($agent['rates']['abandoned_rate']);
        $this->assertSame('agent_attribution_unavailable', $agent['metrics']['abandoned_rate']['reason']);
    }

    public function test_missing_evidence_is_unavailable_but_confirmed_empty_coverage_is_zero(): void
    {
        $batch = $this->batch();
        $this->assertNull($this->report()['rates']['answer_rate']);
        app(CallMetricEvidenceService::class)->import($batch);
        $this->assertSame(['answer_rate' => 0.0, 'contact_rate' => 0.0, 'abandoned_rate' => 0.0], $this->report()['rates']);
        $batch['attempts'] = [$this->attempt('unknown', 'A', ['answered' => null, 'live_answer' => null,
            'agent_connected' => null, 'conversation_verified' => null])];
        app(CallMetricEvidenceService::class)->import($batch);
        $this->assertNull($this->report()['rates']['answer_rate']);
        $this->assertNull($this->report()['rates']['contact_rate']);
        $this->assertNull($this->report()['rates']['abandoned_rate']);
    }

    public function test_half_open_timezone_boundaries_and_unauthorized_campaign_filters(): void
    {
        $batch = $this->batch([
            $this->attempt('start', 'A', ['started_at' => '2026-11-04T16:00:00Z', 'ended_at' => '2026-11-04T16:00:20Z']),
            $this->attempt('end', 'B', ['started_at' => '2026-11-05T16:00:00Z', 'ended_at' => null]),
        ]);
        app(CallMetricEvidenceService::class)->import($batch);
        $this->assertSame(1, $this->report()['metrics']['answer_rate']['denominator']);
        $this->assertNull($this->report(['campaigns' => 'OTHER'])['rates']['answer_rate']);
    }

    public function test_invalid_lifecycle_is_rejected_without_partial_writes(): void
    {
        $batch = $this->batch([$this->attempt('good', 'A'), $this->attempt('bad', 'B', ['live_answer' => true])]);

        try {
            app(CallMetricEvidenceService::class)->import($batch);
            $this->fail('Contradictory evidence must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('attempts.1', $exception->errors());
        }
        $this->assertDatabaseCount('telephony_metric_attempts', 0);
        $this->assertDatabaseCount('telephony_metric_coverage', 0);
    }

    public function test_webhook_requires_its_own_secret_and_exports_share_metric_counts(): void
    {
        $batch = $this->batch([$this->attempt('one', 'A')]);
        $this->postJson(route('api.webhooks.call-metric-evidence'), $batch)->assertStatus(503);
        config(['asterisk.metrics_webhook_secret' => 'test-metric-secret']);
        $this->postJson(route('api.webhooks.call-metric-evidence'), $batch)->assertUnauthorized();
        $this->withHeader('X-Webhook-Secret', 'test-metric-secret')->postJson(route('api.webhooks.call-metric-evidence'), $batch)
            ->assertOk()->assertJsonPath('data.attempts_written', 1);
        $this->actingAs(\App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_ADMIN]))
            ->withSession(['campaign' => 'verified']);
        $filters = ['campaign' => 'verified', 'query_date' => '2026-11-05', 'end_date' => '2026-11-05', 'timezone' => 'Asia/Manila'];
        $this->getJson(route('api.reports.verified-metrics', $filters))->assertOk()
            ->assertJsonPath('data.rates.answer_rate', 0)
            ->assertJsonPath('data.metrics.answer_rate.denominator', 1);
        $csv = $this->get(route('api.reports.verified-metrics.export', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString('overall,answer_rate,0,1,0.00,verified', $csv);
        $this->getJson(route('api.reports.verified-metrics', $filters + ['agent' => 'nobody']))
            ->assertJsonPath('data.metrics.abandoned_rate.reason', 'agent_attribution_unavailable');
    }

    public function test_live_rates_use_disclosed_source_watermark_and_expire_when_stale(): void
    {
        $batch = $this->batch([$this->attempt('one', 'A', ['started_at' => '2026-11-05T02:50:00Z', 'ended_at' => '2026-11-05T02:50:30Z'])]);
        $batch['coverage']['end'] = $batch['coverage']['observed_through'] = '2026-11-05T03:00:00Z';
        app(CallMetricEvidenceService::class)->import($batch);
        $service = app(VerifiedCallMetricsService::class);
        $result = $service->forCampaign('verified', CarbonImmutable::parse('2026-11-05T02:45:05Z'), CarbonImmutable::parse('2026-11-05T03:00:05Z'), ['historical' => false]);
        $this->assertSame(0.0, $result['rates']['answer_rate']);
        $this->assertSame(5, $result['freshness']['lag_seconds']);
        $this->assertSame('2026-11-05T03:00:00+00:00', $result['scope']['end_exclusive']);
        $result = $service->forCampaign('verified', CarbonImmutable::parse('2026-11-05T02:45:00Z'), CarbonImmutable::parse('2026-11-05T03:02:00Z'), ['historical' => false]);
        $this->assertNull($result['rates']['answer_rate']);
        $this->assertSame('stale_source_coverage', $result['metrics']['answer_rate']['reason']);
    }

    public function test_unknown_lead_identity_does_not_invalidate_attempt_level_metrics(): void
    {
        $batch = $this->batch([$this->attempt('one', 'A', ['lead_key' => null]),
            $this->attempt('invalid-input', 'X', ['valid_attempt' => false])]);
        app(CallMetricEvidenceService::class)->import($batch);
        $report = $this->report();
        $this->assertSame(1, $report['metrics']['answer_rate']['denominator']);
        $this->assertSame(0.0, $report['rates']['answer_rate']);
        $this->assertSame(0.0, $report['rates']['abandoned_rate']);
        $this->assertNull($report['rates']['contact_rate']);
    }

    public function test_coverage_gaps_do_not_become_confirmed_zero(): void
    {
        $batch = $this->batch();
        $batch['coverage']['end'] = '2026-11-04T20:00:00Z';
        app(CallMetricEvidenceService::class)->import($batch);
        $batch['coverage']['start'] = '2026-11-04T21:00:00Z';
        $batch['coverage']['end'] = '2026-11-05T16:00:00Z';
        app(CallMetricEvidenceService::class)->import($batch);
        $this->assertNull($this->report()['rates']['answer_rate']);
        $batch['coverage']['start'] = '2026-11-04T20:00:00Z';
        $batch['coverage']['end'] = '2026-11-04T21:00:00Z';
        app(CallMetricEvidenceService::class)->import($batch);
        $this->assertSame(0.0, $this->report()['rates']['answer_rate']);
    }

    public function test_late_deliveries_cannot_regress_newer_evidence_or_reassign_identity(): void
    {
        $batch = $this->batch([$this->attempt('one', 'A')]);
        $service = app(CallMetricEvidenceService::class);
        $service->import($batch);
        $older = $batch;
        unset($older['coverage']);
        $older['observed_at'] = '2026-11-05T15:00:00Z';
        $older['attempts'][0]['answered'] = null;
        $this->assertSame(0, $service->import($older)['attempts_written']);
        $this->assertSame(0.0, $this->report()['rates']['answer_rate']);
        $batch['attempts'][0]['lead_key'] = 'different-lead';
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->import($batch);
    }

    public function test_transfer_participation_does_not_create_another_attempt_or_lead(): void
    {
        $batch = $this->batch([$this->attempt('original', 'A', [
            'answered' => true, 'live_answer' => true, 'answered_at' => '2026-11-05T02:00:02Z',
            'agent_connected' => true, 'agent_connected_at' => '2026-11-05T02:00:04Z',
            'conversation_verified' => true, 'outcome' => 'transfer',
            'agents' => [['login' => 'first', 'conversation_verified' => true], ['login' => 'second', 'conversation_verified' => true]],
        ])]);
        app(CallMetricEvidenceService::class)->import($batch);
        $this->assertSame(1, $this->report()['metrics']['answer_rate']['denominator']);
        $this->assertSame(1, $this->report()['metrics']['contact_rate']['numerator']);
        $this->assertSame(100.0, $this->report(['agent' => 'second'])['rates']['contact_rate']);
        $this->assertSame(0.0, $this->report()['rates']['abandoned_rate']);
    }

    public function test_historical_comparison_uses_evidence_instead_of_raw_dialer_answers(): void
    {
        $batch = $this->batch([$this->attempt('current', 'A')]);
        app(CallMetricEvidenceService::class)->import($batch);
        $batch['coverage']['start'] = '2026-11-03T16:00:00Z';
        $batch['coverage']['end'] = $batch['coverage']['observed_through'] = '2026-11-04T16:00:00Z';
        $batch['attempts'] = [$this->attempt('previous', 'B', ['started_at' => '2026-11-04T02:00:00Z',
            'answered' => true, 'answered_at' => '2026-11-04T02:00:02Z', 'ended_at' => '2026-11-04T02:00:30Z', 'outcome' => 'machine'])];
        app(CallMetricEvidenceService::class)->import($batch);
        $snapshot = [
            'call_status' => \App\Support\OperationResult::success(['rows' => [['VICI', '99', '99', '', '']]]),
            'call_dispo' => \App\Support\OperationResult::success(['rows' => [['campaign', 'ANA', 'SALE'], ['VICI', '50', '49']]]),
            'agent_stats' => \App\Support\OperationResult::success(['rows' => [['user', 'campaign', 'calls'], ['agent', 'VICI', '99']]]),
        ];
        $this->mock(\App\Services\Telephony\ReportingService::class)->shouldReceive('historicalSnapshot')->twice()->andReturn($snapshot);
        $result = app(\App\Services\Telephony\HistoricalTelephonyReportService::class)->dashboard(null, 'verified', [
            'query_date' => '2026-11-05', 'end_date' => '2026-11-05', 'timezone' => 'Asia/Manila',
            'comparison' => 'previous_day', 'disposition_scope' => 'all',
        ]);
        $this->assertSame(0.0, $result['summary']['answer_rate']);
        $this->assertSame(100.0, $result['comparison']['metrics']['answer_rate']['previous']);
        $this->assertSame(-100.0, $result['comparison']['metrics']['answer_rate']['change']);
        $this->assertSame(0.0, $result['campaigns'][0]['answer_rate']);
        $this->assertSame(99, $result['summary']['answered_calls']); // explicitly raw diagnostic count
    }

    public function test_csv_preserves_numeric_campaign_ids_and_overall_campaign_name(): void
    {
        $batch = $this->batch([$this->attempt('one', 'A')]);
        CampaignVicidialMapping::query()->where('vicidial_campaign_code', 'VICI')->update(['vicidial_campaign_code' => '123']);
        $batch['campaign'] = '123';
        app(CallMetricEvidenceService::class)->import($batch);
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => Campaign::where('code', 'verified')->value('id'),
            'vicidial_server_id' => $batch['server_id'], 'vicidial_campaign_code' => 'overall',
            'is_enabled' => true, 'status' => CampaignVicidialMapping::STATUS_ACTIVE,
        ]);
        app(\App\Services\Telephony\CrmCampaignVicidialScopeResolver::class)->clear('verified');
        $this->actingAs(\App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_ADMIN]))->withSession(['campaign' => 'verified']);
        $csv = $this->get(route('api.reports.verified-metrics.export', [
            'query_date' => '2026-11-05', 'end_date' => '2026-11-05', 'timezone' => 'Asia/Manila',
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString("\n123,answer_rate,0,1,0.00,verified", $csv);
        $rows = array_map(fn ($line) => str_getcsv($line, escape: ''), array_filter(explode("\n", trim($csv))));
        $overallRows = array_values(array_filter(array_slice($rows, 1), fn ($row) => $row[0] === 'overall' && $row[1] === 'answer_rate'));
        $this->assertCount(2, $overallRows);
        $this->assertSame(['overall', 'campaign'], array_map(fn ($row) => end($row), $overallRows));
    }

    public function test_padded_source_identities_are_rejected_before_database_collation_can_merge_them(): void
    {
        $batch = $this->batch([$this->attempt('original ', 'A')]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CallMetricEvidenceService::class)->import($batch);
    }

    public function test_answer_rate_is_rounded_once_and_never_subtracts_abandonment(): void
    {
        $attempts = [];
        for ($i = 0; $i < 7; $i++) {
            $attempts[] = $this->attempt('attempt-'.$i, 'lead-'.$i, [
                'answered' => $i < 4, 'answered_at' => $i < 4 ? '2026-11-05T02:00:02Z' : null,
                'live_answer' => $i === 0,
            ]);
        }
        app(CallMetricEvidenceService::class)->import($this->batch($attempts));
        $result = $this->report();
        $this->assertSame(57.14, $result['rates']['answer_rate']);
        $this->assertSame(100.0, $result['rates']['abandoned_rate']);
        $this->assertSame(0.0, $result['rates']['contact_rate']);
    }

    public function test_overall_rates_use_counters_instead_of_averaging_campaign_percentages(): void
    {
        $batch = $this->batch();
        CampaignVicidialMapping::factory()->create([
            'campaign_id' => Campaign::where('code', 'verified')->value('id'),
            'vicidial_server_id' => $batch['server_id'], 'vicidial_campaign_code' => 'VICI_B',
            'is_enabled' => true, 'status' => CampaignVicidialMapping::STATUS_ACTIVE,
        ]);
        foreach (['VICI' => 10, 'VICI_B' => 90] as $code => $total) {
            $batch['campaign'] = $code;
            $batch['attempts'] = [];
            for ($i = 0; $i < $total; $i++) {
                $batch['attempts'][] = $this->attempt($code.'-'.$i, 'shared-lead', [
                    'answered' => $i < 9, 'answered_at' => $i < 9 ? '2026-11-05T02:00:02Z' : null,
                ]);
            }
            app(CallMetricEvidenceService::class)->import($batch);
        }
        $result = $this->report();
        $this->assertSame(18.0, $result['rates']['answer_rate']);
        $this->assertSame(90.0, $result['campaigns']['VICI']['rates']['answer_rate']);
        $this->assertSame(10.0, $result['campaigns']['VICI_B']['rates']['answer_rate']);
        $this->assertSame(1, $result['metrics']['contact_rate']['denominator']);
    }
}
