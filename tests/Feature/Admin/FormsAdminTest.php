<?php

namespace Tests\Feature\Admin;

use App\Events\DashboardLayoutUpdated;
use App\Models\Campaign;
use App\Models\DashboardLayout;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\DashboardLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FormsAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
            'color' => '#3b82f6',
        ]);
    }

    public function test_forms_index_uses_shared_table_component_and_modal_sales_attribution(): void
    {
        $form = $this->createCampaignForm();
        app(CampaignService::class)->clearCampaignsCache();

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.forms.index', ['campaign' => 'mbsales']));

        $response->assertOk()
            ->assertSee('md-table-wrap', false)
            ->assertSee('Campaign forms', false)
            ->assertSee('ezycash', false)
            ->assertSee(route('admin.field-logic.index', ['form' => 'ezycash']), false)
            ->assertSee('Sales Attribution', false)
            ->assertSee('Default markers', false)
            ->assertSee('adminSalesAttributionEditor({', false)
            ->assertSee('aria-labelledby="modal-title-edit-form-'.$form->id.'"', false)
            ->assertSee('name="sales_rule[enabled]"', false)
            ->assertSee('name="sales_rule[form_code]"', false)
            ->assertDontSee('inline-edit-row', false)
            ->assertDontSee('editOpen: true', false)
            ->assertDontSee('<<<<<<<', false);
    }

    public function test_forms_validation_errors_reopen_edit_modal_for_edited_form(): void
    {
        $form = $this->createCampaignForm();

        $this->actingAs($this->superAdmin)
            ->from(route('admin.forms.index', ['campaign' => 'mbsales']))
            ->put(route('admin.forms.update', $form), [
                '_editing' => $form->id,
                'campaign_code' => 'mbsales',
                'form_code' => 'ezycash',
                'name' => '',
                'table_name' => 'ezycash',
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->superAdmin)
            ->get(route('admin.forms.index', ['campaign' => 'mbsales']))
            ->assertOk()
            ->assertSee('modal-title-edit-form-'.$form->id, false)
            ->assertSee('$nextTick(() => $store.modal.show(\'edit-form-'.$form->id.'\'))', false)
            ->assertSee('name="_editing"', false)
            ->assertSee('value="'.$form->id.'"', false)
            ->assertDontSee('inline-edit-row', false);
    }

    public function test_super_admin_can_save_custom_tag_sales_attribution_from_form_edit(): void
    {
        Event::fake([DashboardLayoutUpdated::class]);
        $form = $this->createCampaignForm();
        $this->createField('amenable', 'text');
        $this->createField('ezycash_amount', 'number');

        $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->put(route('admin.forms.update', $form), $this->updatePayload($form, [
                'enabled' => '1',
                'amount_field' => 'ezycash_amount',
                'trigger' => 'tag',
                'conditions' => [[
                    'field_name' => 'amenable',
                    'accepted_values' => ['Yes', 'Approved'],
                ]],
            ]))
            ->assertRedirect(route('admin.forms.index', ['campaign' => 'mbsales']))
            ->assertSessionHas('success', 'Form updated.')
            ->assertSessionHasNoErrors();

        $this->assertSame('mbsales', session('campaign'));
        $layout = DashboardLayout::query()->where('campaign_code', 'mbsales')->firstOrFail()->layout;
        $this->assertSame('custom', data_get($layout, 'sales.mode'));
        $this->assertSame('ezycash', data_get($layout, 'sales.forms.0.form_code'));
        $this->assertSame('tag', data_get($layout, 'sales.forms.0.trigger'));
        $this->assertSame(['yes', 'approved'], data_get($layout, 'sales.forms.0.conditions.0.accepted_values'));
        Event::assertDispatched(DashboardLayoutUpdated::class);
    }

    public function test_custom_tag_attribution_rejects_a_non_tag_condition(): void
    {
        $form = $this->createCampaignForm();
        $this->createField('ezycash_amount', 'number');

        $this->actingAs($this->superAdmin)
            ->from(route('admin.forms.index', ['campaign' => 'mbsales']))
            ->put(route('admin.forms.update', $form), $this->updatePayload($form, [
                'enabled' => '1',
                'amount_field' => 'ezycash_amount',
                'trigger' => 'tag',
                'conditions' => [[
                    'field_name' => 'ezycash_amount',
                    'accepted_values' => ['Yes'],
                ]],
            ]))
            ->assertSessionHasErrors('sales_rule.conditions.0.field_name');

        $this->assertDatabaseMissing('dashboard_layouts', ['campaign_code' => 'mbsales']);
    }

    public function test_super_admin_can_save_marked_amount_sales_attribution(): void
    {
        $form = $this->createCampaignForm();
        $this->createField('ezycash_amount', 'number', true);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.forms.update', $form), $this->updatePayload($form, [
                'enabled' => '1',
                'amount_field' => 'ezycash_amount',
                'trigger' => 'marked_amount',
            ]))
            ->assertRedirect(route('admin.forms.index', ['campaign' => 'mbsales']))
            ->assertSessionHasNoErrors();

        $layout = DashboardLayout::query()->where('campaign_code', 'mbsales')->firstOrFail()->layout;
        $this->assertSame('custom', data_get($layout, 'sales.mode'));
        $this->assertSame('marked_amount', data_get($layout, 'sales.forms.0.trigger'));
        $this->assertSame('ezycash_amount', data_get($layout, 'sales.forms.0.amount_field'));
        $this->assertSame([], data_get($layout, 'sales.forms.0.conditions'));
    }

    public function test_super_admin_can_count_every_form_submission_without_an_amount(): void
    {
        $form = $this->createCampaignForm();
        $this->createField('amenable', 'text');

        $this->actingAs($this->superAdmin)
            ->put(route('admin.forms.update', $form), $this->updatePayload($form, [
                'enabled' => '1',
                'amount_field' => '',
                'trigger' => 'form',
            ]))
            ->assertRedirect(route('admin.forms.index', ['campaign' => 'mbsales']))
            ->assertSessionHasNoErrors();

        $layout = DashboardLayout::query()->where('campaign_code', 'mbsales')->firstOrFail()->layout;
        $this->assertSame('form', data_get($layout, 'sales.forms.0.trigger'));
        $this->assertNull(data_get($layout, 'sales.forms.0.amount_field'));
    }

    public function test_form_sales_attribution_rejects_an_unknown_trigger(): void
    {
        $form = $this->createCampaignForm();

        $this->actingAs($this->superAdmin)
            ->put(route('admin.forms.update', $form), $this->updatePayload($form, [
                'enabled' => '1',
                'amount_field' => '',
                'trigger' => 'unknown',
            ]))
            ->assertSessionHasErrors('sales_rule.trigger');

        $this->assertDatabaseMissing('dashboard_layouts', ['campaign_code' => 'mbsales']);
    }

    public function test_disabling_the_last_form_sales_rule_restores_legacy_mode(): void
    {
        $form = $this->createCampaignForm();
        app(DashboardLayoutService::class)->saveForCampaign(
            'mbsales',
            array_keys(DashboardLayoutService::sectionDefinitions()),
            ['welcome'],
            ['mode' => 'custom', 'forms' => [[
                'form_code' => 'ezycash',
                'amount_field' => null,
                'trigger' => 'form',
                'conditions' => [],
            ]]],
            true,
        );

        $this->actingAs($this->superAdmin)
            ->put(route('admin.forms.update', $form), $this->updatePayload($form, [
                'enabled' => '0',
            ]))
            ->assertRedirect(route('admin.forms.index', ['campaign' => 'mbsales']))
            ->assertSessionHasNoErrors();

        $layout = DashboardLayout::query()->where('campaign_code', 'mbsales')->firstOrFail()->layout;
        $this->assertArrayNotHasKey('sales', $layout);
    }

    public function test_form_sales_save_preserves_dashboard_sections_and_amounts_and_renders_saved_rule(): void
    {
        $form = $this->createCampaignForm();
        $this->createField('amenable', 'text');
        $this->createField('ezycash_amount', 'number');

        $sections = ['forms', 'welcome', 'kpis', 'activity', 'leaderboard', 'campaign_report', 'quick_links'];
        $layoutService = app(DashboardLayoutService::class);
        $before = $layoutService->saveForCampaign(
            'mbsales',
            $sections,
            ['forms', 'welcome'],
            amountConfig: ['enabled' => false, 'change' => false, 'tables' => false],
        );

        $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->put(route('admin.forms.update', $form), $this->updatePayload($form, [
                'enabled' => '1',
                'amount_field' => 'ezycash_amount',
                'trigger' => 'tag',
                'conditions' => [[
                    'field_name' => 'amenable',
                    'accepted_values' => ['Yes'],
                ]],
            ]))
            ->assertRedirect(route('admin.forms.index', ['campaign' => 'mbsales']))
            ->assertSessionHasNoErrors();

        $saved = DashboardLayout::query()->where('campaign_code', 'mbsales')->firstOrFail()->layout;
        $this->assertSame($before['sections'], $saved['sections']);
        $this->assertSame($before['amounts'], $saved['amounts']);
        $this->assertSame('custom', data_get($saved, 'sales.mode'));

        $this->get(route('admin.forms.index', ['campaign' => 'mbsales']))
            ->assertOk()
            ->assertViewHas('salesMode', 'custom')
            ->assertViewHas('salesRulesByForm', static function (array $rules): bool {
                return data_get($rules, 'ezycash.trigger') === 'tag'
                    && data_get($rules, 'ezycash.amount_field') === 'ezycash_amount'
                    && data_get($rules, 'ezycash.conditions.0.accepted_values') === ['yes'];
            })
            ->assertSee('Sales Attribution', false)
            ->assertSee('Tag match', false)
            ->assertSee('adminSalesAttributionEditor({', false)
            ->assertSee('aria-labelledby="modal-title-edit-form-'.$form->id.'"', false);
    }

    private function createCampaignForm(): Form
    {
        return Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createField(string $name, string $type, bool $isSaleAmount = false): void
    {
        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => $name,
            'field_label' => ucwords(str_replace('_', ' ', $name)),
            'field_type' => $type,
            'is_required' => false,
            'is_sale_amount' => $isSaleAmount,
            'field_order' => 1,
        ]);
    }

    /** @param array<string, mixed> $salesRule */
    private function updatePayload(Form $form, array $salesRule): array
    {
        return [
            '_editing' => $form->id,
            'campaign_code' => $form->campaign_code,
            'form_code' => $form->form_code,
            'name' => $form->name,
            'table_name' => $form->table_name,
            'is_active' => '1',
            'sales_rule' => ['form_code' => $form->form_code] + $salesRule,
        ];
    }
}
