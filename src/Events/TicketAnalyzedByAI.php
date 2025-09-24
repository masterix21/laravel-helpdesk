<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\AIAnalysis;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class TicketAnalyzedByAI
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public AIAnalysis $analysis
    ) {}
}