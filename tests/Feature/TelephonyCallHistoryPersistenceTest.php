<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\TelephonyCallHistory;
use App\Models\VicidialCallHistorySyncState;
use App\Models\VicidialServer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class TelephonyCallHistoryPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_telephony_history_stores_normalized_source_fields_and_casts_timestamps(): void
    {
        $campaign = Campaign::factory()->create();
        $server = VicidialServer::factory()->create();

        $record = TelephonyCallHistory::factory()->create([
            'vicidial_server_id' => $server->id,
            'crm_campaign_id' => $campaign->id,
            'call_date' => '2026-09-03 10:00:00',
            'call_started_at' => '2026-09-03 09:58:00',
            'call_ended_at' => '2026-09-03 10:00:00',
            'duration_seconds' => 120,
            'direction' => 'INBOUND',
            'wait_seconds' => 12,
        ]);

        $this->assertSame($campaign->id, $record->crm_campaign_id);
        $this->assertSame($server->id, $record->vicidial_server_id);
        $this->assertInstanceOf(Carbon::class, $record->call_date);
        $this->assertSame('2026-09-03 10:00:00', $record->call_date->format('Y-m-d H:i:s'));
        $this->assertSame(120, $record->duration_seconds);
        $this->assertSame('INBOUND', $record->direction);
        $this->assertSame(12, $record->wait_seconds);
    }

    public function test_source_identity_is_unique_within_a_campaign_scope(): void
    {
        $campaign = Campaign::factory()->create();
        $server = VicidialServer::factory()->create();
        $identity = [
            'vicidial_server_id' => $server->id,
            'crm_campaign_id' => $campaign->id,
            'source_table' => 'vicidial_log',
            'source_unique_id' => 'duplicate-call',
        ];

        TelephonyCallHistory::factory()->create($identity);

        $this->expectException(QueryException::class);
        TelephonyCallHistory::factory()->create($identity);
    }

    public function test_same_source_call_can_be_scoped_to_a_different_crm_campaign(): void
    {
        $server = VicidialServer::factory()->create();
        $firstCampaign = Campaign::factory()->create();
        $secondCampaign = Campaign::factory()->create();
        $identity = [
            'vicidial_server_id' => $server->id,
            'source_table' => 'vicidial_log',
            'source_unique_id' => 'shared-source-call',
        ];

        TelephonyCallHistory::factory()->create($identity + ['crm_campaign_id' => $firstCampaign->id]);
        TelephonyCallHistory::factory()->create($identity + ['crm_campaign_id' => $secondCampaign->id]);

        $this->assertSame(1, TelephonyCallHistory::forCampaign($firstCampaign->id)->count());
        $this->assertSame(1, TelephonyCallHistory::forCampaign($secondCampaign->id)->count());
    }

    public function test_sync_state_has_one_checkpoint_per_server_and_campaign(): void
    {
        $campaign = Campaign::factory()->create();
        $server = VicidialServer::factory()->create();
        $state = VicidialCallHistorySyncState::factory()->create([
            'vicidial_server_id' => $server->id,
            'crm_campaign_id' => $campaign->id,
            'status' => VicidialCallHistorySyncState::STATUS_HEALTHY,
            'last_call_at' => '2026-09-03 10:00:00',
            'last_successful_sync_at' => '2026-09-03 10:01:00',
            'last_rows_inserted' => 10,
            'last_rows_updated' => 5,
        ]);

        $this->assertSame(
            $state->id,
            VicidialCallHistorySyncState::forScope($server->id, $campaign->id)->firstOrFail()->id,
        );
        $this->assertSame(10, $state->last_rows_inserted);
        $this->assertSame(5, $state->last_rows_updated);
        $this->assertInstanceOf(Carbon::class, $state->last_successful_sync_at);
    }
}
