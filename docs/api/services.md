# Service Classes

The package provides service classes for managing different aspects of the helpdesk system.

## Core Services

### TicketService

Main service for ticket operations.

```php
use LucaLongo\LaravelHelpdesk\Services\TicketService;

class TicketService {
    // Create a new ticket
    public function open(array $attributes, ?Model $openedBy = null): Ticket

    // Update ticket attributes
    public function update(Ticket $ticket, array $attributes): Ticket

    // Change ticket status
    public function transition(Ticket $ticket, TicketStatus $next): Ticket

    // Assign ticket to user
    public function assign(Ticket $ticket, ?Model $assignee): Ticket

    // Merge tickets
    public function mergeTickets(Ticket $target, Ticket|array $sources, ?string $reason = null): Ticket

    // Create ticket relation
    public function createRelation(Ticket $ticket, Ticket $related, TicketRelationType $type, ?string $notes = null): TicketRelation

    // Remove ticket relation
    public function removeRelation(Ticket $ticket, Ticket $related, TicketRelationType $type): void

    // Create child ticket
    public function createChildTicket(Ticket $parent, array $attributes, ?Model $openedBy = null): Ticket

    // Move ticket to different parent
    public function moveToParent(Ticket $ticket, ?Ticket $newParent): Ticket
}
```

### CommentService

Manages ticket comments and internal notes.

```php
use LucaLongo\LaravelHelpdesk\Services\CommentService;

class CommentService {
    // Add comment to ticket
    public function store(Ticket $ticket, string $content, ?Model $author = null, bool $isPublic = true): TicketComment

    // Update existing comment
    public function update(TicketComment $comment, string $content): TicketComment

    // Delete comment
    public function delete(TicketComment $comment): bool

    // Get comments for ticket
    public function getComments(Ticket $ticket, bool $publicOnly = false): Collection
}
```

### SlaService

Handles SLA calculations and compliance checking.

```php
use LucaLongo\LaravelHelpdesk\Services\SlaService;

class SlaService {
    // Calculate SLA due dates for ticket
    public function calculateSlaDueDates(Ticket $ticket): Ticket

    // Check SLA compliance
    public function checkCompliance(Ticket $ticket): void

    // Record first response
    public function recordFirstResponse(Ticket $ticket): bool

    // Record resolution
    public function recordResolution(Ticket $ticket): bool

    // Get overdue tickets
    public function getOverdueTickets(): Collection
}
```

## Feature Services

### AutomationService

Manages automation rules and executes automated actions.

```php
use LucaLongo\LaravelHelpdesk\Services\AutomationService;

class AutomationService {
    // Create automation rule
    public function createRule(array $data): AutomationRule

    // Process tickets with automation rules
    public function processTickets(Collection $tickets, string $trigger): void

    // Execute specific rule on tickets
    public function executeRule(AutomationRule $rule, Collection $tickets): Collection

    // Apply template to create rule
    public function applyTemplate(string $templateKey, array $overrides = []): AutomationRule
}
```

### CategoryService

Manages hierarchical categories.

```php
use LucaLongo\LaravelHelpdesk\Services\CategoryService;

class CategoryService {
    // Create category
    public function create(array $data): Category

    // Create subcategory
    public function createSubcategory(Category $parent, array $data): Category

    // Move category to new parent
    public function move(Category $category, ?Category $newParent): Category

    // Merge categories
    public function merge(Category $source, Category $target): void

    // Duplicate category with children
    public function duplicate(Category $category, ?string $nameSuffix = ' (Copy)'): Category

    // Get category tree
    public function getTree(): Collection

    // Search categories
    public function search(string $query): Collection
}
```

### TagService

Manages tags and tagging.

```php
use LucaLongo\LaravelHelpdesk\Services\TagService;

class TagService {
    // Create or find tag by name
    public function findOrCreateByName(string $name): Tag

    // Attach tags to ticket
    public function attachToTicket(Ticket $ticket, array|Tag $tags): void

    // Detach tags from ticket
    public function detachFromTicket(Ticket $ticket, array|Tag $tags): void

    // Sync tags for ticket
    public function syncForTicket(Ticket $ticket, array $tags): void

    // Get popular tags
    public function getPopular(int $limit = 10): Collection

    // Get unused tags
    public function getUnused(): Collection

    // Merge tags
    public function merge(Tag $source, Tag $target): void

    // Cleanup unused tags
    public function cleanupUnused(): int

    // Generate tag cloud
    public function getTagCloud(int $limit = 30): Collection
}
```

### TimeTrackingService

Tracks time spent on tickets.

```php
use LucaLongo\LaravelHelpdesk\Services\TimeTrackingService;

class TimeTrackingService {
    // Start timer for ticket
    public function startTimer(Ticket $ticket, Model $user, ?string $description = null): TicketTimeEntry

    // Stop running timer
    public function stopTimer(TicketTimeEntry $entry): ?TicketTimeEntry

    // Log time manually
    public function logTime(Ticket $ticket, Model $user, int $minutes, array $data = []): TicketTimeEntry

    // Get total time for ticket
    public function getTotalTime(Ticket $ticket, bool $billableOnly = false): int

    // Get total cost for ticket
    public function getTotalCost(Ticket $ticket): float

    // Generate time report for user
    public function getUserReport(Model $user, ?Carbon $from = null, ?Carbon $to = null): array

    // Generate project time report
    public function getProjectReport(?Carbon $from = null, ?Carbon $to = null): Collection
}
```

### RatingService

Manages customer satisfaction ratings.

```php
use LucaLongo\LaravelHelpdesk\Services\RatingService;

class RatingService {
    // Submit rating for ticket
    public function submitRating(Ticket $ticket, Model $rater, int $rating, ?string $feedback = null): TicketRating

    // Check if user can rate ticket
    public function canRate(Ticket $ticket, Model $user): bool

    // Get average rating
    public function getAverageRating(?Carbon $from = null, ?Carbon $to = null): ?float

    // Calculate CSAT score
    public function calculateCSAT(?Carbon $from = null, ?Carbon $to = null): ?float

    // Calculate NPS score
    public function calculateNPS(?Carbon $from = null, ?Carbon $to = null): ?float

    // Get recent feedback
    public function getRecentFeedback(int $limit = 10, ?int $minRating = null, ?int $maxRating = null): Collection

    // Get comprehensive metrics
    public function getMetrics(?Carbon $from = null, ?Carbon $to = null): array
}
```

### BulkActionService

Handles bulk operations on multiple tickets.

```php
use LucaLongo\LaravelHelpdesk\Services\BulkActionService;

class BulkActionService {
    // Filter tickets for bulk action
    public function filterTickets(array $filters): Collection

    // Perform bulk action
    public function performAction(Collection $tickets, string $action, array $params = []): array

    // Available actions
    public const ALLOWED_ACTIONS = [
        'change_status',
        'change_priority',
        'assign',
        'unassign',
        'add_tags',
        'remove_tags',
        'add_category',
        'close',
    ];
}
```

### ResponseTemplateService

Manages response templates.

```php
use LucaLongo\LaravelHelpdesk\Services\ResponseTemplateService;

class ResponseTemplateService {
    // Create template
    public function create(array $data): ResponseTemplate

    // Get active templates
    public function getActiveTemplates(): Collection

    // Get template by slug
    public function getBySlug(string $slug): ?ResponseTemplate

    // Apply template with variables
    public function applyTemplate(string $slug, array $variables = []): ?string

    // Render template content
    public function render(ResponseTemplate $template, array $variables = []): string

    // Create default templates
    public function createDefaultTemplates(): void
}
```

### SubscriptionService

Manages ticket subscriptions and notifications.

```php
use LucaLongo\LaravelHelpdesk\Services\SubscriptionService;

class SubscriptionService {
    // Subscribe to ticket
    public function subscribe(Ticket $ticket, Model $subscriber, ?array $events = null): TicketSubscription

    // Unsubscribe from ticket
    public function unsubscribe(Ticket $ticket, Model $subscriber): bool

    // Check if subscribed
    public function isSubscribed(Ticket $ticket, Model $subscriber): bool

    // Notify subscribers
    public function notifySubscribers(Ticket $ticket, TicketStatus $newStatus): void

    // Get ticket subscribers
    public function getSubscribers(Ticket $ticket): Collection
}
```

### WorkflowService

Manages custom workflows.

```php
use LucaLongo\LaravelHelpdesk\Services\WorkflowService;

class WorkflowService {
    // Register workflow
    public function register(string $name, Closure $handler): void

    // Execute workflow
    public function execute(string $name, Ticket $ticket, array $data = []): mixed

    // Check if workflow exists
    public function has(string $name): bool

    // Get all registered workflows
    public function all(): array
}
```

### KnowledgeService

Manages knowledge base and FAQ generation.

```php
use LucaLongo\LaravelHelpdesk\Services\KnowledgeService;

class KnowledgeService {
    // Generate FAQ candidates from resolved tickets
    public function generateFaqCandidates(int $minOccurrences = 3, int $daysBack = 90): Collection

    // Find similar resolved tickets
    public function findSimilarResolved(string $query, int $limit = 5): Collection

    // Suggest knowledge articles for ticket
    public function suggestArticles(Ticket $ticket, int $limit = 3): Collection

    // Link ticket to knowledge article
    public function linkArticle(Ticket $ticket, KnowledgeArticle $article): void
}
```

## AI Services

### AIService

Provides AI-powered analysis and suggestions.

```php
use LucaLongo\LaravelHelpdesk\AI\AIService;

class AIService {
    // Analyze ticket with AI
    public function analyze(Ticket $ticket): ?AIAnalysis

    // Generate response suggestion
    public function generateSuggestion(Ticket $ticket): ?string

    // Find similar tickets using AI
    public function findSimilarTickets(Ticket $ticket): ?array
}
```

### AIProviderSelector

Manages AI provider selection with round-robin.

```php
use LucaLongo\LaravelHelpdesk\AI\AIProviderSelector;

class AIProviderSelector {
    // Select next available provider
    public function selectProvider(?string $requiredCapability = null): ?string
}
```

## Using Services

### Via Dependency Injection

```php
class TicketController extends Controller
{
    public function __construct(
        private TicketService $tickets,
        private CommentService $comments,
        private SlaService $sla
    ) {}

    public function store(Request $request)
    {
        $ticket = $this->tickets->open($request->validated());
        $this->sla->calculateSlaDueDates($ticket);
        return $ticket;
    }
}
```

### Via Service Container

```php
$ticketService = app(TicketService::class);
$ticket = $ticketService->open([...]);
```

### Via Facade

```php
use LucaLongo\LaravelHelpdesk\Facades\LaravelHelpdesk;

$ticket = LaravelHelpdesk::open([...]);
LaravelHelpdesk::comment($ticket, 'Response text');
```