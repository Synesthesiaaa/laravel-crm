<?php

namespace Tests\Feature\Admin;

use App\Jobs\ImportLeadsCsvJob;
use App\Models\Campaign;
use App\Models\Form;
use App\Models\LeadHopper;
use App\Models\LeadImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadHopperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);
        Form::create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'color' => 'green',
            'icon' => 'cash',
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_index_shows_hopper_counts_for_current_campaign(): void
    {
        LeadHopper::create([
            'campaign_code' => 'mbsales',
            'lead_id' => '1001',
            'phone_number' => '09171234567',
            'status' => 'pending',
        ]);
        LeadHopper::create([
            'campaign_code' => 'mbsales',
            'lead_id' => '1002',
            'phone_number' => '09170000000',
            'status' => 'assigned',
        ]);

        $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('admin.lead-hopper.index'))
            ->assertOk()
            ->assertSee('Lead Hopper')
            ->assertSee('09171234567')
            ->assertSee('assigned');
    }

    public function test_csv_import_creates_import_record_and_queues_job(): void
    {
        Queue::fake();
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('leads.csv', "date,cardholder_name,phone_number\n2026-06-09,Jane Doe,09171234567\n");

        $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->post(route('admin.lead-hopper.import'), [
                'form_type' => 'ezycash',
                'csv_file' => $file,
            ])
            ->assertRedirect(route('admin.lead-hopper.index'))
            ->assertSessionHas('success');

        $import = LeadImport::first();
        $this->assertNotNull($import);
        $this->assertSame(LeadImport::STATUS_QUEUED, $import->status);
        $this->assertSame('mbsales', $import->campaign_code);

        Queue::assertPushed(ImportLeadsCsvJob::class);
    }

    public function test_csv_import_rejects_form_outside_current_campaign(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('leads.csv', "phone_number\n09171234567\n");

        $this->actingAs($this->superAdmin)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->post(route('admin.lead-hopper.import'), [
                'form_type' => 'unknown',
                'csv_file' => $file,
            ])
            ->assertRedirect(route('admin.lead-hopper.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('lead_imports', 0);
    }
}
