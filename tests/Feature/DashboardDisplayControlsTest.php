<?php

namespace Tests\Feature;

use App\Events\DashboardLayoutUpdated;
use App\Models\Campaign;
use App\Models\Form;
use App\Models\User;
use App\Services\DashboardLayoutService;
use Carbon\Carbon;
use Database\Seeders\CampaignSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DashboardDisplayControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Campaign::factory()->create(['code' => 'mbsales']);
        Event::fake([DashboardLayoutUpdated::class]);
    }

    public function test_amount_settings_are_saved_per_campaign_and_preserved_when_omitted(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->withSession(['campaign' => 'mbsales']);
        $payload = [
            'section_order' => array_keys(DashboardLayoutService::sectionDefinitions()),
            'visible_sections' => array_keys(DashboardLayoutService::sectionDefinitions()),
        ];
        $this->post(route('admin.dashboard-layout.update'), $payload + ['amounts' => ['enabled' => '0', 'change' => '0']])
            ->assertSessionHasNoErrors();
        $service = app(DashboardLayoutService::class);
        $this->assertFalse($service->getForCampaign('mbsales')['amounts']['enabled']);
        $this->assertTrue($service->getForCampaign('other')['amounts']['enabled']);
        $this->post(route('admin.dashboard-layout.update'), $payload)->assertSessionHasNoErrors();
        $this->assertFalse($service->getForCampaign('mbsales')['amounts']['enabled']);
        $this->post(route('admin.dashboard-layout.update'), $payload + ['amounts' => ['enabled' => '1']])->assertSessionHasNoErrors();
        $this->assertTrue($service->getForCampaign('mbsales')['amounts']['enabled']);
        $this->assertFalse($service->getForCampaign('mbsales')['amounts']['change']);
    }

    public function test_invalid_amount_settings_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->withSession(['campaign' => 'mbsales'])
            ->post(route('admin.dashboard-layout.update'), [
                'section_order' => array_keys(DashboardLayoutService::sectionDefinitions()),
                'amounts' => ['enabled' => 'invalid'],
            ])->assertSessionHasErrors('amounts.enabled');
        $this->assertDatabaseMissing('dashboard_layouts', ['campaign_code' => 'mbsales']);
    }

    public function test_non_admin_cannot_change_amount_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
            ->withSession(['campaign' => 'mbsales'])
            ->post(route('admin.dashboard-layout.update'), [
                'section_order' => array_keys(DashboardLayoutService::sectionDefinitions()),
                'amounts' => ['enabled' => false],
            ])->assertForbidden();
        $this->assertDatabaseMissing('dashboard_layouts', ['campaign_code' => 'mbsales']);
    }

    public function test_disabled_amounts_hide_monetary_dashboard_displays(): void
    {
        $service = app(DashboardLayoutService::class);
        $sections = array_keys($service::sectionDefinitions());
        $service->saveForCampaign('mbsales', $sections, $sections, amountConfig: ['enabled' => false]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
            ->withSession(['campaign' => 'mbsales'])
            ->get(route('dashboard'))->assertOk()
            ->assertSee('Transactions')
            ->assertDontSee('Total Amount')
            ->assertDontSee('Amount Change')
            ->assertDontSee('Total Value:')
            ->assertDontSee('data-report-table="daily-amounts"', false)
            ->assertDontSee('x-on:mouseenter=', false)
            ->assertDontSee('x-on:focusin=', false);
    }

    public function test_amount_cards_can_be_disabled_independently(): void
    {
        $service = app(DashboardLayoutService::class);
        $sections = array_keys($service::sectionDefinitions());
        $service->saveForCampaign('mbsales', $sections, $sections, amountConfig: ['change' => false]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
            ->withSession(['campaign' => 'mbsales'])
            ->get(route('dashboard'))->assertOk()->assertSee('Total Amount')->assertDontSee('Amount Change');
    }

    public function test_submission_trends_offer_only_active_forms_in_the_selected_campaign(): void
    {
        $this->seed(CampaignSeeder::class);
        Form::query()->where('campaign_code', 'mbsales')->where('form_code', 'ezytransfer')->update(['is_active' => false]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
            ->withSession(['campaign' => 'mbsales'])
            ->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['data-activity-form="ezycash"', 'data-activity-period="daily"', 'data-activity-period="weekly"', 'data-activity-period="monthly"', 'data-activity-form="ezyconvert"'], false)
            ->assertDontSee('data-activity-form="ezytransfer"', false)
            ->assertDontSee('data-activity-form="cycle"', false)
            ->assertDontSee('id="dashboard-activity-form"', false)
            ->assertSee('Submission counts')
            ->assertViewHas('activityTrends', function (array $trends): bool {
                $this->assertSame(['daily', 'weekly', 'monthly'], array_keys($trends));
                foreach ($trends as $trend) {
                    $this->assertSame(['ezycash', 'ezyconvert'], array_column($trend['forms'], 'code'));
                    $this->assertNotEmpty($trend['labels']);
                }

                return true;
            });
    }

    public function test_historical_activity_periods_are_independent_and_invalid_periods_fall_back(): void
    {
        $this->seed(CampaignSeeder::class);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
            ->withSession(['campaign' => 'mbsales']);

        $this->travelTo(Carbon::parse('2024-03-06 13:00:00'), function (): void {
            $this->get(route('dashboard', [
                'activity_week' => '2024-02-28',
                'activity_month' => '2024-02',
                'sales_date' => '2024-03-05',
                'sales_start' => '07:00',
                'sales_end' => '09:00',
            ]))->assertOk()
                ->assertViewHas('activityQuery', ['activity_week' => '2024-02-26', 'activity_month' => '2024-02'])
                ->assertSee('sales_date=2024-03-05')
                ->assertSee('Feb 26 – Mar 3, 2024')
                ->assertSee('February 2024')
                ->assertViewHas('activityTrends', function (array $trends): bool {
                    $this->assertCount(24, $trends['daily']['labels']);
                    $this->assertCount(7, $trends['weekly']['labels']);
                    $this->assertCount(29, $trends['monthly']['labels']);

                    return true;
                });

            $this->get(route('dashboard', ['activity_week' => ['2024-02-28'], 'activity_month' => '2024-04']))
                ->assertOk()
                ->assertViewHas('activityQuery', [])
                ->assertSee('Mar 4 – Mar 10, 2024');

            $this->get(route('dashboard', ['activity_week' => '2024-02-30', 'activity_month' => '2024-02']))
                ->assertOk()
                ->assertViewHas('activityQuery', ['activity_month' => '2024-02']);
        });
    }

    public function test_submission_trends_handle_absent_forms_and_respect_activity_visibility(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
            ->withSession(['campaign' => 'mbsales']);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('No forms configured')
            ->assertDontSee('id="dashboard-activity-form"', false)
            ->assertViewHas('activityTrends', []);

        $this->seed(CampaignSeeder::class);
        $service = app(DashboardLayoutService::class);
        $sections = array_keys($service::sectionDefinitions());
        $service->saveForCampaign('mbsales', $sections, array_diff($sections, ['activity']));

        $this->get(route('dashboard'))->assertOk()
            ->assertDontSee('data-dashboard-section="activity"', false)
            ->assertViewHas('activityTrends', []);
    }
}
