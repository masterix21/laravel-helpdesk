<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\TicketTimeEntry;

class TimeEntryStopped
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TicketTimeEntry $timeEntry
    ) {}
}
