<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\KnowledgeArticle;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class KnowledgeArticleViewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public KnowledgeArticle $article,
        public ?Ticket $ticket = null
    ) {}
}
