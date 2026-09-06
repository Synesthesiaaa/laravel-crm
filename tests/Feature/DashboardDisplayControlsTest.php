<?php

namespace Tests\Feature;

use App\Events\DashboardLayoutUpdated;
use App\Models\Campaign;
use App\Models\User;
use App\Services\DashboardLayoutService;
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
            ->assertDontSee('Total amount')
            ->assertDontSee('Amount change')
            ->assertDontSee('Total value:')
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
            ->get(route('dashboard'))->assertOk()->assertSee('Total amount')->assertDontSee('Amount change');
    }
}
