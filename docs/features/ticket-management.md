# Ticket Management

## Overview

The ticket management system is the core of the Laravel Helpdesk package, providing complete lifecycle management for support tickets including creation, updates, status transitions, assignments, and merging.

## TicketService

The `TicketService` class provides the main interface for managing tickets.

### Opening Tickets

```php
use LucaLongo\LaravelHelpdesk\Services\TicketService;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;

$ticketService = app(TicketService::class);

// Basic ticket creation
$ticket = $ticketService->open([
    'type' => TicketType::ProductSupport,
    'subject' => 'Cannot access dashboard',
    'description' => 'Getting 404 error when accessing /dashboard',
    'priority' => TicketPriority::High,
]);

// With opener reference
$ticket = $ticketService->open([
    'type' => TicketType::Commercial,
    'subject' => 'Quote request',
    'description' => 'Need pricing for enterprise plan',
], $customer);

// With custom metadata
$ticket = $ticketService->open([
    'type' => TicketType::ProductSupport,
    'subject' => 'API Integration Issue',
    'description' => 'Webhook not firing',
    'meta' => [
        'api_version' => '2.0',
        'endpoint' => '/webhooks/orders',
        'error_code' => 'TIMEOUT_ERROR',
    ],
]);
```

### Updating Tickets

```php
// Update ticket details
$ticket = $ticketService->update($ticket, [
    'subject' => 'Updated subject',
    'description' => 'More detailed description',
    'priority' => TicketPriority::Urgent,
    'due_at' => now()->addHours(2),
]);

// Update metadata
$ticket = $ticketService->update($ticket, [
    'meta' => [
        'customer_tier' => 'premium',
        'contract_id' => 'CNT-2024-001',
    ],
]);
```

### Status Transitions

```php
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Exceptions\InvalidTransitionException;

// Transition to in-progress
try {
    $ticket = $ticketService->transition($ticket, TicketStatus::InProgress);
} catch (InvalidTransitionException $e) {
    // Handle invalid transition
}

// Valid transitions
// Open -> InProgress
// Open -> On Hold
// InProgress -> Resolved
// InProgress -> On Hold
// On Hold -> InProgress
// Resolved -> Closed
// Resolved -> Open (reopen)
```

### Assigning Tickets

```php
// Assign to an agent
$ticket = $ticketService->assign($ticket, $agent);

// Release assignment
$ticket = $ticketService->assign($ticket, null);

// Assignment triggers TicketAssigned event
```

### Merging Tickets

```php
// Merge single ticket
$mergedTicket = $ticketService->mergeTickets(
    $targetTicket,
    $sourceTicket,
    'Duplicate issue'
);

// Merge multiple tickets
$mergedTicket = $ticketService->mergeTickets(
    $targetTicket,
    [$ticket1, $ticket2, $ticket3],
    'Consolidating related issues'
);

// Merged tickets are marked with MergedInto status
// Comments and attachments are copied to target
```

### Creating Child Tickets

```php
// Split complex issue into subtasks
$childTicket = $ticketService->createChildTicket($parentTicket, [
    'subject' => 'Subtask: Fix database connection',
    'description' => 'Investigation shows DB connection timeout',
    'assignee' => $specialist,
]);

// Child tickets maintain parent relationship
```

## Ticket Model

The `Ticket` model provides additional methods and relationships.

### Relationships

```php
// Access relationships
$opener = $ticket->opener;        // Who opened the ticket
$assignee = $ticket->assignee;    // Current assignee
$comments = $ticket->comments;    // All comments
$attachments = $ticket->attachments; // File attachments
$subscriptions = $ticket->subscriptions; // Subscribers
$ratings = $ticket->ratings;      // Customer ratings
$timeEntries = $ticket->timeEntries; // Time tracking
$relations = $ticket->relations;  // Related tickets
$children = $ticket->children;    // Child tickets
$parent = $ticket->parent;        // Parent ticket
```

### Scopes

```php
use LucaLongo\LaravelHelpdesk\Models\Ticket;

// Status scopes
$openTickets = Ticket::open()->get();
$resolvedTickets = Ticket::resolved()->get();
$closedTickets = Ticket::closed()->get();
$onHoldTickets = Ticket::onHold()->get();

// Priority scopes
$urgentTickets = Ticket::priority(TicketPriority::Urgent)->get();
$highPriorityTickets = Ticket::highPriority()->get();

// Assignment scopes
$unassignedTickets = Ticket::unassigned()->get();
$myTickets = Ticket::assignedTo($user)->get();

// SLA scopes
$overdueTickets = Ticket::overdue()->get();
$breachedTickets = Ticket::slaBreach()->get();
$atRiskTickets = Ticket::slaWarning()->get();

// Date scopes
$todayTickets = Ticket::createdToday()->get();
$weekTickets = Ticket::createdThisWeek()->get();
$monthTickets = Ticket::createdThisMonth()->get();

// Combination
$urgentUnassigned = Ticket::urgent()
    ->unassigned()
    ->orderBy('created_at')
    ->get();
```

### Helper Methods

```php
// Check status
if ($ticket->isOpen()) { }
if ($ticket->isResolved()) { }
if ($ticket->isClosed()) { }
if ($ticket->isOnHold()) { }

// Check assignment
if ($ticket->isAssigned()) { }
if ($ticket->isAssignedTo($user)) { }

// Check SLA
if ($ticket->isOverdue()) { }
if ($ticket->hasSlaBreached()) { }

// Check relations
if ($ticket->hasParent()) { }
if ($ticket->hasChildren()) { }
if ($ticket->isMerged()) { }

// Calculations
$responseTime = $ticket->getFirstResponseTime();
$resolutionTime = $ticket->getResolutionTime();
$timeWorked = $ticket->getTotalTimeWorked();
```

## Events

The ticket system fires several events:

```php
use LucaLongo\LaravelHelpdesk\Events\*;

// TicketCreated - When a new ticket is opened
// TicketStatusChanged - When status transitions
// TicketAssigned - When assignment changes
// TicketMerged - When tickets are merged
// ChildTicketCreated - When child ticket is created
```

## Configuration

Ticket behavior is configured in `config/helpdesk.php`:

```php
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;

'defaults' => [
    'due_minutes' => 1440,  // 24 hours default due time
    'priority' => TicketPriority::Normal->value,  // Default priority
],

'types' => [
    TicketType::ProductSupport->value => [
        'label' => 'Product Support',
        'default_priority' => TicketPriority::Normal->value,
        'due_minutes' => 720,  // 12 hours
    ],
    TicketType::Commercial->value => [
        'label' => 'Commercial',
        'default_priority' => TicketPriority::High->value,
        'due_minutes' => 480,  // 8 hours
    ],
],
```

## Best Practices

1. **Always handle InvalidTransitionException** when changing status
2. **Use scopes** for efficient querying instead of filtering collections
3. **Subscribe to events** for extending functionality
4. **Store additional data in meta field** rather than extending the table
5. **Use child tickets** for complex issues requiring multiple assignments
6. **Merge duplicate tickets** to maintain clean ticket history