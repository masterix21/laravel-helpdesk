<?php

namespace LucaLongo\LaravelHelpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LucaLongo\LaravelHelpdesk\Models\AutomationRule;

class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'trigger' => $this->faker->randomElement(['ticket_created', 'ticket_updated', 'manual', 'time_based']),
            'conditions' => [],
            'actions' => [],
            'priority' => $this->faker->numberBetween(0, 100),
            'is_active' => true,
            'stop_processing' => false,
            'last_executed_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withStopProcessing(): static
    {
        return $this->state(fn (array $attributes) => [
            'stop_processing' => true,
        ]);
    }

    public function withConditions(array $conditions): static
    {
        return $this->state(fn (array $attributes) => [
            'conditions' => $conditions,
        ]);
    }

    public function withActions(array $actions): static
    {
        return $this->state(fn (array $attributes) => [
            'actions' => $actions,
        ]);
    }
}