<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\VicidialCallHistorySyncState;
use App\Models\VicidialServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VicidialCallHistorySyncState>
 */
class VicidialCallHistorySyncStateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vicidial_server_id' => VicidialServer::factory(),
            'crm_campaign_id' => Campaign::factory(),
            'status' => VicidialCallHistorySyncState::STATUS_NEVER_SYNCED,
            'last_call_at' => null,
            'last_unique_id' => null,
            'last_successful_sync_at' => null,
            'last_started_at' => null,
            'last_failed_at' => null,
            'last_error_classification' => null,
            'last_error_message' => null,
            'last_sync_duration_ms' => null,
            'last_rows_received' => 0,
            'last_rows_inserted' => 0,
            'last_rows_updated' => 0,
            'current_window_start' => null,
            'current_window_end' => null,
        ];
    }
}
