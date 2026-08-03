<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\DataRetentionPolicy;
use App\Models\Form;
use App\Models\FormField;
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
        $otherForm = $this->createForm([
            'form_code' => 'ezyconvert',
            'name' => 'EzyConvert',
            'table_name' => 'ezyconvert',
        ]);
        $this->createField($form, 'cardholder_name');
        $this->createField($otherForm, 'rate');
        DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'cutoff_date' => '2026-01-31',
            'deletion_mode' => 'selected_fields',
            'selected_fields' => ['cardholder_name'],
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
        $response->assertSee('Delete entire records', false);
        $response->assertSee('Clear selected fields only', false);
        $response->assertSee('Cardholder name', false);
        $response->assertDontSee('Rate', false);
        $response->assertSee('Selected fields', false);
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
                'deletion_mode' => 'whole_record',
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
                'deletion_mode' => 'whole_record',
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

    public function test_super_admin_can_create_selected_field_policy_and_clear_selection_when_switching_mode(): void
    {
        $form = $this->createForm();
        $this->createField($form, 'cardholder_name');
        $this->createField($form, 'account_number');

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'cutoff_date' => '2026-01-31',
                'deletion_mode' => 'selected_fields',
                'selected_fields' => ['cardholder_name', 'account_number'],
            ])
            ->assertRedirect();

        $policy = DataRetentionPolicy::query()->firstOrFail();
        $this->assertSame('selected_fields', $policy->deletion_mode);
        $this->assertSame(['cardholder_name', 'account_number'], $policy->selected_fields);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'cutoff_date' => '2026-02-28',
                'deletion_mode' => 'whole_record',
            ])
            ->assertRedirect();

        $policy->refresh();
        $this->assertSame('whole_record', $policy->deletion_mode);
        $this->assertNull($policy->selected_fields);
    }

    public function test_selected_field_policy_requires_eligible_fields_for_the_selected_form(): void
    {
        $form = $this->createForm();
        $otherForm = $this->createForm([
            'form_code' => 'ezyconvert',
            'name' => 'EzyConvert',
            'table_name' => 'ezyconvert',
        ]);
        $this->createField($form, 'cardholder_name');
        $this->createField($otherForm, 'rate');

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'cutoff_date' => '2026-01-31',
                'deletion_mode' => 'selected_fields',
                'selected_fields' => [],
            ])
            ->assertSessionHasErrors('selected_fields');

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'cutoff_date' => '2026-01-31',
                'deletion_mode' => 'selected_fields',
                'selected_fields' => ['rate'],
            ])
            ->assertSessionHasErrors('selected_fields');

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'cutoff_date' => '2026-01-31',
                'deletion_mode' => 'selected_fields',
                'selected_fields' => ['date'],
            ])
            ->assertSessionHasErrors('selected_fields');

        $this->assertDatabaseCount('data_retention_policies', 0);
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

    private function createField(Form $form, string $fieldName): FormField
    {
        return FormField::query()->create([
            'campaign_code' => $form->campaign_code,
            'form_type' => $form->form_code,
            'field_name' => $fieldName,
            'field_label' => str_replace('_', ' ', ucfirst($fieldName)),
            'field_type' => 'text',
            'field_order' => 1,
        ]);
    }
}
