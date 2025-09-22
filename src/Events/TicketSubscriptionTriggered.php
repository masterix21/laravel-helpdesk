<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

readonly class TicketSubscriptionTriggered
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Collection<int, \LucaLongo\LaravelHelpdesk\Models\TicketSubscription>  $subscriptions
     */
    public function __construct(
        public Ticket $ticket,
        public TicketStatus $status,
        public Collection $subscriptions,
    ) {
    }
}
