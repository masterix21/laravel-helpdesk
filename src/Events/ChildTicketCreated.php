<?php

declare(strict_types=1);

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class ChildTicketCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $parent,
        public readonly Ticket $child
    ) {}
}