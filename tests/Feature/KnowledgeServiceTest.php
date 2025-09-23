<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LucaLongo\LaravelHelpdesk\Database\Factories\TicketFactory;
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeArticleStatus;
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeSuggestionMatchType;
use LucaLongo\LaravelHelpdesk\Events\KnowledgeSuggestionGenerated;
use LucaLongo\LaravelHelpdesk\Models\KnowledgeArticle;
use LucaLongo\LaravelHelpdesk\Models\KnowledgeSuggestion;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Services\KnowledgeService;

it('returns no suggestions and clears stale records when limit is zero', function () {
    Event::fake();

    /** @var Ticket $ticket */
    $ticket = TicketFactory::new()->create();
    $article = KnowledgeArticle::create([
        'ulid' => (string) Str::ulid(),
        'title' => 'Example Article',
        'slug' => 'example-article',
        'content' => 'Support content',
        'status' => KnowledgeArticleStatus::Published,
        'is_public' => true,
        'published_at' => now()->subDay(),
    ]);

    KnowledgeSuggestion::create([
        'ticket_id' => $ticket->id,
        'article_id' => $article->id,
        'relevance_score' => 42,
        'match_type' => KnowledgeSuggestionMatchType::Keyword,
        'matched_terms' => ['support'],
    ]);

    $result = (new KnowledgeService)->suggestArticlesForTicket($ticket, 0);

    expect($result)->toBeEmpty();
    expect($ticket->knowledgeSuggestions()->exists())->toBeFalse();

    Event::assertDispatched(KnowledgeSuggestionGenerated::class, function ($event) use ($ticket) {
        return $event->ticket->is($ticket) && $event->suggestionsCount === 0;
    });
});

it('returns an empty collection when no resolved tickets meet FAQ criteria', function () {
    $faqs = (new KnowledgeService)->generateFAQFromResolvedTickets();

    expect($faqs)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($faqs)->toBeEmpty();
});
