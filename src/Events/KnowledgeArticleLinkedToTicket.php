<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\KnowledgeArticle;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class KnowledgeArticleLinkedToTicket
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public KnowledgeArticle $article,
        public Ticket $ticket,
        public ?Model $linkedBy = null
    ) {}
}
