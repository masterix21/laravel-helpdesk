<?php

declare(strict_types=1);

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class TicketMerged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $source,
        public readonly Ticket $target,
        public readonly ?string $reason = null
    ) {}
}
