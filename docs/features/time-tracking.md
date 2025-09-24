# Time Tracking

The Laravel Helpdesk package includes comprehensive time tracking functionality that allows support agents to track time spent on tickets, generate reports, and calculate costs for billable work.

## Overview

The `TimeTrackingService` provides functionality to:
- Start and stop timers for active work sessions
- Log time entries manually with specific durations
- Track billable and non-billable time separately
- Generate detailed time reports for users and projects
- Calculate costs based on hourly rates

## Service Methods

### Start Timer

Start a new timer for active time tracking on a ticket.

```php
use LucaLongo\LaravelHelpdesk\Services\TimeTrackingService;

$service = app(TimeTrackingService::class);

$entry = $service->startTimer(
    ticketId: $ticket->id,
    userId: auth()->id(),
    description: 'Investigating database connection issue',
    stopRunning: true  // Stop other running timers for this user
);
```

**Parameters:**
- `$ticketId` (int) - ID of the ticket
- `$userId` (int|null) - User ID (defaults to authenticated user)
- `$description` (string|null) - Description of work being performed
- `$stopRunning` (bool) - Whether to stop other running timers for the user

**Returns:** `TicketTimeEntry` model

### Stop Timer

Stop a running timer and calculate the duration.

```php
$entry = $service->stopTimer($entryId);
```

**Returns:** `TicketTimeEntry|null` - The stopped entry, or null if not found/not running

### Stop User's Running Timers

Stop all running timers for a specific user.

```php
$stoppedEntries = $service->stopUserRunningTimers($userId);
```

**Returns:** `Collection` of stopped time entries

### Log Time Manually

Log a completed time entry without using a timer.

```php
$entry = $service->logTime(
    ticketId: $ticket->id,
    durationMinutes: 90,
    userId: auth()->id(),
    description: 'Resolved authentication bug',
    startedAt: now()->subMinutes(90),
    isBillable: true,
    hourlyRate: 75.00
);
```

**Parameters:**
- `$ticketId` (int) - ID of the ticket
- `$durationMinutes` (int) - Duration in minutes
- `$userId` (int|null) - User ID
- `$description` (string|null) - Description of work
- `$startedAt` (Carbon|null) - When work started (defaults to duration ago)
- `$isBillable` (bool) - Whether time is billable
- `$hourlyRate` (float|null) - Hourly rate for billing

### Get Ticket Time Entries

Retrieve all time entries for a specific ticket.

```php
$entries = $service->getTicketTimeEntries($ticketId);
```

**Returns:** `Collection` of `TicketTimeEntry` models with user relationships loaded

### Get Ticket Total Time

Calculate total time spent on a ticket.

```php
// All time (billable and non-billable)
$totalMinutes = $service->getTicketTotalTime($ticketId);

// Only billable time
$billableMinutes = $service->getTicketTotalTime($ticketId, billableOnly: true);
```

**Returns:** `int` - Total minutes

### Get Ticket Total Cost

Calculate total billable cost for a ticket.

```php
$totalCost = $service->getTicketTotalCost($ticketId);
```

**Returns:** `float` - Total cost for billable time entries with hourly rates

### Generate User Time Report

Generate a comprehensive time report for a user within a date range.

```php
use Carbon\Carbon;

$report = $service->getUserTimeReport(
    userId: $user->id,
    startDate: Carbon::parse('2023-01-01'),
    endDate: Carbon::parse('2023-01-31'),
    billableOnly: null  // null=all, true=billable only, false=non-billable only
);
```

**Returns:** Detailed report array:
```php
[
    'user_id' => 123,
    'start_date' => '2023-01-01',
    'end_date' => '2023-01-31',
    'total_minutes' => 2400,
    'total_hours' => 40.0,
    'billable_minutes' => 1800,
    'billable_hours' => 30.0,
    'total_cost' => 2250.00,
    'entries_count' => 15,
    'by_ticket' => [
        'ticket_id' => [
            'ticket' => Ticket::model,
            'total_minutes' => 480,
            'billable_minutes' => 360,
            'total_cost' => 450.00,
            'entries_count' => 3
        ]
    ]
]
```

### Generate Project Time Report

Generate a time report for all tickets in a project.

```php
$report = $service->getProjectTimeReport(
    projectIdentifier: 'ecommerce-redesign',
    startDate: Carbon::parse('2023-01-01'),
    endDate: Carbon::parse('2023-01-31')
);
```

**Returns:** Project report with breakdowns by user and ticket

### Update Time Entry

Modify an existing time entry.

```php
$entry = $service->updateEntry($entryId, [
    'description' => 'Updated description',
    'is_billable' => false,
    'hourly_rate' => 80.00
]);
```

### Delete Time Entry

Remove a time entry.

```php
$success = $service->deleteEntry($entryId);
```

**Returns:** `bool` - Whether deletion was successful

## TicketTimeEntry Model

The time entry model stores time tracking data for tickets.

### Model Properties

```php
class TicketTimeEntry extends Model
{
    protected $fillable = [
        'ticket_id',           // Associated ticket
        'user_id',            // User who performed the work
        'started_at',         // When work started
        'ended_at',           // When work ended (null for running timers)
        'duration_minutes',   // Duration in minutes
        'description',        // Description of work performed
        'is_billable',        // Whether time is billable
        'hourly_rate',        // Hourly rate for billing
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_billable' => 'boolean',
        'hourly_rate' => 'decimal:2',
    ];
}
```

### Model Methods

```php
// Check if timer is currently running
$isRunning = $entry->isRunning();

// Stop a running timer
$entry->stop();

// Get duration in hours
$hours = $entry->duration_hours;

// Get total cost (if billable and has hourly rate)
$cost = $entry->total_cost;
```

### Relationships

```php
// Get associated ticket
$entry->ticket;

// Get user who logged the time
$entry->user;
```

### Scopes

```php
// Get only running timers
TicketTimeEntry::running()->get();

// Get only billable entries
TicketTimeEntry::billable()->get();

// Get entries by user
TicketTimeEntry::byUser($userId)->get();

// Get entries in date range
TicketTimeEntry::dateRange($startDate, $endDate)->get();
```

## Events

### TimeEntryStarted

Fired when a timer is started.

```php
use LucaLongo\LaravelHelpdesk\Events\TimeEntryStarted;

class TimeEntryStarted
{
    public function __construct(
        public TicketTimeEntry $entry
    ) {}
}
```

### TimeEntryStopped

Fired when a timer is stopped.

```php
use LucaLongo\LaravelHelpdesk\Events\TimeEntryStopped;

class TimeEntryStopped
{
    public function __construct(
        public TicketTimeEntry $entry
    ) {}
}
```

## Configuration

Configure time tracking in `config/helpdesk.php`:

```php
'time_tracking' => [
    'billable_by_default' => true,
    'default_hourly_rate' => 75.00,
    'round_to_minutes' => 15,  // Round time entries to nearest 15 minutes
    'max_daily_hours' => 16,   // Maximum hours per day
    'auto_stop_hours' => 8,    // Auto-stop timers after X hours
],
```

## Usage Examples

### Basic Time Tracking Workflow

```php
$service = app(TimeTrackingService::class);

// Agent starts working on a ticket
$timer = $service->startTimer(
    ticketId: $ticket->id,
    userId: $agent->id,
    description: 'Investigating customer login issue'
);

// ... work is performed ...

// Agent stops the timer
$completedEntry = $service->stopTimer($timer->id);

echo "Worked for {$completedEntry->duration_hours} hours";
echo "Cost: $" . number_format($completedEntry->total_cost, 2);
```

### Manual Time Logging

```php
// Log time for work done offline
$entry = $service->logTime(
    ticketId: $ticket->id,
    durationMinutes: 120,
    userId: $agent->id,
    description: 'Researched database optimization techniques',
    startedAt: now()->subHours(2),
    isBillable: true,
    hourlyRate: 85.00
);
```

### Generating Reports

```php
// Weekly report for an agent
$weeklyReport = $service->getUserTimeReport(
    userId: $agent->id,
    startDate: now()->startOfWeek(),
    endDate: now()->endOfWeek()
);

echo "Total hours worked: {$weeklyReport['total_hours']}";
echo "Billable hours: {$weeklyReport['billable_hours']}";
echo "Total earnings: $" . number_format($weeklyReport['total_cost'], 2);

// Project summary
$projectReport = $service->getProjectTimeReport(
    projectIdentifier: 'mobile-app',
    startDate: now()->startOfMonth(),
    endDate: now()->endOfMonth()
);

foreach ($projectReport['by_user'] as $userData) {
    echo "{$userData['user']->name}: {$userData['total_hours']} hours";
}
```

### Integration with Ticket Workflow

```php
class TicketController extends Controller
{
    public function show(Ticket $ticket)
    {
        $timeEntries = $this->timeService->getTicketTimeEntries($ticket->id);
        $totalTime = $this->timeService->getTicketTotalTime($ticket->id);
        $totalCost = $this->timeService->getTicketTotalCost($ticket->id);

        return view('tickets.show', compact('ticket', 'timeEntries', 'totalTime', 'totalCost'));
    }

    public function startWork(Ticket $ticket)
    {
        $entry = $this->timeService->startTimer(
            ticketId: $ticket->id,
            userId: auth()->id(),
            description: request('description')
        );

        return response()->json(['entry' => $entry]);
    }
}
```

### Time Entry Validation

```php
class TimeEntryRequest extends FormRequest
{
    public function rules()
    {
        return [
            'duration_minutes' => 'required|integer|min:1|max:960', // Max 16 hours
            'description' => 'required|string|max:500',
            'is_billable' => 'boolean',
            'hourly_rate' => 'nullable|numeric|min:0|max:1000',
        ];
    }
}
```

### Running Timer Management

```php
// Check for running timers on login
public function checkRunningTimers(User $user)
{
    $runningTimers = TicketTimeEntry::running()
        ->byUser($user->id)
        ->with('ticket')
        ->get();

    if ($runningTimers->isNotEmpty()) {
        // Show notification about running timers
        foreach ($runningTimers as $timer) {
            $duration = $timer->started_at->diffInMinutes(now());

            if ($duration > 480) { // 8 hours
                // Auto-stop timer or send warning
                $this->timeService->stopTimer($timer->id);
            }
        }
    }
}
```

### Billing Integration

```php
class BillingService
{
    public function generateInvoice(Customer $customer, Carbon $startDate, Carbon $endDate)
    {
        $tickets = $customer->tickets()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $billableEntries = TicketTimeEntry::whereIn('ticket_id', $tickets->pluck('id'))
            ->billable()
            ->dateRange($startDate, $endDate)
            ->with(['ticket', 'user'])
            ->get();

        $total = $billableEntries->sum('total_cost');

        return [
            'entries' => $billableEntries,
            'total_hours' => $billableEntries->sum('duration_minutes') / 60,
            'total_amount' => $total,
        ];
    }
}
```

## Best Practices

1. **Timer Management**: Encourage agents to start/stop timers consistently
2. **Descriptions**: Require meaningful descriptions for time entries
3. **Billable vs Non-billable**: Clearly define what work is billable
4. **Regular Reviews**: Review time entries for accuracy before billing
5. **Auto-stop**: Implement auto-stop for long-running timers
6. **Reporting**: Generate regular reports for analysis
7. **Rate Management**: Keep hourly rates updated and role-appropriate
8. **Integration**: Integrate with existing project management tools

## Database Schema

The time tracking system uses the `helpdesk_ticket_time_entries` table:

```php
Schema::create('helpdesk_ticket_time_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->timestamp('started_at');
    $table->timestamp('ended_at')->nullable();
    $table->integer('duration_minutes')->nullable();
    $table->text('description')->nullable();
    $table->boolean('is_billable')->default(true);
    $table->decimal('hourly_rate', 8, 2)->nullable();
    $table->timestamps();

    $table->index(['ticket_id', 'user_id']);
    $table->index(['started_at', 'ended_at']);
    $table->index('is_billable');
});
```

## Advanced Features

### Automatic Time Tracking

Implement automatic time tracking based on ticket activity:

```php
// Track time automatically when agent works on ticket
class TrackTimeOnActivity
{
    public function handle(TicketCommentAdded $event): void
    {
        if ($event->comment->author_type === User::class) {
            $timeService = app(TimeTrackingService::class);

            // Log minimum time for comment activity
            $timeService->logTime(
                ticketId: $event->ticket->id,
                durationMinutes: 15, // Minimum 15 minutes
                userId: $event->comment->author_id,
                description: 'Ticket activity - comment added',
                isBillable: true
            );
        }
    }
}
```

### Time Tracking Analytics

```php
class TimeTrackingAnalytics
{
    public function getProductivityMetrics(User $user, Carbon $period)
    {
        $entries = TicketTimeEntry::byUser($user->id)
            ->dateRange($period->startOfMonth(), $period->endOfMonth())
            ->with('ticket')
            ->get();

        return [
            'total_hours' => $entries->sum('duration_minutes') / 60,
            'billable_percentage' => $entries->where('is_billable', true)->sum('duration_minutes') / $entries->sum('duration_minutes') * 100,
            'avg_session_duration' => $entries->avg('duration_minutes'),
            'tickets_worked_on' => $entries->pluck('ticket_id')->unique()->count(),
            'avg_time_per_ticket' => $entries->groupBy('ticket_id')->map->sum('duration_minutes')->avg(),
        ];
    }
}
```