<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\DataRetentionPolicy;
use App\Models\Form;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataRetentionAdminTest extends TestCase
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

    public function test_super_admin_can_view_retention_configuration(): void
    {
        $form = $this->createForm();
        DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'cutoff_date' => '2026-01-31',
        ]);
        app(CampaignService::class)->clearCampaignsCache();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.configuration', [
                'tab' => 'retention',
                'retention_form' => $form->id,
            ]));

        $response->assertOk();
        $response->assertSee('Data Retention', false);
        $response->assertSee('permanently deletes complete records', false);
        $response->assertSee('ezycash', false);
        $response->assertSee('2026-01-31', false);
    }

    public function test_non_super_admin_cannot_view_retention_configuration(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('admin.configuration', ['tab' => 'retention']));

        $response->assertForbidden();
    }

    public function test_policy_requires_an_active_form_and_valid_cutoff_date(): void
    {
        $inactiveForm = $this->createForm(['is_active' => false]);

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $inactiveForm->id,
                'cutoff_date' => '31-01-2026',
            ])
            ->assertSessionHasErrors(['form_id', 'cutoff_date']);

        $this->assertDatabaseCount('data_retention_policies', 0);
    }

    public function test_super_admin_can_create_update_and_deactivate_a_policy(): void
    {
        $form = $this->createForm();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'cutoff_date' => '2026-01-31',
            ])
            ->assertRedirect(route('admin.configuration', [
                'tab' => 'retention',
                'retention_form' => $form->id,
            ]))
            ->assertSessionHas('status');

        $policy = DataRetentionPolicy::query()->firstOrFail();
        $this->assertTrue($policy->is_active);
        $this->assertSame('2026-01-31', $policy->cutoff_date->format('Y-m-d'));

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'cutoff_date' => '2026-02-28',
                'is_active' => true,
            ])
            ->assertRedirect();

        $policy->refresh();
        $this->assertSame('2026-02-28', $policy->cutoff_date->format('Y-m-d'));
        $this->assertSame(1, DataRetentionPolicy::query()->count());

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.deactivate', $policy))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse($policy->fresh()->is_active);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createForm(array $overrides = []): Form
    {
        return Form::query()->create(array_merge([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'is_active' => true,
        ], $overrides));
    }
}
