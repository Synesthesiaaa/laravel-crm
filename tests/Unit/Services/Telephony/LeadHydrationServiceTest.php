<?php

namespace Tests\Unit\Services\Telephony;

use App\Models\AgentScreenField;
use App\Models\User;
use App\Services\Telephony\LeadHydrationService;
use App\Services\Telephony\LeadService;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialNonAgentApiService;
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

    public function test_hydrate_filters_capture_data_by_direction_get_and_both_only(): void
    {
        $user = User::factory()->create();
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'email_get',
            'vici_field' => 'email',
            'direction' => 'get',
            'field_label' => 'Email (GET)',
            'field_order' => 1,
            'field_width' => 'full',
        ]);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'first_name_both',
            'vici_field' => 'first_name',
            'direction' => 'both',
            'field_label' => 'First Name (BOTH)',
            'field_order' => 2,
            'field_width' => 'full',
        ]);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'last_name_post',
            'vici_field' => 'last_name',
            'direction' => 'post',
            'field_label' => 'Last Name (POST)',
            'field_order' => 3,
            'field_width' => 'full',
        ]);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'phone_none',
            'vici_field' => 'phone_number',
            'direction' => 'none',
            'field_label' => 'Phone (NONE)',
            'field_order' => 4,
            'field_width' => 'full',
        ]);

        $service = $this->makeService(OperationResult::success([
            'rows' => [
                ['lead_id', 'phone_number', 'first_name', 'last_name', 'email'],
                ['909', '15550009999', 'Alice', 'Brown', 'alice@example.test'],
            ],
        ]));

        $data = $service->hydrate($user, 'mbsales', 909, null);

        $this->assertSame([
            'email_get' => 'alice@example.test',
            'first_name_both' => 'Alice',
        ], $data['capture_data']);
        $this->assertArrayNotHasKey('last_name_post', $data['capture_data']);
        $this->assertArrayNotHasKey('phone_none', $data['capture_data']);
    }

    public function test_probe_inbound_returns_null_when_not_incall(): void
    {
        $user = User::factory()->create(['vici_user' => 'agent001']);
        $result = OperationResult::success([
            'raw_response' => "READY|123|mbsales\n",
            'rows' => [],
        ]);

        $service = $this->makeService(OperationResult::success(['rows' => []]), $result);

        $this->assertNull($service->probeInbound($user, 'mbsales'));
    }

    public function test_probe_inbound_extracts_lead_id_and_phone_from_pipe_row(): void
    {
        $user = User::factory()->create(['vici_user' => 'agent002']);
        AgentScreenField::create([
            'campaign_code' => 'mbsales',
            'field_key' => 'customer_email',
            'vici_field' => 'email',
            'field_label' => 'Customer Email',
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        $nonAgentResult = OperationResult::success([
            'raw_response' => "SUCCESS\nINCALL|456|mbsales|0|15551234567\n",
            'rows' => [],
        ]);

        $service = $this->makeService(OperationResult::success([
            'rows' => [
                ['lead_id', 'phone_number', 'email'],
                ['456', '15551234567', 'jane@example.test'],
            ],
        ]), $nonAgentResult);

        $data = $service->probeInbound($user, 'mbsales');

        $this->assertNotNull($data);
        $this->assertSame('456', $data['lead_id']);
        $this->assertSame('15551234567', $data['phone_number']);
        $this->assertSame(['customer_email' => 'jane@example.test'], $data['capture_data']);
    }

    private function makeService(OperationResult $result, ?OperationResult $nonAgentResult = null): LeadHydrationService
    {
        $leadService = Mockery::mock(LeadService::class);
        $leadService->shouldReceive('allInfo')->andReturn($result);

        $nonAgentApi = Mockery::mock(VicidialNonAgentApiService::class);
        if ($nonAgentResult) {
            $nonAgentApi->shouldReceive('execute')->andReturn($nonAgentResult);
        } else {
            $nonAgentApi->shouldIgnoreMissing();
        }

        $logger = Mockery::mock(TelephonyLogger::class);
        $logger->shouldIgnoreMissing();

        return new LeadHydrationService($leadService, $nonAgentApi, $logger);
    }
}
