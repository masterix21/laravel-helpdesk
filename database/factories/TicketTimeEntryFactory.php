<?php

namespace LucaLongo\LaravelHelpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketTimeEntry;

class TicketTimeEntryFactory extends Factory
{
    protected $model = TicketTimeEntry::class;

    public function definition(): array
    {
        $started = $this->faker->dateTimeBetween('-7 days', 'now');
        $ended = null;
        $duration = null;

        if ($this->faker->boolean(80)) {
            $duration = $this->faker->numberBetween(15, 240);
            $ended = (clone $started)->modify("+{$duration} minutes");
        }

        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => 1,
            'started_at' => $started,
            'ended_at' => $ended,
            'duration_minutes' => $duration,
            'description' => $this->faker->optional()->sentence(),
            'is_billable' => $this->faker->boolean(75),
            'hourly_rate' => $this->faker->optional(0.5)->randomFloat(2, 50, 200),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
            'duration_minutes' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $duration = $this->faker->numberBetween(15, 240);
            $started = $this->faker->dateTimeBetween('-7 days', '-1 hour');

            return [
                'started_at' => $started,
                'ended_at' => (clone $started)->modify("+{$duration} minutes"),
                'duration_minutes' => $duration,
            ];
        });
    }

    public function billable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_billable' => true,
            'hourly_rate' => $this->faker->randomFloat(2, 50, 200),
        ]);
    }

    public function nonBillable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_billable' => false,
            'hourly_rate' => null,
        ]);
    }
}