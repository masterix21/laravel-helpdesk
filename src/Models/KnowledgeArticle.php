<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeArticleRelationType;
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeArticleStatus;

class KnowledgeArticle extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_knowledge_articles';

    protected $guarded = [];

    protected $casts = [
        'status' => KnowledgeArticleStatus::class,
        'is_featured' => 'boolean',
        'is_faq' => 'boolean',
        'is_public' => 'boolean',
        'keywords' => 'array',
        'meta' => 'array',
        'published_at' => 'datetime',
        'last_reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $article): void {
            if ($article->ulid === null) {
                $article->ulid = (string) Str::ulid();
            }

            if ($article->slug === null && $article->title !== null) {
                $article->slug = Str::slug($article->title);
            }
        });

        static::updating(static function (self $article): void {
            if ($article->isDirty('status') && $article->status === KnowledgeArticleStatus::Published) {
                $article->published_at = $article->published_at ?? now();
            }
        });
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeSection::class,
            'helpdesk_knowledge_article_sections',
            'article_id',
            'section_id'
        )->withPivot('position')->withTimestamps();
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(
            Ticket::class,
            'helpdesk_knowledge_article_tickets',
            'article_id',
            'ticket_id'
        )->withPivot(['was_helpful', 'resolved_issue', 'linked_by_type', 'linked_by_id'])
            ->withTimestamps();
    }

    public function relatedArticles(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'helpdesk_knowledge_article_relations',
            'article_id',
            'related_article_id'
        )->withPivot(['relation_type', 'position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function relatedTo(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'helpdesk_knowledge_article_relations',
            'related_article_id',
            'article_id'
        )->withPivot(['relation_type', 'position'])
            ->withTimestamps();
    }

    public function suggestions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KnowledgeSuggestion::class, 'article_id');
    }

    public function getEffectivenessScore(): float
    {
        if ($this->effectiveness_score !== null) {
            return $this->effectiveness_score;
        }

        $total = $this->helpful_count + $this->not_helpful_count;

        if ($total === 0) {
            return 0;
        }

        return round(($this->helpful_count / $total) * 100, 2);
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function markAsHelpful(): void
    {
        $this->increment('helpful_count');
        $this->updateEffectivenessScore();
    }

    public function markAsNotHelpful(): void
    {
        $this->increment('not_helpful_count');
        $this->updateEffectivenessScore();
    }

    protected function updateEffectivenessScore(): void
    {
        $this->effectiveness_score = $this->getEffectivenessScore();
        $this->saveQuietly();
    }

    public function isPublished(): bool
    {
        return $this->status === KnowledgeArticleStatus::Published
            && $this->is_public
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function needsReview(): bool
    {
        if ($this->last_reviewed_at === null) {
            return $this->created_at->diffInDays() > 90;
        }

        return $this->last_reviewed_at->diffInDays() > 90;
    }

    public function attachToTicket(Ticket $ticket, array $pivotData = []): void
    {
        $this->tickets()->syncWithoutDetaching([
            $ticket->id => array_merge([
                'linked_by_type' => auth()->user()?->getMorphClass(),
                'linked_by_id' => auth()->id(),
            ], $pivotData),
        ]);
    }

    public function addRelatedArticle(self $article, KnowledgeArticleRelationType $type = KnowledgeArticleRelationType::Related, int $position = 0): void
    {
        $this->relatedArticles()->syncWithoutDetaching([
            $article->id => [
                'relation_type' => $type->value,
                'position' => $position,
            ],
        ]);
    }

    #[Scope]
    public function published(Builder $query): void
    {
        $query->where('status', KnowledgeArticleStatus::Published)
            ->where('is_public', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    #[Scope]
    public function featured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    #[Scope]
    public function faq(Builder $query): void
    {
        $query->where('is_faq', true);
    }

    #[Scope]
    public function mostHelpful(Builder $query): void
    {
        $query->orderByDesc('effectiveness_score')
            ->orderByDesc('helpful_count');
    }

    #[Scope]
    public function popular(Builder $query): void
    {
        $query->orderByDesc('view_count');
    }

    #[Scope]
    public function recent(Builder $query): void
    {
        $query->orderByDesc('published_at');
    }

    #[Scope]
    public function needingReview(Builder $query): void
    {
        $query->where(function ($q) {
            $q->whereNull('last_reviewed_at')
                ->where('created_at', '<=', now()->subDays(90))
                ->orWhere('last_reviewed_at', '<=', now()->subDays(90));
        });
    }

    #[Scope]
    public function inSection(Builder $query, int|KnowledgeSection $section): void
    {
        $sectionId = $section instanceof KnowledgeSection ? $section->id : $section;

        $query->whereHas('sections', function ($q) use ($sectionId) {
            $q->where('section_id', $sectionId);
        });
    }

    #[Scope]
    public function search(Builder $query, string $term): void
    {
        $query->where(function ($q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
                ->orWhere('content', 'LIKE', "%{$term}%")
                ->orWhere('excerpt', 'LIKE', "%{$term}%")
                ->orWhereJsonContains('keywords', $term);
        });
    }
}
