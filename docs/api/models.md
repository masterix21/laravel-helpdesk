# Models

Complete reference for all Eloquent models provided by the package.

## Core Models

### Ticket

Main ticket model with nested set support for hierarchical organization.

```php
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class Ticket extends Model
{
    use HasFactory, NodeTrait; // Nested set support

    protected $table = 'helpdesk_tickets';

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
        'merged_at' => 'datetime',
        'sla_breached' => 'boolean',
    ];

    // Relationships
    public function opener(): MorphTo
    public function assignee(): MorphTo
    public function comments(): HasMany
    public function attachments(): HasMany
    public function subscriptions(): HasMany
    public function rating(): HasOne
    public function timeEntries(): HasMany
    public function aiAnalyses(): HasMany
    public function mergedTo(): BelongsTo
    public function mergedTickets(): HasMany
    public function relatedTickets(): BelongsToMany
    public function categories(): BelongsToMany
    public function tags(): BelongsToMany
    public function knowledgeArticles(): BelongsToMany

    // Scopes
    public function scopeOpen(Builder $query)
    public function scopeResolved(Builder $query)
    public function scopeClosed(Builder $query)
    public function scopeOverdue(Builder $query)
    public function scopeWithinSla(Builder $query)
    public function scopeBreachedSla(Builder $query)
    public function scopeApproachingSla(Builder $query, int $percentage)
    public function scopeOverdueSla(Builder $query)
    public function scopeWithCategories(Builder $query, array|int $categoryIds)
    public function scopeWithTags(Builder $query, array|int $tagIds)

    // Methods
    public function transitionTo(TicketStatus $status): bool
    public function assignTo(?Model $assignee): bool
    public function releaseAssignment(): bool
    public function isMerged(): bool
    public function slaCompliancePercentage(): ?float
    public function recordFirstResponse(): bool
    public function recordResolution(): bool
    public function getLatestAIAnalysis(): ?AIAnalysis
    public function analyzeWithAI(): ?AIAnalysis
    public function getAISuggestedResponse(): ?string
    public function findSimilarTickets(): ?array
}
```

### TicketComment

Comments and internal notes on tickets.

```php
use LucaLongo\LaravelHelpdesk\Models\TicketComment;

class TicketComment extends Model
{
    protected $table = 'helpdesk_ticket_comments';

    protected $casts = [
        'is_public' => 'boolean',
        'is_pinned' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function ticket(): BelongsTo
    public function author(): MorphTo
    public function attachments(): HasMany

    // Scopes
    public function scopePublic(Builder $query)
    public function scopeInternal(Builder $query)
    public function scopePinned(Builder $query)
}
```

### TicketAttachment

File attachments for tickets and comments.

```php
use LucaLongo\LaravelHelpdesk\Models\TicketAttachment;

class TicketAttachment extends Model
{
    protected $table = 'helpdesk_ticket_attachments';

    protected $casts = [
        'size' => 'integer',
        'metadata' => 'array',
    ];

    // Relationships
    public function ticket(): BelongsTo
    public function comment(): BelongsTo
    public function uploader(): MorphTo

    // Accessors
    public function getUrlAttribute(): string
    public function getSizeForHumansAttribute(): string
}
```

## Organization Models

### Category

Hierarchical categories using nested sets.

```php
use LucaLongo\LaravelHelpdesk\Models\Category;

class Category extends Model
{
    use HasFactory, NodeTrait;

    protected $table = 'helpdesk_categories';

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function tickets(): BelongsToMany

    // Methods
    public function getPath(): Collection
    public function getFullNameAttribute(): string
}
```

### Tag

Flexible tagging system.

```php
use LucaLongo\LaravelHelpdesk\Models\Tag;

class Tag extends Model
{
    protected $table = 'helpdesk_tags';

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function tickets(): BelongsToMany

    // Accessors
    public function getUsageCountAttribute(): int
}
```

## Feature Models

### TicketRating

Customer satisfaction ratings.

```php
use LucaLongo\LaravelHelpdesk\Models\TicketRating;

class TicketRating extends Model
{
    protected $table = 'helpdesk_ticket_ratings';

    protected $casts = [
        'rating' => 'integer',
        'metadata' => 'array',
        'rated_at' => 'datetime',
    ];

    // Relationships
    public function ticket(): BelongsTo
    public function rater(): MorphTo

    // Methods
    public function isPositive(): bool
    public function isNeutral(): bool
    public function isNegative(): bool
    public function getStarsAttribute(): string
    public function getSatisfactionLevelAttribute(): string
}
```

### TicketTimeEntry

Time tracking entries.

```php
use LucaLongo\LaravelHelpdesk\Models\TicketTimeEntry;

class TicketTimeEntry extends Model
{
    protected $table = 'helpdesk_ticket_time_entries';

    protected $casts = [
        'minutes' => 'integer',
        'is_billable' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Relationships
    public function ticket(): BelongsTo
    public function user(): MorphTo

    // Accessors
    public function getDurationHoursAttribute(): float
    public function getTotalCostAttribute(): float
    public function getIsRunningAttribute(): bool
}
```

### TicketSubscription

User subscriptions for notifications.

```php
use LucaLongo\LaravelHelpdesk\Models\TicketSubscription;

class TicketSubscription extends Model
{
    protected $table = 'helpdesk_ticket_subscriptions';

    protected $casts = [
        'events' => 'array',
        'metadata' => 'array',
    ];

    // Relationships
    public function ticket(): BelongsTo
    public function subscriber(): MorphTo

    // Scopes
    public function scopeForEvent(Builder $query, string $event)
}
```

### TicketRelation

Relationships between tickets.

```php
use LucaLongo\LaravelHelpdesk\Models\TicketRelation;

class TicketRelation extends Model
{
    protected $table = 'helpdesk_ticket_relations';

    protected $casts = [
        'relation_type' => TicketRelationType::class,
    ];

    // Relationships
    public function ticket(): BelongsTo
    public function relatedTicket(): BelongsTo
}
```

## Automation Models

### AutomationRule

Automation rule definitions.

```php
use LucaLongo\LaravelHelpdesk\Models\AutomationRule;

class AutomationRule extends Model
{
    protected $table = 'helpdesk_automation_rules';

    protected $casts = [
        'is_active' => 'boolean',
        'conditions' => 'array',
        'actions' => 'array',
        'priority' => 'integer',
        'stop_processing' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function executions(): HasMany

    // Scopes
    public function scopeActive(Builder $query)
    public function scopeForTrigger(Builder $query, string $trigger)
}
```

### AutomationExecution

Automation execution history.

```php
use LucaLongo\LaravelHelpdesk\Models\AutomationExecution;

class AutomationExecution extends Model
{
    protected $table = 'helpdesk_automation_executions';

    protected $casts = [
        'success' => 'boolean',
        'conditions_met' => 'boolean',
        'actions_data' => 'array',
        'error_message' => 'string',
        'executed_at' => 'datetime',
    ];

    // Relationships
    public function rule(): BelongsTo
    public function ticket(): BelongsTo
    public function executor(): MorphTo
}
```

## Content Models

### ResponseTemplate

Pre-defined response templates.

```php
use LucaLongo\LaravelHelpdesk\Models\ResponseTemplate;

class ResponseTemplate extends Model
{
    protected $table = 'helpdesk_response_templates';

    protected $casts = [
        'is_active' => 'boolean',
        'variables' => 'array',
        'metadata' => 'array',
        'ticket_types' => 'array',
    ];

    // Scopes
    public function scopeActive(Builder $query)
    public function scopeForType(Builder $query, ?string $type)
}
```

### KnowledgeArticle

Knowledge base articles.

```php
use LucaLongo\LaravelHelpdesk\Models\KnowledgeArticle;

class KnowledgeArticle extends Model
{
    protected $table = 'helpdesk_knowledge_articles';

    protected $casts = [
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'view_count' => 'integer',
        'helpful_count' => 'integer',
        'tags' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
    ];

    // Relationships
    public function section(): BelongsTo
    public function author(): MorphTo
    public function tickets(): BelongsToMany

    // Scopes
    public function scopePublished(Builder $query)
    public function scopeFeatured(Builder $query)
}
```

## AI Models

### AIAnalysis

AI-powered ticket analysis results.

```php
use LucaLongo\LaravelHelpdesk\Models\AIAnalysis;

class AIAnalysis extends Model
{
    protected $table = 'helpdesk_ai_analyses';

    protected $casts = [
        'keywords' => 'array',
        'raw_response' => 'array',
        'confidence' => 'float',
        'processing_time_ms' => 'integer',
    ];

    // Relationships
    public function ticket(): BelongsTo

    // Methods
    public static function fromResponse(
        string $response,
        array $capabilities,
        ?string $provider = null,
        ?string $model = null,
        ?int $processingTime = null
    ): self

    public function toSimpleArray(): array
}
```

## Model Traits

### Common Features

Most models include:
- ULIDs as primary keys
- Timestamps (`created_at`, `updated_at`)
- Factory support for testing
- Query scopes for common filters
- Relationship methods
- Accessor methods for computed properties

### Usage Examples

```php
// Query with relationships
$ticket = Ticket::with(['comments', 'assignee', 'tags'])
    ->open()
    ->overdue()
    ->first();

// Use scopes
$urgentTickets = Ticket::where('priority', TicketPriority::Urgent)
    ->breachedSla()
    ->get();

// Access relationships
foreach ($ticket->comments as $comment) {
    if ($comment->is_public) {
        echo $comment->content;
    }
}

// Use accessors
echo $ticket->slaCompliancePercentage(); // 75.5
echo $rating->satisfaction_level; // "satisfied"
echo $timeEntry->duration_hours; // 2.5
```