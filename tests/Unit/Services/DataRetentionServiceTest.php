<?php

namespace Tests\Unit\Services;

use App\Models\DataRetentionPolicy;
use App\Models\Form;
use App\Services\DataRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataRetentionServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryTables = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryTables as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_it_deletes_records_on_or_before_the_cutoff_and_isolates_forms(): void
    {
        $sourceTable = $this->createStorageTable('retention_source_records');
        $otherTable = $this->createStorageTable('retention_other_records');

        $sourceForm = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'source_form',
            'name' => 'Source Form',
            'table_name' => $sourceTable,
            'is_active' => true,
        ]);
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'other_form',
            'name' => 'Other Form',
            'table_name' => $otherTable,
            'is_active' => true,
        ]);

        $policy = DataRetentionPolicy::query()->create([
            'form_id' => $sourceForm->id,
            'cutoff_date' => '2026-01-31',
        ]);

        DB::table($sourceTable)->insert([
            ['date' => '2025-12-31', 'request_id' => 'before-cutoff', 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-01-31', 'request_id' => 'on-cutoff', 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-02-01', 'request_id' => 'after-cutoff', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table($otherTable)->insert([
            'date' => '2025-12-31',
            'request_id' => 'other-form-record',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = app(DataRetentionService::class)->run();

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(2, $summary['deleted']);
        $this->assertSame(0, $summary['skipped']);
        $this->assertDatabaseMissing($sourceTable, ['request_id' => 'before-cutoff']);
        $this->assertDatabaseMissing($sourceTable, ['request_id' => 'on-cutoff']);
        $this->assertDatabaseHas($sourceTable, ['request_id' => 'after-cutoff']);
        $this->assertDatabaseHas($otherTable, ['request_id' => 'other-form-record']);
        $this->assertNotNull($policy->fresh()->last_run_at);
        $this->assertSame(2, $policy->fresh()->last_deleted_count);
    }

    public function test_it_skips_missing_storage_and_continues_with_other_policies(): void
    {
        $validTable = $this->createStorageTable('retention_valid_records');
        $validForm = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'valid_form',
            'name' => 'Valid Form',
            'table_name' => $validTable,
            'is_active' => true,
        ]);
        $missingForm = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'missing_form',
            'name' => 'Missing Form',
            'table_name' => 'missing_retention_records',
            'is_active' => true,
        ]);

        DataRetentionPolicy::query()->create([
            'form_id' => $validForm->id,
            'cutoff_date' => '2026-01-31',
        ]);
        DataRetentionPolicy::query()->create([
            'form_id' => $missingForm->id,
            'cutoff_date' => '2026-01-31',
        ]);
        DB::table($validTable)->insert([
            'date' => '2026-01-01',
            'request_id' => 'valid-expired',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = app(DataRetentionService::class)->run();

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['deleted']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertDatabaseMissing($validTable, ['request_id' => 'valid-expired']);
    }

    private function createStorageTable(string $table): string
    {
        Schema::create($table, function ($table): void {
            $table->id();
            $table->date('date')->index();
            $table->string('request_id');
            $table->timestamps();
        });
        $this->temporaryTables[] = $table;

        return $table;
    }
}
