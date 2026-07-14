<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldLogicAdminTest extends TestCase
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

    public function test_store_accepts_percentage_with_visibility_configuration(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.field-logic.store'), [
                'campaign_code' => 'mbsales',
                'form_type' => 'lead_capture',
                'field_name' => 'discount_rate',
                'field_label' => 'Discount Rate',
                'field_type' => 'percentage',
                'field_width' => 'full',
                'visibility' => [
                    'field' => 'customer_tier',
                    'operator' => 'in',
                    'values' => ['gold, platinum'],
                ],
            ])
            ->assertRedirect(route('admin.field-logic.index', ['form' => 'lead_capture']))
            ->assertSessionHasNoErrors();

        $field = FormField::query()
            ->where('campaign_code', 'mbsales')
            ->where('form_type', 'lead_capture')
            ->where('field_name', 'discount_rate')
            ->firstOrFail();

        $this->assertSame('percentage', $field->field_type);
        $this->assertSame([
            'field' => 'customer_tier',
            'operator' => 'in',
            'values' => ['gold', 'platinum'],
        ], $field->visibility);
    }

    public function test_store_marks_numeric_field_as_sale_amount(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.field-logic.store'), [
                'campaign_code' => 'mbsales',
                'form_type' => 'lead_capture',
                'field_name' => 'sale_amount',
                'field_label' => 'Sale Amount',
                'field_type' => 'number',
                'field_width' => 'full',
                'is_sale_amount' => '1',
            ])
            ->assertRedirect(route('admin.field-logic.index', ['form' => 'lead_capture']))
            ->assertSessionHasNoErrors();

        $field = FormField::query()
            ->where('campaign_code', 'mbsales')
            ->where('form_type', 'lead_capture')
            ->where('field_name', 'sale_amount')
            ->firstOrFail();

        $this->assertTrue($field->is_sale_amount);
    }

    public function test_non_numeric_field_cannot_be_marked_as_sale_amount(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.field-logic.store'), [
                'campaign_code' => 'mbsales',
                'form_type' => 'lead_capture',
                'field_name' => 'sale_notes',
                'field_label' => 'Sale Notes',
                'field_type' => 'textarea',
                'field_width' => 'full',
                'is_sale_amount' => '1',
            ])
            ->assertRedirect(route('admin.field-logic.index', ['form' => 'lead_capture']))
            ->assertSessionHasNoErrors();

        $field = FormField::query()
            ->where('campaign_code', 'mbsales')
            ->where('form_type', 'lead_capture')
            ->where('field_name', 'sale_notes')
            ->firstOrFail();

        $this->assertFalse($field->is_sale_amount);
    }

    public function test_store_rejects_invalid_visibility_operator(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.field-logic.store'), [
                'campaign_code' => 'mbsales',
                'form_type' => 'lead_capture',
                'field_name' => 'commission_rate',
                'field_label' => 'Commission Rate',
                'field_type' => 'percentage',
                'field_width' => 'half',
                'visibility' => [
                    'field' => 'result_code',
                    'operator' => 'contains',
                    'values' => ['SALE'],
                ],
            ])
            ->assertSessionHasErrors(['visibility.operator']);
    }

    public function test_field_logic_edit_page_renders_instead_of_modal(): void
    {
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'lead_capture',
            'name' => 'Lead Capture',
            'table_name' => 'lead_capture',
            'display_order' => 1,
            'is_active' => true,
        ]);
        app(CampaignService::class)->clearCampaignsCache();

        $field = FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'lead_capture',
            'field_name' => 'customer_tier',
            'field_label' => 'Customer Tier',
            'field_type' => 'text',
            'field_order' => 1,
            'field_width' => 'full',
            'is_required' => false,
        ]);

        $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.field-logic.edit', $field))
            ->assertOk()
            ->assertSee('Edit field', false)
            ->assertSee('Update field', false)
            ->assertSee('customer_tier', false);

        $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.field-logic.index', ['form' => 'lead_capture']))
            ->assertOk()
            ->assertSee('field-logic/'.$field->id.'/edit', false)
            ->assertDontSee('edit-field-logic', false);
    }

    public function test_update_persists_sale_amount_for_numeric_fields_and_clears_it_for_text_fields(): void
    {
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'lead_capture',
            'name' => 'Lead Capture',
            'table_name' => 'lead_capture',
            'display_order' => 1,
            'is_active' => true,
        ]);
        app(CampaignService::class)->clearCampaignsCache();

        $field = FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'lead_capture',
            'field_name' => 'sale_amount',
            'field_label' => 'Sale Amount',
            'field_type' => 'number',
            'field_order' => 1,
            'field_width' => 'full',
            'is_required' => false,
            'is_sale_amount' => false,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.field-logic.edit', $field))
            ->assertOk()
            ->assertSee('Is sale amount', false);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.field-logic.update', $field->id), [
                'field_name' => 'sale_amount',
                'field_label' => 'Sale Amount',
                'field_type' => 'number',
                'field_width' => 'full',
                'field_order' => 1,
                'is_sale_amount' => '1',
            ])
            ->assertRedirect(route('admin.field-logic.index', ['form' => 'lead_capture']))
            ->assertSessionHasNoErrors();

        $this->assertTrue($field->fresh()->is_sale_amount);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.field-logic.update', $field->id), [
                'field_name' => 'sale_amount',
                'field_label' => 'Sale Amount',
                'field_type' => 'text',
                'field_width' => 'full',
                'field_order' => 1,
                'is_sale_amount' => '1',
            ])
            ->assertRedirect(route('admin.field-logic.index', ['form' => 'lead_capture']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($field->fresh()->is_sale_amount);
    }

    public function test_index_handles_campaign_with_no_forms(): void
    {
        Campaign::factory()->create([
            'code' => 'emptycamp',
            'name' => 'Empty Campaign',
            'color' => '#111827',
        ]);
        app(CampaignService::class)->clearCampaignsCache();

        $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'emptycamp', 'campaign_name' => 'Empty Campaign'])
            ->get(route('admin.field-logic.index'))
            ->assertOk()
            ->assertSee('No active forms are configured for this campaign.');
    }

    public function test_field_logic_update_from_edit_page_redirects_to_index(): void
    {
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'lead_capture',
            'name' => 'Lead Capture',
            'table_name' => 'lead_capture',
            'display_order' => 1,
            'is_active' => true,
        ]);
        app(CampaignService::class)->clearCampaignsCache();

        $field = FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'lead_capture',
            'field_name' => 'notes',
            'field_label' => 'Notes',
            'field_type' => 'textarea',
            'field_order' => 2,
            'field_width' => 'full',
            'is_required' => false,
        ]);

        $this->actingAs($this->superAdmin)
            ->from(route('admin.field-logic.edit', $field))
            ->put(route('admin.field-logic.update', $field->id), [
                'field_name' => 'notes',
                'field_label' => 'Call Notes',
                'field_type' => 'textarea',
                'field_width' => 'full',
                'field_order' => 2,
            ])
            ->assertRedirect(route('admin.field-logic.index', ['form' => 'lead_capture']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Call Notes', $field->fresh()->field_label);
    }

    public function test_update_to_percentage_with_empty_visibility_values_passes_validation(): void
    {
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'lead_capture',
            'name' => 'Lead Capture',
            'table_name' => 'lead_capture',
            'display_order' => 1,
            'is_active' => true,
        ]);
        app(CampaignService::class)->clearCampaignsCache();

        $field = FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'lead_capture',
            'field_name' => 'discount_rate',
            'field_label' => 'Discount Rate',
            'field_type' => 'number',
            'field_order' => 1,
            'field_width' => 'full',
            'is_required' => false,
            'visibility' => [
                'field' => 'customer_tier',
                'operator' => 'in',
                'values' => ['gold', 'platinum'],
            ],
        ]);

        $this->actingAs($this->superAdmin)
            ->from(route('admin.field-logic.edit', $field))
            ->put(route('admin.field-logic.update', $field->id), [
                'field_name' => 'discount_rate',
                'field_label' => 'Discount Rate',
                'field_type' => 'percentage',
                'field_width' => 'full',
                'field_order' => 1,
                'visibility' => [
                    'field' => 'customer_tier',
                    'operator' => 'in',
                    'values' => [''],
                ],
            ])
            ->assertRedirect(route('admin.field-logic.index', ['form' => 'lead_capture']))
            ->assertSessionHasNoErrors();

        $field->refresh();
        $this->assertSame('percentage', $field->field_type);
        $this->assertNull($field->visibility);
    }

    public function test_update_to_percentage_preserves_visibility_from_textarea(): void
    {
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'lead_capture',
            'name' => 'Lead Capture',
            'table_name' => 'lead_capture',
            'display_order' => 1,
            'is_active' => true,
        ]);
        app(CampaignService::class)->clearCampaignsCache();

        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'lead_capture',
            'field_name' => 'customer_tier',
            'field_label' => 'Customer Tier',
            'field_type' => 'select',
            'field_order' => 0,
            'field_width' => 'full',
            'is_required' => false,
            'options' => json_encode(['gold', 'platinum']),
        ]);

        $field = FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'lead_capture',
            'field_name' => 'discount_rate',
            'field_label' => 'Discount Rate',
            'field_type' => 'number',
            'field_order' => 1,
            'field_width' => 'full',
            'is_required' => false,
        ]);

        $this->actingAs($this->superAdmin)
            ->from(route('admin.field-logic.edit', $field))
            ->put(route('admin.field-logic.update', $field->id), [
                'field_name' => 'discount_rate',
                'field_label' => 'Discount Rate',
                'field_type' => 'percentage',
                'field_width' => 'full',
                'field_order' => 1,
                'visibility' => [
                    'field' => 'customer_tier',
                    'operator' => 'in',
                    'values' => ["gold\nplatinum"],
                ],
            ])
            ->assertRedirect(route('admin.field-logic.index', ['form' => 'lead_capture']))
            ->assertSessionHasNoErrors();

        $field->refresh();
        $this->assertSame('percentage', $field->field_type);
        $this->assertSame([
            'field' => 'customer_tier',
            'operator' => 'in',
            'values' => ['gold', 'platinum'],
        ], $field->visibility);
    }
}
