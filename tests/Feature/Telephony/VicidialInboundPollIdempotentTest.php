<?php

namespace Tests\Feature\Telephony;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadList;
use App\Services\Telephony\ViciDialInboundDispoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VicidialInboundPollIdempotentTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_from_poll_second_call_is_noop_when_status_already_mapped(): void
    {
        config(['vicidial.inbound_poll_enabled' => true]);
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);
        $lead = Lead::create([
            'list_id' => $list->id,
            'campaign_code' => 'testcamp',
            'phone_number' => '5554444',
            'enabled' => true,
            'status' => 'NEW',
        ]);

        $svc = app(ViciDialInboundDispoService::class);
        $svc->applyFromPoll($lead, 'NA', ['lead_id' => '1']);
        $lead->refresh();
        $this->assertSame('NO_ANSWER', $lead->status);

        $svc->applyFromPoll($lead->fresh(), 'NA', ['lead_id' => '1']);
        $this->assertSame(1, \App\Models\AgentCallDisposition::where('lead_pk', $lead->id)
            ->where('disposition_source', 'vicidial_poll')->count());
    }
}
