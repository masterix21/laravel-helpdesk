# Ticket Management

Comprehensive guide to managing support tickets in Laravel Helpdesk.

## Overview

The ticket management system provides a complete lifecycle for handling customer support requests, from creation through resolution.

## Creating Tickets

### Basic Ticket Creation

```php
use LucaLongo\LaravelHelpdesk\Facades\LaravelHelpdesk;

$ticket = LaravelHelpdesk::open([
    'type' => 'product_support',
    'subject' => 'Cannot login to application',
    'description' => 'I am getting an error when trying to login...',
    'priority' => 'high',
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
]);
```

### With Authenticated User

```php
$ticket = LaravelHelpdesk::open([
    'type' => 'product_support',
    'subject' => 'Feature request',
    'description' => 'It would be great if...',
], auth()->user());
```

### With Custom Metadata

```php
$ticket = LaravelHelpdesk::open([
    'type' => 'commercial',
    'subject' => 'Quote request',
    'description' => 'Need pricing for enterprise plan',
    'meta' => [
        'company' => 'Acme Corp',
        'employees' => 500,
        'industry' => 'Technology',
        'budget' => '$50,000',
    ],
]);
```

## Ticket Properties

### Core Attributes

- **ULID**: Unique identifier (auto-generated)
- **Ticket Number**: Human-readable identifier
- **Type**: Categorizes the ticket (product_support, commercial, etc.)
- **Subject**: Brief description of the issue
- **Description**: Detailed explanation
- **Status**: Current state (open, in_progress, resolved, closed)
- **Priority**: Urgency level (low, normal, high, urgent)

### Timestamps

- **opened_at**: When ticket was created
- **closed_at**: When ticket was closed
- **due_at**: Expected resolution time
- **first_response_at**: When first agent responded
- **first_response_due_at**: SLA deadline for first response
- **resolution_due_at**: SLA deadline for resolution

## Status Management

### Available Statuses

```php
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;

TicketStatus::Open;       // New ticket
TicketStatus::InProgress; // Being worked on
TicketStatus::OnHold;     // Waiting for customer
TicketStatus::Resolved;   // Solution provided
TicketStatus::Closed;     // Ticket closed
TicketStatus::Cancelled;  // Ticket cancelled
```

### Transitioning Status

```php
use LucaLongo\LaravelHelpdesk\Services\TicketService;

$ticketService = app(TicketService::class);

// Move to in progress
$ticketService->transition($ticket, TicketStatus::InProgress);

// Resolve ticket
$ticketService->transition($ticket, TicketStatus::Resolved);

// Close ticket
$ticketService->transition($ticket, TicketStatus::Closed);
```

### Valid Transitions

The system enforces valid status transitions:

- **Open** → InProgress, OnHold, Resolved, Cancelled
- **InProgress** → OnHold, Resolved, Cancelled
- **OnHold** → InProgress, Resolved, Cancelled
- **Resolved** → Closed, InProgress (reopened)
- **Closed** → InProgress (reopened)
- **Cancelled** → (terminal state)

## Assignment

### Assign to User

```php
$ticketService->assign($ticket, $user);
```

### Assign to Agent

```php
$agent = User::where('role', 'agent')->first();
$ticketService->assign($ticket, $agent);
```

### Release Assignment

```php
$ticketService->assign($ticket, null);
```

### Check Assignment

```php
if ($ticket->isAssigned()) {
    $assignee = $ticket->assignee;
    echo "Assigned to: " . $assignee->name;
}
```

## Updating Tickets

### Update Basic Information

```php
$ticketService->update($ticket, [
    'subject' => 'Updated subject',
    'description' => 'More detailed description',
    'priority' => 'urgent',
    'type' => 'bug_report',
]);
```

### Update Metadata

```php
$ticketService->update($ticket, [
    'meta' => [
        'severity' => 'critical',
        'affected_users' => 150,
        'workaround_available' => false,
    ],
]);
```

## Ticket Relationships

### Parent-Child Tickets

```php
// Create a child ticket
$childTicket = $ticketService->createChildTicket($parentTicket, [
    'type' => 'product_support',
    'subject' => 'Sub-task: Fix database connection',
    'description' => 'Part of the main issue',
]);

// Move ticket to different parent
$ticketService->moveToParent($ticket, $newParentTicket);

// Make ticket a root ticket
$ticketService->moveToParent($ticket, null);
```

### Related Tickets

```php
use LucaLongo\LaravelHelpdesk\Enums\TicketRelationType;

// Create relationship
$ticketService->createRelation(
    $ticket,
    $relatedTicket,
    TicketRelationType::Blocks,
    'This ticket must be resolved first'
);

// Remove relationship
$ticketService->removeRelation(
    $ticket,
    $relatedTicket,
    TicketRelationType::Blocks
);
```

### Relationship Types

- **Blocks/BlockedBy**: Dependency relationship
- **Duplicates/DuplicatedBy**: Same issue reported multiple times
- **Relates**: General relationship
- **Causes/CausedBy**: Cause-effect relationship

## Merging Tickets

### Merge Single Ticket

```php
$targetTicket = $ticketService->mergeTickets(
    $targetTicket,
    $sourceTicket,
    'Duplicate issue reported'
);
```

### Merge Multiple Tickets

```php
$targetTicket = $ticketService->mergeTickets(
    $targetTicket,
    [$ticket1, $ticket2, $ticket3],
    'Consolidating related issues'
);
```

Merging transfers:
- All comments
- All attachments
- All subscriptions (avoiding duplicates)
- Child tickets
- Time entries

## Querying Tickets

### Basic Queries

```php
use LucaLongo\LaravelHelpdesk\Models\Ticket;

// All open tickets
$openTickets = Ticket::open()->get();

// High priority tickets
$urgentTickets = Ticket::where('priority', 'urgent')->get();

// Assigned to specific user
$myTickets = Ticket::assignedTo($user)->get();

// Unassigned tickets
$unassignedTickets = Ticket::unassigned()->get();
```

### Advanced Queries

```php
// Complex query
$tickets = Ticket::query()
    ->whereIn('status', [TicketStatus::Open, TicketStatus::InProgress])
    ->where('priority', '>=', TicketPriority::High)
    ->whereNull('assigned_to_id')
    ->where('created_at', '>=', now()->subDay())
    ->orderBy('priority', 'desc')
    ->orderBy('created_at', 'asc')
    ->get();

// With relationships
$tickets = Ticket::with(['comments', 'attachments', 'assignee'])
    ->open()
    ->get();

// SLA breach tickets
$breachedTickets = Ticket::slaBreached()->get();

// Overdue tickets
$overdueTickets = Ticket::overdue()->get();
```

### Scopes

Available query scopes:

```php
// Status scopes
Ticket::open();
Ticket::inProgress();
Ticket::onHold();
Ticket::resolved();
Ticket::closed();
Ticket::cancelled();

// Assignment scopes
Ticket::assigned();
Ticket::unassigned();
Ticket::assignedTo($user);

// SLA scopes
Ticket::slaBreached();
Ticket::slaCompliant();
Ticket::overdue();
Ticket::dueSoon($minutes = 60);

// Priority scopes
Ticket::urgent();
Ticket::highPriority();
Ticket::lowPriority();

// Relationship scopes
Ticket::hasChildren();
Ticket::isChild();
Ticket::merged();

// Time scopes
Ticket::createdToday();
Ticket::updatedRecently($minutes = 60);
Ticket::stale($days = 7);
```

## Ticket Events

The system dispatches events for all major operations:

```php
// Listen for ticket creation
Event::listen(TicketCreated::class, function ($event) {
    $ticket = $event->ticket;
    // Send notification, update metrics, etc.
});

// Listen for status changes
Event::listen(TicketStatusChanged::class, function ($event) {
    $ticket = $event->ticket;
    $oldStatus = $event->previousStatus;
    $newStatus = $event->newStatus;
    // Log status change, trigger automation, etc.
});
```

Available events:
- `TicketCreated`
- `TicketStatusChanged`
- `TicketAssigned`
- `TicketCommentAdded`
- `TicketMerged`
- `TicketRelationCreated`
- `TicketRelationRemoved`
- `ChildTicketCreated`
- `TicketEscalated`

## Ticket Metrics

### Calculate Response Time

```php
$responseTime = $ticket->response_time_minutes;
echo "First response in {$responseTime} minutes";
```

### Calculate Resolution Time

```php
$resolutionTime = $ticket->resolution_time_minutes;
$hours = round($resolutionTime / 60, 1);
echo "Resolved in {$hours} hours";
```

### Get Ticket Age

```php
$age = $ticket->opened_at->diffForHumans();
echo "Ticket opened {$age}";
```

## Best Practices

### 1. Always Use Services

Use service classes instead of direct model manipulation:

```php
// Good
$ticketService->transition($ticket, TicketStatus::Resolved);

// Avoid
$ticket->status = TicketStatus::Resolved;
$ticket->save();
```

### 2. Handle Exceptions

```php
use LucaLongo\LaravelHelpdesk\Exceptions\InvalidTransitionException;

try {
    $ticketService->transition($ticket, TicketStatus::Closed);
} catch (InvalidTransitionException $e) {
    // Handle invalid transition
    Log::error("Cannot transition from {$e->from} to {$e->to}");
}
```

### 3. Use Transactions for Complex Operations

```php
DB::transaction(function () use ($tickets, $targetTicket) {
    foreach ($tickets as $ticket) {
        $this->ticketService->mergeTickets($targetTicket, $ticket);
    }
});
```

### 4. Leverage Events

```php
class TicketEventSubscriber
{
    public function handleTicketCreated(TicketCreated $event): void
    {
        // Auto-assign based on category
        // Send welcome email
        // Create initial SLA timers
    }

    public function subscribe($events): void
    {
        $events->listen(
            TicketCreated::class,
            [TicketEventSubscriber::class, 'handleTicketCreated']
        );
    }
}
```

## Next Steps

- [Comments & Attachments](communication.md) - Add comments and files to tickets
- [SLA Management](sla.md) - Configure and monitor service levels
- [Automation](automation.md) - Automate ticket workflows