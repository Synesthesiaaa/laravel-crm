<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignDispositionRecord;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\DashboardStatsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardSalesRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sales_kpis_use_marked_form_values_inside_the_selected_range(): void
    {
        $this->registerSalesForm('cash', 'Cash Sale', 'cash_sales');
        $this->registerSalesForm('transfer', 'Bank Transfer', 'transfer_sales');

        $this->insertSale('cash_sales', 'Alice', 999.00, '2026-05-15 05:59:59');
        $this->insertSale('cash_sales', 'Alice', 100.00, '2026-05-15 06:00:00');
        $this->insertSale('cash_sales', 'Alice', 25.00, '2026-05-15 17:59:59');
        $this->insertSale('transfer_sales', 'Bob', 500.00, '2026-05-15 12:00:00');
        $this->insertSale('transfer_sales', 'Bob', 700.00, '2026-05-15 18:00:00');

        $kpis = app(DashboardStatsService::class)->getSalesKpisForCampaign(
            'mbsales',
            Carbon::parse('2026-05-15 06:00:00'),
            Carbon::parse('2026-05-15 18:00:00'),
        );

        $this->assertSame(3, $kpis['sales']);
        $this->assertSame(625.0, $kpis['sales_amount']);
        $this->assertSame('Bob', $kpis['top_agent']);
        $this->assertSame(1, $kpis['top_agent_sales']);
        $this->assertSame(500.0, $kpis['top_agent_sales_amount']);

        $breakdown = collect($kpis['sales_by_form'])->keyBy('form_code');
        $this->assertSame(2, $breakdown['cash']['sales']);
        $this->assertSame(125.0, $breakdown['cash']['sales_amount']);
        $this->assertSame(1, $breakdown['transfer']['sales']);
        $this->assertSame(500.0, $breakdown['transfer']['sales_amount']);
    }

    public function test_sales_kpis_return_a_selected_range_leaderboard_sorted_by_sales_amount_then_sales_count_then_name(): void
    {
        $this->registerSalesForm('cash', 'Cash Sale', 'cash_sales');

        $this->insertSale('cash_sales', 'Alice', 100.00, '2026-05-15 07:00:00');
        $this->insertSale('cash_sales', 'Alice', 25.00, '2026-05-15 08:00:00');
        $this->insertSale('cash_sales', 'Bob', 200.00, '2026-05-15 09:00:00');
        $this->insertSale('cash_sales', 'Carl', 300.00, '2026-05-15 10:00:00');
        $this->insertSale('cash_sales', 'Aaron', 150.00, '2026-05-15 11:00:00');
        $this->insertSale('cash_sales', 'Aaron', 150.00, '2026-05-15 12:00:00');
        $this->insertSale('cash_sales', 'Amy', 300.00, '2026-05-15 13:00:00');
        $this->insertSale('cash_sales', 'Zed', 300.00, '2026-05-15 14:00:00');
        $this->insertSale('cash_sales', 'Zed', 999.00, '2026-05-15 19:00:00');

        $kpis = app(DashboardStatsService::class)->getSalesKpisForCampaign(
            'mbsales',
            Carbon::parse('2026-05-15 06:00:00'),
            Carbon::parse('2026-05-15 18:00:00'),
        );

        $this->assertSame([
            ['agent' => 'Aaron', 'sales_count' => 2, 'sales_amount' => 300.0],
            ['agent' => 'Amy', 'sales_count' => 1, 'sales_amount' => 300.0],
            ['agent' => 'Carl', 'sales_count' => 1, 'sales_amount' => 300.0],
            ['agent' => 'Zed', 'sales_count' => 1, 'sales_amount' => 300.0],
            ['agent' => 'Bob', 'sales_count' => 1, 'sales_amount' => 200.0],
            ['agent' => 'Alice', 'sales_count' => 2, 'sales_amount' => 125.0],
        ], $kpis['agent_leaderboard']);
    }

    public function test_sales_kpis_ignore_dispositions_when_no_marked_form_sale_field_exists(): void
    {
        CampaignDispositionRecord::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Disposition Agent',
            'disposition_code' => 'SALE',
            'called_at' => Carbon::parse('2026-05-15 10:00:00'),
            'lead_data_json' => ['amount' => 999.00],
        ]);

        $kpis = app(DashboardStatsService::class)->getSalesKpisForCampaign(
            'mbsales',
            Carbon::parse('2026-05-15 06:00:00'),
            Carbon::parse('2026-05-15 18:00:00'),
        );

        $this->assertSame(0, $kpis['sales']);
        $this->assertSame(0.0, $kpis['sales_amount']);
        $this->assertNull($kpis['top_agent']);
        $this->assertSame([], $kpis['sales_by_form']);
    }

    public function test_dashboard_defaults_the_sales_filter_to_the_current_business_day(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('name="sales_date"', false);
        $response->assertSee('value="2026-05-15"', false);
        $response->assertSee('name="sales_start"', false);
        $response->assertSee('value="06:00"', false);
        $response->assertSee('name="sales_end"', false);
        $response->assertSee('value="18:00"', false);
    }

    public function test_dashboard_uses_requested_sales_filter_values_and_renders_the_sales_modal_trigger(): void
    {
        Cache::flush();

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard', [
                'sales_date' => '2026-05-12',
                'sales_start' => '07:30',
                'sales_end' => '16:45',
            ]));

        $response->assertOk();
        $response->assertSee('value="2026-05-12"', false);
        $response->assertSee('value="07:30"', false);
        $response->assertSee('value="16:45"', false);
        $response->assertSee('Sales by form', false);
        $response->assertSee('x-on:mouseenter="openSalesModal()"', false);
        $response->assertSee('x-on:click="openSalesModal()"', false);
        $response->assertSee('x-on:focusin="openSalesModal()"', false);
        $response->assertSee('x-on:mouseleave="scheduleSalesModalClose()"', false);
        $response->assertSee('openSalesModal() {', false);
        $response->assertSee('x-transition:leave="transition ease-in duration-150"', false);
        $response->assertSee('pointer-events: none;', false);
        $response->assertSee('pointer-events: auto;', false);
        $response->assertSee('Ranked by total sale amount, then qualifying sales count and agent name.', false);
        $response->assertSee('x-on:mouseenter="openLeaderboardModal()"', false);
        $response->assertSee('x-on:click="openLeaderboardModal()"', false);
        $response->assertSee('Daily agent leaderboard', false);
        $response->assertSee('Agent leaderboard', false);
        $response->assertSee('class="stat-card h-full"', false);
    }

    public function test_dashboard_renders_selected_range_agent_leaderboard_amounts(): void
    {
        $this->registerSalesForm('cash', 'Cash Sale', 'cash_sales');

        $this->insertSale('cash_sales', 'Alice', 100.00, '2026-05-12 07:00:00');
        $this->insertSale('cash_sales', 'Bob', 250.00, '2026-05-12 08:00:00');
        $this->insertSale('cash_sales', 'Outside', 999.00, '2026-05-12 19:00:00');

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard', [
                'sales_date' => '2026-05-12',
                'sales_start' => '06:00',
                'sales_end' => '18:00',
            ]));

        $response->assertOk();
        $response->assertSee('Daily agent leaderboard', false);
        $response->assertSee('Alice', false);
        $response->assertSee('Bob', false);
        $response->assertSee('100.00', false);
        $response->assertSee('250.00', false);
        $response->assertDontSee('999.00', false);
    }

    public function test_daily_campaign_report_aggregates_daily_and_month_to_date_rows_by_agent(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');
        Cache::flush();
        $this->registerSalesForm('cash', 'Cash Sale', 'cash_sales');
        $this->registerSalesForm('transfer', 'Bank Transfer', 'transfer_sales');

        $this->insertSale('cash_sales', 'Alice', 100.00, '2026-05-15 07:00:00');
        $this->insertSale('cash_sales', 'Alice', 900.00, '2026-05-14 07:00:00');
        $this->insertSale('cash_sales', 'Bob', 25.00, '2026-05-15 08:00:00');
        $this->insertSale('transfer_sales', 'Alice', 50.00, '2026-05-15 09:00:00');
        $this->insertSale('transfer_sales', 'Bob', 70.00, '2026-05-13 10:00:00');

        $report = app(DashboardStatsService::class)->getDailyCampaignReport(
            'mbsales',
            Carbon::parse('2026-05-15'),
        );

        $this->assertSame([
            ['code' => 'cash', 'name' => 'Cash Sale'],
            ['code' => 'transfer', 'name' => 'Bank Transfer'],
        ], $report['forms']);

        $daily = collect($report['daily'])->keyBy('agent');
        $this->assertSame(2, $daily['Alice']['total_count']);
        $this->assertSame(150.0, $daily['Alice']['total_amount']);
        $this->assertSame(1, $daily['Bob']['counts']['cash']);
        $this->assertSame(0, $daily['Bob']['counts']['transfer']);
        $this->assertSame(25.0, $daily['Bob']['total_amount']);
        $this->assertSame(3, $report['totals']['daily']['total_count']);
        $this->assertSame(175.0, $report['totals']['daily']['total_amount']);

        $monthToDate = collect($report['month_to_date'])->keyBy('agent');
        $this->assertSame(3, $monthToDate['Alice']['total_count']);
        $this->assertSame(1050.0, $monthToDate['Alice']['total_amount']);
        $this->assertSame(2, $monthToDate['Bob']['total_count']);
        $this->assertSame(95.0, $monthToDate['Bob']['total_amount']);
        $this->assertSame(5, $report['totals']['month_to_date']['total_count']);
        $this->assertSame(1145.0, $report['totals']['month_to_date']['total_amount']);
    }

    public function test_daily_campaign_report_returns_a_stable_empty_shape_without_valid_forms(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');
        Cache::flush();

        $report = app(DashboardStatsService::class)->getDailyCampaignReport(
            'mbsales',
            Carbon::parse('2026-05-15'),
        );

        $this->assertSame('2026-05-15', $report['date']);
        $this->assertSame([], $report['forms']);
        $this->assertSame([], $report['daily']);
        $this->assertSame([], $report['month_to_date']);
        $this->assertSame(0, $report['totals']['daily']['total_count']);
        $this->assertSame(0.0, $report['totals']['month_to_date']['total_amount']);
    }

    public function test_dashboard_renders_campaign_report_tables_without_mpi_cards_label(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');
        Cache::flush();
        $this->registerSalesForm('cash', 'Cash Sale', 'cash_sales');
        $this->insertSale('cash_sales', 'Alice', 100.00, '2026-05-15 07:00:00');

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Daily amounts', false);
        $response->assertSee('Daily counts', false);
        $response->assertSee('Month to date accounts', false);
        $response->assertSee('Month to date submitted amounts', false);
        $response->assertSee('Cash Sale', false);
        $response->assertDontSee('MPI Cards', false);

        $content = $response->getContent();
        $sectionStart = strpos($content, 'data-report-table="month-to-date-accounts"');
        $sectionEnd = $sectionStart === false ? false : strpos($content, '</section>', $sectionStart);
        $monthToDateAccounts = $sectionStart === false || $sectionEnd === false
            ? ''
            : substr($content, $sectionStart, $sectionEnd - $sectionStart);

        $this->assertStringContainsString('Cash Sale', $monthToDateAccounts);
        $this->assertStringContainsString('Total accounts', $monthToDateAccounts);
        $this->assertStringNotContainsString('Submitted amount', $monthToDateAccounts);
        $this->assertLessThan(
            strpos($content, 'data-report-table="daily-counts"'),
            strpos($content, 'data-report-table="month-to-date-accounts"'),
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<section[^>]*campaign-report-wide[^>]*data-report-table="daily-counts"/',
            $content,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<section[^>]*campaign-report-wide[^>]*data-report-table="month-to-date-submitted-amounts"/',
            $content,
        );
        $this->assertSame(4, substr_count($content, 'class="report-table--wide"'));
    }

    public function test_dashboard_reverts_invalid_sales_filters_to_the_default_business_hours(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard', [
                'sales_date' => 'not-a-date',
                'sales_start' => '18:00',
                'sales_end' => '06:00',
            ]));

        $response->assertOk();
        $response->assertSee('value="2026-05-15"', false);
        $response->assertSee('value="06:00"', false);
        $response->assertSee('value="18:00"', false);
    }

    private function registerSalesForm(string $formCode, string $name, string $tableName): void
    {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('request_id')->nullable();
            $table->string('agent')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();
        });

        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => $formCode,
            'name' => $name,
            'table_name' => $tableName,
            'display_order' => 1,
            'is_active' => true,
        ]);
        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => $formCode,
            'field_name' => 'amount',
            'field_label' => 'Amount',
            'field_type' => 'number',
            'is_required' => false,
            'is_sale_amount' => true,
            'field_order' => 1,
        ]);
    }

    private function insertSale(string $tableName, string $agent, float $amount, string $createdAt): void
    {
        $timestamp = Carbon::parse($createdAt);

        DB::table($tableName)->insert([
            'date' => $timestamp->toDateString(),
            'request_id' => 'sale_'.uniqid(),
            'agent' => $agent,
            'amount' => $amount,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}
