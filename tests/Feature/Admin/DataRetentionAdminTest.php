<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\DataRetentionPolicy;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    protected function tearDown(): void
    {
        Schema::dropIfExists('retention_admin_action_records');

        parent::tearDown();
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
            'to_date' => '2026-01-31',
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
        $response->assertSee('From date', false);
        $response->assertSee('To date', false);
        $response->assertSee('name="from_date"', false);
        $response->assertSee('name="to_date"', false);
        $response->assertSee('Cardholder name', false);
        $response->assertDontSee('Rate', false);
        $response->assertSee('Selected fields', false);
        $response->assertSee('ezycash', false);
        $response->assertSee('Any date', false);
        $response->assertSee('2026-01-31', false);
        $response->assertSee('Run Once', false);
        $response->assertSee('Recurring', false);
        $response->assertSee('Scheduled run date and time', false);
        $response->assertSee('Frequency', false);
        $response->assertSee('Next run', false);
        $response->assertSee('Run Now', false);
        $response->assertSee('Delete', false);
        $response->assertDontSee('Deactivate', false);
    }

    public function test_non_super_admin_cannot_view_retention_configuration(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('admin.configuration', ['tab' => 'retention']));

        $response->assertForbidden();
    }

    public function test_policy_requires_an_active_form_and_valid_date_range(): void
    {
        $inactiveForm = $this->createForm(['is_active' => false]);

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $inactiveForm->id,
                'from_date' => '31-01-2026',
                'to_date' => '2026-01-31',
            ])
            ->assertSessionHasErrors(['form_id', 'from_date']);

        $this->assertDatabaseCount('data_retention_policies', 0);
    }

    public function test_policy_rejects_a_reversed_date_range(): void
    {
        $form = $this->createForm();

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-02-01',
                'to_date' => '2026-01-31',
                'deletion_mode' => 'whole_record',
            ])
            ->assertSessionHasErrors('from_date');

        $this->assertDatabaseCount('data_retention_policies', 0);
    }

    public function test_policy_requires_execution_settings_for_the_selected_mode(): void
    {
        $form = $this->createForm();

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-01-01',
                'to_date' => '2026-01-31',
                'deletion_mode' => 'whole_record',
                'run_mode' => 'once',
            ])
            ->assertSessionHasErrors('run_at');

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-01-01',
                'to_date' => '2026-01-31',
                'deletion_mode' => 'whole_record',
                'run_mode' => 'recurring',
            ])
            ->assertSessionHasErrors(['recurrence', 'run_time']);
    }

    public function test_policy_rejects_invalid_schedule_values(): void
    {
        $form = $this->createForm();

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-01-01',
                'to_date' => '2026-01-31',
                'deletion_mode' => 'whole_record',
                'run_mode' => 'once',
                'run_at' => '04-08-2026 09:15',
            ])
            ->assertSessionHasErrors('run_at');

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-01-01',
                'to_date' => '2026-01-31',
                'deletion_mode' => 'whole_record',
                'run_mode' => 'recurring',
                'recurrence' => 'weekly',
                'run_time' => '09:15',
            ])
            ->assertSessionHasErrors('run_day_of_week');
    }

    public function test_super_admin_can_create_update_and_delete_a_policy(): void
    {
        $form = $this->createForm();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-01-01',
                'to_date' => '2026-01-31',
                'deletion_mode' => 'whole_record',
                'run_mode' => 'recurring',
                'recurrence' => 'daily',
                'run_time' => '03:00',
            ])
            ->assertRedirect(route('admin.configuration', [
                'tab' => 'retention',
                'retention_form' => $form->id,
            ]))
            ->assertSessionHas('status');

        $policy = DataRetentionPolicy::query()->firstOrFail();
        $this->assertTrue($policy->is_active);
        $this->assertSame('2026-01-01', $policy->from_date->format('Y-m-d'));
        $this->assertSame('2026-01-31', $policy->to_date->format('Y-m-d'));
        $this->assertSame('recurring', $policy->run_mode);
        $this->assertSame('daily', $policy->recurrence);
        $this->assertSame('03:00', substr((string) $policy->run_time, 0, 5));
        $this->assertNotNull($policy->next_run_at);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-02-01',
                'to_date' => '2026-02-28',
                'deletion_mode' => 'whole_record',
                'is_active' => true,
                'run_mode' => 'recurring',
                'recurrence' => 'daily',
                'run_time' => '03:00',
            ])
            ->assertRedirect();

        $policy->refresh();
        $this->assertSame('2026-02-01', $policy->from_date->format('Y-m-d'));
        $this->assertSame('2026-02-28', $policy->to_date->format('Y-m-d'));
        $this->assertSame('recurring', $policy->run_mode);
        $this->assertNotNull($policy->next_run_at);
        $this->assertSame(1, DataRetentionPolicy::query()->count());

        $this->actingAs($this->superAdmin)
            ->delete(route('admin.configuration.retention.destroy', $policy))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('data_retention_policies', ['id' => $policy->id]);
    }

    public function test_super_admin_can_save_a_policy_without_automatic_execution(): void
    {
        $form = $this->createForm();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-01-01',
                'to_date' => '2026-01-31',
                'deletion_mode' => 'whole_record',
                'is_active' => '0',
                'run_mode' => 'recurring',
                'recurrence' => 'daily',
                'run_time' => '03:00',
            ])
            ->assertRedirect();

        $policy = DataRetentionPolicy::query()->firstOrFail();
        $this->assertFalse($policy->is_active);
        $this->assertNull($policy->next_run_at);
    }

    public function test_super_admin_can_create_selected_field_policy_and_clear_selection_when_switching_mode(): void
    {
        $form = $this->createForm();
        $this->createField($form, 'cardholder_name');
        $this->createField($form, 'account_number');

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-01-01',
                'to_date' => '2026-01-31',
                'deletion_mode' => 'selected_fields',
                'selected_fields' => ['cardholder_name', 'account_number'],
                'run_mode' => 'recurring',
                'recurrence' => 'daily',
                'run_time' => '03:00',
            ])
            ->assertRedirect();

        $policy = DataRetentionPolicy::query()->firstOrFail();
        $this->assertSame('selected_fields', $policy->deletion_mode);
        $this->assertSame(['cardholder_name', 'account_number'], $policy->selected_fields);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-02-01',
                'to_date' => '2026-02-28',
                'deletion_mode' => 'whole_record',
                'run_mode' => 'recurring',
                'recurrence' => 'daily',
                'run_time' => '03:00',
            ])
            ->assertRedirect();

        $policy->refresh();
        $this->assertSame('whole_record', $policy->deletion_mode);
        $this->assertNull($policy->selected_fields);
    }

    public function test_super_admin_can_run_once_now_and_delete_only_policy_configuration(): void
    {
        Schema::create('retention_admin_action_records', function ($table): void {
            $table->id();
            $table->date('date');
            $table->string('request_id');
            $table->timestamps();
        });

        $form = $this->createForm([
            'form_code' => 'action_form',
            'table_name' => 'retention_admin_action_records',
        ]);
        $policy = DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'to_date' => '2026-01-31',
            'run_mode' => 'once',
            'run_at' => now()->addDay(),
            'next_run_at' => now()->addDay(),
        ]);
        DB::table('retention_admin_action_records')->insert([
            'date' => '2026-01-01',
            'request_id' => 'run-now-record',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.run', $policy))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse($policy->fresh()->is_active);
        $this->assertDatabaseMissing('retention_admin_action_records', ['request_id' => 'run-now-record']);

        $deleteForm = $this->createForm([
            'form_code' => 'delete_action_form',
            'table_name' => 'retention_admin_action_records',
        ]);
        $deletePolicy = DataRetentionPolicy::query()->create([
            'form_id' => $deleteForm->id,
            'to_date' => '2026-01-31',
        ]);
        DB::table('retention_admin_action_records')->insert([
            'date' => '2026-01-01',
            'request_id' => 'preserve-on-policy-delete',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->superAdmin)
            ->delete(route('admin.configuration.retention.destroy', $deletePolicy))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('data_retention_policies', ['id' => $deletePolicy->id]);
        $this->assertDatabaseHas('retention_admin_action_records', ['request_id' => 'preserve-on-policy-delete']);
    }

    public function test_super_admin_run_now_preserves_a_recurring_schedule(): void
    {
        Schema::create('retention_admin_action_records', function ($table): void {
            $table->id();
            $table->date('date');
            $table->string('request_id');
            $table->timestamps();
        });

        $form = $this->createForm([
            'form_code' => 'recurring_action_form',
            'table_name' => 'retention_admin_action_records',
        ]);
        $nextRunAt = now()->addDay();
        $policy = DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'to_date' => '2026-01-31',
            'run_mode' => 'recurring',
            'recurrence' => 'daily',
            'run_time' => '03:00',
            'next_run_at' => $nextRunAt,
        ]);
        DB::table('retention_admin_action_records')->insert([
            'date' => '2026-01-01',
            'request_id' => 'recurring-run-now-record',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.configuration.retention.run', $policy))
            ->assertRedirect();

        $freshPolicy = $policy->fresh();
        $this->assertTrue($freshPolicy->is_active);
        $this->assertSame($nextRunAt->format('Y-m-d H:i:s'), $freshPolicy->next_run_at->format('Y-m-d H:i:s'));
    }

    public function test_non_super_admin_cannot_run_or_delete_retention_policies(): void
    {
        $form = $this->createForm();
        $policy = DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'to_date' => '2026-01-31',
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.configuration.retention.run', $policy))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('admin.configuration.retention.destroy', $policy))
            ->assertForbidden();

        $this->assertDatabaseHas('data_retention_policies', ['id' => $policy->id]);
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
                'from_date' => '2026-01-01',
                'to_date' => '2026-01-31',
                'deletion_mode' => 'selected_fields',
                'selected_fields' => [],
                'run_mode' => 'recurring',
                'recurrence' => 'daily',
                'run_time' => '03:00',
            ])
            ->assertSessionHasErrors('selected_fields');

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-01-01',
                'to_date' => '2026-01-31',
                'deletion_mode' => 'selected_fields',
                'selected_fields' => ['rate'],
                'run_mode' => 'recurring',
                'recurrence' => 'daily',
                'run_time' => '03:00',
            ])
            ->assertSessionHasErrors('selected_fields');

        $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'retention']))
            ->post(route('admin.configuration.retention.store'), [
                'form_id' => $form->id,
                'from_date' => '2026-01-01',
                'to_date' => '2026-01-31',
                'deletion_mode' => 'selected_fields',
                'selected_fields' => ['date'],
                'run_mode' => 'recurring',
                'recurrence' => 'daily',
                'run_time' => '03:00',
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
