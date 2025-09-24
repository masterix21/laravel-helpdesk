# Subscriptions & Notifications

The Laravel Helpdesk package provides a comprehensive subscription system that allows users to receive notifications when tickets change status. This feature helps keep stakeholders informed about ticket progress without manual communication.

## Overview

The `SubscriptionService` manages ticket subscriptions, allowing users to:
- Subscribe to specific tickets for status change notifications
- Subscribe to all status changes or specific status transitions
- Automatically notify all subscribers when ticket status changes
- Handle subscription deduplication and cleanup

## Service Methods

### Subscribe to Ticket

Subscribe a user to receive notifications for ticket status changes.

```php
use LucaLongo\LaravelHelpdesk\Services\SubscriptionService;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;

$service = app(SubscriptionService::class);

// Subscribe to all status changes
$subscription = $service->subscribe($ticket, $user);

// Subscribe to specific status changes only
$subscription = $service->subscribe($ticket, $user, TicketStatus::Resolved);
```

**Parameters:**
- `$ticket` (Ticket) - The ticket to subscribe to
- `$subscriber` (Model) - The user/model that wants to receive notifications
- `$status` (TicketStatus|string|null) - Optional status to filter notifications

**Returns:** `TicketSubscription` model

### Unsubscribe from Ticket

Remove a user's subscription from a ticket.

```php
$success = $service->unsubscribe($ticket, $user);

// Unsubscribe from specific status notifications
$success = $service->unsubscribe($ticket, $user, TicketStatus::Closed);
```

**Returns:** `bool` - True if subscription was removed, false if no subscription existed

### Notify Subscribers

Trigger notifications to all subscribers when a ticket status changes.

```php
$service->notifySubscribers($ticket, TicketStatus::Resolved);
```

This method:
1. Finds all subscribers for the ticket
2. Filters by notification preferences (all changes or specific status)
3. Fires `TicketSubscriptionTriggered` event with relevant subscriptions

## TicketSubscription Model

The subscription model tracks which users want notifications for specific tickets.

### Model Properties

```php
class TicketSubscription extends Model
{
    protected $fillable = [
        'ticket_id',        // ID of the subscribed ticket
        'subscriber_type',   // Morphed subscriber model type
        'subscriber_id',     // Subscriber model ID
        'notify_on',         // Specific status to notify on (null = all)
    ];
}
```

### Relationships

```php
// Get the ticket being subscribed to
$subscription->ticket;

// Get the subscriber (polymorphic)
$subscription->subscriber;
```

## Events

### TicketSubscriptionCreated

Fired when a new subscription is created.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketSubscriptionCreated;

class TicketSubscriptionCreated
{
    public function __construct(
        public TicketSubscription $subscription
    ) {}
}
```

### TicketSubscriptionTriggered

Fired when subscribers should be notified of a status change.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketSubscriptionTriggered;

class TicketSubscriptionTriggered
{
    public function __construct(
        public Ticket $ticket,
        public TicketStatus $status,
        public Collection $subscriptions
    ) {}
}
```

## Configuration

Currently, subscriptions don't have specific configuration options. Notification behavior is controlled through event listeners.

## Usage Examples

### Basic Subscription Workflow

```php
use LucaLongo\LaravelHelpdesk\Services\SubscriptionService;

$service = app(SubscriptionService::class);

// Customer automatically subscribes when creating ticket
$ticket = Ticket::create([
    'subject' => 'Login Issue',
    'description' => 'Cannot log into my account',
    'opened_by_type' => User::class,
    'opened_by_id' => $customer->id,
]);

$service->subscribe($ticket, $customer);

// Support agent subscribes to track progress
$service->subscribe($ticket, $supportAgent);

// Manager subscribes only to resolution notifications
$service->subscribe($ticket, $manager, TicketStatus::Resolved);

// When ticket status changes, notify all relevant subscribers
$ticket->status = TicketStatus::InProgress;
$ticket->save();

$service->notifySubscribers($ticket, TicketStatus::InProgress);
// This will notify customer and support agent, but not manager
```

### Handling Subscription Events

Create event listeners to send actual notifications:

```php
// In EventServiceProvider
use LucaLongo\LaravelHelpdesk\Events\TicketSubscriptionTriggered;

protected $listen = [
    TicketSubscriptionTriggered::class => [
        SendTicketNotificationEmails::class,
        LogTicketActivity::class,
    ],
];
```

```php
// SendTicketNotificationEmails listener
class SendTicketNotificationEmails
{
    public function handle(TicketSubscriptionTriggered $event): void
    {
        foreach ($event->subscriptions as $subscription) {
            $subscriber = $subscription->subscriber;

            // Send email notification
            Mail::to($subscriber->email)
                ->send(new TicketStatusChangedMail(
                    $event->ticket,
                    $event->status,
                    $subscriber
                ));
        }
    }
}
```

### Multiple Status Subscriptions

```php
// Subscribe to multiple specific statuses
$service->subscribe($ticket, $customer, TicketStatus::InProgress);
$service->subscribe($ticket, $customer, TicketStatus::Resolved);
$service->subscribe($ticket, $customer, TicketStatus::Closed);

// Or subscribe to all status changes (pass null)
$service->subscribe($ticket, $customer, null);
```

### Bulk Subscription Management

```php
// Subscribe customer to all their tickets
$customerTickets = Ticket::where('opened_by_id', $customer->id)
    ->where('opened_by_type', User::class)
    ->get();

foreach ($customerTickets as $ticket) {
    $service->subscribe($ticket, $customer);
}
```

## Best Practices

1. **Automatic Subscriptions**: Subscribe ticket creators automatically
2. **Assignee Subscriptions**: Subscribe assigned agents to their tickets
3. **Manager Notifications**: Subscribe managers to resolution/closure events only
4. **Cleanup**: Remove subscriptions when tickets are closed or users are deactivated
5. **Deduplication**: The service handles duplicate subscriptions automatically
6. **Event Listeners**: Use event listeners to send actual notifications (email, SMS, push, etc.)

## Integration with Other Features

### With Ticket Status Changes

```php
// In your ticket status change logic
$ticket->transitionTo(TicketStatus::Resolved);
$subscriptionService->notifySubscribers($ticket, TicketStatus::Resolved);
```

### With Assignment

```php
// Auto-subscribe when ticket is assigned
$ticket->assignTo($agent);
$subscriptionService->subscribe($ticket, $agent);
```

### With Customer Portal

```php
// Allow customers to manage their subscriptions
public function toggleSubscription(Ticket $ticket)
{
    $subscription = TicketSubscription::where([
        'ticket_id' => $ticket->id,
        'subscriber_id' => auth()->id(),
        'subscriber_type' => User::class,
    ])->first();

    if ($subscription) {
        $this->subscriptionService->unsubscribe($ticket, auth()->user());
        return 'unsubscribed';
    } else {
        $this->subscriptionService->subscribe($ticket, auth()->user());
        return 'subscribed';
    }
}
```

## Database Schema

The subscription system uses the `helpdesk_ticket_subscriptions` table:

```php
Schema::create('helpdesk_ticket_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->onDelete('cascade');
    $table->string('subscriber_type'); // Polymorphic relation
    $table->unsignedBigInteger('subscriber_id');
    $table->string('notify_on')->nullable(); // Specific status or null for all
    $table->timestamps();

    $table->unique(['ticket_id', 'subscriber_type', 'subscriber_id', 'notify_on']);
    $table->index(['subscriber_type', 'subscriber_id']);
});
```