<?php

namespace Tests\Unit\Services\Telephony;

use App\Models\AgentScreenField;
use App\Models\User;
use App\Services\Telephony\LeadHydrationService;
use App\Services\Telephony\LeadService;
use App\Services\Telephony\TelephonyLogger;
use App\Support\OperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LeadHydrationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_hydrate_maps_capture_data_using_vici_field_mapping(): void
    {
        $user = User::factory()->create();
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'customer_email',
            'vici_field' => 'email',
            'field_label' => 'Customer Email',
            'field_order' => 1,
            'field_width' => 'full',
        ]);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'customer_first_name',
            'vici_field' => 'first_name',
            'field_label' => 'Customer First Name',
            'field_order' => 2,
            'field_width' => 'full',
        ]);

        $service = $this->makeService(OperationResult::success([
            'rows' => [
                ['lead_id', 'phone_number', 'first_name', 'last_name', 'email'],
                ['101', '15551234567', 'Jane', 'Doe', 'jane@example.test'],
            ],
        ]));

        $data = $service->hydrate($user, 'mbsales', 101, null);

        $this->assertSame('101', $data['lead_id']);
        $this->assertSame('15551234567', $data['phone_number']);
        $this->assertSame('Jane Doe', $data['client_name']);
        $this->assertSame([
            'customer_email' => 'jane@example.test',
            'customer_first_name' => 'Jane',
        ], $data['capture_data']);
    }

    public function test_hydrate_supports_key_value_rows_and_ignores_missing_mappings(): void
    {
        $user = User::factory()->create();
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'customer_email',
            'vici_field' => 'email',
            'field_label' => 'Customer Email',
            'field_order' => 1,
            'field_width' => 'full',
        ]);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'missing_field',
            'vici_field' => 'does_not_exist',
            'field_label' => 'Missing',
            'field_order' => 2,
            'field_width' => 'full',
        ]);

        $service = $this->makeService(OperationResult::success([
            'rows' => [
                ['lead_id', '202'],
                ['phone_number', '18005550199'],
                ['first_name', 'John'],
                ['last_name', 'Smith'],
                ['email', 'john@example.test'],
            ],
        ]));

        $data = $service->hydrate($user, 'mbsales', null, '18005550199');

        $this->assertSame('202', $data['lead_id']);
        $this->assertSame('John Smith', $data['client_name']);
        $this->assertSame(['customer_email' => 'john@example.test'], $data['capture_data']);
        $this->assertArrayNotHasKey('missing_field', $data['capture_data']);
    }

    public function test_hydrate_returns_safe_defaults_when_vicidial_lookup_fails(): void
    {
        $user = User::factory()->create();
        $service = $this->makeService(OperationResult::failure('ERROR: lead not found'));

        $data = $service->hydrate($user, 'mbsales', 303, '15550001111');

        $this->assertSame('303', $data['lead_id']);
        $this->assertSame('15550001111', $data['phone_number']);
        $this->assertNull($data['client_name']);
        $this->assertSame([], $data['capture_data']);
        $this->assertSame([], $data['raw_fields']);
    }

    private function makeService(OperationResult $result): LeadHydrationService
    {
        $leadService = Mockery::mock(LeadService::class);
        $leadService->shouldReceive('allInfo')->andReturn($result);

        $logger = Mockery::mock(TelephonyLogger::class);
        $logger->shouldIgnoreMissing();

        return new LeadHydrationService($leadService, $logger);
    }
}
