<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VicidialServer>
 */
class VicidialServerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_code' => 'testcamp',
            'server_name'   => fake()->words(3, true),
            'api_url'       => 'http://' . fake()->ipv4() . '/agc/api.php',
            'db_host'       => fake()->ipv4(),
            'db_username'   => 'cron',
            'db_password'   => fake()->password(8),
            'db_name'       => 'asterisk',
            'db_port'       => 3306,
            'api_user'      => null,
            'api_pass'      => null,
            'source'        => 'crm_tracker',
            'is_active'     => true,
            'is_default'    => false,
            'priority'      => 0,
        ];
    }
}
