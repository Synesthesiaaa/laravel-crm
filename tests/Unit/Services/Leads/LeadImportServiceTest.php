<?php

namespace Tests\Unit\Services\Leads;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadList;
use App\Services\Leads\LeadImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeadImportService $service;

    private LeadList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LeadImportService::class);
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        $this->list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);
    }

    public function test_apply_mapping_translates_headers_to_db_columns(): void
    {
        $headers = ['Phone', 'First', 'Note'];
        $mapping = [
            'Phone' => 'phone_number',
            'First' => 'first_name',
            'Note' => 'note_custom',
        ];
        $raw = [
            ['5551111', 'Alice', 'vip'],
            ['5552222', 'Bob', 'x'],
        ];

        $mapped = $this->service->applyMapping($raw, $headers, $mapping);

        $this->assertSame('5551111', $mapped[0]['phone_number']);
        $this->assertSame('Alice', $mapped[0]['first_name']);
        $this->assertSame('vip', $mapped[0]['note_custom']);
    }

    public function test_persist_rows_inserts_and_skips_empty_phone(): void
    {
        $rows = [
            ['phone_number' => '5551111', 'first_name' => 'A'],
            ['phone_number' => '', 'first_name' => 'B'],
            ['phone_number' => '5552222', 'note' => 'x'],
        ];

        $result = $this->service->persistRows($this->list, $rows);

        $this->assertSame(2, $result['inserted']);
        $this->assertSame(1, $result['skipped']);

        $lead = Lead::where('phone_number', '5552222')->first();
        $this->assertSame(['note' => 'x'], $lead->custom_fields);
    }

    public function test_persist_rows_dedupe_by_phone_skips_duplicates_by_default(): void
    {
        $this->service->persistRows($this->list, [
            ['phone_number' => '5551111', 'first_name' => 'A'],
        ]);

        $result = $this->service->persistRows($this->list, [
            ['phone_number' => '5551111', 'first_name' => 'B'],
        ], ['dedupe' => 'phone_number', 'update_existing' => false]);

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame('A', Lead::where('phone_number', '5551111')->first()->first_name);
    }

    public function test_persist_rows_dedupe_by_phone_updates_when_requested(): void
    {
        $this->service->persistRows($this->list, [
            ['phone_number' => '5551111', 'first_name' => 'A'],
        ]);

        $result = $this->service->persistRows($this->list, [
            ['phone_number' => '5551111', 'first_name' => 'B'],
        ], ['dedupe' => 'phone_number', 'update_existing' => true]);

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame('B', Lead::where('phone_number', '5551111')->first()->first_name);
    }

    public function test_stash_rejects_unsupported_extension(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('data.txt', 1);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->stash($file, 'testcamp');
    }
}
