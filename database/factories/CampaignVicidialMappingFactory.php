<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\VicidialServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CampaignVicidialMapping>
 */
class CampaignVicidialMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory()->state(['code' => 'testcamp']),
            'vicidial_server_id' => VicidialServer::factory()->state(['campaign_code' => 'testcamp']),
            'vicidial_campaign_code' => strtoupper(fake()->unique()->bothify('CAMP##')),
            'is_enabled' => true,
            'status' => 'active',
            'last_seen_at' => now(),
        ];
    }
}
