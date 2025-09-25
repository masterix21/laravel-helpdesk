# Ticket Relations

Manage relationships between tickets including parent-child hierarchies and various relation types.

## Parent-Child Hierarchy

Tickets can be organized in a tree structure using nested sets.

### Creating Child Tickets

```php
use LucaLongo\LaravelHelpdesk\Services\TicketService;

$ticketService = app(TicketService::class);

// Create parent ticket
$parent = $ticketService->open([
    'subject' => 'Website redesign project',
    'type' => 'feature_request',
]);

// Create child ticket
$child = $ticketService->createChildTicket($parent, [
    'subject' => 'Update homepage layout',
    'type' => 'feature_request',
]);

// Create another child
$child2 = $ticketService->createChildTicket($parent, [
    'subject' => 'Redesign navigation menu',
    'type' => 'feature_request',
]);
```

### Navigating Hierarchy

```php
// Get parent
$parent = $ticket->parent;

// Get all children
$children = $ticket->children;

// Get all descendants (nested children)
$descendants = $ticket->descendants;

// Get all ancestors
$ancestors = $ticket->ancestors;

// Get root ticket
$root = $ticket->getRootAncestor();

// Check if has children
if ($ticket->hasChildren()) {
    // Process children
}

// Get hierarchy path
$path = $ticket->getHierarchyPath();
// ['Root Issue' => 1, 'Sub Issue' => 2, 'Current Issue' => 3]
```

### Moving Tickets

```php
// Move to different parent
$ticketService->moveToParent($ticket, $newParent);

// Make ticket root (remove from parent)
$ticketService->moveToParent($ticket, null);
```

## Ticket Relations

Create various types of relationships between tickets.

### Relation Types

```php
use LucaLongo\LaravelHelpdesk\Enums\TicketRelationType;

// Available relation types
TicketRelationType::RelatedTo;      // General relation
TicketRelationType::DuplicateOf;    // Marks as duplicate
TicketRelationType::Blocks;         // This blocks another ticket
TicketRelationType::BlockedBy;      // This is blocked by another
TicketRelationType::CausedBy;       // This was caused by another
TicketRelationType::Causes;         // This causes another issue
```

### Creating Relations

```php
use LucaLongo\LaravelHelpdesk\Services\TicketService;

$ticketService = app(TicketService::class);

// Mark as duplicate
$ticketService->createRelation(
    $ticket,
    $originalTicket,
    TicketRelationType::DuplicateOf,
    'Same issue reported earlier'
);

// Create blocking relation
$ticketService->createRelation(
    $ticket,
    $blockedTicket,
    TicketRelationType::Blocks
);

// The inverse relation is created automatically
// $blockedTicket will have BlockedBy relation to $ticket
```

### Querying Relations

```php
// Get all related tickets
$relatedTickets = $ticket->relatedTickets;

// Get specific relation type
$duplicates = $ticket->relatedTickets()
    ->wherePivot('relation_type', TicketRelationType::DuplicateOf)
    ->get();

// Get blocking tickets
$blockers = $ticket->relatedTickets()
    ->wherePivot('relation_type', TicketRelationType::BlockedBy)
    ->get();

// Check if ticket has blockers
if ($blockers->isNotEmpty()) {
    // Cannot proceed until blockers are resolved
}
```

### Removing Relations

```php
$ticketService->removeRelation(
    $ticket,
    $relatedTicket,
    TicketRelationType::RelatedTo
);
```

## Merging Tickets

Combine multiple tickets into one.

### Basic Merge

```php
$ticketService = app(TicketService::class);

// Merge source into target
$merged = $ticketService->mergeTickets(
    $targetTicket,
    $sourceTicket,
    'Duplicate report'
);

// The source ticket is:
// - Marked as merged
// - Status changed to Closed
// - Comments/attachments transferred to target
```

### Bulk Merge

```php
// Merge multiple tickets
$merged = $ticketService->mergeTickets(
    $targetTicket,
    [$ticket1, $ticket2, $ticket3],
    'Consolidating related issues'
);
```

### What Happens During Merge

1. **Comments**: All comments are transferred to target
2. **Attachments**: All attachments are moved to target
3. **Subscriptions**: Transferred (duplicates removed)
4. **Child Tickets**: Moved to target if source had children
5. **Source Status**: Changed to Closed
6. **Merge Tracking**: Source keeps reference to target

### Checking Merge Status

```php
// Check if merged
if ($ticket->isMerged()) {
    $mergedTo = $ticket->mergedTo;
    echo "This ticket was merged into #{$mergedTo->id}";
}

// Get merge info
echo $ticket->merged_at;     // When it was merged
echo $ticket->merge_reason;  // Why it was merged
```

## Use Cases

### Project Management

```php
// Create project structure
$project = $ticketService->open([
    'subject' => 'Q1 2024 Development',
    'type' => 'project',
]);

$frontend = $ticketService->createChildTicket($project, [
    'subject' => 'Frontend Development',
]);

$backend = $ticketService->createChildTicket($project, [
    'subject' => 'Backend Development',
]);

// Create sub-tasks
$task1 = $ticketService->createChildTicket($frontend, [
    'subject' => 'Implement user dashboard',
]);

// Track progress
$totalTasks = $project->descendants()->count();
$completed = $project->descendants()
    ->where('status', TicketStatus::Closed)
    ->count();

$progress = ($completed / $totalTasks) * 100;
```

### Dependency Management

```php
// Create tickets with dependencies
$database = $ticketService->open(['subject' => 'Setup database']);
$api = $ticketService->open(['subject' => 'Build API']);
$frontend = $ticketService->open(['subject' => 'Create UI']);

// API blocks frontend
$ticketService->createRelation(
    $api,
    $frontend,
    TicketRelationType::Blocks
);

// Database blocks API
$ticketService->createRelation(
    $database,
    $api,
    TicketRelationType::Blocks
);

// Check if frontend can start
$blockers = $frontend->relatedTickets()
    ->wherePivot('relation_type', TicketRelationType::BlockedBy)
    ->where('status', '!=', TicketStatus::Closed)
    ->exists();

if ($blockers) {
    echo "Cannot start - waiting for dependencies";
}
```

### Duplicate Management

```php
// Find and merge duplicates
$original = Ticket::where('subject', 'LIKE', '%login error%')
    ->oldest()
    ->first();

$duplicates = Ticket::where('subject', 'LIKE', '%login error%')
    ->where('id', '!=', $original->id)
    ->get();

foreach ($duplicates as $duplicate) {
    // Mark as duplicate
    $ticketService->createRelation(
        $duplicate,
        $original,
        TicketRelationType::DuplicateOf
    );

    // Optionally merge
    $ticketService->mergeTickets($original, $duplicate);
}
```

## Events

```php
use LucaLongo\LaravelHelpdesk\Events;

// When child ticket is created
Event::listen(Events\ChildTicketCreated::class, function ($event) {
    $parent = $event->parent;
    $child = $event->child;
});

// When tickets are merged
Event::listen(Events\TicketMerged::class, function ($event) {
    $source = $event->source;
    $target = $event->target;
    $reason = $event->reason;
});

// When relation is created
Event::listen(Events\TicketRelationCreated::class, function ($event) {
    $ticket = $event->ticket;
    $related = $event->relatedTicket;
    $type = $event->relationType;
});
```