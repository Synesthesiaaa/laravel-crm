<?php

namespace Tests\Feature\Admin\Leads;

use App\Jobs\ImportLeadsFileJob;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private LeadList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        $this->list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);
    }

    public function test_csv_upload_stashes_file_and_headers(): void
    {
        Storage::fake('local');
        $csv = "phone_number,first_name\n5551111,Alice\n5552222,Bob\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $response = $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->post(route('admin.leads.import.upload', $this->list), ['file' => $file]);

        $response->assertRedirect(route('admin.leads.import.mapping', $this->list));
        $response->assertSessionHas('lead_import_'.$this->list->id);

        $files = Storage::disk('local')->files('lead-imports');
        $this->assertNotEmpty($files);
    }

    public function test_confirm_dispatches_import_job_when_phone_is_mapped(): void
    {
        Queue::fake();
        Storage::fake('local');
        $csv = "Phone,First\n5551111,Alice\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->post(route('admin.leads.import.upload', $this->list), ['file' => $file]);

        $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->post(route('admin.leads.import.confirm', $this->list), [
                'mapping' => ['Phone' => 'phone_number', 'First' => 'first_name'],
                'dedupe' => 'phone_number',
                'update_existing' => 0,
            ])
            ->assertRedirect(route('admin.leads.lists.show', $this->list));

        Queue::assertPushed(ImportLeadsFileJob::class);
    }

    public function test_confirm_rejects_mapping_without_phone_number(): void
    {
        Queue::fake();
        Storage::fake('local');
        $csv = "A,B\n1,2\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->post(route('admin.leads.import.upload', $this->list), ['file' => $file]);

        $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->post(route('admin.leads.import.confirm', $this->list), [
                'mapping' => ['A' => 'first_name', 'B' => 'last_name'],
            ])
            ->assertRedirect();

        Queue::assertNothingPushed();
    }

    public function test_export_download_returns_xlsx_response(): void
    {
        Lead::create([
            'list_id' => $this->list->id,
            'campaign_code' => 'testcamp',
            'phone_number' => '5551111',
            'first_name' => 'A',
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->get(route('admin.leads.export.download', $this->list));

        $response->assertOk();
        $this->assertStringContainsString('leads_testcamp_list', $response->headers->get('content-disposition', ''));
    }

    public function test_export_template_returns_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->get(route('admin.leads.export.template', $this->list));

        $response->assertOk();
        $this->assertStringContainsString('leads_template', $response->headers->get('content-disposition', ''));
    }

    public function test_non_admin_cannot_import(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        Storage::fake('local');
        $csv = "phone_number\n5551111\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->actingAs($agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->post(route('admin.leads.import.upload', $this->list), ['file' => $file])
            ->assertForbidden();
    }
}
