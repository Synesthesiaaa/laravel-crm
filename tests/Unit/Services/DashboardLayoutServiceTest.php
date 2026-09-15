<?php

namespace Tests\Unit\Services;

use App\Models\DashboardLayout;
use App\Services\DashboardLayoutService;
use App\Services\DashboardSalesRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardLayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_layout_contains_all_user_dashboard_sections(): void
    {
        $layout = app(DashboardLayoutService::class)->defaultLayout();

        $this->assertSame([
            'welcome',
            'kpis',
            'activity',
            'leaderboard',
            'campaign_report',
            'forms',
            'quick_links',
        ], array_keys($layout['sections']));
        $this->assertTrue($layout['sections']['welcome']['visible']);
    }

    public function test_dashboard_layout_and_sales_rule_services_are_scoped_in_the_container(): void
    {
        $this->assertSame(
            app(DashboardLayoutService::class),
            app(DashboardLayoutService::class),
        );
        $this->assertSame(
            app(DashboardSalesRuleService::class),
            app(DashboardSalesRuleService::class),
        );
    }

    public function test_save_normalizes_order_visibility_and_unknown_sections(): void
    {
        $service = app(DashboardLayoutService::class);

        $layout = $service->saveForCampaign(
            'mbsales',
            ['forms', 'welcome', 'forms', 'unknown'],
            ['forms', 'welcome'],
        );

        $this->assertSame(['forms', 'welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'quick_links'], array_keys($layout['sections']));
        $this->assertTrue($layout['sections']['forms']['visible']);
        $this->assertFalse($layout['sections']['kpis']['visible']);
        $this->assertSame(0, $layout['sections']['forms']['order']);
        $this->assertSame(1, $layout['sections']['welcome']['order']);
        $this->assertDatabaseHas('dashboard_layouts', ['campaign_code' => 'mbsales']);
    }

    public function test_saved_layouts_are_scoped_to_campaign(): void
    {
        $service = app(DashboardLayoutService::class);
        $service->saveForCampaign('mbsales', ['forms'], ['forms']);

        $this->assertTrue($service->getForCampaign('mbsales')['sections']['forms']['visible']);
        $this->assertTrue($service->getForCampaign('other')['sections']['welcome']['visible']);
        $this->assertDatabaseMissing('dashboard_layouts', ['campaign_code' => 'other']);
    }

    public function test_get_for_campaign_memoizes_the_campaign_lookup_within_the_service_instance(): void
    {
        DashboardLayout::query()->create([
            'campaign_code' => 'mbsales',
            'layout' => [
                'sections' => [
                    'forms' => ['visible' => true, 'order' => 0],
                ],
            ],
        ]);

        $service = app(DashboardLayoutService::class);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $first = $service->getForCampaign('mbsales');
        $second = $service->getForCampaign('mbsales');

        $layoutSelects = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'dashboard_layouts'))
            ->filter(fn (array $query): bool => str_starts_with(ltrim(strtolower($query['query'])), 'select'));

        $this->assertSame($first, $second);
        $this->assertCount(1, $layoutSelects);
    }

    public function test_save_persists_custom_sales_rules_with_campaign_layout(): void
    {
        $service = app(DashboardLayoutService::class);
        $sales = [
            'mode' => 'custom',
            'forms' => [
                [
                    'form_code' => 'ezycash',
                    'amount_field' => 'ezycash_amount',
                    'conditions' => [
                        [
                            'field_name' => 'amenable',
                            'accepted_values' => ['Yes', 'Approved'],
                        ],
                    ],
                ],
            ],
        ];

        $layout = $service->saveForCampaign(
            'mbsales',
            ['welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'forms', 'quick_links'],
            ['welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'forms', 'quick_links'],
            $sales,
        );

        $this->assertSame($sales, $layout['sales']);
        $this->assertSame($sales, $service->getForCampaign('mbsales')['sales']);
    }
}
