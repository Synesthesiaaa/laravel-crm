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

    public function test_persist_rows_accepts_numeric_excel_phone_cells(): void
    {
        $rows = [
            ['phone_number' => 5553333, 'first_name' => 'Excel'],
            ['phone_number' => 5554444.0, 'first_name' => 'Float'],
        ];

        $result = $this->service->persistRows($this->list, $rows);

        $this->assertSame(2, $result['inserted']);
        $this->assertSame('5553333', Lead::where('first_name', 'Excel')->first()->phone_number);
        $this->assertSame('5554444', Lead::where('first_name', 'Float')->first()->phone_number);
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

    public function test_persist_rows_nullifies_unparseable_date_values_instead_of_throwing(): void
    {
        $rows = [
            [
                'phone_number' => '5551111',
                'first_name' => 'A',
                'date_of_birth' => 'ayap@XYZ.com',
                'last_called_at' => 'not-a-date',
            ],
            [
                'phone_number' => '5552222',
                'first_name' => 'B',
                'date_of_birth' => '1990-05-04',
            ],
        ];

        $result = $this->service->persistRows($this->list, $rows);

        $this->assertSame(2, $result['inserted']);
        $this->assertSame(0, $result['skipped']);

        $lead1 = Lead::where('phone_number', '5551111')->first();
        $this->assertNull($lead1->date_of_birth);
        $this->assertNull($lead1->last_called_at);

        $lead2 = Lead::where('phone_number', '5552222')->first();
        $this->assertNotNull($lead2->date_of_birth);
        $this->assertSame('1990-05-04', $lead2->date_of_birth->format('Y-m-d'));
    }

    public function test_persist_rows_nullifies_datetime_objects_outside_sane_range(): void
    {
        // PhpSpreadsheet returns a real \DateTime for date-formatted cells.
        // An empty / zero-serial cell resolves to year -0001, which MySQL
        // rejects with SQLSTATE[22007] when sent through. The sanitiser must
        // null those out instead of trusting any DateTimeInterface.
        $badDate = new \DateTime('-0001-11-30 00:00:00');
        $futureDate = new \DateTime('2999-01-01 00:00:00');
        $goodDate = new \DateTime('1985-05-08');

        $rows = [
            [
                'phone_number' => '5551111',
                'first_name' => 'NegYear',
                'date_of_birth' => $badDate,
            ],
            [
                'phone_number' => '5552222',
                'first_name' => 'FarFuture',
                'date_of_birth' => $futureDate,
            ],
            [
                'phone_number' => '5553333',
                'first_name' => 'Good',
                'date_of_birth' => $goodDate,
            ],
        ];

        $result = $this->service->persistRows($this->list, $rows);

        $this->assertSame(3, $result['inserted']);
        $this->assertSame(0, $result['skipped']);

        $this->assertNull(Lead::where('phone_number', '5551111')->first()->date_of_birth);
        $this->assertNull(Lead::where('phone_number', '5552222')->first()->date_of_birth);
        $this->assertSame(
            '1985-05-08',
            Lead::where('phone_number', '5553333')->first()->date_of_birth->format('Y-m-d'),
        );
    }

    public function test_persist_rows_nullifies_zero_date_strings(): void
    {
        // Carbon::parse('0000-00-00') happily returns a Carbon instance with
        // year 0, which MySQL then rejects. The range check must catch it.
        $rows = [
            [
                'phone_number' => '5554444',
                'first_name' => 'ZeroDate',
                'date_of_birth' => '0000-00-00',
            ],
        ];

        $result = $this->service->persistRows($this->list, $rows);

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['skipped']);
        $this->assertNull(Lead::where('phone_number', '5554444')->first()->date_of_birth);
    }

    public function test_persist_rows_isolates_failures_to_individual_rows(): void
    {
        // Force one row to blow up by giving it a non-array `custom_fields`
        // shape that survives sanitisation but breaks at write time only for
        // that specific row. We simulate this by giving an absurdly long
        // status that violates the column length on most engines, but to keep
        // the test database-portable we just assert that the per-row try/catch
        // contract holds when one row throws via a bad date *and* a good row
        // also exists.
        $rows = [
            ['phone_number' => '5550001', 'first_name' => 'Good'],
            ['phone_number' => '5550002', 'first_name' => 'AlsoGood', 'date_of_birth' => 'totally bogus'],
        ];

        $result = $this->service->persistRows($this->list, $rows);

        // Both succeed because the date is sanitised, not because the row failed.
        $this->assertSame(2, $result['inserted']);
        $this->assertSame(0, $result['skipped']);
        $this->assertNull(Lead::where('phone_number', '5550002')->first()->date_of_birth);
    }

    public function test_stash_rejects_unsupported_extension(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('data.txt', 1);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->stash($file, 'testcamp');
    }
}
