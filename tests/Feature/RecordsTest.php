<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\CrmCallHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecordsTest extends TestCase
{
    use RefreshDatabase;

    private function campaignSession(): array
    {
        return ['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
            'color' => '#3b82f6',
        ]);
    }

    protected function tearDown(): void
    {
        config(['vicidial.events_webhook_secret' => '']);

        parent::tearDown();
    }

    public function test_call_history_lists_only_authenticated_agents_call_sessions(): void
    {
        $agent = User::factory()->create(['full_name' => 'Agent One']);
        $otherAgent = User::factory()->create(['full_name' => 'Agent Two']);

        CallSession::factory()->for($agent)->completed()->create([
            'campaign_code' => 'mbsales',
            'lead_id' => 101,
            'phone_number' => '639111111111',
            'disposition_code' => 'SALE',
            'disposition_label' => 'Sale',
        ]);
        CallSession::factory()->for($otherAgent)->completed()->create([
            'campaign_code' => 'mbsales',
            'lead_id' => 202,
            'phone_number' => '639222222222',
        ]);

        $this->actingAs($agent)
            ->withSession($this->campaignSession())
            ->get(route('records.index'))
            ->assertOk()
            ->assertSee('639111111111')
            ->assertSee('Sale')
            ->assertDontSee('639222222222');
    }

    public function test_call_history_filters_by_date_status_and_phone(): void
    {
        $agent = User::factory()->create();

        $match = CallSession::factory()->for($agent)->completed()->create([
            'campaign_code' => 'mbsales',
            'phone_number' => '639121234567',
            'status' => CallSession::STATUS_COMPLETED,
        ]);
        $match->forceFill(['dialed_at' => '2026-05-18 09:00:00'])->save();

        $wrongStatus = CallSession::factory()->for($agent)->ringing()->create([
            'campaign_code' => 'mbsales',
            'phone_number' => '639121234567',
        ]);
        $wrongStatus->forceFill(['dialed_at' => '2026-05-18 10:00:00'])->save();

        $wrongDate = CallSession::factory()->for($agent)->completed()->create([
            'campaign_code' => 'mbsales',
            'phone_number' => '639129999999',
        ]);
        $wrongDate->forceFill(['dialed_at' => '2026-05-10 09:00:00'])->save();

        $this->actingAs($agent)
            ->withSession($this->campaignSession())
            ->get(route('records.index', [
                'start_date' => '2026-05-17',
                'end_date' => '2026-05-19',
                'phone' => '1234567',
                'status' => CallSession::STATUS_COMPLETED,
            ]))
            ->assertOk()
            ->assertSee('639121234567')
            ->assertDontSee('639129999999');
    }

    public function test_call_history_does_not_use_crm_submission_history(): void
    {
        $agent = User::factory()->create();

        CrmCallHistory::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'loan_application',
            'record_id' => 55,
            'agent' => $agent->full_name,
            'phone_number' => '639133333333',
            'status' => 'RECORDED',
        ]);

        $this->actingAs($agent)
            ->withSession($this->campaignSession())
            ->get(route('records.index'))
            ->assertOk()
            ->assertSee('No call sessions found.')
            ->assertDontSee('loan_application')
            ->assertDontSee('639133333333');
    }

    public function test_call_history_renders_completed_status_and_duration_after_out_of_order_vicidial_events(): void
    {
        config(['vicidial.events_webhook_secret' => 'vicidial-secret']);

        $this->travelTo(Carbon::parse('2026-06-23 12:00:00'), function () {
            $agent = User::factory()->create([
                'username' => 'agent_vici',
                'vici_user' => 'agent_vici',
                'full_name' => 'Agent Vici',
            ]);

            $session = CallSession::factory()
                ->for($agent)
                ->ringing()
                ->create([
                    'campaign_code' => 'mbsales',
                    'lead_id' => 3030,
                    'phone_number' => '639155500000',
                    'vicidial_call_id' => 'VICI-RACE-1',
                    'dialed_at' => now()->subMinutes(2),
                    'ringing_at' => now()->subMinutes(2),
                ]);

            $this->postJson(route('api.webhooks.vicidial-events'), [
                'user' => 'agent_vici',
                'event' => 'agent_hangup',
            ], [
                'X-Webhook-Secret' => 'vicidial-secret',
            ])
                ->assertOk()
                ->assertJsonPath('received', true)
                ->assertJsonPath('processed', true);

            $this->postJson(route('api.webhooks.vicidial-events'), [
                'user' => 'agent_vici',
                'event' => 'call_answered',
            ], [
                'X-Webhook-Secret' => 'vicidial-secret',
            ])
                ->assertOk()
                ->assertJsonPath('received', true)
                ->assertJsonPath('processed', true);

            $session->refresh();
            $this->assertSame(CallSession::STATUS_COMPLETED, $session->status);
            $this->assertSame(120, $session->call_duration_seconds);

            $response = $this->actingAs($agent)
                ->withSession($this->campaignSession())
                ->get(route('records.index'));

            $response->assertOk()
                ->assertSee('Agent Vici')
                ->assertSee('Completed')
                ->assertSee('02:00', false);
        });
    }
}
