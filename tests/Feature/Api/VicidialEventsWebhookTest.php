<?php

namespace Tests\Feature\Api;

use App\Events\InboundCallReceived;
use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Services\Telephony\LeadHydrationService;
use App\Services\Telephony\TelephonyLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class VicidialEventsWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_answered_resolves_campaign_from_active_vicidial_agent_session_when_no_call_session(): void
    {
        Event::fake([InboundCallReceived::class]);

        $user = User::factory()->create([
            'vici_user' => '1001',
        ]);

        VicidialAgentSession::create([
            'user_id' => $user->id,
            'campaign_code' => 'othercamp',
            'session_status' => 'ready',
            'logged_in_at' => now(),
            'last_synced_at' => now(),
        ]);

        $hydration = Mockery::mock(LeadHydrationService::class);
        $hydration->shouldReceive('hydrate')
            ->once()
            ->withArgs(function ($hydrationUser, $campaign, $leadId, $phoneNumber) use ($user) {
                return $hydrationUser->is($user)
                    && $campaign === 'othercamp'
                    && $leadId === 123
                    && $phoneNumber === '639111111111';
            })
            ->andReturn([
                'lead_id' => '123',
                'phone_number' => '639111111111',
                'client_name' => 'John Doe',
                'capture_data' => ['email' => 'john@example.com'],
                'raw_fields' => ['lead_id' => '123'],
            ]);
        $this->app->instance(LeadHydrationService::class, $hydration);

        $this->post(route('api.webhooks.vicidial-events'), [
            'user' => '1001',
            'event' => 'call_answered',
            'message' => '639111111111',
            'lead_id' => '123',
        ])->assertOk();

        Event::assertDispatched(InboundCallReceived::class, function (InboundCallReceived $event) {
            return $event->campaignCode === 'othercamp'
                && $event->leadId === 123
                && $event->leadData === ['email' => 'john@example.com'];
        });
    }

    public function test_call_answered_falls_back_to_user_default_campaign_when_no_sessions(): void
    {
        Event::fake([InboundCallReceived::class]);

        $user = User::factory()->create([
            'vici_user' => '2002',
            'default_campaign' => 'othercamp',
        ]);

        $hydration = Mockery::mock(LeadHydrationService::class);
        $hydration->shouldReceive('hydrate')
            ->once()
            ->withArgs(function ($hydrationUser, $campaign, $leadId, $phoneNumber) use ($user) {
                return $hydrationUser->is($user)
                    && $campaign === 'othercamp'
                    && $leadId === 555
                    && $phoneNumber === '639222222222';
            })
            ->andReturn([
                'lead_id' => '555',
                'phone_number' => '639222222222',
                'client_name' => null,
                'capture_data' => [],
                'raw_fields' => [],
            ]);
        $this->app->instance(LeadHydrationService::class, $hydration);

        $this->post(route('api.webhooks.vicidial-events'), [
            'user' => '2002',
            'event' => 'call_answered',
            'message' => '639222222222',
            'lead_id' => '555',
        ])->assertOk();

        Event::assertDispatched(InboundCallReceived::class, function (InboundCallReceived $event) {
            return $event->campaignCode === 'othercamp'
                && $event->leadId === 555;
        });
    }

    public function test_call_answered_logs_hydration_capture_keys_for_diagnostics(): void
    {
        Event::fake([InboundCallReceived::class]);

        $user = User::factory()->create([
            'vici_user' => '3003',
        ]);

        VicidialAgentSession::create([
            'user_id' => $user->id,
            'campaign_code' => 'othercamp',
            'session_status' => 'ready',
            'logged_in_at' => now(),
            'last_synced_at' => now(),
        ]);

        $hydration = Mockery::mock(LeadHydrationService::class);
        $hydration->shouldReceive('hydrate')
            ->once()
            ->andReturn([
                'lead_id' => '777',
                'phone_number' => '639333333333',
                'client_name' => 'Jane Doe',
                'capture_data' => ['first_name' => 'Jane', 'last_name' => 'Doe'],
                'raw_fields' => ['lead_id' => '777', 'phone_number' => '639333333333'],
            ]);
        $this->app->instance(LeadHydrationService::class, $hydration);

        $logger = Mockery::spy(TelephonyLogger::class);
        $this->app->instance(TelephonyLogger::class, $logger);

        $this->post(route('api.webhooks.vicidial-events'), [
            'user' => '3003',
            'event' => 'call_answered',
            'message' => '639333333333',
            'lead_id' => '777',
        ])->assertOk();

        /** @var \Mockery\Expectation $expectation */
        $expectation = $logger->shouldHaveReceived('event');
        $expectation->with(
            'VicidialEventsWebhook',
            'call_answered',
            'Inbound hydration result',
            Mockery::on(function ($context) {
                return ($context['capture_keys'] ?? null) === ['first_name', 'last_name']
                    && ($context['raw_keys'] ?? null) === ['lead_id', 'phone_number'];
            })
        )->once();
    }
}
