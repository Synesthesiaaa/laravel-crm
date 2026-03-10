<?php

namespace Tests\Unit\Services;

use App\Models\FormField;
use App\Repositories\FormFieldRepository;
use App\Repositories\FormSubmissionRepository;
use App\Services\CallHistoryService;
use App\Services\CampaignService;
use App\Services\FormSubmissionService;
use Illuminate\Support\Collection;
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
            'date'       => '2026-01-15',
            'request_id' => '260115001',
            'full_name'  => 'John Doe',
            'amount'     => '1000.50',
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
            'date'       => '2026-01-15',
            'request_id' => '260115001',
            'phone'      => '+63 (912) 345-6789',
        ], 'agent1');

        $this->assertEquals('639123456789', $result['phone']);
    }

    public function test_generate_request_id_returns_default_when_table_missing(): void
    {
        $campaignService = \Mockery::mock(CampaignService::class);
        $campaignService->shouldReceive('getAllFormTableNames')->once()->andReturn([]);

        $service = new FormSubmissionService(
            $campaignService,
            \Mockery::mock(FormFieldRepository::class),
            \Mockery::mock(FormSubmissionRepository::class),
            \Mockery::mock(CallHistoryService::class)
        );

        $requestId = $service->generateRequestId('table_that_does_not_exist');
        $this->assertStringEndsWith('001', $requestId);
    }
}
