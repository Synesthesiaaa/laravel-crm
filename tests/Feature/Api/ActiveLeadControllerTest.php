<?php

namespace Tests\Feature\Api;

use App\Models\AgentScreenField;
use App\Models\User;
use App\Services\Telephony\LeadService;
use App\Services\Telephony\VicidialNonAgentApiService;
use App\Support\OperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ActiveLeadControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'testpass',
            'extension' => '6001',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_active_lead_returns_hydrated_capture_data_for_incall(): void
    {
        AgentScreenField::create([
            'campaign_code' => 'testcamp',
            'field_key' => 'first_name',
            'field_label' => 'First Name',
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        $nonAgentMock = Mockery::mock(VicidialNonAgentApiService::class);
        $nonAgentMock->shouldReceive('execute')
            ->once()
            ->andReturn(OperationResult::success([
                'raw_response' => "STATUS|CALL_ID|LEAD_ID|CAMPAIGN|CALLS_TODAY|FULL_NAME|USER_GROUP|USER_LEVEL|PAUSE_CODE|RT_SUB_STATUS|PHONE_NUMBER\nINCALL|1|456|testcamp|0|John Doe|AGENTS|1|||63999111222",
                'rows' => [
                    ['STATUS', 'CALL_ID', 'LEAD_ID', 'CAMPAIGN', 'CALLS_TODAY', 'FULL_NAME', 'USER_GROUP', 'USER_LEVEL', 'PAUSE_CODE', 'RT_SUB_STATUS', 'PHONE_NUMBER'],
                    ['INCALL', '1', '456', 'testcamp', '0', 'John Doe', 'AGENTS', '1', '', '', '63999111222'],
                ],
            ]));
        $this->instance(VicidialNonAgentApiService::class, $nonAgentMock);

        $leadServiceMock = Mockery::mock(LeadService::class);
        $leadServiceMock->shouldReceive('allInfo')
            ->once()
            ->andReturn(OperationResult::success([
                'rows' => [
                    ['first_name', 'John'],
                    ['last_name', 'Doe'],
                    ['lead_id', '456'],
                    ['phone_number', '63999111222'],
                ],
            ]));
        $this->instance(LeadService::class, $leadServiceMock);

        $response = $this->actingAs($this->agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Camp'])
            ->getJson('/api/telephony/active-lead?campaign=testcamp');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('active', true)
            ->assertJsonPath('status', 'INCALL')
            ->assertJsonPath('agent_state', 'in_call')
            ->assertJsonPath('lead_id', '456')
            ->assertJsonPath('phone_number', '63999111222')
            ->assertJsonPath('capture_data.first_name', 'John');
    }

    public function test_active_lead_returns_inactive_when_agent_status_is_ready(): void
    {
        $nonAgentMock = Mockery::mock(VicidialNonAgentApiService::class);
        $nonAgentMock->shouldReceive('execute')
            ->once()
            ->andReturn(OperationResult::success([
                'raw_response' => "STATUS|CALL_ID|LEAD_ID|CAMPAIGN|CALLS_TODAY|FULL_NAME|USER_GROUP|USER_LEVEL|PAUSE_CODE|RT_SUB_STATUS|PHONE_NUMBER\nREADY|1||testcamp|0|John Doe|AGENTS|1|||",
                'rows' => [
                    ['STATUS', 'CALL_ID', 'LEAD_ID', 'CAMPAIGN', 'CALLS_TODAY', 'FULL_NAME', 'USER_GROUP', 'USER_LEVEL', 'PAUSE_CODE', 'RT_SUB_STATUS', 'PHONE_NUMBER'],
                    ['READY', '1', '', 'testcamp', '0', 'John Doe', 'AGENTS', '1', '', '', ''],
                ],
            ]));
        $this->instance(VicidialNonAgentApiService::class, $nonAgentMock);

        $leadServiceMock = Mockery::mock(LeadService::class);
        $leadServiceMock->shouldReceive('allInfo')->never();
        $this->instance(LeadService::class, $leadServiceMock);

        $response = $this->actingAs($this->agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Camp'])
            ->getJson('/api/telephony/active-lead?campaign=testcamp');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('active', false)
            ->assertJsonPath('status', 'READY')
            ->assertJsonPath('agent_state', 'ready');
    }

    public function test_active_lead_returns_paused_agent_state_when_status_is_paused(): void
    {
        $nonAgentMock = Mockery::mock(VicidialNonAgentApiService::class);
        $nonAgentMock->shouldReceive('execute')
            ->once()
            ->andReturn(OperationResult::success([
                'raw_response' => "STATUS|CALL_ID|LEAD_ID|CAMPAIGN|CALLS_TODAY|FULL_NAME|USER_GROUP|USER_LEVEL|PAUSE_CODE|RT_SUB_STATUS|PHONE_NUMBER\nPAUSED|1||testcamp|0|John Doe|AGENTS|1|BREAK||",
                'rows' => [
                    ['STATUS', 'CALL_ID', 'LEAD_ID', 'CAMPAIGN', 'CALLS_TODAY', 'FULL_NAME', 'USER_GROUP', 'USER_LEVEL', 'PAUSE_CODE', 'RT_SUB_STATUS', 'PHONE_NUMBER'],
                    ['PAUSED', '1', '', 'testcamp', '0', 'John Doe', 'AGENTS', '1', 'BREAK', '', ''],
                ],
            ]));
        $this->instance(VicidialNonAgentApiService::class, $nonAgentMock);

        $leadServiceMock = Mockery::mock(LeadService::class);
        $leadServiceMock->shouldReceive('allInfo')->never();
        $this->instance(LeadService::class, $leadServiceMock);

        $response = $this->actingAs($this->agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Camp'])
            ->getJson('/api/telephony/active-lead?campaign=testcamp');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('active', false)
            ->assertJsonPath('status', 'PAUSED')
            ->assertJsonPath('agent_state', 'paused');
    }

    public function test_active_lead_works_in_iframe_only_mode(): void
    {
        config()->set('vicidial.session_iframe_agent_api_only', true);

        AgentScreenField::create([
            'campaign_code' => 'testcamp',
            'field_key' => 'last_name',
            'field_label' => 'Last Name',
            'field_order' => 1,
            'field_width' => 'full',
        ]);

        $nonAgentMock = Mockery::mock(VicidialNonAgentApiService::class);
        $nonAgentMock->shouldReceive('execute')
            ->once()
            ->andReturn(OperationResult::success([
                'raw_response' => "STATUS|CALL_ID|LEAD_ID|CAMPAIGN|CALLS_TODAY|FULL_NAME|USER_GROUP|USER_LEVEL|PAUSE_CODE|RT_SUB_STATUS|PHONE_NUMBER\nINCALL|1|789|testcamp|0|Jane Smith|AGENTS|1|||63977123456",
                'rows' => [
                    ['STATUS', 'CALL_ID', 'LEAD_ID', 'CAMPAIGN', 'CALLS_TODAY', 'FULL_NAME', 'USER_GROUP', 'USER_LEVEL', 'PAUSE_CODE', 'RT_SUB_STATUS', 'PHONE_NUMBER'],
                    ['INCALL', '1', '789', 'testcamp', '0', 'Jane Smith', 'AGENTS', '1', '', '', '63977123456'],
                ],
            ]));
        $this->instance(VicidialNonAgentApiService::class, $nonAgentMock);

        $leadServiceMock = Mockery::mock(LeadService::class);
        $leadServiceMock->shouldReceive('allInfo')
            ->once()
            ->andReturn(OperationResult::success([
                'rows' => [
                    ['last_name', 'Smith'],
                    ['lead_id', '789'],
                    ['phone_number', '63977123456'],
                ],
            ]));
        $this->instance(LeadService::class, $leadServiceMock);

        $response = $this->actingAs($this->agent)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'Test Camp'])
            ->getJson('/api/telephony/active-lead?campaign=testcamp');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('active', true)
            ->assertJsonPath('status', 'INCALL')
            ->assertJsonPath('agent_state', 'in_call')
            ->assertJsonPath('lead_id', '789')
            ->assertJsonPath('capture_data.last_name', 'Smith');
    }
}
