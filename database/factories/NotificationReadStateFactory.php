<?php

namespace Database\Factories;

use App\Models\NotificationReadState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationReadState>
 */
class NotificationReadStateFactory extends Factory
{
    protected $model = NotificationReadState::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'item_key' => 'history:'.$this->faker->unique()->numberBetween(1, 999999),
            'read_at' => now(),
        ];
    }
}
