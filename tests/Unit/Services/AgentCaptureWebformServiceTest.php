<?php

namespace Tests\Unit\Services;

use App\Models\AgentScreenField;
use App\Models\Campaign;
use App\Models\Form;
use App\Services\AgentCaptureWebformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AgentCaptureWebformServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_requires_an_active_selected_form_and_returns_ordered_fields(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'mbsales', 'is_active' => true]);
        $form = Form::create([
            'campaign_code' => $campaign->code,
            'form_code' => 'capture',
            'name' => 'Capture Form',
            'table_name' => 'agent_capture_records',
            'display_order' => 1,
            'is_active' => true,
        ]);
        $campaign->update(['agent_webform_form_id' => $form->id]);
        AgentScreenField::create([
            'campaign_code' => $campaign->code,
            'field_key' => 'last_name',
            'field_label' => 'Last Name',
            'field_order' => 2,
            'direction' => 'none',
        ]);
        AgentScreenField::create([
            'campaign_code' => $campaign->code,
            'field_key' => 'first_name',
            'field_label' => 'First Name',
            'field_order' => 1,
            'direction' => 'get',
            'vici_field' => 'first_name',
        ]);

        $configuration = app(AgentCaptureWebformService::class)->configuration($campaign->code);

        $this->assertNotNull($configuration);
        $this->assertTrue($configuration['campaign']->is($campaign));
        $this->assertTrue($configuration['form']->is($form));
        $this->assertSame(['first_name', 'last_name'], $configuration['fields']->pluck('field_key')->all());
    }

    public function test_prefill_uses_vicidial_query_values_for_get_and_both_fields_only(): void
    {
        $fields = collect([
            new AgentScreenField([
                'field_key' => 'first_name',
                'vici_field' => 'first_name',
                'direction' => 'get',
            ]),
            new AgentScreenField([
                'field_key' => 'email',
                'vici_field' => 'email',
                'direction' => 'both',
            ]),
            new AgentScreenField([
                'field_key' => 'notes',
                'vici_field' => 'comments',
                'direction' => 'post',
            ]),
        ]);

        $prefill = app(AgentCaptureWebformService::class)->prefill(
            $fields,
            Request::create('/agent-webforms/mbsales', 'GET', [
                'lead_id' => '123',
                'phone_number' => '5551234',
                'first_name' => 'Ada',
                'email' => 'ada@example.test',
                'comments' => 'Should stay blank',
            ]),
        );

        $this->assertSame('123', $prefill['lead_id']);
        $this->assertSame('5551234', $prefill['phone_number']);
        $this->assertSame([
            'first_name' => 'Ada',
            'email' => 'ada@example.test',
        ], $prefill['fields']);
    }

    public function test_vicidial_url_contains_metadata_and_unique_get_or_both_mappings(): void
    {
        $fields = collect([
            new AgentScreenField([
                'field_key' => 'first_name',
                'vici_field' => 'first_name',
                'direction' => 'get',
            ]),
            new AgentScreenField([
                'field_key' => 'email',
                'vici_field' => 'email',
                'direction' => 'both',
            ]),
            new AgentScreenField([
                'field_key' => 'ignored',
                'vici_field' => 'email',
                'direction' => 'get',
            ]),
            new AgentScreenField([
                'field_key' => 'notes',
                'vici_field' => 'comments',
                'direction' => 'post',
            ]),
        ]);

        $url = app(AgentCaptureWebformService::class)->vicidialUrl('mbsales', $fields);

        $this->assertStringStartsWith('VAR'.url('/agent-webforms/mbsales'), $url);
        $this->assertStringContainsString('lead_id=--A--lead_id--B--', $url);
        $this->assertStringContainsString('phone_number=--A--phone_number--B--', $url);
        $this->assertSame(1, substr_count($url, 'first_name=--A--first_name--B--'));
        $this->assertSame(1, substr_count($url, 'email=--A--email--B--'));
        $this->assertStringNotContainsString('comments=--A--comments--B--', $url);
    }
}
