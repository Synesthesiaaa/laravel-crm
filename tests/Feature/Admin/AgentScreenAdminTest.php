<?php

namespace Tests\Feature\Admin;

use App\Models\AgentScreenField;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentScreenAdminTest extends TestCase
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

    private function campaignSession(): array
    {
        return ['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'];
    }

    public function test_store_rejects_invalid_direction_and_field_type(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.agent-screen.store'), [
                'campaign_code' => 'mbsales',
                'field_key' => 'customer_email',
                'field_label' => 'Customer Email',
                'field_type' => 'invalid_type',
                'direction' => 'invalid_direction',
                'field_width' => 'full',
            ])
            ->assertSessionHasErrors(['field_type', 'direction']);
    }

    public function test_update_persists_capture_field_configuration(): void
    {
        $field = AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'customer_email',
            'field_label' => 'Customer Email',
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->put(route('admin.agent-screen.update', $field), [
                'field_key' => 'customer_email',
                'field_label' => 'Primary Email',
                'vici_field' => 'email',
                'direction' => 'both',
                'field_type' => 'select',
                'options' => "Valid\nInvalid",
                'placeholder' => 'Select status',
                'is_required' => '1',
                'field_order' => 9,
                'field_width' => 'half',
            ])
            ->assertRedirect(route('admin.agent-screen.index', ['campaign' => 'mbsales']));

        $field->refresh();

        $this->assertSame('Primary Email', $field->field_label);
        $this->assertSame('email', $field->vici_field);
        $this->assertSame('both', $field->direction);
        $this->assertSame('select', $field->field_type);
        $this->assertSame(['Valid', 'Invalid'], $field->options);
        $this->assertSame('Select status', $field->placeholder);
        $this->assertTrue((bool) $field->is_required);
        $this->assertSame(9, (int) $field->field_order);
        $this->assertSame('half', $field->field_width);
    }

    public function test_destroy_soft_deletes_capture_field(): void
    {
        $field = AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'customer_phone',
            'field_label' => 'Customer Phone',
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.agent-screen.destroy'), ['id' => $field->id])
            ->assertRedirect(route('admin.agent-screen.index', ['campaign' => 'mbsales']));

        $this->assertSoftDeleted('agent_screen_fields', ['id' => $field->id]);
    }
}
