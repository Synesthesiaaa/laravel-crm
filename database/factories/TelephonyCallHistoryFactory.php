<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\VicidialServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TelephonyCallHistory>
 */
class TelephonyCallHistoryFactory extends Factory
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
            'source_table' => 'vicidial_log',
            'source_unique_id' => fake()->unique()->uuid(),
            'vicidial_campaign_id' => 'CAMP_A',
            'vicidial_list_id' => 'LIST_A',
            'lead_id' => fake()->numberBetween(1, 999999),
            'vicidial_user' => 'agent_one',
            'crm_user_id' => null,
            'phone_number' => '639'.fake()->numerify('#########'),
            'status' => 'SALE',
            'disposition_code' => 'SALE',
            'disposition_label' => 'Sale',
            'call_date' => now()->subMinutes(5),
            'call_started_at' => now()->subMinutes(7),
            'call_ended_at' => now()->subMinutes(5),
            'duration_seconds' => 120,
            'talk_seconds' => null,
            'wait_seconds' => null,
            'direction' => 'OUTBOUND',
            'raw_end_reason' => 'HANGUP',
            'source_updated_at' => now()->subMinutes(5),
            'synced_at' => now(),
        ];
    }
}
