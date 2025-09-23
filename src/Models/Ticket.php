<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Kalnoy\Nestedset\NodeTrait;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;

/**
 * @property int $id
 * @property string $ulid
 * @property TicketType $type
 * @property string $subject
 * @property ?string $description
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property ?string $ticket_number
 * @property ?string $customer_name
 * @property ?string $customer_email
 * @property ?\ArrayObject $meta
 * @property ?\DateTimeInterface $opened_at
 * @property ?\DateTimeInterface $closed_at
 * @property ?\DateTimeInterface $due_at
 * @property ?\DateTimeInterface $first_response_at
 * @property ?\DateTimeInterface $first_response_due_at
 * @property ?\DateTimeInterface $resolution_due_at
 * @property bool $sla_breached
 * @property ?string $sla_breach_type
 * @property ?int $response_time_minutes
 * @property ?int $resolution_time_minutes
 * @property ?string $opened_by_type
 * @property ?int $opened_by_id
 * @property ?string $assigned_to_type
 * @property ?int $assigned_to_id
 * @property ?int $merged_to_id
 * @property ?\DateTimeInterface $merged_at
 * @property ?string $merge_reason
 * @property ?int $parent_id
 * @property \DateTimeInterface $created_at
 * @property \DateTimeInterface $updated_at
 */
class Ticket extends Model
{
    use HasFactory, NodeTrait;

    protected $table = 'helpdesk_tickets';

    protected $fillable = [
        'type',
        'subject',
        'description',
        'status',
        'priority',
        'ticket_number',
        'customer_name',
        'customer_email',
        'meta',
        'opened_at',
        'closed_at',
        'due_at',
        'first_response_at',
        'first_response_due_at',
        'resolution_due_at',
        'sla_breached',
        'sla_breach_type',
        'response_time_minutes',
        'resolution_time_minutes',
        'opened_by_type',
        'opened_by_id',
        'assigned_to_type',
        'assigned_to_id',
        'merged_to_id',
        'merged_at',
        'merge_reason',
        'parent_id',
    ];

    protected $attributes = [
        'status' => TicketStatus::Open,
    ];

    protected $casts = [
        'type' => TicketType::class,
        'status' => TicketStatus::class,
        'priority' => TicketPriority::class,
        'meta' => AsArrayObject::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'first_response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'sla_breached' => 'boolean',
        'merged_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $ticket): void {
            $ticket->ulid ??= (string) Str::ulid();
            $ticket->opened_at ??= now();
        });
    }

    public function opener(): MorphTo
    {
        return $this->morphTo('opened_by');
    }

    public function assignee(): MorphTo
    {
        return $this->morphTo('assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TicketSubscription::class);
    }

    public function rating(): HasOne
    {
        return $this->hasOne(TicketRating::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TicketTimeEntry::class);
    }

    public function knowledgeArticles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeArticle::class,
            'helpdesk_knowledge_article_tickets',
            'ticket_id',
            'article_id'
        )->withPivot(['was_helpful', 'resolved_issue', 'linked_by_type', 'linked_by_id'])
            ->withTimestamps();
    }

    public function knowledgeSuggestions(): HasMany
    {
        return $this->hasMany(KnowledgeSuggestion::class);
    }

    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'helpdesk_ticket_categories',
            'ticket_id',
            'category_id'
        )->withTimestamps();
    }

    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'helpdesk_ticket_tags',
            'ticket_id',
            'tag_id'
        )->withTimestamps();
    }

    public function mergedTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_to_id');
    }

    public function mergedTickets(): HasMany
    {
        return $this->hasMany(self::class, 'merged_to_id');
    }

    public function relations(): HasMany
    {
        return $this->hasMany(TicketRelation::class, 'ticket_id');
    }

    public function relatedTickets(): HasMany
    {
        return $this->hasMany(TicketRelation::class, 'related_ticket_id');
    }

    public function getAllRelations(): \Illuminate\Support\Collection
    {
        $directRelations = $this->relations;
        $inverseRelations = $this->relatedTickets;

        return $directRelations->merge($inverseRelations);
    }

    public function isMerged(): bool
    {
        return $this->merged_to_id !== null;
    }

    public function canBeMerged(): bool
    {
        return ! $this->isMerged() && ! $this->hasDescendants();
    }

    public function isChild(): bool
    {
        return $this->parent_id !== null;
    }

    public function transitionTo(TicketStatus $next): bool
    {
        if ($this->status->canTransitionTo($next) === false) {
            return false;
        }

        if ($this->status === $next) {
            return false;
        }

        $this->status = $next;
        $this->closed_at = $next->isTerminal() ? now() : null;

        return $this->save();
    }

    public function assignTo(?Model $assignee): bool
    {
        if ($assignee === null) {
            return $this->releaseAssignment();
        }

        if ($this->isAssignedTo($assignee)) {
            return false;
        }

        $this->assigned_to_type = $assignee->getMorphClass();
        $this->assigned_to_id = $assignee->getKey();

        return $this->save();
    }

    public function releaseAssignment(): bool
    {
        if ($this->assigned_to_type === null && $this->assigned_to_id === null) {
            return false;
        }

        $this->assigned_to_type = null;
        $this->assigned_to_id = null;

        return $this->save();
    }

    public function isAssignedTo(Model $assignee): bool
    {
        if (! $this->assigned_to_type || ! $this->assigned_to_id) {
            return false;
        }

        return $this->assigned_to_type === $assignee->getMorphClass()
            && (string) $this->assigned_to_id === (string) $assignee->getKey();
    }

    public function shouldQueueSlaAlert(): bool
    {
        if (! $this->due_at || $this->status->isTerminal()) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->due_at);
    }

    #[Scope]
    public function forType(Builder $query, TicketType|string $type): void
    {
        $value = $type instanceof TicketType ? $type->value : $type;

        $query->where('type', $value);
    }

    #[Scope]
    public function withPriority(Builder $query, TicketPriority|string $priority): void
    {
        $value = $priority instanceof TicketPriority ? $priority->value : $priority;

        $query->where('priority', $value);
    }

    #[Scope]
    public function open(Builder $query): void
    {
        $query->where('status', TicketStatus::Open->value);
    }

    #[Scope]
    public function approachingSla(Builder $query, int $withinMinutes = 60): void
    {
        $threshold = now()->addMinutes($withinMinutes);

        $query->where('sla_breached', false)
            ->where(function (Builder $q) use ($threshold) {
                $q->where(function (Builder $subQuery) use ($threshold) {
                    $subQuery->whereNotNull('first_response_due_at')
                        ->whereNull('first_response_at')
                        ->where('first_response_due_at', '<=', $threshold);
                })->orWhere(function (Builder $subQuery) use ($threshold) {
                    $subQuery->whereNotNull('resolution_due_at')
                        ->whereNull('closed_at')
                        ->where('resolution_due_at', '<=', $threshold);
                });
            });
    }

    #[Scope]
    public function overdueSla(Builder $query): void
    {
        $query->where('sla_breached', false)
            ->where(function (Builder $q) {
                $q->where(function (Builder $subQuery) {
                    $subQuery->whereNotNull('first_response_due_at')
                        ->whereNull('first_response_at')
                        ->where('first_response_due_at', '<', now());
                })->orWhere(function (Builder $subQuery) {
                    $subQuery->whereNotNull('resolution_due_at')
                        ->whereNull('closed_at')
                        ->where('resolution_due_at', '<', now());
                });
            });
    }

    #[Scope]
    public function breachedSla(Builder $query): void
    {
        $query->where('sla_breached', true);
    }

    #[Scope]
    public function withCategories(Builder $query, array|int $categoryIds): void
    {
        $query->whereHas('categories', function (Builder $q) use ($categoryIds) {
            $q->whereIn('category_id', (array) $categoryIds);
        });
    }

    #[Scope]
    public function withAllCategories(Builder $query, array $categoryIds): void
    {
        foreach ($categoryIds as $categoryId) {
            $query->whereHas('categories', function (Builder $q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }
    }

    #[Scope]
    public function withTags(Builder $query, array|int $tagIds): void
    {
        $query->whereHas('tags', function (Builder $q) use ($tagIds) {
            $q->whereIn('tag_id', (array) $tagIds);
        });
    }

    #[Scope]
    public function withAllTags(Builder $query, array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            $query->whereHas('tags', function (Builder $q) use ($tagId) {
                $q->where('tag_id', $tagId);
            });
        }
    }

    #[Scope]
    public function inCategory(Builder $query, int|Category $category): void
    {
        $categoryId = $category instanceof Category ? $category->id : $category;

        if ($category instanceof Category) {
            $descendantIds = $category->getAllDescendants()->pluck('id')->toArray();
            $categoryIds = array_merge([$categoryId], $descendantIds);

            $query->whereHas('categories', function (Builder $q) use ($categoryIds) {
                $q->whereIn('category_id', $categoryIds);
            });
        } else {
            $query->whereHas('categories', function (Builder $q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }
    }

    #[Scope]
    public function withinSla(Builder $query): void
    {
        $query->where('sla_breached', false)
            ->where(function (Builder $q) {
                $q->where(function (Builder $subQuery) {
                    $subQuery->whereNull('first_response_due_at')
                        ->orWhereNotNull('first_response_at')
                        ->orWhere('first_response_due_at', '>=', now());
                })->where(function (Builder $subQuery) {
                    $subQuery->whereNull('resolution_due_at')
                        ->orWhereNotNull('closed_at')
                        ->orWhere('resolution_due_at', '>=', now());
                });
            });
    }

    #[Scope]
    public function withAllRelations(Builder $query): void
    {
        $query->with([
            'opener',
            'assignee',
            'comments.author',
            'attachments',
            'subscriptions',
            'categories',
            'tags',
            'rating',
        ]);
    }

    #[Scope]
    public function withEssentialRelations(Builder $query): void
    {
        $query->with([
            'opener',
            'assignee',
            'categories',
            'tags',
        ]);
    }

    public function isFirstResponseOverdue(): bool
    {
        if ($this->first_response_at || ! $this->first_response_due_at) {
            return false;
        }

        return now()->greaterThan($this->first_response_due_at);
    }

    public function isResolutionOverdue(): bool
    {
        if ($this->status->isTerminal() || ! $this->resolution_due_at) {
            return false;
        }

        return now()->greaterThan($this->resolution_due_at);
    }

    public function getSlaCompliancePercentage(string $type = 'first_response'): ?float
    {
        $dueField = $type === 'first_response' ? 'first_response_due_at' : 'resolution_due_at';
        $responseField = $type === 'first_response' ? 'first_response_at' : 'closed_at';

        if (! $this->$dueField) {
            return null;
        }

        $totalMinutes = $this->opened_at->diffInMinutes($this->$dueField);
        if ($totalMinutes === 0) {
            return 0;
        }

        $usedMinutes = $this->opened_at->diffInMinutes($this->$responseField ?? now());
        $percentage = (1 - ($usedMinutes / $totalMinutes)) * 100;

        return max(0, min(100, $percentage));
    }

    public function markFirstResponse(): bool
    {
        if ($this->first_response_at !== null) {
            return false;
        }

        $this->first_response_at = now();
        $this->response_time_minutes = $this->opened_at->diffInMinutes($this->first_response_at);

        if ($this->first_response_due_at && $this->first_response_at->greaterThan($this->first_response_due_at)) {
            $this->sla_breached = true;
            $this->sla_breach_type = 'first_response';
        }

        return $this->save();
    }

    public function markResolution(): bool
    {
        if (! $this->status->isTerminal()) {
            return false;
        }

        $this->resolution_time_minutes = $this->opened_at->diffInMinutes($this->closed_at ?? now());

        if ($this->resolution_due_at && ($this->closed_at ?? now())->greaterThan($this->resolution_due_at)) {
            $this->sla_breached = true;
            $this->sla_breach_type = $this->sla_breach_type ?? 'resolution';
        }

        return $this->save();
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
