<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class TicketEscalated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly int $escalationLevel,
        public readonly ?string $reason = null,
    ) {}
}