<?php

namespace LucaLongo\LaravelHelpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketAttachment;

/**
 * @extends Factory<TicketAttachment>
 */
class TicketAttachmentFactory extends Factory
{
    protected $model = TicketAttachment::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'disk' => 'local',
            'path' => $this->faker->uuid().'.txt',
            'original_name' => $this->faker->words(3, true).'.txt',
            'size' => $this->faker->numberBetween(1024, 2048),
            'meta' => ['mime' => 'text/plain'],
        ];
    }
}
