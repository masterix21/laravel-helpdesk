# SLA Management

Service Level Agreement (SLA) management ensures timely response and resolution of support tickets.

## Overview

The SLA system automatically tracks response and resolution times, sends warnings when deadlines approach, and records breaches for reporting.

## SLA Configuration

### Priority-Based Rules

Configure SLA rules in `config/helpdesk.php`:

```php
'sla' => [
    'enabled' => true,
    'rules' => [
        'urgent' => [
            'first_response' => 30,  // 30 minutes
            'resolution' => 240,     // 4 hours
        ],
        'high' => [
            'first_response' => 120, // 2 hours
            'resolution' => 480,     // 8 hours
        ],
        'normal' => [
            'first_response' => 240, // 4 hours
            'resolution' => 1440,    // 24 hours
        ],
        'low' => [
            'first_response' => 480, // 8 hours
            'resolution' => 2880,    // 48 hours
        ],
    ],
],
```

### Type-Specific Overrides

Different ticket types can have custom SLA rules:

```php
'type_overrides' => [
    'commercial' => [
        'high' => [
            'first_response' => 60,  // 1 hour for commercial inquiries
            'resolution' => 240,     // 4 hours
        ],
    ],
    'bug_report' => [
        'urgent' => [
            'first_response' => 15,  // 15 minutes for urgent bugs
            'resolution' => 120,     // 2 hours
        ],
    ],
],
```

## Using SLA Service

### Calculate SLA Due Dates

```php
use LucaLongo\LaravelHelpdesk\Services\SlaService;

$slaService = app(SlaService::class);

// Calculate SLA for a ticket
$slaService->calculateSlaDueDates($ticket);

// After calculation, ticket will have:
// - first_response_due_at
// - resolution_due_at
```

### Check SLA Compliance

```php
// Check if ticket is compliant
$isCompliant = $slaService->checkCompliance($ticket);

// Get compliance details
$compliance = $slaService->getComplianceStatus($ticket);
// Returns: ['first_response' => true/false, 'resolution' => true/false]

// Get time remaining
$remaining = $slaService->getTimeRemaining($ticket);
// Returns: ['first_response' => minutes, 'resolution' => minutes]
```

### Handle SLA Breaches

```php
// Mark as breached
$slaService->markAsBreached($ticket, 'first_response');

// Check breach status
if ($ticket->sla_breached) {
    $breachType = $ticket->sla_breach_type; // 'first_response' or 'resolution'
}

// Get breached tickets
$breachedTickets = Ticket::slaBreached()->get();
```

## Business Hours

### Configure Business Hours

```php
'business_hours' => [
    'enabled' => true,
    'timezone' => 'America/New_York',
    'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    'start' => '09:00',
    'end' => '17:00',
    'holidays' => [
        '2024-01-01', // New Year
        '2024-07-04', // Independence Day
        '2024-12-25', // Christmas
    ],
],
```

### Calculate with Business Hours

```php
use LucaLongo\LaravelHelpdesk\Support\BusinessHours;

$businessHours = new BusinessHours(config('helpdesk.sla.business_hours'));

// Calculate deadline considering business hours
$deadline = $businessHours->addMinutes(now(), 240); // 4 business hours from now

// Check if datetime is within business hours
$isBusinessHour = $businessHours->isBusinessHour(now());

// Get next business hour
$nextBusinessHour = $businessHours->nextBusinessHour(now());
```

## SLA Warnings

### Configure Warning Thresholds

```php
'warning_thresholds' => [
    75, // Warning when 75% of time consumed
    90, // Critical when 90% of time consumed
],
```

### Monitor SLA Warnings

```php
// Check warning status
$warnings = $slaService->checkWarnings($ticket);

if ($warnings['first_response'] >= 75) {
    // Send warning notification
    event(new SlaWarning($ticket, 'first_response', $warnings['first_response']));
}

// Get tickets approaching SLA
$approachingTickets = Ticket::query()
    ->whereNotNull('first_response_due_at')
    ->where('first_response_due_at', '<=', now()->addMinutes(30))
    ->whereNull('first_response_at')
    ->get();
```

## SLA Events

### Available Events

```php
use LucaLongo\LaravelHelpdesk\Events\SlaWarning;
use LucaLongo\LaravelHelpdesk\Events\SlaBreach;

// Listen for SLA warnings
Event::listen(SlaWarning::class, function ($event) {
    $ticket = $event->ticket;
    $type = $event->type; // 'first_response' or 'resolution'
    $percentage = $event->percentage;

    // Send urgent notification to manager
    Mail::to('manager@example.com')->send(new SlaWarningMail($ticket));
});

// Listen for SLA breaches
Event::listen(SlaBreach::class, function ($event) {
    $ticket = $event->ticket;
    $type = $event->breachType;

    // Escalate ticket
    // Log breach for reporting
    // Notify customer service manager
});
```

## SLA Reporting

### Generate SLA Reports

```php
use LucaLongo\LaravelHelpdesk\Services\ReportingService;

$reportingService = app(ReportingService::class);

// Get SLA compliance metrics
$metrics = $reportingService->getSlaMetrics(
    startDate: now()->subMonth(),
    endDate: now()
);

// Returns:
[
    'total_tickets' => 500,
    'first_response' => [
        'compliant' => 450,
        'breached' => 50,
        'compliance_rate' => 90.0,
        'average_time' => 145, // minutes
    ],
    'resolution' => [
        'compliant' => 420,
        'breached' => 80,
        'compliance_rate' => 84.0,
        'average_time' => 1320, // minutes
    ],
]
```

### Query SLA Performance

```php
// Tickets by SLA status
$metrics = Ticket::query()
    ->selectRaw('
        COUNT(*) as total,
        SUM(sla_breached = 1) as breached,
        SUM(sla_breached = 0) as compliant,
        AVG(response_time_minutes) as avg_response_time,
        AVG(resolution_time_minutes) as avg_resolution_time
    ')
    ->whereBetween('created_at', [$startDate, $endDate])
    ->first();

// SLA performance by priority
$byPriority = Ticket::query()
    ->selectRaw('
        priority,
        COUNT(*) as total,
        AVG(CASE WHEN sla_breached = 1 THEN 1 ELSE 0 END) * 100 as breach_rate
    ')
    ->groupBy('priority')
    ->get();

// SLA performance by agent
$byAgent = Ticket::query()
    ->selectRaw('
        assigned_to_id,
        COUNT(*) as total_tickets,
        AVG(response_time_minutes) as avg_response,
        SUM(sla_breached = 1) as breaches
    ')
    ->whereNotNull('assigned_to_id')
    ->groupBy('assigned_to_id')
    ->get();
```

## Scheduled SLA Checks

### Configure Scheduled Command

In `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Check SLA compliance every minute
    $schedule->command('helpdesk:check-sla')
        ->everyMinute()
        ->withoutOverlapping();

    // Generate SLA report daily
    $schedule->command('helpdesk:sla-report')
        ->daily()
        ->at('09:00');

    // Send SLA breach summary weekly
    $schedule->command('helpdesk:sla-summary')
        ->weekly()
        ->mondays()
        ->at('08:00');
}
```

### Create Custom SLA Command

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LucaLongo\LaravelHelpdesk\Services\SlaService;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class CheckSlaCompliance extends Command
{
    protected $signature = 'helpdesk:check-sla';
    protected $description = 'Check SLA compliance for all open tickets';

    public function handle(SlaService $slaService): void
    {
        $tickets = Ticket::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->get();

        $this->info("Checking SLA for {$tickets->count()} tickets...");

        foreach ($tickets as $ticket) {
            $compliance = $slaService->checkCompliance($ticket);

            if (!$compliance['first_response'] && !$ticket->first_response_at) {
                $this->warn("Ticket #{$ticket->ticket_number} - First response overdue");
                event(new SlaBreach($ticket, 'first_response'));
            }

            if (!$compliance['resolution'] && !$ticket->isResolved()) {
                $this->warn("Ticket #{$ticket->ticket_number} - Resolution overdue");
                event(new SlaBreach($ticket, 'resolution'));
            }
        }

        $this->info('SLA check completed.');
    }
}
```

## SLA Pause and Resume

### Pause SLA Timer

```php
// Pause SLA when waiting for customer
$slaService->pauseSla($ticket, 'Waiting for customer response');

// Resume SLA when customer responds
$slaService->resumeSla($ticket);

// Check if SLA is paused
if ($ticket->sla_paused_at) {
    $pausedDuration = $ticket->sla_paused_minutes;
}
```

### Exclude Time from SLA

```php
// Exclude maintenance windows
$slaService->excludeTimeRange(
    $ticket,
    start: '2024-01-15 02:00:00',
    end: '2024-01-15 04:00:00',
    reason: 'Scheduled maintenance'
);
```

## Custom SLA Rules

### Create Custom SLA Calculator

```php
<?php

namespace App\Services;

use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Services\SlaService;

class CustomSlaCalculator extends SlaService
{
    public function calculateCustomSla(Ticket $ticket): array
    {
        // Custom logic based on customer tier
        $customer = $ticket->opener;

        if ($customer->is_vip) {
            return [
                'first_response' => 15, // 15 minutes for VIP
                'resolution' => 120,    // 2 hours
            ];
        }

        if ($customer->subscription === 'enterprise') {
            return [
                'first_response' => 30,
                'resolution' => 240,
            ];
        }

        // Default to standard SLA
        return parent::getSlaRules($ticket);
    }
}
```

## Best Practices

### 1. Set Realistic SLA Targets

```php
// Consider your team's capacity
'rules' => [
    'urgent' => [
        'first_response' => 30,  // Achievable with proper staffing
        'resolution' => 240,     // Realistic for urgent issues
    ],
],
```

### 2. Monitor SLA Performance

```php
// Regular monitoring
$compliance = Ticket::query()
    ->whereBetween('created_at', [now()->subWeek(), now()])
    ->selectRaw('AVG(CASE WHEN sla_breached = 0 THEN 1 ELSE 0 END) * 100 as rate')
    ->first();

if ($compliance->rate < 90) {
    // Alert management about declining SLA performance
}
```

### 3. Automate SLA Escalation

```php
// In automation rules
'conditions' => [
    ['type' => 'sla_percentage', 'operator' => '>=', 'value' => 75],
],
'actions' => [
    ['type' => 'escalate', 'level' => 1],
    ['type' => 'notify', 'recipients' => ['manager']],
],
```

### 4. Provide SLA Transparency

```php
// Show SLA status to customers
public function getTicketSlaStatus(Ticket $ticket): array
{
    return [
        'due_date' => $ticket->resolution_due_at,
        'time_remaining' => $ticket->resolution_due_at->diffForHumans(),
        'compliance' => $ticket->sla_breached ? 'breached' : 'on-track',
    ];
}
```

## Next Steps

- [Time Tracking](time-tracking.md) - Track time spent on tickets
- [Automation](automation.md) - Automate SLA escalations
- [Reporting](../api/services.md#reporting) - Generate SLA reports