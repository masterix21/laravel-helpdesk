# Bulk Actions

The Laravel Helpdesk package provides a powerful bulk action system that allows you to perform operations on multiple tickets simultaneously. This feature significantly improves efficiency when managing large numbers of tickets.

## Overview

The `BulkActionService` enables you to:
- Apply actions to multiple tickets at once
- Use filters to target specific sets of tickets
- Perform various operations like status changes, assignments, and tagging
- Track success and failure rates for bulk operations
- Apply automation rules to multiple tickets

## Service Methods

### Apply Action to Tickets

Apply a bulk action to a list of ticket IDs.

```php
use LucaLongo\LaravelHelpdesk\Services\BulkActionService;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;

$service = app(BulkActionService::class);

$results = $service->applyAction(
    action: 'change_status',
    ticketIds: [1, 2, 3, 4, 5],
    params: ['status' => TicketStatus::Resolved->value]
);
```

**Parameters:**
- `$action` (string) - The action to perform
- `$ticketIds` (array) - Array of ticket IDs
- `$params` (array) - Action-specific parameters

**Returns:** Results array
```php
[
    'success' => 4,           // Number of successful operations
    'failed' => 1,            // Number of failed operations
    'details' => [            // Per-ticket results
        'ticket_id' => ['status' => 'success'],
        'ticket_id' => ['status' => 'failed', 'error' => 'Error message']
    ]
]
```

### Apply Action with Query Filter

Apply an action to tickets matching query criteria.

```php
$query = Ticket::where('status', TicketStatus::Open->value)
    ->where('priority', TicketPriority::Low->value)
    ->where('created_at', '<', now()->subDays(30));

$results = $service->applyActionWithFilter(
    action: 'close',
    query: $query,
    params: ['comment' => 'Auto-closed due to inactivity', 'add_comment' => true]
);
```

### Build Filter Query

Build a query based on filter criteria.

```php
$query = $service->buildFilterQuery([
    'status' => [TicketStatus::Open->value, TicketStatus::InProgress->value],
    'priority' => TicketPriority::Urgent->value,
    'assigned' => false,
    'created_after' => '2023-01-01',
    'sla_status' => 'breached',
    'search' => 'login issue'
]);

$affectedTickets = $query->get();
```

### Get Available Actions

Retrieve list of available bulk actions.

```php
$actions = $service->getAvailableActions();
```

**Returns:** Array of action definitions
```php
[
    ['key' => 'change_status', 'label' => 'Change Status'],
    ['key' => 'assign', 'label' => 'Assign'],
    ['key' => 'add_tags', 'label' => 'Add Tags'],
    // ... more actions
]
```

## Available Actions

### Change Status

Change the status of multiple tickets.

```php
$service->applyAction('change_status', $ticketIds, [
    'status' => TicketStatus::Resolved->value
]);
```

### Change Priority

Modify ticket priority levels.

```php
$service->applyAction('change_priority', $ticketIds, [
    'priority' => TicketPriority::High->value
]);
```

### Assignment Operations

Assign or unassign tickets.

```php
// Assign to user
$service->applyAction('assign', $ticketIds, [
    'assignee_id' => $userId,
    'assignee_type' => User::class
]);

// Unassign all tickets
$service->applyAction('unassign', $ticketIds);
```

### Tag Management

Add or remove tags from multiple tickets.

```php
// Add tags
$service->applyAction('add_tags', $ticketIds, [
    'tag_ids' => [1, 2, 3]
]);

// Remove tags
$service->applyAction('remove_tags', $ticketIds, [
    'tag_ids' => [4, 5]
]);
```

### Category Management

Manage ticket categories.

```php
// Add category
$service->applyAction('add_category', $ticketIds, [
    'category_id' => 5
]);

// Remove category
$service->applyAction('remove_category', $ticketIds, [
    'category_id' => 3
]);
```

### Close Tickets

Close multiple tickets with optional comments.

```php
$service->applyAction('close', $ticketIds, [
    'comment' => 'Closing due to no response from customer',
    'add_comment' => true
]);
```

### Delete Tickets

Delete multiple tickets (soft delete by default).

```php
// Soft delete
$service->applyAction('delete', $ticketIds, [
    'soft_delete' => true
]);

// Hard delete (permanent)
$service->applyAction('delete', $ticketIds, [
    'soft_delete' => false
]);
```

### Apply Automation

Apply specific automation rules to multiple tickets.

```php
$service->applyAction('apply_automation', $ticketIds, [
    'rule_id' => 15
]);
```

## Filter Options

The `buildFilterQuery` method supports various filter criteria:

### Status Filters

```php
'status' => TicketStatus::Open->value,                    // Single status
'status' => [TicketStatus::Open->value, TicketStatus::InProgress->value],   // Multiple statuses
```

### Priority Filters

```php
'priority' => TicketPriority::Urgent->value,
'priority' => [TicketPriority::High->value, TicketPriority::Urgent->value],
```

### Assignment Filters

```php
'assigned' => true,     // Only assigned tickets
'assigned' => false,    // Only unassigned tickets
```

### Category and Tag Filters

```php
'category_ids' => [1, 2, 3],
'tag_ids' => [5, 6, 7],
```

### Date Filters

```php
'created_after' => '2023-01-01',
'created_before' => '2023-12-31',
```

### SLA Filters

```php
'sla_status' => 'breached',           // SLA breached tickets
'sla_status' => 'approaching',        // Approaching SLA breach
'sla_status' => 'within',             // Within SLA
'sla_threshold_minutes' => 60,        // For approaching filter
```

### Search Filters

```php
'search' => 'login issue',  // Searches subject, description, and ULID
```

## Usage Examples

### Mass Status Update

Update all old open tickets to closed:

```php
$service = app(BulkActionService::class);

$results = $service->applyActionWithFilter(
    action: 'change_status',
    query: Ticket::where('status', TicketStatus::Open->value)
        ->where('created_at', '<', now()->subDays(30)),
    params: ['status' => TicketStatus::Closed->value]
);

Log::info("Bulk status update completed", $results);
```

### Emergency Priority Escalation

Escalate all urgent unassigned tickets:

```php
$urgentQuery = Ticket::where('priority', TicketPriority::Urgent->value)
    ->whereNull('assigned_to_id')
    ->where('status', TicketStatus::Open->value);

$results = $service->applyActionWithFilter(
    action: 'assign',
    query: $urgentQuery,
    params: [
        'assignee_id' => $emergencyAgent->id,
        'assignee_type' => User::class
    ]
);
```

### Cleanup Operations

Clean up spam tickets:

```php
$spamTickets = Ticket::where('subject', 'like', '%spam%')
    ->orWhere('description', 'like', '%spam%')
    ->pluck('id')
    ->toArray();

$results = $service->applyAction('delete', $spamTickets, [
    'soft_delete' => true
]);
```

### Tag Management

Add seasonal tags to relevant tickets:

```php
// Find all tickets mentioning holiday issues
$filters = [
    'search' => 'holiday',
    'status' => [TicketStatus::Open->value, TicketStatus::InProgress->value],
    'created_after' => '2023-11-01'
];

$query = $service->buildFilterQuery($filters);

$results = $service->applyActionWithFilter(
    action: 'add_tags',
    query: $query,
    params: ['tag_ids' => [Tag::where('name', 'holiday-support')->first()->id]]
);
```

### SLA Breach Response

Handle SLA-breached tickets:

```php
$filters = [
    'sla_status' => 'breached',
    'assigned' => false
];

$query = $service->buildFilterQuery($filters);

// Change priority and assign to senior team
$priorityResults = $service->applyActionWithFilter(
    action: 'change_priority',
    query: clone $query,
    params: ['priority' => TicketPriority::Urgent->value]
);

$assignResults = $service->applyActionWithFilter(
    action: 'assign',
    query: clone $query,
    params: [
        'assignee_id' => $seniorTeamLead->id,
        'assignee_type' => User::class
    ]
);
```

### Customer Tier Management

Update tickets from VIP customers:

```php
$vipQuery = Ticket::whereHas('customer', function ($q) {
    $q->where('tier', 'vip');
})->where('priority', '!=', TicketPriority::Urgent->value);

$results = $service->applyActionWithFilter(
    action: 'change_priority',
    query: $vipQuery,
    params: ['priority' => TicketPriority::High->value]
);
```

## Advanced Usage

### Custom Bulk Actions

While the service provides predefined actions, you can extend it by adding custom action handlers:

```php
class CustomBulkActionService extends BulkActionService
{
    protected array $allowedActions = [
        // ... existing actions
        'custom_notification',
        'export_to_crm',
    ];

    protected function handleCustomNotification(Ticket $ticket, array $params): bool
    {
        // Custom notification logic
        return true;
    }

    protected function handleExportToCrm(Ticket $ticket, array $params): bool
    {
        // CRM export logic
        return true;
    }
}
```

### Progress Tracking

For large bulk operations, implement progress tracking:

```php
class BulkActionWithProgress
{
    public function applyWithProgress(string $action, array $ticketIds, array $params = [])
    {
        $total = count($ticketIds);
        $processed = 0;

        foreach ($ticketIds as $ticketId) {
            // Process individual ticket
            $ticket = Ticket::find($ticketId);
            if ($ticket) {
                // Apply action logic here
                $processed++;

                // Update progress
                $this->updateProgress($processed, $total);
            }
        }
    }

    private function updateProgress(int $processed, int $total): void
    {
        $percentage = round(($processed / $total) * 100, 2);

        // Update progress in cache/database
        cache()->put('bulk_action_progress', [
            'processed' => $processed,
            'total' => $total,
            'percentage' => $percentage
        ]);
    }
}
```

### Validation and Permissions

Add validation and permission checks:

```php
class SecureBulkActionService extends BulkActionService
{
    public function applyAction(string $action, array $ticketIds, array $params = []): array
    {
        // Validate user permissions
        if (!$this->canPerformAction($action)) {
            throw new UnauthorizedException("Not allowed to perform {$action}");
        }

        // Validate ticket ownership
        $ticketIds = $this->filterOwnedTickets($ticketIds);

        return parent::applyAction($action, $ticketIds, $params);
    }

    private function canPerformAction(string $action): bool
    {
        return auth()->user()->can("bulk_{$action}_tickets");
    }

    private function filterOwnedTickets(array $ticketIds): array
    {
        return Ticket::whereIn('id', $ticketIds)
            ->whereIn('assigned_to_id', [auth()->id()])
            ->pluck('id')
            ->toArray();
    }
}
```

## Performance Considerations

1. **Batch Size**: Process tickets in batches to avoid memory issues
2. **Database Transactions**: Use transactions for consistency
3. **Background Jobs**: Use queues for large operations
4. **Progress Tracking**: Implement progress indicators for UX
5. **Error Handling**: Log and handle individual failures gracefully

### Queue Implementation

```php
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class BulkActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $action,
        public array $ticketIds,
        public array $params = []
    ) {}

    public function handle(BulkActionService $service): void
    {
        $results = $service->applyAction(
            $this->action,
            $this->ticketIds,
            $this->params
        );

        // Handle results, send notifications, etc.
        Log::info('Bulk action completed', $results);
    }
}

// Usage
BulkActionJob::dispatch('change_status', $ticketIds, ['status' => TicketStatus::Resolved->value]);
```

## Best Practices

1. **Test First**: Test bulk actions on a small set before full deployment
2. **Backup Data**: Always backup before major bulk operations
3. **User Feedback**: Provide clear feedback on operation results
4. **Audit Trail**: Log all bulk operations for audit purposes
5. **Permissions**: Implement proper authorization checks
6. **Validation**: Validate parameters before processing
7. **Error Recovery**: Plan for partial failures and recovery
8. **Resource Limits**: Set appropriate limits on batch sizes

## Error Handling

The service provides comprehensive error handling:

```php
$results = $service->applyAction('change_status', $ticketIds, ['status' => TicketStatus::Resolved->value]);

// Check for failures
if ($results['failed'] > 0) {
    foreach ($results['details'] as $ticketId => $detail) {
        if ($detail['status'] === 'failed') {
            Log::error("Failed to update ticket {$ticketId}", [
                'error' => $detail['error'] ?? 'Unknown error'
            ]);
        }
    }
}
```

## Integration Examples

### With Admin Panel

```php
class TicketController extends Controller
{
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'action' => 'required|string|in:change_status,assign,add_tags',
            'ticket_ids' => 'required|array|min:1',
            'params' => 'required|array'
        ]);

        $results = $this->bulkActionService->applyAction(
            $request->input('action'),
            $request->input('ticket_ids'),
            $request->input('params')
        );

        return response()->json([
            'message' => "Processed {$results['success']} tickets successfully",
            'results' => $results
        ]);
    }
}
```

### With Scheduled Commands

```php
class CloseInactiveTickets extends Command
{
    protected $signature = 'helpdesk:close-inactive {--days=30}';

    public function handle(BulkActionService $service)
    {
        $days = $this->option('days');

        $results = $service->applyActionWithFilter(
            action: 'close',
            query: Ticket::where('status', 'pending')
                ->where('updated_at', '<', now()->subDays($days)),
            params: [
                'comment' => "Auto-closed due to {$days} days of inactivity",
                'add_comment' => true
            ]
        );

        $this->info("Closed {$results['success']} inactive tickets");

        if ($results['failed'] > 0) {
            $this->warn("Failed to close {$results['failed']} tickets");
        }
    }
}
```