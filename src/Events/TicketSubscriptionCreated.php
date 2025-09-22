<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\TicketSubscription;

readonly class TicketSubscriptionCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public TicketSubscription $subscription) {}
}
