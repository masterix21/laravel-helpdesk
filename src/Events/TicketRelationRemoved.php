<?php

declare(strict_types=1);

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Enums\TicketRelationType;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class TicketRelationRemoved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly Ticket $relatedTicket,
        public readonly TicketRelationType $type
    ) {}
}
