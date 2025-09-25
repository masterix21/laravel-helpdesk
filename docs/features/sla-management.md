# SLA Management

Service Level Agreement (SLA) management ensures timely response and resolution of tickets based on priority levels.

## Configuration

SLA rules are configured in `config/helpdesk.php`:

```php
'sla' => [
    'enabled' => true,
    'rules' => [
        // Priority-based SLA rules (in minutes)
        TicketPriority::Urgent->value => [
            'first_response' => 30,  // 30 minutes
            'resolution' => 240,      // 4 hours
        ],
        TicketPriority::High->value => [
            'first_response' => 120,  // 2 hours
            'resolution' => 480,      // 8 hours
        ],
        TicketPriority::Normal->value => [
            'first_response' => 240,  // 4 hours
            'resolution' => 1440,     // 24 hours
        ],
        TicketPriority::Low->value => [
            'first_response' => 480,  // 8 hours
            'resolution' => 2880,     // 48 hours
        ],
    ],
    // Type-specific overrides
    'type_overrides' => [
        TicketType::Commercial->value => [
            TicketPriority::High->value => [
                'first_response' => 60,  // 1 hour for commercial high priority
                'resolution' => 240,     // 4 hours
            ],
        ],
    ],
    // Warning thresholds (percentage)
    'warning_thresholds' => [75, 90],
],
```

## How It Works

### Automatic SLA Calculation

When a ticket is created, SLA due dates are automatically calculated:

```php
$ticket = LaravelHelpdesk::open([
    'type' => 'support',
    'priority' => 'high',
    // ...
]);

// SLA dates are set automatically
echo $ticket->first_response_due_at;  // 2 hours from now
echo $ticket->resolution_due_at;      // 8 hours from now
```

### First Response Tracking

The first public comment from support marks the first response:

```php
// This marks first response time
LaravelHelpdesk::comment($ticket, 'Looking into this', $agent);

// Ticket is updated
echo $ticket->first_response_at;        // Now
echo $ticket->response_time_minutes;    // Minutes taken to respond
```

### Resolution Tracking

When a ticket is closed, resolution time is calculated:

```php
LaravelHelpdesk::transition($ticket, TicketStatus::Closed);

echo $ticket->resolution_time_minutes;  // Minutes taken to resolve
```

## SLA Breach Detection

### Automatic Breach Detection

The system automatically detects SLA breaches:

```php
// Check if SLA is breached
if ($ticket->sla_breached) {
    echo "Breach type: " . $ticket->sla_breach_type; // 'first_response' or 'resolution'
}
```

### Query Breached Tickets

```php
use LucaLongo\LaravelHelpdesk\Models\Ticket;

// Get all breached tickets
$breached = Ticket::breachedSla()->get();

// Get tickets approaching SLA (75% of time consumed)
$approaching = Ticket::approachingSla(75)->get();

// Get overdue tickets
$overdue = Ticket::overdueSla()->get();

// Get tickets within SLA
$withinSla = Ticket::withinSla()->get();
```

## SLA Compliance Metrics

### Calculate Compliance Percentage

```php
$ticket = Ticket::find($id);

// Get SLA compliance percentage (0-100)
$compliance = $ticket->slaCompliancePercentage();

if ($compliance < 50) {
    // Less than 50% time remaining
    $this->escalate($ticket);
}
```

### Bulk SLA Analysis

```php
use LucaLongo\LaravelHelpdesk\Services\SlaService;

$slaService = app(SlaService::class);

// Check all open tickets for SLA status
$tickets = Ticket::open()->get();
foreach ($tickets as $ticket) {
    $slaService->checkCompliance($ticket);

    if ($ticket->wasChanged('sla_breached')) {
        // SLA was just breached
        event(new SlaBreached($ticket));
    }
}
```

## Working with SLA Scopes

```php
// Complex queries with SLA
$criticalTickets = Ticket::query()
    ->where('priority', TicketPriority::Urgent)
    ->overdueSla()
    ->with(['assignee', 'comments'])
    ->get();

// Combine multiple SLA scopes
$needsAttention = Ticket::query()
    ->approachingSla(90)  // 90% of time consumed
    ->orWhere->breachedSla()
    ->orderBy('priority', 'desc')
    ->get();
```

## Custom SLA Rules

Override SLA for specific tickets:

```php
// Set custom SLA for VIP customer
$ticket = LaravelHelpdesk::open([...]);

$ticket->update([
    'first_response_due_at' => now()->addMinutes(15),
    'resolution_due_at' => now()->addHours(2),
]);
```

## SLA Events and Notifications

Listen for SLA-related events:

```php
use Illuminate\Support\Facades\Event;

// When SLA is approaching
Event::listen(SlaApproaching::class, function ($event) {
    $ticket = $event->ticket;
    $percentage = $event->percentageRemaining;

    // Notify manager
    $ticket->assignee?->notify(new SlaWarningNotification($ticket));
});

// When SLA is breached
Event::listen(SlaBreached::class, function ($event) {
    $ticket = $event->ticket;

    // Escalate to manager
    $ticket->update(['priority' => TicketPriority::Urgent]);

    // Log breach
    Log::error('SLA breached', [
        'ticket_id' => $ticket->id,
        'type' => $ticket->sla_breach_type,
    ]);
});
```

## Disable SLA

### Globally

```php
// config/helpdesk.php
'sla' => [
    'enabled' => false,
],
```

### For Specific Tickets

```php
$ticket = LaravelHelpdesk::open([...]);

// Clear SLA dates
$ticket->update([
    'first_response_due_at' => null,
    'resolution_due_at' => null,
]);
```

## Best Practices

1. **Set Realistic Targets**: Configure SLA times based on your team's capacity
2. **Use Priority Levels**: Let priority drive SLA calculations automatically
3. **Monitor Compliance**: Regularly check SLA metrics
4. **Automate Escalation**: Use automation rules for SLA breaches
5. **Track Patterns**: Identify recurring SLA issues

## Reporting

Generate SLA reports:

```php
$report = Ticket::query()
    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
    ->selectRaw('
        COUNT(*) as total,
        SUM(sla_breached) as breached,
        AVG(response_time_minutes) as avg_response_time,
        AVG(resolution_time_minutes) as avg_resolution_time
    ')
    ->first();

echo "SLA Compliance: " . (($report->total - $report->breached) / $report->total * 100) . "%";
echo "Avg Response Time: " . $report->avg_response_time . " minutes";
echo "Avg Resolution Time: " . $report->avg_resolution_time . " minutes";
```