<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\FormField;
use App\Models\User;
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
}

