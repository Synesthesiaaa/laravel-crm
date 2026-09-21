<?php

namespace Tests\Feature\Api;

use App\Events\DispositionSaved;
use App\Events\InboundCallReceived;
use App\Models\CallSession;
use App\Models\DispositionCode;
use App\Models\TelephonyAlert;
use App\Models\User;
use App\Services\Telephony\LeadHydrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class VicidialCallUrlControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'vicidial.call_url_secret' => 'vicidial-secret',
        ]);
    }

    protected function tearDown(): void
    {
        config([
            'vicidial.call_url_secret' => '',
            'vicidial.report_system_disposition_codes' => [],
        ]);

        Mockery::close();
        parent::tearDown();
    }

    public function test_invalid_signature_is_rejected_for_each_vicidial_call_url_route(): void
    {
        $routes = [
            'api.webhooks.vicidial.start-call' => [
                'campaign' => 'mbsales',
                'lead_id' => 123,
            ],
            'api.webhooks.vicidial.dispo-call' => [
                'campaign' => 'mbsales',
                'call_id' => 'VICI-DISPO-1',
                'dispo' => 'SALE',
            ],
            'api.webhooks.vicidial.no-agent-call' => [
                'campaign' => 'mbsales',
                'call_id' => 'VICI-NOAGENT-1',
                'status' => 'NOAGENT',
            ],
            'api.webhooks.vicidial.dead-call-trigger' => [
                'campaign' => 'mbsales',
                'call_id' => 'VICI-DEAD-1',
            ],
            'api.webhooks.vicidial.pause-max' => [
                'campaign' => 'mbsales',
                'user' => 'agent001',
            ],
        ];

        foreach ($routes as $route => $params) {
            $this->getJson(route($route, array_merge(['sig' => 'wrong-secret'], $params)))
                ->assertStatus(401)
                ->assertJson([
                    'ok' => false,
                    'error' => 'invalid_signature',
                ]);
        }
    }

    public function test_start_call_rejects_missing_lead_and_phone_number(): void
    {
        $this->getJson(route('api.webhooks.vicidial.start-call', [
            'sig' => 'vicidial-secret',
            'campaign' => 'mbsales',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'validation_failed')
            ->assertJsonStructure([
                'fields' => [
                    'lead_id',
                    'phone_number',
                ],
            ]);
    }

    public function test_start_call_accepts_lead_id_and_creates_screen_pop_session(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'username' => 'agent001',
            'vici_user' => 'agent001',
            'vici_pass' => 'secret',
            'extension' => '1001',
            'full_name' => 'Agent One',
        ]);

        $hydration = Mockery::mock(LeadHydrationService::class);
        $hydration->shouldReceive('hydrate')
            ->once()
            ->with(
                Mockery::on(fn ($authUser) => $authUser instanceof User && $authUser->is($user)),
                'mbsales',
                123,
                null,
            )
            ->andReturn([
                'lead_id' => '123',
                'phone_number' => '15551234567',
                'client_name' => 'Jane Doe',
                'capture_data' => [
                    'customer_email' => 'jane@example.test',
                ],
                'raw_fields' => [],
            ]);
        $this->instance(LeadHydrationService::class, $hydration);

        $response = $this->getJson(route('api.webhooks.vicidial.start-call', [
            'sig' => 'vicidial-secret',
            'campaign' => 'mbsales',
            'lead_id' => 123,
            'call_id' => 'VICI-START-1',
            'user' => 'agent001',
            'phone_login' => '1001',
        ]));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.lead_id', 123)
            ->assertJsonPath('data.phone_number', '15551234567')
            ->assertJsonPath('data.client_name', 'Jane Doe')
            ->assertJsonPath('data.capture_data.customer_email', 'jane@example.test');

        $this->assertDatabaseHas('call_sessions', [
            'user_id' => $user->id,
            'campaign_code' => 'mbsales',
            'lead_id' => 123,
            'phone_number' => '15551234567',
            'vicidial_call_id' => 'VICI-START-1',
            'status' => CallSession::STATUS_DIALING,
        ]);

        Event::assertDispatched(InboundCallReceived::class, function (InboundCallReceived $event) use ($user): bool {
            return $event->userId === $user->id
                && $event->phoneNumber === '15551234567'
                && $event->leadId === 123
                && $event->clientName === 'Jane Doe'
                && $event->campaignCode === 'mbsales'
                && $event->leadData['customer_email'] === 'jane@example.test';
        });
    }

    public function test_start_call_accepts_phone_number_and_hydrates_lead_data(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'username' => 'agent002',
            'vici_user' => 'agent002',
            'vici_pass' => 'secret',
            'extension' => '1002',
            'full_name' => 'Agent Two',
        ]);

        $hydration = Mockery::mock(LeadHydrationService::class);
        $hydration->shouldReceive('hydrate')
            ->once()
            ->with(
                Mockery::on(fn ($authUser) => $authUser instanceof User && $authUser->is($user)),
                'mbsales',
                null,
                '15559876543',
            )
            ->andReturn([
                'lead_id' => '321',
                'phone_number' => '15559876543',
                'client_name' => 'John Smith',
                'capture_data' => [
                    'customer_email' => 'john@example.test',
                ],
                'raw_fields' => [],
            ]);
        $this->instance(LeadHydrationService::class, $hydration);

        $response = $this->getJson(route('api.webhooks.vicidial.start-call', [
            'sig' => 'vicidial-secret',
            'campaign' => 'mbsales',
            'phone_number' => '15559876543',
            'call_id' => 'VICI-START-2',
            'user' => 'agent002',
            'phone_login' => '1002',
        ]));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.lead_id', 321)
            ->assertJsonPath('data.phone_number', '15559876543')
            ->assertJsonPath('data.client_name', 'John Smith');

        $this->assertDatabaseHas('call_sessions', [
            'user_id' => $user->id,
            'campaign_code' => 'mbsales',
            'lead_id' => 321,
            'phone_number' => '15559876543',
            'vicidial_call_id' => 'VICI-START-2',
            'status' => CallSession::STATUS_DIALING,
        ]);

        Event::assertDispatched(InboundCallReceived::class, function (InboundCallReceived $event) use ($user): bool {
            return $event->userId === $user->id
                && $event->phoneNumber === '15559876543'
                && $event->leadId === 321
                && $event->campaignCode === 'mbsales';
        });
    }

    public function test_dispo_call_maps_into_the_existing_disposition_flow_and_is_idempotent(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'username' => 'agent003',
            'vici_user' => 'agent003',
        ]);

        DispositionCode::create([
            'campaign_code' => 'mbsales',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
        ]);

        $session = CallSession::factory()
            ->for($user)
            ->inCall()
            ->create([
                'campaign_code' => 'mbsales',
                'lead_id' => 456,
                'phone_number' => '15550001111',
                'vicidial_lead_id' => '456',
                'vicidial_call_id' => 'VICI-DISPO-1',
            ]);

        $payload = [
            'sig' => 'vicidial-secret',
            'campaign' => 'mbsales',
            'call_id' => 'VICI-DISPO-1',
            'dispo' => 'SALE',
            'call_notes' => 'Customer agreed to proceed.',
            'user' => 'agent003',
            'lead_id' => 456,
            'phone_number' => '15550001111',
        ];

        $firstResponse = $this->getJson(route('api.webhooks.vicidial.dispo-call', $payload));

        $firstResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.disposition_code', 'SALE')
            ->assertJsonPath('data.call_session_id', $session->id);

        $session->refresh();
        $this->assertSame(CallSession::STATUS_COMPLETED, $session->status);
        $this->assertSame('SALE', $session->disposition_code);
        $this->assertDatabaseCount('campaign_disposition_records', 1);
        $this->assertDatabaseHas('campaign_disposition_records', [
            'call_session_id' => $session->id,
            'campaign_code' => 'mbsales',
            'disposition_code' => 'SALE',
            'lead_id' => 456,
        ]);

        $secondResponse = $this->getJson(route('api.webhooks.vicidial.dispo-call', $payload));

        $secondResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.already_processed', true)
            ->assertJsonPath('data.call_session_id', $session->id);

        $this->assertDatabaseCount('campaign_disposition_records', 1);
    }

    public function test_dispo_call_skips_report_persistence_for_system_dispositions(): void
    {
        Event::fake();
        config(['vicidial.report_system_disposition_codes' => ['SYS_AUTO']]);

        $user = User::factory()->create([
            'username' => 'agent007',
            'vici_user' => 'agent007',
        ]);

        $session = CallSession::factory()
            ->for($user)
            ->inCall()
            ->create([
                'campaign_code' => 'mbsales',
                'lead_id' => 777,
                'phone_number' => '15550007777',
                'vicidial_lead_id' => '777',
                'vicidial_call_id' => 'VICI-SYS-1',
            ]);

        $payload = [
            'sig' => 'vicidial-secret',
            'campaign' => 'mbsales',
            'call_id' => 'VICI-SYS-1',
            'dispo' => 'SYS_AUTO',
            'call_notes' => 'System-generated disposition.',
            'user' => 'agent007',
            'lead_id' => 777,
            'phone_number' => '15550007777',
        ];

        $response = $this->getJson(route('api.webhooks.vicidial.dispo-call', $payload));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.excluded_from_reports', true)
            ->assertJsonPath('data.disposition_code', 'SYS_AUTO')
            ->assertJsonPath('data.call_session_id', $session->id);

        $session->refresh();
        $this->assertSame(CallSession::STATUS_COMPLETED, $session->status);
        $this->assertSame('SYS_AUTO', $session->disposition_code);
        $this->assertSame('SYS_AUTO', $session->disposition_label);
        $this->assertDatabaseCount('campaign_disposition_records', 0);
        Event::assertNotDispatched(DispositionSaved::class);
    }

    public function test_no_agent_call_logs_alert_without_mutating_session_state(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'username' => 'agent004',
            'vici_user' => 'agent004',
        ]);

        $session = CallSession::factory()
            ->for($user)
            ->inCall()
            ->create([
                'campaign_code' => 'mbsales',
                'lead_id' => 789,
                'phone_number' => '15550002222',
                'vicidial_lead_id' => '789',
                'vicidial_call_id' => 'VICI-NOAGENT-1',
            ]);

        $response = $this->getJson(route('api.webhooks.vicidial.no-agent-call', [
            'sig' => 'vicidial-secret',
            'campaign' => 'mbsales',
            'status' => 'NOAGENT',
            'call_id' => 'VICI-NOAGENT-1',
            'user' => 'agent004',
            'lead_id' => 789,
            'phone_number' => '15550002222',
        ]));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.alert_id', 1);

        $this->assertDatabaseCount('telephony_alerts', 1);
        $this->assertDatabaseHas('telephony_alerts', [
            'type' => 'vicidial_no_agent_call',
            'severity' => TelephonyAlert::SEVERITY_WARNING,
        ]);

        $session->refresh();
        $this->assertSame(CallSession::STATUS_IN_CALL, $session->status);
        $this->assertNull($session->disposition_code);
        $this->assertNull($session->ended_at);
    }

    public function test_dead_call_trigger_is_idempotent_with_existing_hangup_handling(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'username' => 'agent005',
            'vici_user' => 'agent005',
        ]);

        $session = CallSession::factory()
            ->for($user)
            ->inCall()
            ->create([
                'campaign_code' => 'mbsales',
                'lead_id' => 999,
                'phone_number' => '15550003333',
                'vicidial_lead_id' => '999',
                'vicidial_call_id' => 'VICI-DEAD-1',
            ]);

        $payload = [
            'sig' => 'vicidial-secret',
            'campaign' => 'mbsales',
            'call_id' => 'VICI-DEAD-1',
            'user' => 'agent005',
            'lead_id' => 999,
            'phone_number' => '15550003333',
        ];

        $firstResponse = $this->getJson(route('api.webhooks.vicidial.dead-call-trigger', $payload));

        $firstResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.call_session_id', $session->id)
            ->assertJsonPath('data.matched_session_id', $session->id);

        $session->refresh();
        $endedAt = $session->ended_at;

        $this->assertSame(CallSession::STATUS_COMPLETED, $session->status);
        $this->assertSame('customer_hangup', $session->end_reason);
        $this->assertNotNull($endedAt);

        $secondResponse = $this->getJson(route('api.webhooks.vicidial.dead-call-trigger', $payload));

        $secondResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.call_session_id', $session->id)
            ->assertJsonPath('data.session_status', CallSession::STATUS_COMPLETED);

        $session->refresh();
        $this->assertSame(CallSession::STATUS_COMPLETED, $session->status);
        $this->assertEquals($endedAt?->toDateTimeString(), $session->ended_at?->toDateTimeString());
    }

    public function test_pause_max_only_logs_alert_and_does_not_mutate_call_state(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'username' => 'agent006',
            'vici_user' => 'agent006',
        ]);

        $session = CallSession::factory()
            ->for($user)
            ->inCall()
            ->create([
                'campaign_code' => 'mbsales',
                'lead_id' => 654,
                'phone_number' => '15550004444',
                'vicidial_lead_id' => '654',
                'vicidial_call_id' => 'VICI-PAUSE-1',
            ]);

        $response = $this->getJson(route('api.webhooks.vicidial.pause-max', [
            'sig' => 'vicidial-secret',
            'campaign' => 'mbsales',
            'user' => 'agent006',
            'fullname' => 'Agent Six',
            'agent_email' => 'agent6@example.test',
            'user_group' => 'SUPPORT',
        ]));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.alert_id', 1);

        $this->assertDatabaseCount('telephony_alerts', 1);
        $this->assertDatabaseHas('telephony_alerts', [
            'type' => 'vicidial_pause_max',
            'severity' => TelephonyAlert::SEVERITY_WARNING,
        ]);

        $session->refresh();
        $this->assertSame(CallSession::STATUS_IN_CALL, $session->status);
        $this->assertNull($session->disposition_code);
        $this->assertNull($session->ended_at);
    }
}
