<?php

namespace LucaLongo\LaravelHelpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketRating;

class TicketRatingFactory extends Factory
{
    protected $model = TicketRating::class;

    public function definition(): array
    {
        $resolvedAt = $this->faker->dateTimeBetween('-30 days', '-1 hour');
        $ratedAt = $this->faker->dateTimeBetween($resolvedAt, 'now');
        $responseTimeHours = $this->faker->numberBetween(1, 72);

        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => 1,
            'rating' => $this->faker->numberBetween(1, 5),
            'feedback' => $this->faker->optional(0.7)->realText(200),
            'resolved_at' => $resolvedAt,
            'rated_at' => $ratedAt,
            'response_time_hours' => $responseTimeHours,
            'metadata' => [
                'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
                'device' => $this->faker->randomElement(['Desktop', 'Mobile', 'Tablet']),
                'channel' => $this->faker->randomElement(['web', 'email', 'phone']),
            ],
        ];
    }

    public function positive(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $this->faker->numberBetween(4, 5),
            'feedback' => $this->faker->randomElement([
                'Great support! The issue was resolved quickly.',
                'Excellent service, very professional and helpful.',
                'Quick response and effective solution. Thank you!',
                'Very satisfied with the support provided.',
            ]),
        ]);
    }

    public function negative(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $this->faker->numberBetween(1, 2),
            'feedback' => $this->faker->randomElement([
                'The resolution took too long.',
                'Not satisfied with the support received.',
                'Issue was not properly resolved.',
                'Poor communication and slow response.',
            ]),
        ]);
    }

    public function neutral(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => 3,
            'feedback' => $this->faker->randomElement([
                'Service was okay, nothing special.',
                'Average support experience.',
                'Could have been better, but acceptable.',
                null,
            ]),
        ]);
    }

    public function withoutFeedback(): static
    {
        return $this->state(fn (array $attributes) => [
            'feedback' => null,
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved_at' => $this->faker->dateTimeBetween('-7 days', '-1 hour'),
            'rated_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ]);
    }
}
