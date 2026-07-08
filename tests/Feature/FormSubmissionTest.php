<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $user = User::factory()->create(['username' => 'agent1']);
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
        $this->assertTrue(Str::isUlid($requestId));
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

    public function test_form_submit_returns_validation_errors_for_ajax_requests(): void
    {
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
