<?php

namespace Tests\Unit\Services;

use App\Models\CampaignDispositionRecord;
use App\Models\Form;
use App\Models\FormField;
use App\Services\DashboardStatsService;
use Carbon\Carbon;
use Database\Seeders\CampaignSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        config(['vicidial.report_system_disposition_codes' => []]);
        parent::tearDown();
    }

    public function test_get_kpis_returns_zero_without_disposition_rows(): void
    {
        $service = app(DashboardStatsService::class);
        Cache::flush();

        $kpis = $service->getKpisForCampaign('mbsales');

        $this->assertSame(0, $kpis['calls']);
        $this->assertSame(0, $kpis['sales']);
        $this->assertNull($kpis['top_agent']);
        $this->assertSame(0, $kpis['top_agent_calls']);
    }

    public function test_get_kpis_counts_calls_and_sales_inside_window(): void
    {
        Carbon::setTestNow('2026-05-07 15:00:00');
        Cache::flush();
        config([
            'dashboard.kpi_window_hours' => 9,
            'vicidial.report_system_disposition_codes' => ['SYSTEM'],
        ]);

        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Alex',
            'disposition_code' => 'NC',
            'called_at' => Carbon::parse('2026-05-07 14:00:00'),
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Alex',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-07 13:00:00'),
            'lead_data_json' => ['ezycash_amount' => 125.50],
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Alex',
            'disposition_code' => 'OTHER',
            'called_at' => Carbon::parse('2026-05-07 05:30:00'),
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Alex',
            'disposition_code' => 'SYSTEM',
            'called_at' => Carbon::parse('2026-05-07 12:30:00'),
        ]);

        /** @var DashboardStatsService $service */
        $service = app(DashboardStatsService::class);
        $kpis = $service->getKpisForCampaign('mbsales');

        $this->assertSame(2, $kpis['calls']);
        $this->assertSame(1, $kpis['sales']);
        $this->assertSame(125.5, $kpis['sales_amount']);
        $this->assertSame('Alex', $kpis['top_agent']);
        $this->assertSame(3, $kpis['top_agent_calls']);
    }

    public function test_get_kpis_excludes_rows_outside_window(): void
    {
        Carbon::setTestNow('2026-05-07 15:00:00');
        Cache::flush();
        config(['dashboard.kpi_window_hours' => 9]);

        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Older',
            'disposition_code' => 'NC',
            'called_at' => Carbon::parse('2026-05-06 08:00:00'),
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Recent',
            'disposition_code' => 'NC',
            'called_at' => Carbon::parse('2026-05-07 14:00:00'),
        ]);

        $kpis = app(DashboardStatsService::class)->getKpisForCampaign('mbsales');

        $this->assertSame(1, $kpis['calls']);
        $this->assertSame('Recent', $kpis['top_agent']);
    }

    public function test_get_kpis_counts_submissions_with_marked_amounts_once(): void
    {
        Carbon::setTestNow('2026-05-07 15:00:00');
        Cache::flush();
        config(['dashboard.kpi_window_hours' => 9]);
        $this->seed(CampaignSeeder::class);

        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'ezycash_amount',
            'field_label' => 'EzyCash Amount',
            'field_type' => 'number',
            'is_required' => false,
            'is_sale_amount' => true,
            'field_order' => 1,
        ]);

        $this->insertEzycashSaleRow('2026-05-07', 'Alice', 100.00, 10.00, '2026-05-07 14:00:00', 1);
        $this->insertEzycashSaleRow('2026-05-07', 'Alice', 25.50, 10.00, '2026-05-07 13:00:00', 2);
        $this->insertEzycashSaleRow('2026-05-06', 'Alice', 900.00, 10.00, '2026-05-06 05:00:00', 3);

        $kpis = app(DashboardStatsService::class)->getKpisForCampaign('mbsales');

        $this->assertSame(2, $kpis['sales']);
        $this->assertSame(125.5, $kpis['sales_amount']);
    }

    public function test_get_kpis_top_agent_uses_marked_sales_and_amount(): void
    {
        Carbon::setTestNow('2026-05-07 15:00:00');
        Cache::flush();
        config(['dashboard.kpi_window_hours' => 9]);
        $this->seed(CampaignSeeder::class);

        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'ezycash_amount',
            'field_label' => 'EzyCash Amount',
            'field_type' => 'number',
            'is_required' => false,
            'is_sale_amount' => true,
            'field_order' => 1,
        ]);

        $this->insertEzycashSaleRow('2026-05-07', 'Alice', 100.00, 10.00, '2026-05-07 14:00:00', 1);
        $this->insertEzycashSaleRow('2026-05-07', 'Alice', 25.50, 10.00, '2026-05-07 13:00:00', 2);
        $this->insertEzycashSaleRow('2026-05-07', 'Bob', 500.00, 10.00, '2026-05-07 12:00:00', 3);

        $kpis = app(DashboardStatsService::class)->getKpisForCampaign('mbsales');

        $this->assertSame('Alice', $kpis['top_agent']);
        $this->assertSame(2, $kpis['top_agent_sales']);
        $this->assertSame(125.5, $kpis['top_agent_sales_amount']);
    }

    public function test_get_kpis_uses_rolling_sales_window_separately_from_calls(): void
    {
        Carbon::setTestNow('2026-05-07 15:00:00');
        Cache::flush();
        config([
            'dashboard.kpi_window_hours' => 9,
            'dashboard.sales_kpi_window_hours' => 24,
        ]);
        $this->seed(CampaignSeeder::class);

        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'ezycash_amount',
            'field_label' => 'EzyCash Amount',
            'field_type' => 'number',
            'is_required' => false,
            'is_sale_amount' => true,
            'field_order' => 1,
        ]);

        $this->insertEzycashSaleRow('2026-05-07', 'Alice', 200.00, 10.00, '2026-05-07 05:00:00', 4);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Alice',
            'disposition_code' => 'NC',
            'called_at' => Carbon::parse('2026-05-07 05:00:00'),
        ]);

        $kpis = app(DashboardStatsService::class)->getKpisForCampaign('mbsales');

        $this->assertSame(0, $kpis['calls']);
        $this->assertSame(1, $kpis['sales']);
        $this->assertSame(200.0, $kpis['sales_amount']);
        $this->assertSame('Alice', $kpis['top_agent']);
        $this->assertSame(1, $kpis['top_agent_sales']);
        $this->assertSame(200.0, $kpis['top_agent_sales_amount']);
    }

    public function test_get_kpis_uses_the_sales_window_for_fallback_top_agent(): void
    {
        Carbon::setTestNow('2026-05-07 15:00:00');
        Cache::flush();
        config([
            'dashboard.kpi_window_hours' => 9,
            'dashboard.sales_kpi_window_hours' => 24,
        ]);

        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Alice',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-07 05:00:00'),
            'lead_data_json' => ['ezycash_amount' => 200.00],
        ]);

        $kpis = app(DashboardStatsService::class)->getKpisForCampaign('mbsales');

        $this->assertSame(0, $kpis['calls']);
        $this->assertSame(1, $kpis['sales']);
        $this->assertSame(200.0, $kpis['sales_amount']);
        $this->assertSame('Alice', $kpis['top_agent']);
    }

    public function test_get_kpis_respects_additional_sale_codes_from_config(): void
    {
        Carbon::setTestNow('2026-05-07 15:00:00');
        Cache::flush();
        config(['dashboard.kpi_window_hours' => 9, 'dashboard.sale_disposition_codes' => ['SALE', 'UPSELL']]);

        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'A',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-07 14:00:00'),
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'A',
            'disposition_code' => 'UPSELL',
            'called_at' => Carbon::parse('2026-05-07 14:00:00'),
        ]);

        $kpis = app(DashboardStatsService::class)->getKpisForCampaign('mbsales');

        $this->assertSame(2, $kpis['sales']);
    }

    public function test_get_kpis_top_agent_tie_breaks_by_agent_name_asc(): void
    {
        Carbon::setTestNow('2026-05-07 15:00:00');
        Cache::flush();
        config(['dashboard.kpi_window_hours' => 9]);

        foreach (['Bob', 'Zoe'] as $agent) {
            CampaignDispositionRecord::create([
                'campaign_code' => 'mbsales',
                'agent' => $agent,
                'disposition_code' => 'NC',
                'called_at' => Carbon::parse('2026-05-07 14:00:00'),
            ]);
            CampaignDispositionRecord::create([
                'campaign_code' => 'mbsales',
                'agent' => $agent,
                'disposition_code' => 'NC',
                'called_at' => Carbon::parse('2026-05-07 13:00:00'),
            ]);
        }

        $kpis = app(DashboardStatsService::class)->getKpisForCampaign('mbsales');

        $this->assertSame('Bob', $kpis['top_agent']);
        $this->assertSame(2, $kpis['top_agent_calls']);
    }

    public function test_get_monthly_activity_trend_counts_form_submissions_for_campaign(): void
    {
        Carbon::setTestNow('2026-05-07 12:00:00');
        Cache::flush();
        $this->seed(CampaignSeeder::class);

        $this->insertEzycashRow('2026-05-01');
        $this->insertEzycashRow('2026-05-07');

        $trend = app(DashboardStatsService::class)->getMonthlyActivityTrend('mbsales');

        $this->assertCount(7, $trend['labels']);
        $this->assertCount(7, $trend['values']);
        $this->assertSame(1, $trend['values'][0]);
        $this->assertSame(0, $trend['values'][3]);
        $this->assertSame(1, $trend['values'][6]);
    }

    public function test_get_last_24_hour_activity_trend_buckets_by_hour(): void
    {
        Carbon::setTestNow('2026-05-07 15:30:00');
        Cache::flush();
        $this->seed(CampaignSeeder::class);

        $base = [
            'cardholder_name' => 'Test',
            'mpi_credit_card_no' => '0000',
            'bank' => 'Test',
            'account_type' => 'Savings',
            'account_number' => '1',
            'surname' => 'User',
            'first_name' => 'Test',
            'ezycash_amount' => 100.00,
            'term' => '12',
            'rate' => 1.5,
            'agent' => 'AgentX',
        ];

        DB::table('ezycash')->insert(array_merge($base, [
            'date' => '2026-05-07',
            'request_id' => 'req_24h_1_'.uniqid(),
            'created_at' => Carbon::parse('2026-05-07 14:15:00'),
            'updated_at' => Carbon::parse('2026-05-07 14:15:00'),
        ]));
        DB::table('ezycash')->insert(array_merge($base, [
            'date' => '2026-05-06',
            'request_id' => 'req_24h_2_'.uniqid(),
            'created_at' => Carbon::parse('2026-05-06 16:00:00'),
            'updated_at' => Carbon::parse('2026-05-06 16:00:00'),
        ]));

        $trend = app(DashboardStatsService::class)->getLast24HourActivityTrend('mbsales');

        $this->assertCount(24, $trend['labels']);
        $this->assertCount(24, $trend['values']);
        $this->assertSame(2, array_sum($trend['values']));
    }

    public function test_get_weekly_activity_trend_shows_current_week_daily(): void
    {
        Carbon::setTestNow('2026-05-07 12:00:00');
        Cache::flush();
        $this->seed(CampaignSeeder::class);

        $this->insertEzycashRow('2026-05-06');
        $this->insertEzycashRow('2026-05-07');

        $trend = app(DashboardStatsService::class)->getWeeklyActivityTrend('mbsales');

        // ISO week: Mon May 4–Thu May 7 (today) => 4 points
        $this->assertCount(4, $trend['labels']);
        $this->assertCount(4, $trend['values']);
        $this->assertSame(2, array_sum($trend['values']));
    }

    public function test_get_agent_leaderboard_sorts_by_submissions_then_sales(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        Cache::flush();
        $this->seed(CampaignSeeder::class);
        config(['vicidial.report_system_disposition_codes' => ['SYSTEM']]);

        $this->insertEzycashRowWithAgent('2026-05-10', 'Carl', 1);
        $this->insertEzycashRowWithAgent('2026-05-10', 'Carl', 2);
        $this->insertEzycashRowWithAgent('2026-05-10', 'Carl', 3);
        $this->insertEzycashRowWithAgent('2026-05-11', 'Alice', 4);
        $this->insertEzycashRowWithAgent('2026-05-11', 'Alice', 5);

        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Alice',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-11 12:00:00'),
            'lead_data_json' => ['ezycash_amount' => 500],
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Bob',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-12 12:00:00'),
            'lead_data_json' => ['ezycash_amount' => 100],
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Bob',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-12 14:00:00'),
            'lead_data_json' => ['ezycash_amount' => 200],
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Bob',
            'disposition_code' => 'SYSTEM',
            'called_at' => Carbon::parse('2026-05-12 15:00:00'),
            'lead_data_json' => ['ezycash_amount' => 999],
        ]);

        $board = app(DashboardStatsService::class)->getAgentLeaderboard('mbsales', 10);

        $this->assertSame('Carl', $board[0]['agent']);
        $this->assertSame(3, $board[0]['submissions']);
        $this->assertSame('Alice', $board[1]['agent']);
        $this->assertSame(2, $board[1]['submissions']);
        $this->assertSame(1, $board[1]['sales_count']);
        $this->assertSame(500.0, $board[1]['sales_amount']);
        $this->assertSame('Bob', $board[2]['agent']);
        $this->assertSame(0, $board[2]['submissions']);
        $this->assertSame(2, $board[2]['sales_count']);
        $this->assertSame(300.0, $board[2]['sales_amount']);
    }

    public function test_agent_leaderboard_sums_marked_values_per_submission(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        Cache::flush();
        $this->seed(CampaignSeeder::class);

        foreach (['ezycash_amount', 'rate'] as $order => $fieldName) {
            FormField::query()->create([
                'campaign_code' => 'mbsales',
                'form_type' => 'ezycash',
                'field_name' => $fieldName,
                'field_label' => $fieldName,
                'field_type' => 'number',
                'is_required' => false,
                'is_sale_amount' => true,
                'field_order' => $order + 1,
            ]);
        }

        $this->insertEzycashSaleRow('2026-05-11', 'Alice', 100.00, 25.50, '2026-05-11 12:00:00', 1);
        $this->insertEzycashSaleRow('2026-05-12', 'Alice', 200.00, 10.00, '2026-05-12 12:00:00', 2);

        $board = app(DashboardStatsService::class)->getAgentLeaderboard('mbsales', 10);
        $alice = collect($board)->firstWhere('agent', 'Alice');

        $this->assertNotNull($alice);
        $this->assertSame(2, $alice['sales_count']);
        $this->assertSame(335.5, $alice['sales_amount']);
    }

    public function test_marked_sales_ignore_empty_malformed_and_missing_columns(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        Cache::flush();
        $this->seed(CampaignSeeder::class);

        Schema::create('sale_probe', function ($table): void {
            $table->id();
            $table->date('date');
            $table->string('agent');
            $table->string('amount_one')->nullable();
            $table->string('amount_two')->nullable();
            $table->timestamps();
        });

        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'sale_probe',
            'name' => 'Sale Probe',
            'table_name' => 'sale_probe',
            'display_order' => 4,
            'is_active' => true,
        ]);
        FormField::query()->insert([
            [
                'campaign_code' => 'mbsales',
                'form_type' => 'sale_probe',
                'field_name' => 'amount_one',
                'field_label' => 'Amount One',
                'field_type' => 'number',
                'is_required' => false,
                'is_sale_amount' => true,
                'field_order' => 1,
            ],
            [
                'campaign_code' => 'mbsales',
                'form_type' => 'sale_probe',
                'field_name' => 'amount_two',
                'field_label' => 'Amount Two',
                'field_type' => 'number',
                'is_required' => false,
                'is_sale_amount' => true,
                'field_order' => 2,
            ],
            [
                'campaign_code' => 'mbsales',
                'form_type' => 'sale_probe',
                'field_name' => 'missing_amount',
                'field_label' => 'Missing Amount',
                'field_type' => 'number',
                'is_required' => false,
                'is_sale_amount' => true,
                'field_order' => 3,
            ],
        ]);

        DB::table('sale_probe')->insert([
            [
                'date' => '2026-05-15',
                'agent' => 'Alice',
                'amount_one' => '100.00',
                'amount_two' => null,
                'created_at' => Carbon::parse('2026-05-15 09:00:00'),
                'updated_at' => Carbon::parse('2026-05-15 09:00:00'),
            ],
            [
                'date' => '2026-05-15',
                'agent' => 'Alice',
                'amount_one' => '',
                'amount_two' => 'not-a-number',
                'created_at' => Carbon::parse('2026-05-15 09:01:00'),
                'updated_at' => Carbon::parse('2026-05-15 09:01:00'),
            ],
            [
                'date' => '2026-05-15',
                'agent' => 'Alice',
                'amount_one' => null,
                'amount_two' => '25.50',
                'created_at' => Carbon::parse('2026-05-15 09:02:00'),
                'updated_at' => Carbon::parse('2026-05-15 09:02:00'),
            ],
        ]);

        $kpis = app(DashboardStatsService::class)->getKpisForCampaign('mbsales');
        $board = app(DashboardStatsService::class)->getAgentLeaderboard('mbsales', 10);
        $alice = collect($board)->firstWhere('agent', 'Alice');

        $this->assertSame(2, $kpis['sales']);
        $this->assertNotNull($alice);
        $this->assertSame(2, $alice['sales_count']);
        $this->assertSame(125.5, $alice['sales_amount']);
    }

    public function test_get_kpis_counts_marked_sales_without_disposition_storage(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        Cache::flush();
        $this->seed(CampaignSeeder::class);

        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'ezycash_amount',
            'field_label' => 'EzyCash Amount',
            'field_type' => 'number',
            'is_required' => false,
            'is_sale_amount' => true,
            'field_order' => 1,
        ]);

        $this->insertEzycashSaleRow('2026-05-15', 'Alice', 100.00, 10.00, '2026-05-15 09:00:00', 1);
        Schema::drop('campaign_disposition_records');

        $kpis = app(DashboardStatsService::class)->getKpisForCampaign('mbsales');

        $this->assertSame(1, $kpis['sales']);
        $this->assertSame(100.0, $kpis['sales_amount']);
        $this->assertSame('Alice', $kpis['top_agent']);
    }

    private function insertEzycashRow(string $dateYmd): void
    {
        $now = now();
        DB::table('ezycash')->insert([
            'date' => $dateYmd,
            'request_id' => 'req_'.$dateYmd.'_'.uniqid(),
            'cardholder_name' => 'Test',
            'mpi_credit_card_no' => '0000',
            'bank' => 'Test',
            'account_type' => 'Savings',
            'account_number' => '1',
            'surname' => 'User',
            'first_name' => 'Test',
            'ezycash_amount' => 100.00,
            'term' => '12',
            'rate' => 1.5,
            'agent' => 'AgentX',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertEzycashRowWithAgent(string $dateYmd, string $agent, int $suffix): void
    {
        $now = now();
        DB::table('ezycash')->insert([
            'date' => $dateYmd,
            'request_id' => 'req_'.$dateYmd.'_'.$suffix.'_'.uniqid(),
            'cardholder_name' => 'Test',
            'mpi_credit_card_no' => '0000',
            'bank' => 'Test',
            'account_type' => 'Savings',
            'account_number' => '1',
            'surname' => 'User',
            'first_name' => 'Test',
            'ezycash_amount' => 100.00,
            'term' => '12',
            'rate' => 1.5,
            'agent' => $agent,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertEzycashSaleRow(
        string $dateYmd,
        string $agent,
        float $amount,
        float $rate,
        string $createdAt,
        int $suffix,
    ): void {
        DB::table('ezycash')->insert([
            'date' => $dateYmd,
            'request_id' => 'req_sale_'.$suffix.'_'.uniqid(),
            'cardholder_name' => 'Test',
            'mpi_credit_card_no' => '0000',
            'bank' => 'Test',
            'account_type' => 'Savings',
            'account_number' => '1',
            'surname' => 'User',
            'first_name' => 'Test',
            'ezycash_amount' => $amount,
            'term' => '12',
            'rate' => $rate,
            'agent' => $agent,
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);
    }
}
