# Events

The package dispatches events for all major operations, allowing you to hook into the lifecycle of tickets and related entities.

## Ticket Events

### TicketCreated

Fired when a new ticket is created.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketCreated;

class TicketCreated {
    public function __construct(
        public Ticket $ticket
    ) {}
}

// Listen for event
Event::listen(TicketCreated::class, function (TicketCreated $event) {
    $ticket = $event->ticket;
    // Send notification, log activity, etc.
});
```

### TicketStatusChanged

Fired when ticket status changes.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketStatusChanged;

class TicketStatusChanged {
    public function __construct(
        public Ticket $ticket,
        public TicketStatus $previous,
        public TicketStatus $next
    ) {}
}

Event::listen(TicketStatusChanged::class, function ($event) {
    if ($event->next === TicketStatus::Closed) {
        // Ticket was closed
    }
});
```

### TicketAssigned

Fired when ticket is assigned to a user.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketAssigned;

class TicketAssigned {
    public function __construct(
        public Ticket $ticket,
        public ?Model $assignee
    ) {}
}
```

### TicketCommentAdded

Fired when a comment is added to a ticket.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketCommentAdded;

class TicketCommentAdded {
    public function __construct(
        public TicketComment $comment,
        public Ticket $ticket
    ) {}
}
```

### TicketEscalated

Fired when a ticket is escalated.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketEscalated;

class TicketEscalated {
    public function __construct(
        public Ticket $ticket,
        public string $reason,
        public ?Model $escalatedBy = null
    ) {}
}
```

## SLA Events

### SlaWarning

Fired when SLA deadline is approaching.

```php
use LucaLongo\LaravelHelpdesk\Events\SlaWarning;

class SlaWarning {
    public function __construct(
        public Ticket $ticket,
        public string $type, // 'first_response' or 'resolution'
        public int $minutesRemaining
    ) {}
}
```

### SlaBreach

Fired when SLA is breached.

```php
use LucaLongo\LaravelHelpdesk\Events\SlaBreach;

class SlaBreach {
    public function __construct(
        public Ticket $ticket,
        public string $type, // 'first_response' or 'resolution'
        public int $minutesOverdue
    ) {}
}
```

## Subscription Events

### TicketSubscriptionCreated

Fired when user subscribes to a ticket.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketSubscriptionCreated;

class TicketSubscriptionCreated {
    public function __construct(
        public TicketSubscription $subscription,
        public Ticket $ticket,
        public Model $subscriber
    ) {}
}
```

### TicketSubscriptionTriggered

Fired when a subscription is triggered by an event.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketSubscriptionTriggered;

class TicketSubscriptionTriggered {
    public function __construct(
        public TicketSubscription $subscription,
        public string $event,
        public array $data = []
    ) {}
}
```

## Rating Events

### TicketRated

Fired when a ticket receives a rating.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketRated;

class TicketRated {
    public function __construct(
        public TicketRating $rating,
        public Ticket $ticket
    ) {}
}
```

### TicketRatingUpdated

Fired when an existing rating is updated.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketRatingUpdated;

class TicketRatingUpdated {
    public function __construct(
        public TicketRating $rating,
        public int $previousRating
    ) {}
}
```

## Relation Events

### ChildTicketCreated

Fired when a child ticket is created.

```php
use LucaLongo\LaravelHelpdesk\Events\ChildTicketCreated;

class ChildTicketCreated {
    public function __construct(
        public Ticket $parent,
        public Ticket $child
    ) {}
}
```

### TicketMerged

Fired when tickets are merged.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketMerged;

class TicketMerged {
    public function __construct(
        public Ticket $source,
        public Ticket $target,
        public ?string $reason = null
    ) {}
}
```

### TicketRelationCreated

Fired when a relation is created between tickets.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketRelationCreated;

class TicketRelationCreated {
    public function __construct(
        public Ticket $ticket,
        public Ticket $relatedTicket,
        public TicketRelationType $relationType
    ) {}
}
```

### TicketRelationRemoved

Fired when a relation is removed.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketRelationRemoved;

class TicketRelationRemoved {
    public function __construct(
        public Ticket $ticket,
        public Ticket $relatedTicket,
        public TicketRelationType $relationType
    ) {}
}
```

## Time Tracking Events

### TimeEntryStarted

Fired when time tracking starts.

```php
use LucaLongo\LaravelHelpdesk\Events\TimeEntryStarted;

class TimeEntryStarted {
    public function __construct(
        public TicketTimeEntry $entry,
        public Ticket $ticket,
        public Model $user
    ) {}
}
```

### TimeEntryStopped

Fired when time tracking stops.

```php
use LucaLongo\LaravelHelpdesk\Events\TimeEntryStopped;

class TimeEntryStopped {
    public function __construct(
        public TicketTimeEntry $entry,
        public int $totalMinutes
    ) {}
}
```

## Organization Events

### CategoryCreated/Updated/Deleted

Fired for category operations.

```php
use LucaLongo\LaravelHelpdesk\Events\CategoryCreated;

class CategoryCreated {
    public function __construct(
        public Category $category
    ) {}
}
```

### TagCreated/Updated/Deleted

Fired for tag operations.

```php
use LucaLongo\LaravelHelpdesk\Events\TagCreated;

class TagCreated {
    public function __construct(
        public Tag $tag
    ) {}
}
```

## Knowledge Base Events

### KnowledgeArticleViewed

Fired when an article is viewed.

```php
use LucaLongo\LaravelHelpdesk\Events\KnowledgeArticleViewed;

class KnowledgeArticleViewed {
    public function __construct(
        public KnowledgeArticle $article,
        public ?Model $viewer = null
    ) {}
}
```

### KnowledgeArticleLinkedToTicket

Fired when article is linked to a ticket.

```php
use LucaLongo\LaravelHelpdesk\Events\KnowledgeArticleLinkedToTicket;

class KnowledgeArticleLinkedToTicket {
    public function __construct(
        public KnowledgeArticle $article,
        public Ticket $ticket
    ) {}
}
```

### FAQGeneratedFromTickets

Fired when FAQ candidates are generated.

```php
use LucaLongo\LaravelHelpdesk\Events\FAQGeneratedFromTickets;

class FAQGeneratedFromTickets {
    public function __construct(
        public Collection $candidates,
        public array $parameters
    ) {}
}
```

## AI Events

### TicketAnalyzedByAI

Fired when AI analyzes a ticket.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketAnalyzedByAI;

class TicketAnalyzedByAI {
    public function __construct(
        public Ticket $ticket,
        public AIAnalysis $analysis
    ) {}
}

Event::listen(TicketAnalyzedByAI::class, function ($event) {
    if ($event->analysis->sentiment === 'negative') {
        // Handle negative sentiment
        $event->ticket->update(['priority' => TicketPriority::High]);
    }
});
```

## Listening to Events

### Using Event Listeners

```php
// In EventServiceProvider
protected $listen = [
    TicketCreated::class => [
        SendTicketCreatedNotification::class,
        AssignToDefaultAgent::class,
    ],
    SlaBreach::class => [
        EscalateToManager::class,
        LogSlaBreachMetrics::class,
    ],
];
```

### Using Event Subscribers

```php
class TicketEventSubscriber
{
    public function handleTicketCreated(TicketCreated $event): void
    {
        // Handle ticket creation
    }

    public function handleStatusChanged(TicketStatusChanged $event): void
    {
        // Handle status change
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            TicketCreated::class => 'handleTicketCreated',
            TicketStatusChanged::class => 'handleStatusChanged',
        ];
    }
}
```

### Using Closures

```php
use Illuminate\Support\Facades\Event;

Event::listen(TicketCreated::class, function (TicketCreated $event) {
    Log::info('Ticket created', ['id' => $event->ticket->id]);
});
```

## Custom Events

You can dispatch custom events in your application:

```php
// Create custom event
class TicketArchived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public Carbon $archivedAt
    ) {}
}

// Dispatch event
event(new TicketArchived($ticket, now()));

// Listen for event
Event::listen(TicketArchived::class, function ($event) {
    // Handle archival
});
```

## Event Testing

```php
use Illuminate\Support\Facades\Event;

test('ticket creation fires event', function () {
    Event::fake();

    $ticket = LaravelHelpdesk::open([
        'subject' => 'Test',
        'type' => 'support',
    ]);

    Event::assertDispatched(TicketCreated::class, function ($event) use ($ticket) {
        return $event->ticket->id === $ticket->id;
    });
});
```