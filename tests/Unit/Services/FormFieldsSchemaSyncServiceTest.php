<?php

namespace Tests\Unit\Services;

use App\Models\FormField;
use App\Services\FormFieldsSchemaSyncService;
use Database\Seeders\CampaignSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormFieldsSchemaSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_form_fields_from_physical_table_columns(): void
    {
        $this->seed(CampaignSeeder::class);
        app(FormFieldsSchemaSyncService::class)->syncAllFromRegisteredForms();

        $this->assertDatabaseHas('form_fields', [
            'campaign_code' => 'mbsales',
            'form_type' => 'ezytransfer',
            'field_name' => 'rate',
            'is_required' => true,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('form_fields', [
            'campaign_code' => 'mbsales',
            'form_type' => 'ezytransfer',
            'field_name' => 'other_bank_acc',
            'deleted_at' => null,
        ]);

        $this->assertDatabaseMissing('form_fields', [
            'campaign_code' => 'mbsales',
            'form_type' => 'ezytransfer',
            'field_name' => 'request_id',
        ]);

        $this->assertDatabaseMissing('form_fields', [
            'campaign_code' => 'mbsales',
            'form_type' => 'ezytransfer',
            'field_name' => 'lead_id',
        ]);
    }

    public function test_second_sync_does_not_duplicate_rows(): void
    {
        $this->seed(CampaignSeeder::class);
        $sync = app(FormFieldsSchemaSyncService::class);
        $sync->syncAllFromRegisteredForms();
        $first = FormField::where('form_type', 'ezycash')->count();
        $sync->syncAllFromRegisteredForms();

        $this->assertSame($first, FormField::where('form_type', 'ezycash')->count());
    }
}
