<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class KnowledgeSuggestionGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public int $suggestionsCount
    ) {}
}
