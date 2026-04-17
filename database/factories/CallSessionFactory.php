<?php

namespace Database\Factories;

use App\Models\CallSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallSessionFactory extends Factory
{
    protected $model = CallSession::class;

    public function definition(): array
    {
        $dialedAt = $this->faker->dateTimeBetween('-1 hour', 'now');

        return [
            'user_id' => User::factory(),
            'campaign_code' => 'mbsales',
            'lead_id' => $this->faker->optional(0.7)->numberBetween(1, 9999),
            'phone_number' => '+63'.$this->faker->numerify('9#########'),
            'status' => CallSession::STATUS_DIALING,
            'linkedid' => null,
            'channel' => null,
            'vicidial_lead_id' => null,
            'dialed_at' => $dialedAt,
            'ringing_at' => null,
            'answered_at' => null,
            'ended_at' => null,
            'disposition_code' => null,
            'disposition_label' => null,
            'disposition_at' => null,
            'disposition_remarks' => null,
            'call_duration_seconds' => null,
            'end_reason' => null,
            'metadata' => null,
        ];
    }

    public function dialing(): static
    {
        return $this->state(fn () => ['status' => CallSession::STATUS_DIALING]);
    }

    public function ringing(): static
    {
        return $this->state(fn () => [
            'status' => CallSession::STATUS_RINGING,
            'ringing_at' => now(),
        ]);
    }

    public function inCall(): static
    {
        $answeredAt = now()->subMinutes(2);

        return $this->state(fn () => [
            'status' => CallSession::STATUS_IN_CALL,
            'ringing_at' => now()->subMinutes(2),
            'answered_at' => $answeredAt,
        ]);
    }

    public function completed(): static
    {
        $answeredAt = now()->subMinutes(5);
        $endedAt = now();

        return $this->state(fn () => [
            'status' => CallSession::STATUS_COMPLETED,
            'ringing_at' => $answeredAt->copy()->subSeconds(30),
            'answered_at' => $answeredAt,
            'ended_at' => $endedAt,
            'call_duration_seconds' => 300,
            'end_reason' => 'hangup',
        ]);
    }

    public function withLinkedId(string $linkedid): static
    {
        return $this->state(fn () => ['linkedid' => $linkedid]);
    }
}
