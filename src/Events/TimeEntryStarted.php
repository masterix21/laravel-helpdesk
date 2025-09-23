<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\TicketTimeEntry;

class TimeEntryStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TicketTimeEntry $timeEntry
    ) {}
}
