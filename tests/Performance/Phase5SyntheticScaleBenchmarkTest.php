<?php

namespace Tests\Performance;

use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\VicidialServer;
use App\Services\DashboardCustomSalesAggregationService;
use App\Services\DashboardSalesRuleService;
use App\Services\EmailCampaignRecipientTransitionService;
use App\Services\EmailCampaignSnapshotAuthenticator;
use App\Services\Telephony\HistoricalCallRecord;
use App\Services\Telephony\RealtimeTelephonyReportService;
use App\Services\Telephony\SupervisorOperationalService;
use App\Services\Telephony\VicidialHistoricalCallProvider;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Run explicitly with `php vendor/bin/phpunit tests/Performance/Phase5SyntheticScaleBenchmarkTest.php`.
 * RefreshDatabase uses PHPUnit's isolated in-memory SQLite database; no live services are contacted.
 */
final class Phase5SyntheticScaleBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private const ROWS = 10000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'), 'Synthetic benchmarks must use isolated SQLite.');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'), 'Synthetic benchmarks must use an in-memory database.');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_bounded_call_sync_email_and_custom_sales_workloads_keep_outputs_equivalent(): void
    {
        $metrics = [
            'row_count' => self::ROWS,
            'database' => 'SQLite :memory: synthetic fixtures',
            'workloads' => [],
        ];

        $metrics['workloads']['rolling_calls'] = $this->benchmarkRollingCalls();
        $metrics['workloads']['vicidial_sync_fetch'] = $this->benchmarkVicidialSync();
        $metrics['workloads']['email_manifest_and_totals'] = $this->benchmarkEmailSendWork();
        $metrics['workloads']['custom_sales'] = $this->benchmarkCustomSales();

        fwrite(STDERR, 'PHASE5_BENCHMARK '.json_encode($metrics, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL);
        $this->addToAssertionCount(1);
    }

    private function benchmarkRollingCalls(): array
    {
        $user = User::factory()->create();
        $now = Carbon::parse('2026-10-26 12:00:00');
        $from = $now->copy()->subMinutes(15);
        foreach (array_chunk(range(1, self::ROWS), 500) as $chunk) {
            $created = now();
            DB::table('call_sessions')->insert(array_map(static function (int $index) use ($user, $now, $created): array {
                $dialedAt = $now->copy()->subSeconds(($index % 840) + 1);
                $answeredAt = $index % 3 === 0 ? null : $dialedAt->copy()->addSeconds(12);

                return [
                    'user_id' => $user->id,
                    'campaign_code' => 'BENCH_CALLS',
                    'lead_id' => $index,
                    'phone_number' => '639120000000',
                    'status' => $index % 7 === 0 ? CallSession::STATUS_ABANDONED : CallSession::STATUS_COMPLETED,
                    'linkedid' => 'bench-call-'.$index,
                    'channel' => null,
                    'vicidial_lead_id' => null,
                    'dialed_at' => $dialedAt,
                    'ringing_at' => null,
                    'answered_at' => $answeredAt,
                    'ended_at' => $dialedAt->copy()->addSeconds(45),
                    'disposition_code' => $index % 4 === 0 ? 'SALE' : 'NOANSWER',
                    'disposition_label' => null,
                    'disposition_at' => $dialedAt->copy()->addSeconds(45),
                    'disposition_remarks' => null,
                    'call_duration_seconds' => $answeredAt ? 33 : null,
                    'end_reason' => null,
                    'metadata' => '[]',
                    'created_at' => $created,
                    'updated_at' => $created,
                    'vicidial_call_id' => null,
                ];
            }, $chunk));
        }

        $legacy = $this->measure(fn (): array => $this->legacyRolling($from, $now));
        $optimized = $this->measure(function () use ($from, $now): array {
            config(['vicidial.supervisor.rolling_chunk_size' => 500]);
            $service = new class($this->mock(SupervisorOperationalService::class)) extends RealtimeTelephonyReportService
            {
                public function roll(Carbon $from, Carbon $until): array
                {
                    return $this->rollingMetrics(['BENCH_CALLS'], $from, $until, 15);
                }
            };

            return $service->roll($from, $now);
        });
        $this->assertSame($legacy['result'], $optimized['result']);

        return ['baseline' => $legacy['measurements'], 'optimized' => $optimized['measurements'], 'outputs_equal' => true];
    }

    private function legacyRolling(Carbon $from, Carbon $until): array
    {
        $rows = CallSession::query()->where('campaign_code', 'BENCH_CALLS')
            ->where(function ($query) use ($from, $until): void {
                $query->whereBetween('dialed_at', [$from, $until])
                    ->orWhereBetween('answered_at', [$from, $until])
                    ->orWhereBetween('ended_at', [$from, $until])
                    ->orWhereBetween('disposition_at', [$from, $until]);
            })
            ->select(['id', 'status', 'dialed_at', 'answered_at', 'ended_at', 'call_duration_seconds', 'disposition_code', 'disposition_at'])
            ->orderBy('id')->get();
        $metrics = ['attempted' => 0, 'answered' => 0, 'abandoned' => 0, 'wait_total' => 0, 'wait_count' => 0, 'talk_total' => 0, 'talk_count' => 0, 'dispositions' => []];

        foreach ($rows as $call) {
            $inWindow = false;
            foreach ([$call->dialed_at, $call->answered_at, $call->ended_at, $call->disposition_at] as $eventAt) {
                if ($eventAt !== null && $eventAt->betweenIncluded($from, $until)) {
                    $inWindow = true;

                    break;
                }
            }
            if (! $inWindow) {
                continue;
            }
            $metrics['attempted']++;
            if ($call->answered_at !== null) {
                $metrics['answered']++;
                if ($call->dialed_at !== null) {
                    $metrics['wait_total'] += max(0, (int) $call->dialed_at->diffInSeconds($call->answered_at, false));
                    $metrics['wait_count']++;
                }
                if ($call->call_duration_seconds !== null) {
                    $metrics['talk_total'] += max(0, (int) $call->call_duration_seconds);
                    $metrics['talk_count']++;
                }
            }
            if ($call->status === CallSession::STATUS_ABANDONED) {
                $metrics['abandoned']++;
            }
            $disposition = trim((string) $call->disposition_code);
            if ($disposition !== '') {
                $metrics['dispositions'][$disposition] = ($metrics['dispositions'][$disposition] ?? 0) + 1;
            }
        }

        $attempted = $metrics['attempted'];

        return [
            'window_minutes' => 15,
            'label' => 'Last 15 minutes',
            'source' => 'crm',
            'available' => true,
            'calls_initiated' => $attempted,
            'answered' => $metrics['answered'],
            'abandoned' => $metrics['abandoned'],
            'answer_rate' => $attempted > 0 ? round(($metrics['answered'] / $attempted) * 100, 2) : 0,
            'average_wait_seconds' => $metrics['wait_count'] > 0 ? round($metrics['wait_total'] / $metrics['wait_count'], 1) : null,
            'average_talk_seconds' => $metrics['talk_count'] > 0 ? round($metrics['talk_total'] / $metrics['talk_count'], 1) : null,
            'dispositions' => collect($metrics['dispositions'])->sortDesc()->all(),
        ];
    }

    private function benchmarkVicidialSync(): array
    {
        $this->createSourceTable('vicidial_log');
        $this->createSourceTable('vicidial_closer_log');
        foreach (['vicidial_log', 'vicidial_closer_log'] as $table) {
            foreach (array_chunk(range(1, self::ROWS / 2), 500) as $chunk) {
                DB::table($table)->insert(array_map(fn (int $index): array => $this->sourceRow($index, $table), $chunk));
            }
        }
        $campaign = Campaign::factory()->create(['code' => 'bench-sync']);
        $server = VicidialServer::factory()->create(['campaign_code' => 'bench-sync']);
        $provider = new SQLiteBenchmarkHistoricalCallProvider;
        $from = Carbon::parse('2026-10-26 00:00:00');
        $to = Carbon::parse('2026-10-26 23:59:59');

        $legacy = $this->measure(function () use ($provider, $server, $campaign, $from, $to): array {
            $result = $provider->fetchRange($server, $campaign, ['BENCH'], $from, $to);
            $this->assertTrue($result->success);

            return $this->identitySummary($result->records);
        });
        $optimized = $this->measure(function () use ($provider, $server, $campaign, $from, $to): array {
            $summary = ['count' => 0, 'sum_lead_id' => 0, 'xor_id_hash' => str_repeat("\0", 32), 'max_page' => 0];
            $result = $provider->streamRange($server, $campaign, ['BENCH'], $from, $to, function (array $records) use (&$summary): void {
                $summary['max_page'] = max($summary['max_page'], count($records));
                foreach ($records as $record) {
                    $summary['count']++;
                    $summary['sum_lead_id'] += $record->leadId ?? 0;
                    $summary['xor_id_hash'] = $summary['xor_id_hash'] ^ hash('sha256', $record->id, true);
                }
            }, 500);
            $this->assertTrue($result->success);
            $this->assertSame(self::ROWS, $result->total);

            return $summary;
        });
        $this->assertSame($legacy['result']['count'], $optimized['result']['count']);
        $this->assertSame($legacy['result']['sum_lead_id'], $optimized['result']['sum_lead_id']);
        $this->assertSame($legacy['result']['xor_id_hash'], $optimized['result']['xor_id_hash']);

        return ['baseline' => $legacy['measurements'], 'optimized' => $optimized['measurements'], 'outputs_equal' => true, 'page_size_max' => $optimized['result']['max_page']];
    }

    private function createSourceTable(string $tableName): void
    {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->string('uniqueid')->nullable();
            $table->string('campaign_id')->nullable();
            $table->string('list_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('user')->nullable();
            $table->string('phone_number')->nullable();
            $table->dateTime('call_date')->nullable();
            $table->unsignedBigInteger('start_epoch')->nullable();
            $table->unsignedBigInteger('end_epoch')->nullable();
            $table->integer('length_in_sec')->nullable();
            $table->string('status')->nullable();
            $table->integer('queue_seconds')->nullable();
            $table->string('term_reason')->nullable();
        });
    }

    private function sourceRow(int $index, string $table): array
    {
        return [
            'uniqueid' => 'bench-'.($table === 'vicidial_log' ? 'out-' : 'in-').$index,
            'campaign_id' => 'BENCH',
            'list_id' => 'LIST',
            'lead_id' => $index,
            'user' => 'agent_'.($index % 25),
            'phone_number' => '63912'.str_pad((string) $index, 7, '0', STR_PAD_LEFT),
            'call_date' => '2026-10-26 12:00:00',
            'start_epoch' => null,
            'end_epoch' => null,
            'length_in_sec' => 60,
            'status' => 'SALE',
            'queue_seconds' => 5,
            'term_reason' => 'HANGUP',
        ];
    }

    /** @param list<HistoricalCallRecord> $records */
    private function identitySummary(array $records): array
    {
        $summary = ['count' => count($records), 'sum_lead_id' => 0, 'xor_id_hash' => str_repeat("\0", 32)];
        foreach ($records as $record) {
            $summary['sum_lead_id'] += $record->leadId ?? 0;
            $summary['xor_id_hash'] = $summary['xor_id_hash'] ^ hash('sha256', $record->id, true);
        }

        return $summary;
    }

    private function benchmarkEmailSendWork(): array
    {
        config(['email_campaigns.max_recipients' => self::ROWS]);
        $template = EmailTemplate::query()->create(['name' => 'Synthetic benchmark', 'subject' => 'Synthetic', 'html_body' => 'Synthetic only.']);
        $campaign = EmailCampaign::query()->create([
            'email_template_id' => $template->id,
            'name' => 'Synthetic benchmark campaign',
            'status' => 'sending',
            'recipient_count' => self::ROWS,
            'open_count' => self::ROWS,
            'attention_count' => 0,
        ]);
        $recipientPayloads = [];
        foreach (array_chunk(range(1, self::ROWS), 500) as $chunk) {
            $now = now();
            DB::table('email_campaign_recipients')->insert(array_map(static function (int $index) use ($campaign, $now, &$recipientPayloads): array {
                $email = 'synthetic-'.$index.'@example.test';
                $recipientPayloads[] = ['id' => $index, 'email' => $email, 'name' => 'Synthetic '.$index, 'documents' => []];

                return [
                    'email_campaign_id' => $campaign->id,
                    'email' => $email,
                    'name' => 'Synthetic '.$index,
                    'status' => $index === 1 ? 'sending' : 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $chunk));
        }
        $target = EmailCampaignRecipient::query()->where('email_campaign_id', $campaign->id)->where('status', 'sending')->firstOrFail();
        $targetSnapshot = $recipientPayloads[0];
        $manifest = ['purpose' => 'transactional', 'recipients' => $recipientPayloads, 'documents' => []];
        $header = ['purpose' => 'transactional', 'template' => $template->only(['name', 'subject', 'html_body']), 'shared_documents' => []];
        $manifestHash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));
        $authenticator = app(EmailCampaignSnapshotAuthenticator::class);
        $headerDigest = $authenticator->campaignDigest($campaign->id, $manifestHash, $header);
        $recipientDigest = $authenticator->recipientDigest($campaign->id, $target->id, $manifestHash, $targetSnapshot);

        $legacy = $this->measure(function () use ($manifest, $campaign): array {
            $hash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));
            $selected = collect($manifest['recipients'])->firstWhere('id', 1);
            $counts = DB::table('email_campaign_recipients')->where('email_campaign_id', $campaign->id)
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

            return ['manifest_hash' => $hash, 'recipient' => $selected, 'counts' => $counts];
        });
        $optimized = $this->measure(function () use ($campaign, $target, $targetSnapshot, $manifestHash, $header, $headerDigest, $recipientDigest, $authenticator): array {
            $headerValid = $authenticator->verify($headerDigest, $authenticator->campaignDigest($campaign->id, $manifestHash, $header));
            $recipientValid = $authenticator->verify($recipientDigest, $authenticator->recipientDigest($campaign->id, $target->id, $manifestHash, $targetSnapshot));
            $changed = app(EmailCampaignRecipientTransitionService::class)->transition($campaign->id, $target->id, 'sending', 'accepted', ['sent_at' => now()]);
            $current = $campaign->fresh();

            return [
                'authenticated' => $headerValid && $recipientValid,
                'changed' => $changed,
                'recipient_email' => $target->fresh()->email,
                'sent_count' => $current->sent_count,
                'failed_count' => $current->failed_count,
                'skipped_count' => $current->skipped_count,
                'open_count' => $current->open_count,
                'attention_count' => $current->attention_count,
            ];
        });
        $this->assertSame(hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)), $legacy['result']['manifest_hash']);
        $this->assertSame($targetSnapshot, $legacy['result']['recipient']);
        $this->assertSame($targetSnapshot['email'], $optimized['result']['recipient_email']);
        $this->assertSame(self::ROWS - 1, (int) ($legacy['result']['counts']['pending'] ?? 0));
        $this->assertSame(1, (int) ($legacy['result']['counts']['sending'] ?? 0));
        $this->assertTrue($optimized['result']['authenticated']);
        $this->assertTrue($optimized['result']['changed']);
        $this->assertSame(1, $optimized['result']['sent_count']);
        $this->assertSame(0, $optimized['result']['failed_count']);
        $this->assertSame(0, $optimized['result']['skipped_count']);
        $this->assertSame(self::ROWS - 1, $optimized['result']['open_count']);
        $this->assertSame(0, $optimized['result']['attention_count']);

        return ['baseline' => $legacy['measurements'], 'optimized' => $optimized['measurements'], 'outputs_equal' => true, 'recipient_count' => self::ROWS];
    }

    private function benchmarkCustomSales(): array
    {
        Carbon::setTestNow('2026-10-26 12:00:00');
        Schema::create('benchmark_custom_sales', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('agent')->nullable();
            $table->string('sale_tag')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();
        });
        foreach (array_chunk(range(1, self::ROWS), 500) as $chunk) {
            DB::table('benchmark_custom_sales')->insert(array_map(static function (int $index): array {
                $createdAt = Carbon::parse('2026-10-26 00:00:00')->addSeconds($index);

                return [
                    'date' => '2026-10-26',
                    'agent' => 'agent_'.($index % 50),
                    'sale_tag' => $index % 4 === 0 ? 'SALE' : 'no',
                    'amount' => number_format(($index % 975) / 10, 2, '.', ''),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }, $chunk));
        }
        $forms = [[
            'form_code' => 'benchmark',
            'form_name' => 'Synthetic benchmark',
            'table' => 'benchmark_custom_sales',
            'amount_field' => 'amount',
            'trigger' => DashboardSalesRuleService::TRIGGER_TAG,
            'conditions' => [['field_name' => 'sale_tag', 'accepted_values' => ['sale']]],
        ]];
        $from = Carbon::parse('2026-10-26 00:00:00');
        $until = Carbon::parse('2026-10-27 00:00:00');
        $service = app(DashboardCustomSalesAggregationService::class);
        $legacy = $this->measure(function () use ($forms, $from, $until, $service): array {
            $form = $forms[0];
            if (! Schema::hasColumn($form['table'], 'created_at')) {
                return ['count' => 0, 'amount' => 0.0, 'counts' => [], 'amounts' => [], 'sales_by_form' => []];
            }
            $hasAgent = Schema::hasColumn($form['table'], 'agent');
            $sales = 0;
            $amount = 0.0;
            $counts = [];
            $amounts = [];
            $breakdown = [$form['table'] => ['form_code' => $form['form_code'], 'form_name' => $form['form_name'], 'sales' => 0, 'sales_amount' => 0.0]];
            $select = ['id', 'sale_tag', 'amount'];
            if ($hasAgent) {
                $select[] = 'agent';
            }
            DB::table($form['table'])->where('created_at', '>=', $from)->where('created_at', '<', $until)
                ->select($select)->orderBy('id')->chunk(1000, function ($rows) use (&$sales, &$amount, &$counts, &$amounts, &$breakdown, $form, $service, $hasAgent): void {
                    foreach ($rows as $row) {
                        if (! $service->matchesConditions($row, $form['conditions'], $form['amount_field'], $form['trigger'])) {
                            continue;
                        }
                        $saleAmount = $service->amount($row, $form['amount_field']);
                        $sales++;
                        $amount += $saleAmount;
                        $breakdown[$form['table']]['sales']++;
                        $breakdown[$form['table']]['sales_amount'] += $saleAmount;
                        $agent = $hasAgent ? trim((string) ($row->agent ?? '')) : '';
                        if ($agent !== '') {
                            $counts[$agent] = ($counts[$agent] ?? 0) + 1;
                            $amounts[$agent] = ($amounts[$agent] ?? 0.0) + $saleAmount;
                        }
                    }
                });
            $breakdown[$form['table']]['sales_amount'] = round($breakdown[$form['table']]['sales_amount'], 2);

            return ['count' => $sales, 'amount' => $amount, 'counts' => $counts, 'amounts' => $amounts, 'sales_by_form' => array_values($breakdown)];
        });
        $optimized = $this->measure(fn (): array => $service->salesByAgentInRange($forms, $from, $until));
        $this->assertSame($legacy['result'], $optimized['result']);

        return ['baseline' => $legacy['measurements'], 'optimized' => $optimized['measurements'], 'outputs_equal' => true];
    }

    private function measure(callable $work): array
    {
        gc_collect_cycles();
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $startMemory = memory_get_usage(false);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $startedAt = hrtime(true);

        try {
            $result = $work();
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
            $queries = count(DB::getQueryLog());
            $peakBytes = max(0, memory_get_peak_usage(false) - $startMemory);
        } finally {
            DB::disableQueryLog();
        }

        return [
            'result' => $result,
            'measurements' => [
                'wall_time_ms' => round($durationMs, 2),
                'peak_extra_memory_bytes' => $peakBytes,
                'query_count' => $queries,
            ],
        ];
    }
}

final class SQLiteBenchmarkHistoricalCallProvider extends VicidialHistoricalCallProvider
{
    protected function makeConnection(VicidialServer $server): Connection
    {
        return DB::connection();
    }

    protected function disconnect(Connection $connection): void
    {
        // Keep the isolated synthetic SQLite connection available for cleanup.
    }
}
