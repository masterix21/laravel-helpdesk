<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use LucaLongo\LaravelHelpdesk\Models\KnowledgeArticle;

class FAQGeneratedFromTickets
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public KnowledgeArticle $faq,
        public Collection $sourceTickets
    ) {}
}
