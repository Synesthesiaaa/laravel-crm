<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'request_id' => '250101001',
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
    }
}
