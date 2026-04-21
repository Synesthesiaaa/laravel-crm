<?php

namespace Database\Seeders;

use App\Models\FormField;
use Illuminate\Database\Seeder;

class FormFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $ezycashFields = [
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'cardholder_name', 'field_label' => 'Cardholder Name', 'field_type' => 'text', 'is_required' => true, 'field_order' => 1],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'mpi_credit_card_no', 'field_label' => 'MPI Credit Card No', 'field_type' => 'text', 'is_required' => true, 'field_order' => 2],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'bank', 'field_label' => 'Bank', 'field_type' => 'text', 'is_required' => true, 'field_order' => 3],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'account_type', 'field_label' => 'Account Type', 'field_type' => 'text', 'is_required' => true, 'field_order' => 4],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'account_number', 'field_label' => 'Account Number', 'field_type' => 'text', 'is_required' => true, 'field_order' => 5],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'surname', 'field_label' => 'Surname', 'field_type' => 'text', 'is_required' => true, 'field_order' => 6],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'first_name', 'field_label' => 'First Name', 'field_type' => 'text', 'is_required' => true, 'field_order' => 7],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'middle_name', 'field_label' => 'Middle Name', 'field_type' => 'text', 'is_required' => false, 'field_order' => 8],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'ezycash_amount', 'field_label' => 'EzyCash Amount', 'field_type' => 'number', 'is_required' => true, 'field_order' => 9],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'term', 'field_label' => 'Term', 'field_type' => 'text', 'is_required' => true, 'field_order' => 10],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'rate', 'field_label' => 'Rate', 'field_type' => 'number', 'is_required' => true, 'field_order' => 11],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'amenable', 'field_label' => 'Amenable', 'field_type' => 'text', 'is_required' => false, 'field_order' => 12],
            ['campaign_code' => 'mbsales', 'form_type' => 'ezycash', 'field_name' => 'remarks', 'field_label' => 'Remarks', 'field_type' => 'textarea', 'is_required' => false, 'field_order' => 13],
        ];
        foreach ($ezycashFields as $f) {
            FormField::updateOrCreate(
                ['campaign_code' => $f['campaign_code'], 'form_type' => $f['form_type'], 'field_name' => $f['field_name']],
                $f,
            );
        }
    }
}
