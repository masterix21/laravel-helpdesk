<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeSuggestionMatchType;

class KnowledgeSuggestion extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_knowledge_suggestions';

    protected $guarded = [];

    protected $casts = [
        'match_type' => KnowledgeSuggestionMatchType::class,
        'matched_terms' => 'array',
        'was_viewed' => 'boolean',
        'was_helpful' => 'boolean',
        'viewed_at' => 'datetime',
        'relevance_score' => 'float',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class);
    }

    public function markAsViewed(): void
    {
        if (!$this->was_viewed) {
            $this->update([
                'was_viewed' => true,
                'viewed_at' => now(),
            ]);

            $this->article->incrementViewCount();
        }
    }

    public function markAsHelpful(bool $helpful = true): void
    {
        $this->update(['was_helpful' => $helpful]);

        if ($helpful) {
            $this->article->markAsHelpful();
        } else {
            $this->article->markAsNotHelpful();
        }
    }

    public function getWeightedScore(): float
    {
        return $this->relevance_score * $this->match_type->weight();
    }

    #[Scope]
    public function viewed(Builder $query): void
    {
        $query->where('was_viewed', true);
    }

    #[Scope]
    public function helpful(Builder $query): void
    {
        $query->where('was_helpful', true);
    }

    #[Scope]
    public function notHelpful(Builder $query): void
    {
        $query->where('was_helpful', false);
    }

    #[Scope]
    public function byMatchType(Builder $query, KnowledgeSuggestionMatchType|string $type): void
    {
        $value = $type instanceof KnowledgeSuggestionMatchType ? $type->value : $type;
        $query->where('match_type', $value);
    }

    #[Scope]
    public function topSuggestions(Builder $query, int $limit = 5): void
    {
        $query->orderByDesc('relevance_score')->limit($limit);
    }

    #[Scope]
    public function forTicket(Builder $query, int|Ticket $ticket): void
    {
        $ticketId = $ticket instanceof Ticket ? $ticket->id : $ticket;
        $query->where('ticket_id', $ticketId);
    }
}