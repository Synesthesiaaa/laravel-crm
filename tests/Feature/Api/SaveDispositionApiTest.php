<?php

namespace Tests\Feature\Api;

use App\Models\CallSession;
use App\Models\DispositionCode;
use App\Models\User;
use App\Services\Telephony\VicidialDispositionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SaveDispositionApiTest extends TestCase
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

        DispositionCode::create([
            'campaign_code' => 'testcamp',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_save_disposition_requires_auth(): void
    {
        $this->postJson('/api/disposition/save', [
            'campaign_code' => 'testcamp',
            'disposition_code' => 'SALE',
        ])->assertUnauthorized();
    }

    public function test_save_disposition_returns_vicidial_sync_skipped_when_no_call_session(): void
    {
        $mock = Mockery::mock(VicidialDispositionSyncService::class);
        $mock->shouldNotReceive('syncDispositionToVicidial');
        $this->instance(VicidialDispositionSyncService::class, $mock);

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->postJson('/api/disposition/save', [
                'campaign_code' => 'testcamp',
                'disposition_code' => 'SALE',
                'disposition_label' => 'Sale',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vicidial_sync.status', 'skipped');
    }

    public function test_save_disposition_returns_vicidial_sync_from_service_when_session_present(): void
    {
        $session = CallSession::factory()
            ->for($this->agent)
            ->completed()
            ->create([
                'campaign_code' => 'testcamp',
                'lead_id' => 4242,
                'phone_number' => '5551234567',
                'disposition_code' => null,
            ]);

        $mock = Mockery::mock(VicidialDispositionSyncService::class);
        $mock->shouldReceive('syncDispositionToVicidial')
            ->once()
            ->andReturn(['status' => 'synced']);
        $this->instance(VicidialDispositionSyncService::class, $mock);

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->postJson('/api/disposition/save', [
                'campaign_code' => 'testcamp',
                'call_session_id' => $session->id,
                'lead_id' => 4242,
                'phone_number' => '5551234567',
                'disposition_code' => 'SALE',
                'disposition_label' => 'Sale',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vicidial_sync.status', 'synced');
    }

    public function test_save_disposition_returns_partial_when_sync_service_reports_partial(): void
    {
        $session = CallSession::factory()
            ->for($this->agent)
            ->completed()
            ->create([
                'campaign_code' => 'testcamp',
                'lead_id' => 99,
                'phone_number' => '5559990000',
                'disposition_code' => null,
            ]);

        $mock = Mockery::mock(VicidialDispositionSyncService::class);
        $mock->shouldReceive('syncDispositionToVicidial')
            ->once()
            ->andReturn([
                'status' => 'partial',
                'message' => 'Disposition saved in CRM; dialer sync completed only partially.',
            ]);
        $this->instance(VicidialDispositionSyncService::class, $mock);

        $this->actingAs($this->agent)
            ->withSession($this->campaignSession())
            ->postJson('/api/disposition/save', [
                'campaign_code' => 'testcamp',
                'call_session_id' => $session->id,
                'disposition_code' => 'SALE',
                'disposition_label' => 'Sale',
            ])
            ->assertOk()
            ->assertJsonPath('vicidial_sync.status', 'partial')
            ->assertJsonPath('vicidial_sync.message', 'Disposition saved in CRM; dialer sync completed only partially.');
    }
}
