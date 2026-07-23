<?php

namespace Tests\Feature;

use App\Models\AgentScreenField;
use App\Models\Campaign;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentCaptureWebformTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_agents_are_redirected_to_crm_login(): void
    {
        $this->get('/agent-webforms/mbsales')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_agent_sees_configured_fields_and_get_prefill(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);
        $campaign = Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);
        $form = $this->createForm($campaign->code);
        $campaign->update(['agent_webform_form_id' => $form->id]);
        AgentScreenField::create([
            'campaign_code' => $campaign->code,
            'field_key' => 'customer_name',
            'field_label' => 'Customer Name',
            'vici_field' => 'first_name',
            'direction' => 'get',
            'field_type' => 'text',
            'field_order' => 1,
            'field_width' => 'full',
        ]);
        AgentScreenField::create([
            'campaign_code' => $campaign->code,
            'field_key' => 'notes',
            'field_label' => 'Notes',
            'direction' => 'post',
            'field_type' => 'textarea',
            'field_order' => 2,
            'field_width' => 'full',
        ]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'othercamp', 'campaign_name' => 'Other Campaign'])
            ->get('/agent-webforms/mbsales?campaign=othercamp&lead_id=123&phone_number=15551234567&first_name=Ada')
            ->assertOk()
            ->assertSee('Capture Form')
            ->assertSee('novalidate', false)
            ->assertSee('Customer Name')
            ->assertSee('value="Ada"', false)
            ->assertSee('name="lead_id"', false)
            ->assertSee('value="123"', false)
            ->assertSee('name="notes"', false)
            ->assertSee('value="mbsales"', false)
            ->assertDontSee('Other Campaign')
            ->assertDontSee('sidebar')
            ->assertDontSee('Call Controls')
            ->assertDontSee('vici-session-frame');

        $this->assertSame('mbsales', session('campaign'));
    }

    public function test_unconfigured_campaign_does_not_render_a_submit_action(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);
        Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get('/agent-webforms/mbsales')
            ->assertOk()
            ->assertSee('No web form is configured for this campaign.')
            ->assertDontSee('Save Capture')
            ->assertDontSee('api/agent/capture');
    }

    private function createForm(string $campaignCode): Form
    {
        return Form::create([
            'campaign_code' => $campaignCode,
            'form_code' => 'agent_capture',
            'name' => 'Capture Form',
            'table_name' => 'agent_capture_records',
            'is_active' => true,
        ]);
    }
}
