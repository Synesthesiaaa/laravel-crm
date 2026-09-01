<?php

namespace Tests\Unit\Services;

use App\Models\CampaignDispositionRecord;
use App\Models\Form;
use App\Models\FormField;
use App\Services\DashboardLayoutService;
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

    public function test_get_agent_leaderboard_sorts_by_sales_amount_then_sales_count_then_name(): void
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
            'lead_data_json' => ['ezycash_amount' => 300],
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Bob',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-12 14:00:00'),
            'lead_data_json' => ['ezycash_amount' => 300],
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Aaron',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-12 16:00:00'),
            'lead_data_json' => ['ezycash_amount' => 200],
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Aaron',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-12 17:00:00'),
            'lead_data_json' => ['ezycash_amount' => 200],
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Amy',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-12 18:00:00'),
            'lead_data_json' => ['ezycash_amount' => 400],
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Zed',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-12 19:00:00'),
            'lead_data_json' => ['ezycash_amount' => 400],
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Bob',
            'disposition_code' => 'SYSTEM',
            'called_at' => Carbon::parse('2026-05-12 15:00:00'),
            'lead_data_json' => ['ezycash_amount' => 999],
        ]);

        $board = app(DashboardStatsService::class)->getAgentLeaderboard('mbsales', 10);

        $this->assertSame(
            ['Bob', 'Alice', 'Aaron', 'Amy', 'Zed', 'Carl'],
            array_column($board, 'agent'),
        );

        $rows = collect($board)->keyBy('agent');
        $this->assertSame(0, $rows['Bob']['submissions']);
        $this->assertSame(2, $rows['Bob']['sales_count']);
        $this->assertSame(600.0, $rows['Bob']['sales_amount']);
        $this->assertSame(2, $rows['Alice']['submissions']);
        $this->assertSame(1, $rows['Alice']['sales_count']);
        $this->assertSame(500.0, $rows['Alice']['sales_amount']);
        $this->assertSame(2, $rows['Aaron']['sales_count']);
        $this->assertSame(400.0, $rows['Aaron']['sales_amount']);
        $this->assertSame(1, $rows['Amy']['sales_count']);
        $this->assertSame(400.0, $rows['Amy']['sales_amount']);
        $this->assertSame(1, $rows['Zed']['sales_count']);
        $this->assertSame(400.0, $rows['Zed']['sales_amount']);
        $this->assertSame(3, $rows['Carl']['submissions']);
        $this->assertSame(0, $rows['Carl']['sales_count']);
        $this->assertSame(0.0, $rows['Carl']['sales_amount']);
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

    public function test_selected_range_sales_use_custom_tag_rules_once_per_submission(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');
        $this->seed(CampaignSeeder::class);
        Schema::create('rule_probe', function ($table): void {
            $table->id();
            $table->date('date');
            $table->string('agent')->nullable();
            $table->string('tag_one')->nullable();
            $table->string('tag_two')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();
        });
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'rule_probe',
            'name' => 'Rule Probe',
            'table_name' => 'rule_probe',
            'display_order' => 4,
            'is_active' => true,
        ]);
        FormField::query()->insert([
            [
                'campaign_code' => 'mbsales',
                'form_type' => 'rule_probe',
                'field_name' => 'tag_one',
                'field_label' => 'Tag One',
                'field_type' => 'text',
                'is_required' => false,
                'field_order' => 1,
            ],
            [
                'campaign_code' => 'mbsales',
                'form_type' => 'rule_probe',
                'field_name' => 'tag_two',
                'field_label' => 'Tag Two',
                'field_type' => 'text',
                'is_required' => false,
                'field_order' => 2,
            ],
            [
                'campaign_code' => 'mbsales',
                'form_type' => 'rule_probe',
                'field_name' => 'amount',
                'field_label' => 'Amount',
                'field_type' => 'number',
                'is_required' => false,
                'field_order' => 3,
            ],
        ]);
        app(DashboardLayoutService::class)->saveForCampaign(
            'mbsales',
            array_keys(app(DashboardLayoutService::class)->defaultLayout()['sections']),
            array_keys(app(DashboardLayoutService::class)->defaultLayout()['sections']),
            [
                'mode' => 'custom',
                'forms' => [[
                    'form_code' => 'rule_probe',
                    'amount_field' => 'amount',
                    'conditions' => [
                        ['field_name' => 'tag_one', 'accepted_values' => ['Yes']],
                        ['field_name' => 'tag_two', 'accepted_values' => ['Approved']],
                    ],
                ]],
            ],
        );

        DB::table('rule_probe')->insert([
            [
                'date' => '2026-05-15',
                'agent' => 'Alice',
                'tag_one' => ' yes ',
                'tag_two' => 'Approved',
                'amount' => 100,
                'created_at' => '2026-05-15 10:00:00',
                'updated_at' => '2026-05-15 10:00:00',
            ],
            [
                'date' => '2026-05-15',
                'agent' => 'Bob',
                'tag_one' => 'No',
                'tag_two' => 'approved',
                'amount' => 50,
                'created_at' => '2026-05-15 11:00:00',
                'updated_at' => '2026-05-15 11:00:00',
            ],
            [
                'date' => '2026-05-15',
                'agent' => 'Ignored',
                'tag_one' => 'No',
                'tag_two' => 'No',
                'amount' => 900,
                'created_at' => '2026-05-15 12:00:00',
                'updated_at' => '2026-05-15 12:00:00',
            ],
            [
                'date' => '2026-05-15',
                'agent' => 'Outside',
                'tag_one' => 'Yes',
                'tag_two' => 'No',
                'amount' => 1000,
                'created_at' => '2026-05-15 18:00:00',
                'updated_at' => '2026-05-15 18:00:00',
            ],
        ]);

        $kpis = app(DashboardStatsService::class)->getSalesKpisForCampaign(
            'mbsales',
            Carbon::parse('2026-05-15 06:00:00'),
            Carbon::parse('2026-05-15 18:00:00'),
        );

        $this->assertSame(2, $kpis['sales']);
        $this->assertSame(150.0, $kpis['sales_amount']);
        $this->assertSame(['Alice', 'Bob'], array_column($kpis['agent_leaderboard'], 'agent'));
        $this->assertSame(2, $kpis['sales_by_form'][0]['sales']);
        $this->assertSame(150.0, $kpis['sales_by_form'][0]['sales_amount']);

        $leaderboard = app(DashboardStatsService::class)->getAgentLeaderboard('mbsales');
        $salesByAgent = collect($leaderboard)->keyBy('agent');
        $this->assertSame(1, $salesByAgent['Alice']['sales_count']);
        $this->assertSame(100.0, $salesByAgent['Alice']['sales_amount']);
        $this->assertSame(1, $salesByAgent['Bob']['sales_count']);

        $rolling = app(DashboardStatsService::class)->getKpisForCampaign('mbsales');
        $this->assertSame(2, $rolling['sales']);
        $this->assertSame(150.0, $rolling['sales_amount']);
        $this->assertSame('Alice', $rolling['top_agent']);

        $summary = app(DashboardStatsService::class)->getDashboardSummaryForCampaign('mbsales');
        $this->assertSame(['count' => 2, 'amount' => 150.0], $summary['summary']['current']);
        $this->assertSame(['count' => 0, 'amount' => 0.0], $summary['summary']['previous']);
        $this->assertSame('new', $summary['comparison']['count']['status']);
        $this->assertSame(2, $summary['daily'][14]['current']['count']);
    }

    public function test_dashboard_summary_compares_aligned_month_to_date_sales_once_per_record(): void
    {
        Carbon::setTestNow('2026-05-07 12:00:00');
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

        $this->insertEzycashSaleRow('2026-05-01', 'Alice', 100.00, 10.00, '2026-05-01 10:00:00', 1);
        $this->insertEzycashSaleRow('2026-05-03', 'Alice', 50.00, 10.00, '2026-05-03 10:00:00', 2);
        $this->insertEzycashSaleRow('2026-05-07', 'Alice', 25.00, 10.00, '2026-05-07 11:00:00', 3);
        $this->insertEzycashSaleRow('2026-05-02', 'Alice', 0.00, 10.00, '2026-05-02 10:00:00', 4);
        $this->insertEzycashSaleRow('2026-04-01', 'Alice', 80.00, 10.00, '2026-04-01 10:00:00', 5);
        $this->insertEzycashSaleRow('2026-04-03', 'Alice', 20.00, 10.00, '2026-04-03 10:00:00', 6);
        $this->insertEzycashSaleRow('2026-04-07', 'Alice', 10.00, 10.00, '2026-04-07 11:00:00', 7);
        $this->insertEzycashSaleRow('2026-04-07', 'Alice', 900.00, 10.00, '2026-04-07 12:00:01', 8);

        $summary = app(DashboardStatsService::class)->getDashboardSummaryForCampaign(
            'mbsales',
            Carbon::create(2026, 5, 7, 12, 0, 0, 'UTC'),
        );

        $this->assertTrue($summary['has_activity']);
        $this->assertSame('May 1, 2026 - May 7, 2026', $summary['period']['current']['label']);
        $this->assertSame('Apr 1, 2026 - Apr 7, 2026', $summary['period']['previous']['label']);
        $this->assertSame(['count' => 4, 'amount' => 175.0], $summary['summary']['current']);
        $this->assertSame(['count' => 3, 'amount' => 110.0], $summary['summary']['previous']);
        $this->assertSame(1, $summary['comparison']['count']['difference']);
        $this->assertSame(33.33, $summary['comparison']['count']['percentage']);
        $this->assertSame(65.0, $summary['comparison']['amount']['difference']);
        $this->assertSame(59.09, $summary['comparison']['amount']['percentage']);
        $this->assertSame(1, $summary['daily'][1]['current']['count']);
        $this->assertSame(0.0, $summary['daily'][1]['current']['amount']);
        $this->assertSame(0, $summary['daily'][3]['current']['count']);
        $this->assertSame(1, $summary['daily'][2]['current']['count']);
        $this->assertSame(1, $summary['daily'][6]['previous']['count']);
        $this->assertSame(25.0, $summary['daily'][6]['current']['amount']);
        $this->assertSame(10.0, $summary['daily'][6]['previous']['amount']);
    }

    public function test_dashboard_summary_marks_missing_previous_month_days_as_unavailable(): void
    {
        Carbon::setTestNow('2026-03-31 12:00:00');

        $summary = app(DashboardStatsService::class)->getDashboardSummaryForCampaign(
            'mbsales',
            Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'),
        );

        $this->assertCount(31, $summary['daily']);
        $this->assertSame('Feb 28, 2026', $summary['daily'][27]['previous_date']);
        $this->assertNull($summary['daily'][28]['previous_date']);
        $this->assertNull($summary['daily'][28]['previous']['count']);
        $this->assertNull($summary['daily'][28]['previous']['amount']);
    }

    public function test_dashboard_summary_preserves_negative_amounts_and_campaign_scope(): void
    {
        Carbon::setTestNow('2026-05-07 12:00:00');
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

        $this->insertEzycashSaleRow('2026-05-07', 'Alice', -25.00, 10.00, '2026-05-07 11:00:00', 9);

        $summary = app(DashboardStatsService::class)->getDashboardSummaryForCampaign('mbsales');
        $otherCampaignSummary = app(DashboardStatsService::class)->getDashboardSummaryForCampaign('pjli');

        $this->assertSame(['count' => 1, 'amount' => -25.0], $summary['summary']['current']);
        $this->assertSame('new', $summary['comparison']['amount']['status']);
        $this->assertSame(0, $otherCampaignSummary['summary']['current']['count']);
        $this->assertSame(0.0, $otherCampaignSummary['summary']['current']['amount']);
        $this->assertFalse($otherCampaignSummary['has_activity']);
    }

    public function test_dashboard_summary_uses_full_previous_month_when_current_month_is_complete(): void
    {
        $asOf = Carbon::create(2026, 5, 31, 23, 59, 59, 'UTC')->endOfDay();

        $summary = app(DashboardStatsService::class)->getDashboardSummaryForCampaign('mbsales', $asOf);

        $this->assertSame('completed_month', $summary['period']['mode']);
        $this->assertSame('May 1, 2026 - May 31, 2026', $summary['period']['current']['label']);
        $this->assertSame('Apr 1, 2026 - Apr 30, 2026', $summary['period']['previous']['label']);
        $this->assertSame(31, $summary['period']['current']['day_count']);
        $this->assertSame(30, $summary['period']['previous']['day_count']);
    }

    public function test_dashboard_summary_includes_records_at_the_observation_timestamp(): void
    {
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
        $this->insertEzycashSaleRow('2026-05-07', 'Alice', 40.00, 10.00, '2026-05-07 12:00:00', 10);

        $summary = app(DashboardStatsService::class)->getDashboardSummaryForCampaign(
            'mbsales',
            Carbon::create(2026, 5, 7, 12, 0, 0, 'UTC'),
        );

        $this->assertSame(['count' => 1, 'amount' => 40.0], $summary['summary']['current']);
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
