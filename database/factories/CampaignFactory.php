<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('??##'),
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'color' => '#'.fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
