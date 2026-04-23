<?php

namespace Tests\Feature\Admin;

use App\Models\AgentCallDisposition;
use App\Models\Campaign;
use App\Models\DispositionCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentCallDispositionsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_leader_can_edit_agent_call_disposition(): void
    {
        Campaign::create(['code' => 'mbsales', 'name' => 'MB', 'is_active' => true]);
        DispositionCode::create([
            'campaign_code' => '',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
        ]);
        DispositionCode::create([
            'campaign_code' => '',
            'code' => 'DNC',
            'label' => 'DNC',
            'is_active' => true,
        ]);

        $leader = User::factory()->create(['role' => User::ROLE_TEAM_LEADER]);
        $record = AgentCallDisposition::create([
            'campaign_code' => 'mbsales',
            'agent' => 'Agent A',
            'disposition_code' => 'SALE',
            'disposition_label' => 'Sale',
            'disposition_source' => 'agent',
            'capture_data' => ['note' => 'typo'],
            'called_at' => now(),
        ]);

        $response = $this->actingAs($leader)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB'])
            ->put(route('admin.agent-records.update', $record), [
                '_token' => csrf_token(),
                'disposition_code' => 'DNC',
                'disposition_label' => 'DNC',
                'remarks' => 'fixed',
                'capture' => ['note' => 'corrected'],
            ]);

        $response->assertRedirect(route('admin.agent-records.index'));
        $record->refresh();
        $this->assertSame('DNC', $record->disposition_code);
        $this->assertSame('corrected', $record->capture_data['note']);
        $this->assertSame($leader->id, $record->last_edited_by_user_id);
    }

    public function test_agent_cannot_access_admin_agent_records(): void
    {
        Campaign::create(['code' => 'mbsales', 'name' => 'MB', 'is_active' => true]);
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);

        $response = $this->actingAs($agent)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB'])
            ->get(route('admin.agent-records.index'));

        $response->assertForbidden();
    }
}
