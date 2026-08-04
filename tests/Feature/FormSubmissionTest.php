<?php

namespace Tests\Feature;

use App\Events\DashboardDataUpdated;
use App\Models\Campaign;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Campaign::create(['code' => 'mbsales', 'name' => 'MBSales', 'description' => 'Test', 'display_order' => 0]);
        Form::create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'display_order' => 1,
        ]);
        $formFields = [
            ['field_name' => 'cardholder_name', 'field_label' => 'Cardholder Name', 'field_type' => 'text', 'is_required' => true, 'field_order' => 1],
            ['field_name' => 'mpi_credit_card_no', 'field_label' => 'Card No', 'field_type' => 'text', 'is_required' => true, 'field_order' => 2],
            ['field_name' => 'bank', 'field_label' => 'Bank', 'field_type' => 'text', 'is_required' => true, 'field_order' => 3],
            ['field_name' => 'account_type', 'field_label' => 'Account Type', 'field_type' => 'text', 'is_required' => true, 'field_order' => 4],
            ['field_name' => 'account_number', 'field_label' => 'Account Number', 'field_type' => 'text', 'is_required' => true, 'field_order' => 5],
            ['field_name' => 'surname', 'field_label' => 'Surname', 'field_type' => 'text', 'is_required' => true, 'field_order' => 6],
            ['field_name' => 'first_name', 'field_label' => 'First Name', 'field_type' => 'text', 'is_required' => true, 'field_order' => 7],
            ['field_name' => 'ezycash_amount', 'field_label' => 'Amount', 'field_type' => 'number', 'is_required' => true, 'field_order' => 8],
            ['field_name' => 'term', 'field_label' => 'Term', 'field_type' => 'text', 'is_required' => true, 'field_order' => 9],
            ['field_name' => 'rate', 'field_label' => 'Rate', 'field_type' => 'number', 'is_required' => true, 'field_order' => 10],
        ];
        foreach ($formFields as $f) {
            FormField::create(array_merge([
                'campaign_code' => 'mbsales',
                'form_type' => 'ezycash',
            ], $f));
        }
    }

    public function test_form_submit_succeeds_with_valid_data(): void
    {
        Carbon::setTestNow('2026-07-21 14:30:15');
        $user = User::factory()->create(['username' => 'agent1']);

        try {
            $response = $this->actingAs($user)->post(route('forms.store'), [
                '_token' => csrf_token(),
                'campaign' => 'mbsales',
                'form_type' => 'ezycash',
                'date' => now()->format('Y-m-d'),
                'request_id' => 'client-should-be-ignored',
                'cardholder_name' => 'John Doe',
                'mpi_credit_card_no' => '4111111111111111',
                'bank' => 'Test Bank',
                'account_type' => 'Savings',
                'account_number' => '123456',
                'surname' => 'Doe',
                'first_name' => 'John',
                'ezycash_amount' => '100.00',
                'term' => '12',
                'rate' => '5.00',
            ]);
            $response->assertRedirect();
            $response->assertSessionHas('success');
            $this->assertDatabaseHas('ezycash', [
                'agent' => $user->full_name ?? $user->username,
                'cardholder_name' => 'John Doe',
            ]);
            $requestId = DB::table('ezycash')->where('cardholder_name', 'John Doe')->value('request_id');
            $this->assertIsString($requestId);
            $this->assertNotSame('client-should-be-ignored', $requestId);
            $this->assertMatchesRegularExpression('/^20260721143015\d{6}$/', $requestId);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_form_submit_broadcasts_dashboard_update_after_successful_commit(): void
    {
        Event::fake([DashboardDataUpdated::class]);
        $user = User::factory()->create(['username' => 'agent1']);

        $response = $this->actingAs($user)->post(route('forms.store'), [
            'campaign' => 'mbsales',
            'form_type' => 'ezycash',
            'date' => now()->format('Y-m-d'),
            'cardholder_name' => 'Broadcasted Submission',
            'mpi_credit_card_no' => '4111111111111111',
            'bank' => 'Test Bank',
            'account_type' => 'Savings',
            'account_number' => '123456',
            'surname' => 'Doe',
            'first_name' => 'John',
            'ezycash_amount' => '100.00',
            'term' => '12',
            'rate' => '5.00',
        ]);

        $response->assertRedirect();
        Event::assertDispatched(DashboardDataUpdated::class, function (DashboardDataUpdated $event): bool {
            return $event->campaignCode === 'mbsales'
                && $event->formType === 'ezycash'
                && $event->action === 'submitted'
                && $event->recordId > 0;
        });
    }

    public function test_existing_request_ids_remain_unchanged_when_a_new_record_is_submitted(): void
    {
        DB::table('ezycash')->insert([
            'date' => '2026-07-20',
            'request_id' => 'legacy-request-id',
            'cardholder_name' => 'Legacy Customer',
            'mpi_credit_card_no' => '4111111111111111',
            'bank' => 'Legacy Bank',
            'account_type' => 'Savings',
            'account_number' => '123456',
            'surname' => 'Legacy',
            'first_name' => 'Customer',
            'ezycash_amount' => '100.00',
            'term' => '12',
            'rate' => '5.00',
            'agent' => 'legacy-agent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create(['username' => 'agent1']);

        $response = $this->actingAs($user)->post(route('forms.store'), [
            'campaign' => 'mbsales',
            'form_type' => 'ezycash',
            'date' => '2026-07-21',
            'cardholder_name' => 'New Customer',
            'mpi_credit_card_no' => '4111111111111111',
            'bank' => 'New Bank',
            'account_type' => 'Savings',
            'account_number' => '654321',
            'surname' => 'New',
            'first_name' => 'Customer',
            'ezycash_amount' => '200.00',
            'term' => '24',
            'rate' => '6.00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ezycash', [
            'request_id' => 'legacy-request-id',
            'cardholder_name' => 'Legacy Customer',
        ]);
    }

    public function test_form_submit_returns_json_for_ajax_requests(): void
    {
        $user = User::factory()->create(['username' => 'agent1']);

        $response = $this->actingAs($user)->postJson(route('forms.store'), [
            'campaign' => 'mbsales',
            'form_type' => 'ezycash',
            'date' => now()->format('Y-m-d'),
            'request_id' => 'client-should-be-ignored',
            'cardholder_name' => 'Jane Doe',
            'mpi_credit_card_no' => '4111111111111111',
            'bank' => 'Test Bank',
            'account_type' => 'Savings',
            'account_number' => '123456',
            'surname' => 'Doe',
            'first_name' => 'Jane',
            'ezycash_amount' => '100.00',
            'term' => '12',
            'rate' => '5.00',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Record saved successfully.');
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'record_id',
            ],
        ]);

        $this->assertDatabaseHas('ezycash', [
            'agent' => $user->full_name ?? $user->username,
            'cardholder_name' => 'Jane Doe',
        ]);
    }

    public function test_form_submission_stores_capture_timestamps(): void
    {
        $user = User::factory()->create(['username' => 'agent1']);

        $response = $this->actingAs($user)->post(route('forms.store'), [
            '_token' => csrf_token(),
            'campaign' => 'mbsales',
            'form_type' => 'ezycash',
            'date' => now()->format('Y-m-d'),
            'cardholder_name' => 'Timestamped Submission',
            'mpi_credit_card_no' => '4111111111111111',
            'bank' => 'Test Bank',
            'account_type' => 'Savings',
            'account_number' => '123456',
            'surname' => 'Doe',
            'first_name' => 'John',
            'ezycash_amount' => '100.00',
            'term' => '12',
            'rate' => '5.00',
        ]);

        $response->assertRedirect();

        $submission = DB::table('ezycash')
            ->where('cardholder_name', 'Timestamped Submission')
            ->first(['created_at', 'updated_at']);

        $this->assertNotNull($submission);
        $this->assertNotNull($submission->created_at);
        $this->assertNotNull($submission->updated_at);
    }

    public function test_dynamic_form_table_gets_timestamp_columns_before_submission(): void
    {
        Event::fake([DashboardDataUpdated::class]);
        Form::create([
            'campaign_code' => 'mbsales',
            'form_code' => 'dynamic_form',
            'name' => 'Dynamic Form',
            'table_name' => 'dynamic_form_records',
            'display_order' => 99,
        ]);
        FormField::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'dynamic_form',
            'field_name' => 'customer_name',
            'field_label' => 'Customer Name',
            'field_type' => 'text',
            'is_required' => true,
            'field_order' => 1,
        ]);
        Schema::create('dynamic_form_records', function ($table) {
            $table->id();
            $table->date('date');
            $table->string('request_id');
            $table->string('agent');
            $table->string('customer_name');
        });

        $user = User::factory()->create(['username' => 'dynamic_agent']);

        $response = $this->actingAs($user)->post(route('forms.store'), [
            '_token' => csrf_token(),
            'campaign' => 'mbsales',
            'form_type' => 'dynamic_form',
            'date' => '2026-07-16',
            'customer_name' => 'Dynamic Customer',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $record = DB::table('dynamic_form_records')->first();

        $this->assertNotNull($record->created_at);
        $this->assertNotNull($record->updated_at);
        Event::assertDispatched(DashboardDataUpdated::class, function (DashboardDataUpdated $event): bool {
            return $event->campaignCode === 'mbsales'
                && $event->formType === 'dynamic_form'
                && $event->action === 'submitted';
        });
    }

    public function test_submission_reconciles_one_configured_field_with_one_unrepresented_storage_column(): void
    {
        Form::create([
            'campaign_code' => 'mbsales',
            'form_code' => 'schema_drift_form',
            'name' => 'Schema Drift Form',
            'table_name' => 'schema_drift_records',
            'display_order' => 99,
        ]);
        FormField::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'schema_drift_form',
            'field_name' => 'configured_bank',
            'field_label' => 'Bank',
            'field_type' => 'text',
            'is_required' => true,
            'field_order' => 1,
        ]);
        Schema::create('schema_drift_records', function ($table): void {
            $table->id();
            $table->date('date');
            $table->string('request_id');
            $table->string('storage_bank');
            $table->string('agent');
            $table->timestamps();
        });

        $user = User::factory()->create(['username' => 'drift_agent']);
        $response = $this->actingAs($user)->post(route('forms.store'), [
            'campaign' => 'mbsales',
            'form_type' => 'schema_drift_form',
            'date' => '2026-08-04',
            'configured_bank' => 'EastWest Bank',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('schema_drift_records', [
            'storage_bank' => 'EastWest Bank',
        ]);
        $this->assertFalse(Schema::hasColumn('schema_drift_records', 'configured_bank'));
    }

    public function test_form_submit_returns_validation_errors_for_ajax_requests(): void
    {
        Event::fake([DashboardDataUpdated::class]);
        $user = User::factory()->create(['username' => 'agent1']);

        $response = $this->actingAs($user)->postJson(route('forms.store'), [
            'campaign' => 'mbsales',
            'form_type' => 'ezycash',
            'date' => now()->format('Y-m-d'),
            'mpi_credit_card_no' => '4111111111111111',
            'bank' => 'Test Bank',
            'account_type' => 'Savings',
            'account_number' => '123456',
            'surname' => 'Doe',
            'first_name' => 'Jane',
            'ezycash_amount' => '100.00',
            'term' => '12',
            'rate' => '5.00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cardholder_name']);
        Event::assertNotDispatched(DashboardDataUpdated::class);
    }

    public function test_percentage_field_backed_by_decimal_column_stores_numeric_value(): void
    {
        Form::create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezytransfer',
            'name' => 'EzyTransfer',
            'table_name' => 'ezytransfer',
            'display_order' => 2,
        ]);

        foreach ([
            ['field_name' => 'rate', 'field_label' => 'Rate', 'field_type' => 'percentage', 'is_required' => true, 'field_order' => 1],
            ['field_name' => 'cardholder_name', 'field_label' => 'Cardholder Name', 'field_type' => 'text', 'is_required' => true, 'field_order' => 2],
            ['field_name' => 'mpi_credit_card_no', 'field_label' => 'Card No', 'field_type' => 'text', 'is_required' => true, 'field_order' => 3],
            ['field_name' => 'ezytransfer_amount', 'field_label' => 'Amount', 'field_type' => 'number', 'is_required' => true, 'field_order' => 4],
            ['field_name' => 'term', 'field_label' => 'Term', 'field_type' => 'text', 'is_required' => true, 'field_order' => 5],
            ['field_name' => 'other_bank_acc', 'field_label' => 'Other Bank Account', 'field_type' => 'text', 'is_required' => true, 'field_order' => 6],
            ['field_name' => 'other_bank_card_number', 'field_label' => 'Other Bank Card Number', 'field_type' => 'text', 'is_required' => true, 'field_order' => 7],
        ] as $field) {
            FormField::create(array_merge([
                'campaign_code' => 'mbsales',
                'form_type' => 'ezytransfer',
            ], $field));
        }
        app(CampaignService::class)->clearCampaignsCache();

        $user = User::factory()->create(['username' => 'agent1']);
        $response = $this->actingAs($user)->post(route('forms.store'), [
            '_token' => csrf_token(),
            'campaign' => 'mbsales',
            'form_type' => 'ezytransfer',
            'date' => '2026-06-09',
            'rate' => '25%',
            'cardholder_name' => 'John Doe',
            'mpi_credit_card_no' => '4111111111111111',
            'ezytransfer_amount' => '1000.00',
            'term' => '12',
            'other_bank_acc' => 'Test Bank',
            'other_bank_card_number' => '5555444433332222',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ezytransfer', [
            'agent' => $user->full_name ?? $user->username,
            'rate' => '25',
        ]);
    }
}
