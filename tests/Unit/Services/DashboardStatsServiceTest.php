<?php

namespace Tests\Unit\Services;

use App\Models\CampaignDispositionRecord;
use App\Services\DashboardStatsService;
use Carbon\Carbon;
use Database\Seeders\CampaignSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
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
        config(['dashboard.kpi_window_hours' => 9]);

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
        ]);
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Alex',
            'disposition_code' => 'OTHER',
            'called_at' => Carbon::parse('2026-05-07 05:30:00'),
        ]);

        /** @var DashboardStatsService $service */
        $service = app(DashboardStatsService::class);
        $kpis = $service->getKpisForCampaign('mbsales');

        $this->assertSame(2, $kpis['calls']);
        $this->assertSame(1, $kpis['sales']);
        $this->assertSame('Alex', $kpis['top_agent']);
        $this->assertSame(2, $kpis['top_agent_calls']);
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

    public function test_get_weekly_activity_trend_returns_expected_week_count(): void
    {
        Carbon::setTestNow('2026-05-07 12:00:00');
        Cache::flush();
        config(['dashboard.weekly_activity_weeks' => 8]);
        $this->seed(CampaignSeeder::class);

        $this->insertEzycashRow('2026-05-06');
        $this->insertEzycashRow('2026-05-07');

        $trend = app(DashboardStatsService::class)->getWeeklyActivityTrend('mbsales');

        $this->assertCount(8, $trend['labels']);
        $this->assertCount(8, $trend['values']);
        $this->assertSame(2, array_sum($trend['values']));
    }

    public function test_get_agent_leaderboard_sorts_by_submissions_then_sales(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        Cache::flush();
        $this->seed(CampaignSeeder::class);

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
}
