<?php

namespace LucaLongo\LaravelHelpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Models\ResponseTemplate;

class ResponseTemplateFactory extends Factory
{
    protected $model = ResponseTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'slug' => $this->faker->unique()->slug(2),
            'content' => $this->generateContent(),
            'ticket_type' => $this->faker->optional(0.5)->randomElement(TicketType::cases()),
            'variables' => $this->generateVariables(),
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function forType(TicketType $type): static
    {
        return $this->state(fn () => [
            'ticket_type' => $type,
        ]);
    }

    public function welcome(): static
    {
        return $this->state(fn () => [
            'name' => 'Welcome',
            'slug' => 'welcome',
            'content' => "Hello {customer_name},\n\nThank you for contacting our support team. Your ticket #{ticket_number} has been created and we will respond to you shortly.\n\nBest regards,\n{agent_name}",
            'variables' => ['customer_name', 'ticket_number', 'agent_name'],
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'name' => 'Ticket Resolved',
            'slug' => 'ticket-resolved',
            'content' => "Hi {customer_name},\n\nYour ticket #{ticket_number} has been resolved. If you have any further questions, please don't hesitate to contact us.\n\nBest regards,\n{agent_name}",
            'variables' => ['customer_name', 'ticket_number', 'agent_name'],
        ]);
    }

    private function generateContent(): string
    {
        $templates = [
            "Hi {customer_name},\n\n{message}\n\nBest regards,\n{agent_name}",
            "Dear {customer_name},\n\nRegarding ticket #{ticket_number}:\n\n{message}\n\nSincerely,\n{agent_name}",
            "Hello,\n\n{message}\n\nThank you,\nSupport Team",
        ];

        return $this->faker->randomElement($templates);
    }

    private function generateVariables(): array
    {
        $allVariables = [
            'customer_name',
            'customer_email',
            'ticket_number',
            'ticket_subject',
            'agent_name',
            'agent_email',
            'message',
            'company_name',
        ];

        return $this->faker->randomElements($allVariables, $this->faker->numberBetween(2, 5));
    }
}
