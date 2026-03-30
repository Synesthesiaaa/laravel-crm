<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Telephony\LeadService;
use App\Support\OperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LeadUpdateFieldsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    /**
     * @return array<string, string>
     */
    private function campaignSession(): array
    {
        return ['campaign' => 'testcamp', 'campaign_name' => 'Test'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'role' => 'Agent',
            'vici_user' => 'testagent',
            'vici_pass' => 'testpass',
            'extension' => '6001',
            'sip_password' => 'sippass',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_update_fields_requires_auth(): void
    {
        $this->postJson('/api/leads/update-fields', [
            'fields' => ['lead_id' => 1, 'first_name' => 'Test'],
        ])->assertUnauthorized();
    }

    public function test_update_fields_returns_200_when_lead_service_succeeds(): void
    {
        $mock = Mockery::mock(LeadService::class);
        $mock->shouldReceive('updateFields')
            ->once()
            ->with(
                Mockery::on(fn ($u) => $u->id === $this->agent->id),
                'testcamp',
                ['lead_id' => 42, 'comments' => 'hello'],
            )
            ->andReturn(OperationResult::success(['raw_response' => 'OK']));
        $this->instance(LeadService::class, $mock);

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->postJson('/api/leads/update-fields?campaign=testcamp', [
                'fields' => ['lead_id' => 42, 'comments' => 'hello'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.raw_response', 'OK');
    }

    public function test_update_fields_returns_422_when_lead_service_fails(): void
    {
        $mock = Mockery::mock(LeadService::class);
        $mock->shouldReceive('updateFields')
            ->once()
            ->andReturn(OperationResult::failure('No active agent session.'));
        $this->instance(LeadService::class, $mock);

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->postJson('/api/leads/update-fields?campaign=testcamp', [
                'fields' => ['lead_id' => 1],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No active agent session.');
    }
}
