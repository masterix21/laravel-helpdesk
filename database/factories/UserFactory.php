<?php

namespace LucaLongo\LaravelHelpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LucaLongo\LaravelHelpdesk\Tests\Fakes\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'team_id' => null,
            'is_available' => true,
        ];
    }

    public function withTeam(int $teamId): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $teamId,
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}