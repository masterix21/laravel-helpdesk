# Quick Start Guide

Get up and running with Laravel Helpdesk in minutes.

## Installation

```bash
composer require masterix21/laravel-helpdesk
```

## Setup

### 1. Publish and Run Migrations

```bash
php artisan vendor:publish --tag="laravel-helpdesk-migrations"
php artisan migrate
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag="laravel-helpdesk-config"
```

## Basic Usage

### Creating Your First Ticket

```php
use LucaLongo\LaravelHelpdesk\Facades\LaravelHelpdesk;

$ticket = LaravelHelpdesk::open([
    'type' => 'support',
    'subject' => 'Need help with login',
    'description' => 'Cannot access my account after password reset',
    'priority' => 'high'
], $user);
```

### Adding Comments

```php
// Public comment
$comment = LaravelHelpdesk::comment($ticket,
    'We are investigating this issue',
    $supportAgent
);

// Internal note
$internalNote = LaravelHelpdesk::comment($ticket,
    'User account locked due to multiple failed attempts',
    $supportAgent,
    isPublic: false
);
```

### Managing Ticket Status

```php
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;

// Change status
LaravelHelpdesk::transition($ticket, TicketStatus::InProgress);

// Resolve ticket
LaravelHelpdesk::transition($ticket, TicketStatus::Resolved);

// Close ticket
LaravelHelpdesk::transition($ticket, TicketStatus::Closed);
```

### Assigning Tickets

```php
// Assign to user
LaravelHelpdesk::assign($ticket, $supportAgent);

// Unassign
LaravelHelpdesk::assign($ticket, null);
```

## Using Services Directly

```php
use LucaLongo\LaravelHelpdesk\Services\TicketService;
use LucaLongo\LaravelHelpdesk\Services\CommentService;

// Via dependency injection
public function __construct(
    private TicketService $tickets,
    private CommentService $comments
) {}

// Or via service container
$ticketService = app(TicketService::class);
```

## Querying Tickets

```php
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;

// Get open tickets
$openTickets = Ticket::where('status', TicketStatus::Open)->get();

// Get high priority tickets
$urgentTickets = Ticket::where('priority', TicketPriority::Urgent)->get();

// Get overdue tickets
$overdueTickets = Ticket::overdue()->get();

// Get tickets with SLA breach
$breachedTickets = Ticket::breachedSla()->get();
```

## Enable AI Features (Optional)

Add to `.env`:

```env
HELPDESK_AI_ENABLED=true
OPENAI_API_KEY=sk-...
```

Now tickets are automatically analyzed:

```php
$ticket = LaravelHelpdesk::open([...]);

// AI analysis happens automatically
$analysis = $ticket->getLatestAIAnalysis();
echo $analysis->sentiment; // "negative"
echo $analysis->suggested_response; // AI-generated response
```

## Next Steps

- [Configure SLA rules](./features/sla-management.md)
- [Set up automation](./features/automation-rules.md)
- [Enable notifications](./features/subscriptions-notifications.md)
- [Customize categories and tags](./features/categories-tags.md)