<?php

namespace Tests\Unit\Imports;

use App\Imports\LeadsImport;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadList;
use App\Services\Leads\LeadImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LeadsImportTest extends TestCase
{
    use RefreshDatabase;

    private LeadList $list;

    private LeadImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        $this->list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);
        $this->service = app(LeadImportService::class);
    }

    public function test_collection_matches_wizard_mapping_against_slugged_runtime_headers(): void
    {
        // Wizard submits original-cased headers; Maatwebsite has already
        // slugged the row keys to lowercase snake_case by the time
        // collection() runs. Both sides must be slugged for the match to work.
        $mapping = [
            'Full_Name' => 'first_name',
            'Phone_Home_1' => 'phone_number',
            'Date_Birthday' => '__skip__',
        ];
        $importer = new LeadsImport($this->list, $mapping, [
            'dedupe' => null,
            'update_existing' => false,
        ], $this->service);

        $rows = new Collection([
            new Collection([
                'full_name' => 'Alice',
                'phone_home_1' => '5551111',
                'date_birthday' => 'NOT-A-DATE',
            ]),
            new Collection([
                'full_name' => 'Bob',
                'phone_home_1' => '5552222',
                'date_birthday' => 'still-not-a-date',
            ]),
        ]);

        $importer->collection($rows);

        $this->assertSame(2, $importer->inserted);
        $this->assertSame(0, $importer->skipped);
        $this->assertSame(0, $importer->failedChunks);

        $alice = Lead::where('phone_number', '5551111')->first();
        $this->assertNotNull($alice);
        $this->assertSame('Alice', $alice->first_name);
        // date_birthday was __skip__; nothing should land in date_of_birth.
        $this->assertNull($alice->date_of_birth);
    }

    public function test_collection_swallows_chunk_failures_without_killing_the_job(): void
    {
        // Build an importer wired to a service that always throws to simulate
        // a Maatwebsite/transactional explosion mid-chunk.
        $blowingService = new class extends LeadImportService
        {
            public function __construct() {}

            public function persistRows(LeadList $list, array $rows, array $options = []): array
            {
                throw new \RuntimeException('boom');
            }
        };

        $importer = new LeadsImport(
            $this->list,
            ['phone_home_1' => 'phone_number'],
            ['dedupe' => null, 'update_existing' => false],
            $blowingService,
        );

        $rows = new Collection([
            new Collection(['phone_home_1' => '5551111']),
            new Collection(['phone_home_1' => '5552222']),
        ]);

        // Must not throw — the chunk failure should be caught and logged.
        $importer->collection($rows);

        $this->assertSame(1, $importer->failedChunks);
        $this->assertSame(2, $importer->skipped);
        $this->assertSame(0, $importer->inserted);
    }
}
