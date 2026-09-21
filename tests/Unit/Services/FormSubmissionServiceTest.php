<?php

namespace Tests\Unit\Services;

use App\Models\FormField;
use App\Repositories\FormFieldRepository;
use App\Repositories\FormSubmissionRepository;
use App\Services\CallHistoryService;
use App\Services\CampaignService;
use App\Services\FormSubmissionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormSubmissionServiceTest extends TestCase
{
    private FormSubmissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(FormSubmissionService::class);
    }

    public function test_prepare_form_row_returns_null_when_date_missing(): void
    {
        $result = $this->service->prepareFormRow(collect(), ['request_id' => 'abc'], 'agent1');
        $this->assertNull($result);
    }

    public function test_prepare_form_row_returns_null_when_request_id_missing(): void
    {
        $result = $this->service->prepareFormRow(collect(), ['date' => '2026-01-01'], 'agent1');
        $this->assertNull($result);
    }

    public function test_prepare_form_row_returns_correct_structure(): void
    {
        $fields = collect([
            (object) ['field_name' => 'full_name', 'field_type' => 'text', 'is_required' => false],
            (object) ['field_name' => 'amount',    'field_type' => 'number', 'is_required' => false],
        ]);

        $result = $this->service->prepareFormRow($fields, [
            'date' => '2026-01-15',
            'request_id' => '260115001',
            'full_name' => 'John Doe',
            'amount' => '1000.50',
        ], 'agent1');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-15', $result['date']);
        $this->assertEquals('260115001', $result['request_id']);
        $this->assertEquals('John Doe', $result['full_name']);
        $this->assertEquals('1000.50', $result['amount']); // numeric field: keeps digits and dots
    }

    public function test_prepare_form_row_strips_numeric_non_digits(): void
    {
        $fields = collect([
            (object) ['field_name' => 'phone', 'field_type' => 'number', 'is_required' => false],
        ]);

        $result = $this->service->prepareFormRow($fields, [
            'date' => '2026-01-15',
            'request_id' => '260115001',
            'phone' => '+63 (912) 345-6789',
        ], 'agent1');

        $this->assertEquals('639123456789', $result['phone']);
    }

    public function test_prepare_form_row_normalizes_percentage_suffix_once(): void
    {
        $fields = collect([
            (object) ['field_name' => 'discount_rate', 'field_type' => 'percentage', 'is_required' => false],
            (object) ['field_name' => 'commission_rate', 'field_type' => 'percentage', 'is_required' => false],
            (object) ['field_name' => 'empty_rate', 'field_type' => 'percentage', 'is_required' => false],
        ]);

        $result = $this->service->prepareFormRow($fields, [
            'date' => '2026-01-15',
            'request_id' => '260115001',
            'discount_rate' => '12.5',
            'commission_rate' => '8%',
            'empty_rate' => '',
        ], 'agent1');

        $this->assertNotNull($result);
        $this->assertSame('12.5%', $result['discount_rate']);
        $this->assertSame('8%', $result['commission_rate']);
        $this->assertNull($result['empty_rate']);
    }

    public function test_prepare_form_row_encodes_multiselect_as_json(): void
    {
        $field = new FormField([
            'field_name' => 'tags',
            'field_type' => 'multiselect',
            'is_required' => true,
            'options' => json_encode(['a', 'b', 'c']),
        ]);
        $fields = collect([$field]);

        $result = $this->service->prepareFormRow($fields, [
            'date' => '2026-01-15',
            'request_id' => '01HZX1234567890ABCDEFGHJK',
            'tags' => ['b', 'a'],
        ], 'agent1');

        $this->assertNotNull($result);
        $this->assertSame('["a","b"]', $result['tags']);
    }

    public function test_prepare_form_row_accepts_ulid_request_id(): void
    {
        $ulid = (string) Str::ulid();
        $this->assertTrue(Str::isUlid($ulid));

        $result = $this->service->prepareFormRow(collect(), [
            'date' => '2026-01-01',
            'request_id' => $ulid,
        ], 'agent1');

        $this->assertNotNull($result);
        $this->assertSame($ulid, $result['request_id']);
    }

    public function test_unique_request_id_generation_retries_an_existing_candidate(): void
    {
        Carbon::setTestNow('2026-07-21 14:30:15');
        Schema::dropIfExists('request_id_generation_test');
        Schema::create('request_id_generation_test', function ($table) {
            $table->id();
            $table->string('request_id');
        });
        DB::table('request_id_generation_test')->insert([
            'request_id' => '20260721143015000001',
        ]);

        try {
            $service = $this->requestIdTestService(['000001', '000002']);

            $this->assertSame(
                '20260721143015000002',
                $service->generateForTable('request_id_generation_test'),
            );
        } finally {
            Schema::dropIfExists('request_id_generation_test');
            Carbon::setTestNow();
        }
    }

    public function test_unique_request_id_generation_fails_when_all_candidates_collide(): void
    {
        Carbon::setTestNow('2026-07-21 14:30:15');
        Schema::dropIfExists('request_id_generation_test');
        Schema::create('request_id_generation_test', function ($table) {
            $table->id();
            $table->string('request_id');
        });
        DB::table('request_id_generation_test')->insert([
            'request_id' => '20260721143015000001',
        ]);

        try {
            $service = $this->requestIdTestService(['000001', '000001']);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unable to generate a unique request ID.');

            $service->generateForTable('request_id_generation_test');
        } finally {
            Schema::dropIfExists('request_id_generation_test');
            Carbon::setTestNow();
        }
    }

    /**
     * @param  list<string>  $suffixes
     */
    private function requestIdTestService(array $suffixes): FormSubmissionService
    {
        return new class($this->app->make(CampaignService::class), $this->app->make(FormFieldRepository::class), $this->app->make(FormSubmissionRepository::class), $this->app->make(CallHistoryService::class), $suffixes) extends FormSubmissionService
        {
            /** @var list<string> */
            private array $suffixes;

            /**
             * @param  list<string>  $suffixes
             */
            public function __construct(
                CampaignService $campaignService,
                FormFieldRepository $formFieldRepository,
                FormSubmissionRepository $formSubmissionRepository,
                CallHistoryService $callHistoryService,
                array $suffixes,
            ) {
                parent::__construct(
                    $campaignService,
                    $formFieldRepository,
                    $formSubmissionRepository,
                    $callHistoryService,
                );
                $this->suffixes = $suffixes;
            }

            public function generateForTable(string $tableName): string
            {
                return $this->generateUniqueRequestId($tableName);
            }

            protected function requestIdRandomSuffix(): string
            {
                return array_shift($this->suffixes) ?? '000001';
            }

            protected function requestIdGenerationAttempts(): int
            {
                return 2;
            }
        };
    }
}
