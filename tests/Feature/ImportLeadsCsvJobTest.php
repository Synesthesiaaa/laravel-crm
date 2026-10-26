<?php

namespace Tests\Feature;

use App\Events\FormSubmitted;
use App\Jobs\ImportLeadsCsvJob;
use App\Models\Campaign;
use App\Models\Form;
use App\Models\FormField;
use App\Models\LeadImport;
use App\Models\LeadImportRow;
use App\Models\User;
use App\Services\CallHistoryService;
use App\Services\CampaignService;
use App\Services\FormStorageSchemaService;
use App\Services\FormSubmissionService;
use App\Support\OperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportLeadsCsvJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_resumes_after_committed_rows_without_duplicate_records(): void
    {
        Event::fake();
        [$user, $import] = $this->createImport();
        $path = $this->csvFile("lead_name\nAda Example\nGrace Example\n");

        try {
            $firstRow = app(FormSubmissionService::class)->submitImportRow(
                'mbsales',
                'import_form',
                ['date' => '2026-10-26', 'lead_name' => 'Ada Example'],
                $user->username,
                $user->id,
                $import->id,
                1,
                "lead-import-{$import->id}-row-1",
            );
            $this->assertTrue($firstRow->success);

            (new ImportLeadsCsvJob($path, 'mbsales', 'import_form', $user->username, $import->id))
                ->handle(app(CampaignService::class), app(FormSubmissionService::class));

            $import->refresh();
            $this->assertSame(LeadImport::STATUS_COMPLETED, $import->status);
            $this->assertSame(2, $import->imported_count);
            $this->assertSame(0, $import->failed_count);
            $this->assertDatabaseCount('import_resume_records', 2);
            $this->assertDatabaseCount('crm_call_history', 2);
            $this->assertDatabaseCount('lead_import_rows', 2);
            $this->assertDatabaseHas('import_resume_records', [
                'request_id' => "lead-import-{$import->id}-row-1",
                'lead_name' => 'Ada Example',
            ]);
            $this->assertDatabaseHas('lead_import_rows', [
                'lead_import_id' => $import->id,
                'row_number' => 1,
                'request_id' => "lead-import-{$import->id}-row-1",
                'status' => LeadImportRow::STATUS_IMPORTED,
            ]);
            $this->assertDatabaseHas('import_resume_records', [
                'request_id' => "lead-import-{$import->id}-row-2",
                'lead_name' => 'Grace Example',
            ]);
        } finally {
            @unlink($path);
        }
    }

    public function test_validation_failures_are_recorded_without_storing_csv_values_in_receipts(): void
    {
        Event::fake();
        [$user, $import] = $this->createImport();
        $path = $this->csvFile("lead_name\nAda Example\n\"\"\n");

        try {
            (new ImportLeadsCsvJob($path, 'mbsales', 'import_form', $user->username, $import->id))
                ->handle(app(CampaignService::class), app(FormSubmissionService::class));

            $import->refresh();
            $this->assertSame(LeadImport::STATUS_FAILED, $import->status);
            $this->assertSame(1, $import->imported_count);
            $this->assertSame(1, $import->failed_count);
            $this->assertDatabaseHas('lead_import_rows', [
                'lead_import_id' => $import->id,
                'row_number' => 2,
                'request_id' => "lead-import-{$import->id}-row-2",
                'status' => LeadImportRow::STATUS_FAILED,
                'failure_code' => 'invalid_submission',
            ]);
            $this->assertNotContains('lead_name', Schema::getColumnListing('lead_import_rows'));
            $this->assertStringNotContainsString('Ada Example', json_encode($import->rows()->get()->toArray()));
        } finally {
            @unlink($path);
        }
    }

    public function test_import_fails_safely_when_row_tracking_schema_is_unavailable(): void
    {
        [$user, $import] = $this->createImport();
        $path = $this->csvFile("lead_name\nShould Not Import\n");
        Schema::drop('lead_import_rows');

        try {
            (new ImportLeadsCsvJob($path, 'mbsales', 'import_form', $user->username, $import->id))
                ->handle(app(CampaignService::class), app(FormSubmissionService::class));

            $import->refresh();
            $this->assertSame(LeadImport::STATUS_FAILED, $import->status);
            $this->assertSame('Import row tracking is unavailable.', $import->error_summary);
            $this->assertSame(0, $import->imported_count);
            $this->assertTrue(Schema::hasTable('import_resume_records'));
            $this->assertSame(0, DB::table('import_resume_records')->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_infrastructure_failure_rolls_back_a_row_and_retry_completes_it(): void
    {
        Event::fake();
        [$user, $import] = $this->createImport();
        $path = $this->csvFile("lead_name\nAda Example\n");
        $job = new ImportLeadsCsvJob($path, 'mbsales', 'import_form', $user->username, $import->id);
        $history = \Mockery::mock(CallHistoryService::class);
        $history->shouldReceive('logFormSubmission')
            ->once()
            ->andReturn(OperationResult::failure('simulated history failure'));
        app()->instance(CallHistoryService::class, $history);

        try {
            try {
                $job->handle(app(CampaignService::class), app(FormSubmissionService::class));
                $this->fail('The infrastructure failure must reach the queue for retry.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('simulated history failure', $exception->getMessage());
            }

            $this->assertSame(0, DB::table('import_resume_records')->count());
            $this->assertSame(0, DB::table('crm_call_history')->count());
            $this->assertSame(0, DB::table('lead_import_rows')->count());
            Event::assertNotDispatched(FormSubmitted::class);

            app()->forgetInstance(CallHistoryService::class);
            $job->handle(app(CampaignService::class), app(FormSubmissionService::class));

            $this->assertSame(LeadImport::STATUS_COMPLETED, $import->fresh()->status);
            $this->assertDatabaseCount('import_resume_records', 1);
            $this->assertDatabaseCount('crm_call_history', 1);
            $this->assertDatabaseCount('lead_import_rows', 1);
        } finally {
            @unlink($path);
        }
    }

    /** @return array{User, LeadImport} */
    private function createImport(): array
    {
        Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);
        Form::create([
            'campaign_code' => 'mbsales',
            'form_code' => 'import_form',
            'name' => 'Import Form',
            'table_name' => 'import_resume_records',
            'is_active' => true,
        ]);
        FormField::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'import_form',
            'field_name' => 'lead_name',
            'field_label' => 'Lead Name',
            'field_type' => 'text',
            'is_required' => true,
            'field_order' => 1,
        ]);
        app(FormStorageSchemaService::class)->provision(
            'import_resume_records',
            FormField::query()->where('campaign_code', 'mbsales')->where('form_type', 'import_form')->get(),
        );
        app(CampaignService::class)->clearCampaignsCache();

        $user = User::factory()->create(['username' => 'import-agent']);
        $import = LeadImport::create([
            'uploaded_by_user_id' => $user->id,
            'campaign_code' => 'mbsales',
            'form_type' => 'import_form',
            'status' => LeadImport::STATUS_QUEUED,
            'file_path' => 'lead-imports/synthetic.csv',
        ]);

        return [$user, $import];
    }

    private function csvFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-lead-import-');
        file_put_contents($path, $contents);

        return $path;
    }
}
