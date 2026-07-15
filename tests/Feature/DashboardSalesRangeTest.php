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
        $this->assertSame('Alice', $kpis['top_agent']);
        $this->assertSame(2, $kpis['top_agent_sales']);
        $this->assertSame(125.0, $kpis['top_agent_sales_amount']);

        $breakdown = collect($kpis['sales_by_form'])->keyBy('form_code');
        $this->assertSame(2, $breakdown['cash']['sales']);
        $this->assertSame(125.0, $breakdown['cash']['sales_amount']);
        $this->assertSame(1, $breakdown['transfer']['sales']);
        $this->assertSame(500.0, $breakdown['transfer']['sales_amount']);
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
