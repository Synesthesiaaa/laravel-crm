<?php

namespace Tests\Unit\Services;

use App\Models\DataRetentionPolicy;
use App\Models\Form;
use App\Models\FormField;
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

    public function test_it_clears_selected_fields_type_safely_and_preserves_records_and_other_fields(): void
    {
        $table = $this->createTypedStorageTable('retention_selected_records');
        $form = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'selected_form',
            'name' => 'Selected Form',
            'table_name' => $table,
            'is_active' => true,
        ]);

        foreach ([
            ['name' => 'required_text', 'type' => 'text'],
            ['name' => 'nullable_text', 'type' => 'text'],
            ['name' => 'amount', 'type' => 'number'],
            ['name' => 'consent_flag', 'type' => 'number'],
            ['name' => 'unselected_text', 'type' => 'text'],
        ] as $field) {
            FormField::query()->create([
                'campaign_code' => $form->campaign_code,
                'form_type' => $form->form_code,
                'field_name' => $field['name'],
                'field_label' => ucfirst($field['name']),
                'field_type' => $field['type'],
                'field_order' => 1,
            ]);
        }

        $policy = DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'cutoff_date' => '2026-01-31',
            'deletion_mode' => 'selected_fields',
            'selected_fields' => ['required_text', 'nullable_text', 'amount', 'consent_flag'],
        ]);

        $matchingId = DB::table($table)->insertGetId([
            'date' => '2026-01-31',
            'required_text' => 'Jane Doe',
            'nullable_text' => 'Sensitive note',
            'amount' => 125.50,
            'consent_flag' => 1,
            'unselected_text' => 'Keep this value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $futureId = DB::table($table)->insertGetId([
            'date' => '2026-02-01',
            'required_text' => 'Future Record',
            'nullable_text' => 'Keep future value',
            'amount' => 50,
            'consent_flag' => 1,
            'unselected_text' => 'Keep future record',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = app(DataRetentionService::class)->run();

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['deleted']);
        $this->assertSame(0, $summary['skipped']);
        $matching = (array) DB::table($table)->where('id', $matchingId)->first();
        $future = (array) DB::table($table)->where('id', $futureId)->first();
        $this->assertSame('', $matching['required_text']);
        $this->assertNull($matching['nullable_text']);
        $this->assertEquals(0, $matching['amount']);
        $this->assertEquals(0, $matching['consent_flag']);
        $this->assertSame('Keep this value', $matching['unselected_text']);
        $this->assertSame('Future Record', $future['required_text']);
        $this->assertSame('Keep future record', $future['unselected_text']);
        $this->assertNotNull($policy->fresh()->last_run_at);
        $this->assertSame(1, $policy->fresh()->last_deleted_count);
    }

    public function test_it_skips_unsupported_selected_fields_without_affecting_other_policies(): void
    {
        $validTable = $this->createStorageTable('retention_valid_selected_records');
        $unsupportedTable = $this->createUnsupportedStorageTable('retention_unsupported_records');
        $validForm = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'valid_form',
            'name' => 'Valid Form',
            'table_name' => $validTable,
            'is_active' => true,
        ]);
        $unsupportedForm = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'unsupported_form',
            'name' => 'Unsupported Form',
            'table_name' => $unsupportedTable,
            'is_active' => true,
        ]);
        FormField::query()->create([
            'campaign_code' => $unsupportedForm->campaign_code,
            'form_type' => $unsupportedForm->form_code,
            'field_name' => 'event_date',
            'field_label' => 'Event Date',
            'field_type' => 'date',
            'field_order' => 1,
        ]);

        DataRetentionPolicy::query()->create([
            'form_id' => $validForm->id,
            'cutoff_date' => '2026-01-31',
        ]);
        DataRetentionPolicy::query()->create([
            'form_id' => $unsupportedForm->id,
            'cutoff_date' => '2026-01-31',
            'deletion_mode' => 'selected_fields',
            'selected_fields' => ['event_date'],
        ]);
        DB::table($validTable)->insert([
            'date' => '2026-01-01',
            'request_id' => 'valid-expired',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table($unsupportedTable)->insert([
            'date' => '2026-01-01',
            'event_date' => '2026-01-01',
            'request_id' => 'unsupported-expired',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = app(DataRetentionService::class)->run();

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['deleted']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertDatabaseMissing($validTable, ['request_id' => 'valid-expired']);
        $this->assertDatabaseHas($unsupportedTable, ['request_id' => 'unsupported-expired']);
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

    private function createTypedStorageTable(string $table): string
    {
        Schema::create($table, function ($table): void {
            $table->id();
            $table->date('date')->index();
            $table->string('required_text');
            $table->string('nullable_text')->nullable();
            $table->decimal('amount', 10, 2);
            $table->boolean('consent_flag');
            $table->string('unselected_text');
            $table->timestamps();
        });
        $this->temporaryTables[] = $table;

        return $table;
    }

    private function createUnsupportedStorageTable(string $table): string
    {
        Schema::create($table, function ($table): void {
            $table->id();
            $table->date('date')->index();
            $table->date('event_date');
            $table->string('request_id');
            $table->timestamps();
        });
        $this->temporaryTables[] = $table;

        return $table;
    }
}
