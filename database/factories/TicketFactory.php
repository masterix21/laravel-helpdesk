<?php

namespace LucaLongo\LaravelHelpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'type' => $this->faker->randomElement(TicketType::values()),
            'subject' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => TicketStatus::Open->value,
            'priority' => $this->faker->randomElement(TicketPriority::values()),
            'meta' => ['source' => 'factory'],
            'opened_at' => now(),
            'due_at' => now()->addDays(2),
        ];
    }

    public function closed(): self
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Closed->value,
            'closed_at' => now(),
        ]);
    }
}
