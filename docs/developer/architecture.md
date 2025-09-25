# Architecture Overview

Understanding the package's architecture and design patterns.

## Package Structure

```
laravel-helpdesk/
├── config/
│   └── helpdesk.php              # Configuration file
├── database/
│   ├── factories/                # Model factories for testing
│   └── migrations/               # Database migration stubs
├── src/
│   ├── AI/                      # AI integration layer
│   │   ├── AIProviderSelector.php
│   │   ├── AIService.php
│   │   └── AIHelper.php
│   ├── Concerns/                # Shared traits
│   │   └── Enums/
│   │       └── ProvidesEnumValues.php
│   ├── Console/
│   │   └── Commands/            # Artisan commands
│   ├── Enums/                   # Enum definitions
│   ├── Events/                  # Event classes
│   ├── Exceptions/              # Custom exceptions
│   ├── Facades/                 # Laravel facades
│   ├── Models/                  # Eloquent models
│   ├── Notifications/           # Notification system
│   │   ├── Channels/           # Notification channels
│   │   └── NotificationDispatcher.php
│   ├── Services/                # Business logic services
│   │   └── Automation/         # Automation subsystem
│   ├── Support/                 # Helper classes
│   └── LaravelHelpdeskServiceProvider.php
└── tests/
    ├── Feature/                 # Feature tests
    ├── Fakes/                   # Test doubles
    └── TestCase.php            # Base test class
```

## Design Patterns

### Service-Oriented Architecture

The package uses a service layer to encapsulate business logic:

```php
// Service handles business logic
class TicketService
{
    public function open(array $attributes, ?Model $openedBy = null): Ticket
    {
        // Business logic for ticket creation
        // SLA calculation
        // Event dispatching
        // etc.
    }
}

// Controller stays thin
class TicketController
{
    public function store(Request $request, TicketService $service)
    {
        $ticket = $service->open($request->validated(), $request->user());
        return response()->json($ticket);
    }
}
```

### Repository Pattern (Implicit)

Models act as repositories with scopes for queries:

```php
// Model scopes act as query methods
Ticket::open()
    ->overdue()
    ->withCategories([1, 2])
    ->get();
```

### Factory Pattern

Model factories for testing:

```php
Ticket::factory()
    ->urgent()
    ->withComments(3)
    ->create();
```

### Observer Pattern

Event-driven architecture for decoupled components:

```php
// Service dispatches events
event(new TicketCreated($ticket));

// Listeners handle side effects
Event::listen(TicketCreated::class, SendNotification::class);
Event::listen(TicketCreated::class, StartSlaTimer::class);
Event::listen(TicketCreated::class, RunAutomation::class);
```

### Strategy Pattern

Automation system uses strategies for conditions and actions:

```php
interface ConditionEvaluator
{
    public function evaluate(Ticket $ticket, array $config): bool;
}

class PriorityCondition implements ConditionEvaluator
{
    public function evaluate(Ticket $ticket, array $config): bool
    {
        return $ticket->priority === $config['value'];
    }
}
```

### Facade Pattern

Simple API access through facades:

```php
LaravelHelpdesk::open([...]);
LaravelHelpdesk::comment($ticket, 'Message');
```

## Core Components

### Models Layer

Models handle data persistence and relationships:

- **Eloquent ORM** for database interaction
- **Nested Sets** for hierarchical data (categories, tickets)
- **Polymorphic relationships** for flexible associations
- **Enum casting** for type safety

### Service Layer

Services encapsulate business logic:

- **TicketService**: Core ticket operations
- **SlaService**: SLA calculations and monitoring
- **AutomationService**: Rule processing
- **AIService**: AI integration
- **TimeTrackingService**: Time management

### Event System

Events enable loose coupling:

```php
// Ticket lifecycle events
TicketCreated::class
TicketStatusChanged::class
TicketAssigned::class

// SLA events
SlaWarning::class
SlaBreach::class

// Rating events
TicketRated::class
```

### Notification System

Multi-channel notification dispatcher:

```php
interface NotificationChannel
{
    public function send(Model $notifiable, array $data): void;
}

class NotificationDispatcher
{
    public function dispatch(string $event, array $data): void
    {
        foreach ($this->channels as $channel) {
            $channel->send($notifiable, $data);
        }
    }
}
```

## Database Design

### Core Tables

```sql
-- Main ticket table with nested set columns
helpdesk_tickets
├── id (ULID)
├── status (enum)
├── priority (enum)
├── _lft, _rgt, parent_id (nested set)
└── timestamps, SLA fields

-- Polymorphic relations
helpdesk_ticket_comments
├── ticket_id
├── author_type, author_id (polymorphic)
└── content, is_public

-- Many-to-many relations
helpdesk_ticket_categories
├── ticket_id
└── category_id

-- AI analysis storage
helpdesk_ai_analyses
├── ticket_id
├── provider, model
└── analysis results
```

### Indexing Strategy

```php
// Migration example
$table->index(['status', 'priority']);
$table->index(['opened_at', 'status']);
$table->index('first_response_due_at');
$table->index(['ticket_id', 'created_at']);
```

## Dependency Injection

Services are registered in the service provider:

```php
class LaravelHelpdeskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton services
        $this->app->singleton(TicketService::class);
        $this->app->singleton(SlaService::class);

        // With dependencies
        $this->app->singleton(TicketService::class, function ($app) {
            return new TicketService(
                $app->make(SlaService::class),
                $app->make(SubscriptionService::class),
                config('helpdesk.ai.enabled') ? $app->make(AIService::class) : null
            );
        });
    }
}
```

## Configuration Management

Hierarchical configuration with defaults:

```php
// config/helpdesk.php
return [
    'defaults' => [...],
    'types' => [
        'support' => [...],
        'commercial' => [...],
    ],
    'sla' => [...],
    'ai' => [...],
];

// Runtime access
config('helpdesk.sla.enabled');
config('helpdesk.ai.providers.openai.model');
```

## Testing Architecture

### Test Organization

```
tests/
├── Feature/           # Integration tests
│   ├── TicketServiceTest.php
│   ├── SlaServiceTest.php
│   └── AI/AIServiceTest.php
├── Unit/             # Unit tests
│   └── Enums/
└── TestCase.php      # Orchestra Testbench setup
```

### Testing Approach

```php
// Feature test with database
test('ticket creation with SLA', function () {
    $ticket = LaravelHelpdesk::open([...]);

    expect($ticket)
        ->status->toBe(TicketStatus::Open)
        ->first_response_due_at->toBeInstanceOf(Carbon::class);
});

// Unit test with mocks
test('AI provider selection', function () {
    Config::set('helpdesk.ai.providers', [...]);

    $selector = new AIProviderSelector();
    expect($selector->selectProvider())->toBe('openai');
});
```

## Performance Considerations

### Query Optimization

- Use eager loading for relationships
- Implement query scopes for common filters
- Index foreign keys and frequently queried columns

### Caching Strategy

```php
// AI provider rotation
Cache::put('helpdesk.ai_provider_index', $index);

// Expensive computations
Cache::remember('ticket.stats', 3600, function () {
    return Ticket::generateStatistics();
});
```

### Queue Integration

For heavy operations (future enhancement):

```php
// Potential queue usage
AnalyzeTicketJob::dispatch($ticket)->onQueue('ai');
GenerateReportJob::dispatch()->onQueue('reports');
```

## Security Considerations

### Data Protection

- ULIDs instead of sequential IDs
- Polymorphic relations for flexible permissions
- Separate internal notes from public comments

### API Key Management

```php
// Environment variables for sensitive data
'api_key' => env('OPENAI_API_KEY'),

// Never log sensitive data
Log::info('AI analysis completed', [
    'ticket_id' => $ticket->id,
    'provider' => $provider,
    // 'api_key' => $apiKey, // NEVER DO THIS
]);
```

## Extension Points

### Custom Service Providers

```php
class CustomHelpdeskServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Add custom automation action
        app(AutomationService::class)->registerAction(
            'custom_action',
            CustomAction::class
        );

        // Add notification channel
        app(NotificationDispatcher::class)->addChannel(
            new SlackChannel()
        );
    }
}
```

### Event Listeners

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    TicketCreated::class => [
        YourCustomListener::class,
    ],
];
```

### Model Extensions

```php
class CustomTicket extends Ticket
{
    protected $table = 'helpdesk_tickets';

    // Add custom methods
    public function calculateCustomMetric(): float
    {
        // Custom logic
    }
}
```