<?php

namespace Tests\Feature\Admin;

use App\Events\DashboardLayoutUpdated;
use App\Models\Campaign;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminDashboardLayoutTest extends TestCase
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

    public function test_admin_can_apply_a_campaign_dashboard_layout(): void
    {
        Event::fake([DashboardLayoutUpdated::class]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->post(route('admin.dashboard-layout.update'), [
                'section_order' => ['forms', 'welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'quick_links'],
                'visible_sections' => ['forms', 'welcome'],
            ]);

        $response->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Dashboard layout applied.');
        $this->assertDatabaseHas('dashboard_layouts', ['campaign_code' => 'mbsales']);
        Event::assertDispatched(DashboardLayoutUpdated::class);
    }

    public function test_team_leader_cannot_apply_a_dashboard_layout(): void
    {
        $teamLeader = User::factory()->create(['role' => User::ROLE_TEAM_LEADER]);

        $this->actingAs($teamLeader)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->post(route('admin.dashboard-layout.update'), [
                'section_order' => ['welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'forms', 'quick_links'],
                'visible_sections' => ['welcome'],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('dashboard_layouts', ['campaign_code' => 'mbsales']);
    }

    public function test_user_dashboard_renders_saved_layout_for_active_campaign(): void
    {
        app(\App\Services\DashboardLayoutService::class)->saveForCampaign(
            'mbsales',
            ['forms', 'welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'quick_links'],
            ['forms', 'welcome'],
        );
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-dashboard-section="forms"', false)
            ->assertDontSee('data-dashboard-section="activity"', false);
    }

    public function test_admin_can_select_a_campaign_without_changing_the_active_campaign_session(): void
    {
        Campaign::factory()->create([
            'code' => 'pjli',
            'name' => 'PJLI',
            'display_order' => 1,
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.dashboard', ['campaign' => 'pjli']));

        $response->assertOk()
            ->assertSee('Campaign: <span class="font-semibold text-[var(--color-primary)]">PJLI</span>', false);
        $this->assertSame('mbsales', session('campaign'));
    }

    public function test_admin_can_save_custom_sales_rules_for_the_selected_campaign(): void
    {
        Event::fake([DashboardLayoutUpdated::class]);
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'display_order' => 1,
            'is_active' => true,
        ]);
        FormField::query()->insert([
            [
                'campaign_code' => 'mbsales',
                'form_type' => 'ezycash',
                'field_name' => 'amenable',
                'field_label' => 'Amenable',
                'field_type' => 'text',
                'is_required' => false,
                'field_order' => 1,
            ],
            [
                'campaign_code' => 'mbsales',
                'form_type' => 'ezycash',
                'field_name' => 'ezycash_amount',
                'field_label' => 'EzyCash Amount',
                'field_type' => 'number',
                'is_required' => false,
                'field_order' => 2,
            ],
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->post(route('admin.dashboard-layout.update'), [
                'campaign_code' => 'mbsales',
                'section_order' => ['welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'forms', 'quick_links'],
                'visible_sections' => ['welcome', 'kpis'],
                'sales_mode' => 'custom',
                'sales_forms' => [[
                    'form_code' => 'ezycash',
                    'amount_field' => 'ezycash_amount',
                    'trigger' => 'tag',
                    'conditions' => [[
                        'field_name' => 'amenable',
                        'accepted_values' => ['Yes', 'Approved'],
                    ]],
                ]],
            ]);

        $response->assertRedirect(route('admin.dashboard', ['campaign' => 'mbsales']))
            ->assertSessionHas('success', 'Dashboard layout applied.');
        $this->assertSame('mbsales', session('campaign'));
        $this->assertSame(
            'custom',
            data_get(\App\Models\DashboardLayout::query()->where('campaign_code', 'mbsales')->first()->layout, 'sales.mode'),
        );
        Event::assertDispatched(DashboardLayoutUpdated::class);
    }

    public function test_custom_sales_rules_require_a_complete_tag_condition(): void
    {
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'display_order' => 1,
            'is_active' => true,
        ]);
        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'amount',
            'field_label' => 'Amount',
            'field_type' => 'number',
            'is_required' => false,
            'field_order' => 1,
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->post(route('admin.dashboard-layout.update'), [
                'campaign_code' => 'mbsales',
                'section_order' => array_keys(
                    \App\Services\DashboardLayoutService::sectionDefinitions(),
                ),
                'visible_sections' => ['welcome'],
                'sales_mode' => 'custom',
                'sales_forms' => [[
                    'form_code' => 'ezycash',
                    'amount_field' => 'amount',
                    'conditions' => [[
                        'field_name' => 'amount',
                        'accepted_values' => ['Yes'],
                    ]],
                ]],
            ])
            ->assertSessionHasErrors('sales_forms.0.conditions.0.field_name');
    }

    public function test_admin_can_save_a_marker_only_custom_sales_rule(): void
    {
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'display_order' => 1,
            'is_active' => true,
        ]);
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
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->post(route('admin.dashboard-layout.update'), [
                'campaign_code' => 'mbsales',
                'section_order' => array_keys(
                    \App\Services\DashboardLayoutService::sectionDefinitions(),
                ),
                'visible_sections' => ['welcome'],
                'sales_mode' => 'custom',
                'sales_forms' => [[
                    'form_code' => 'ezycash',
                    'amount_field' => 'ezycash_amount',
                    'trigger' => 'marked_amount',
                    'conditions' => [],
                ]],
            ])
            ->assertRedirect(route('admin.dashboard', ['campaign' => 'mbsales']))
            ->assertSessionHasNoErrors();

        $layout = \App\Models\DashboardLayout::query()
            ->where('campaign_code', 'mbsales')
            ->firstOrFail()
            ->layout;
        $this->assertSame([], data_get($layout, 'sales.forms.0.conditions'));
    }

    public function test_admin_can_save_a_form_submission_rule_without_a_tag_or_amount(): void
    {
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'display_order' => 1,
            'is_active' => true,
        ]);
        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'customer_name',
            'field_label' => 'Customer name',
            'field_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->post(route('admin.dashboard-layout.update'), [
                'campaign_code' => 'mbsales',
                'section_order' => array_keys(
                    \App\Services\DashboardLayoutService::sectionDefinitions(),
                ),
                'visible_sections' => ['welcome'],
                'sales_mode' => 'custom',
                'sales_forms' => [[
                    'form_code' => 'ezycash',
                    'amount_field' => '',
                    'trigger' => 'form',
                ]],
            ])
            ->assertRedirect(route('admin.dashboard', ['campaign' => 'mbsales']))
            ->assertSessionHasNoErrors();

        $layout = \App\Models\DashboardLayout::query()
            ->where('campaign_code', 'mbsales')
            ->firstOrFail()
            ->layout;
        $this->assertSame('form', data_get($layout, 'sales.forms.0.trigger'));
        $this->assertNull(data_get($layout, 'sales.forms.0.amount_field'));
    }

    public function test_request_rejects_an_unknown_sales_trigger(): void
    {
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'display_order' => 1,
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->post(route('admin.dashboard-layout.update'), [
                'campaign_code' => 'mbsales',
                'section_order' => array_keys(
                    \App\Services\DashboardLayoutService::sectionDefinitions(),
                ),
                'visible_sections' => ['welcome'],
                'sales_mode' => 'custom',
                'sales_forms' => [[
                    'form_code' => 'ezycash',
                    'amount_field' => '',
                    'trigger' => 'unknown',
                ]],
            ])
            ->assertSessionHasErrors('sales_forms.0.trigger');
    }

    public function test_admin_can_reset_custom_sales_rules_to_legacy_mode(): void
    {
        app(\App\Services\DashboardLayoutService::class)->saveForCampaign(
            'mbsales',
            array_keys(\App\Services\DashboardLayoutService::sectionDefinitions()),
            ['welcome'],
            ['mode' => 'custom', 'forms' => []],
            true,
        );
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'mbsales'])
            ->post(route('admin.dashboard-layout.update'), [
                'campaign_code' => 'mbsales',
                'section_order' => array_keys(\App\Services\DashboardLayoutService::sectionDefinitions()),
                'visible_sections' => ['welcome'],
                'sales_mode' => 'legacy',
            ])
            ->assertRedirect(route('admin.dashboard', ['campaign' => 'mbsales']));

        $layout = \App\Models\DashboardLayout::query()->where('campaign_code', 'mbsales')->firstOrFail()->layout;
        $this->assertArrayNotHasKey('sales', $layout);
    }
}
