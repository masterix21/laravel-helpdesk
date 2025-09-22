<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

readonly class TicketCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }
}
