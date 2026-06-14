<?php

namespace Tests\Feature\Admin;

use App\Models\AgentScreenField;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_store_accepts_percentage_with_visibility_configuration(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.agent-screen.store'), [
                'campaign_code' => 'mbsales',
                'field_key' => 'interest_rate',
                'field_label' => 'Interest Rate',
                'field_type' => 'percentage',
                'direction' => 'none',
                'field_width' => 'full',
                'visibility' => [
                    'field' => 'customer_type',
                    'operator' => 'in',
                    'values' => ['vip, premium'],
                ],
            ])
            ->assertRedirect(route('admin.agent-screen.index', ['campaign' => 'mbsales']))
            ->assertSessionHasNoErrors();

        $field = AgentScreenField::query()
            ->where('campaign_code', 'mbsales')
            ->where('field_key', 'interest_rate')
            ->firstOrFail();

        $this->assertSame('percentage', $field->field_type);
        $this->assertSame([
            'field' => 'customer_type',
            'operator' => 'in',
            'values' => ['vip', 'premium'],
        ], $field->visibility);
    }

    public function test_campaign_cache_is_cleared_when_field_changes(): void
    {
        Cache::put('campaigns_with_forms', ['stale' => ['name' => 'Stale']], 300);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.agent-screen.store'), [
                'campaign_code' => 'mbsales',
                'field_key' => 'cache_probe',
                'field_label' => 'Cache Probe',
                'field_type' => 'text',
                'direction' => 'none',
                'field_width' => 'full',
            ])
            ->assertRedirect(route('admin.agent-screen.index', ['campaign' => 'mbsales']));

        $this->assertFalse(Cache::has('campaigns_with_forms'));
    }

    public function test_store_rejects_invalid_visibility_operator(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.agent-screen.store'), [
                'campaign_code' => 'mbsales',
                'field_key' => 'score_percent',
                'field_label' => 'Score Percent',
                'field_type' => 'percentage',
                'direction' => 'none',
                'field_width' => 'half',
                'visibility' => [
                    'field' => 'status',
                    'operator' => 'greater_than',
                    'values' => ['active'],
                ],
            ])
            ->assertSessionHasErrors(['visibility.operator']);
    }
}
