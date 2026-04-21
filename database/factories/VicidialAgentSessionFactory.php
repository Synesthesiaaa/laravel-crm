<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VicidialAgentSession>
 */
class VicidialAgentSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'campaign_code' => 'testcamp',
            'phone_login' => fake()->numerify('####'),
            'session_status' => 'ready',
            'pause_code' => null,
            'blended' => true,
            'ingroup_choices' => null,
            'logged_in_at' => now(),
            'last_synced_at' => now(),
        ];
    }
}
