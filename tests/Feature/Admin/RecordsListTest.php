<?php

namespace Tests\Feature\Admin;

use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\CrmCallHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordsListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'full_name' => 'Admin User',
        ]);

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
            'color' => '#3b82f6',
        ]);
        Campaign::factory()->create([
            'code' => 'othercamp',
            'name' => 'Other Camp',
            'color' => '#ef4444',
        ]);
    }

    private function campaignSession(): array
    {
        return ['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'];
    }

    public function test_default_tab_shows_submitted_records(): void
    {
        CrmCallHistory::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'loan_application',
            'record_id' => 10,
            'agent' => 'agent_submit',
            'phone_number' => '639111111111',
            'status' => 'RECORDED',
        ]);
        CallSession::factory()->for($this->admin)->completed()->create([
            'campaign_code' => 'mbsales',
            'phone_number' => '639222222222',
        ]);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.records.index'))
            ->assertOk()
            ->assertSee('Submitted CRM records')
            ->assertSee('loan_application')
            ->assertSee('639111111111')
            ->assertDontSee('639222222222');
    }

    public function test_call_sessions_tab_shows_campaign_call_sessions(): void
    {
        $agent = User::factory()->create([
            'full_name' => 'Agent Caller',
            'username' => 'agent_caller',
        ]);
        $otherCampaignAgent = User::factory()->create();

        CallSession::factory()->for($agent)->completed()->create([
            'campaign_code' => 'mbsales',
            'lead_id' => 303,
            'phone_number' => '639333333333',
            'disposition_label' => 'Interested',
        ]);
        CallSession::factory()->for($otherCampaignAgent)->completed()->create([
            'campaign_code' => 'othercamp',
            'lead_id' => 404,
            'phone_number' => '639444444444',
        ]);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.records.index', ['tab' => 'calls']))
            ->assertOk()
            ->assertSee('Call session records')
            ->assertSee('Agent Caller')
            ->assertSee('639333333333')
            ->assertSee('Interested')
            ->assertDontSee('639444444444');
    }

    public function test_call_sessions_tab_filters_and_tab_links_preserve_filters(): void
    {
        $agent = User::factory()->create([
            'full_name' => 'Agent Filter',
            'username' => 'agent_filter',
        ]);
        $wrongAgent = User::factory()->create([
            'full_name' => 'Agent Hidden',
            'username' => 'agent_hidden',
        ]);

        $match = CallSession::factory()->for($agent)->completed()->create([
            'campaign_code' => 'mbsales',
            'phone_number' => '639555123456',
        ]);
        $match->forceFill(['dialed_at' => '2026-05-18 09:00:00'])->save();

        $hidden = CallSession::factory()->for($wrongAgent)->completed()->create([
            'campaign_code' => 'mbsales',
            'phone_number' => '639555999999',
        ]);
        $hidden->forceFill(['dialed_at' => '2026-05-18 10:00:00'])->save();

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.records.index', [
                'tab' => 'calls',
                'start_date' => '2026-05-17',
                'end_date' => '2026-05-19',
                'agent' => 'Filter',
                'phone' => '123456',
                'status' => CallSession::STATUS_COMPLETED,
            ]))
            ->assertOk()
            ->assertSee('639555123456')
            ->assertDontSee('639555999999')
            ->assertSee('tab=submissions', false)
            ->assertSee('agent=Filter', false);
    }
}
