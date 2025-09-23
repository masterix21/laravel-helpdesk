<?php

namespace LucaLongo\LaravelHelpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LucaLongo\LaravelHelpdesk\Models\AutomationExecution;
use LucaLongo\LaravelHelpdesk\Models\AutomationRule;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class AutomationExecutionFactory extends Factory
{
    protected $model = AutomationExecution::class;

    public function definition(): array
    {
        return [
            'automation_rule_id' => AutomationRule::factory(),
            'ticket_id' => Ticket::factory(),
            'executed_at' => now(),
            'conditions_snapshot' => [],
            'actions_snapshot' => [],
            'success' => true,
            'error_details' => null,
        ];
    }

    public function failed(string $error = 'Test error'): static
    {
        return $this->state(fn (array $attributes) => [
            'success' => false,
            'error_details' => ['error' => $error],
        ]);
    }
}