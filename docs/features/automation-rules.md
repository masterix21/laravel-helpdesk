# Automation Rules

The Laravel Helpdesk package includes a powerful automation system that can automatically perform actions on tickets based on configurable conditions. This helps streamline support workflows and reduce manual intervention.

## Overview

The `AutomationService` provides functionality to:
- Create and manage automation rules with conditions and actions
- Process tickets against automation rules automatically
- Test rules before deployment
- Track rule execution statistics
- Apply rule templates for common scenarios

## Service Methods

### Process Single Ticket

Process a ticket against all active automation rules.

```php
use LucaLongo\LaravelHelpdesk\Services\AutomationService;

$service = app(AutomationService::class);

$results = $service->processTicket($ticket, 'manual');
```

**Parameters:**
- `$ticket` (Ticket) - The ticket to process
- `$trigger` (string) - The trigger type (e.g., 'manual', 'created', 'updated')

**Returns:** Array with execution results
```php
[
    'executed' => [1, 3, 5],    // Rule IDs that executed successfully
    'failed' => [2],            // Rule IDs that failed
    'skipped' => [4, 6],        // Rule IDs that didn't match conditions
]
```

### Process Multiple Tickets

Process a collection of tickets in batch.

```php
$tickets = Ticket::where('status', 'open')->get();
$results = $service->processBatch($tickets, 'batch');
```

**Returns:** Batch processing summary
```php
[
    'processed' => 15,          // Successfully processed tickets
    'failed' => 2,              // Failed tickets
    'details' => [              // Per-ticket results
        'ticket_id' => ['executed' => [], 'failed' => [], 'skipped' => []]
    ]
]
```

### Create Automation Rule

Create a new automation rule with conditions and actions.

```php
$rule = $service->createRule([
    'name' => 'Auto-assign urgent tickets',
    'description' => 'Automatically assign urgent tickets to senior agents',
    'trigger' => 'ticket_created',
    'conditions' => [
        [
            'field' => 'priority',
            'operator' => 'equals',
            'value' => 'urgent'
        ]
    ],
    'actions' => [
        [
            'type' => 'assign',
            'assignee_type' => 'App\\Models\\User',
            'assignee_id' => 5
        ]
    ],
    'priority' => 10,
    'is_active' => true,
    'stop_processing' => false,
]);
```

### Update Automation Rule

Modify an existing rule.

```php
$rule = $service->updateRule($existingRule, [
    'name' => 'Updated rule name',
    'is_active' => false,
]);
```

### Delete Automation Rule

Remove a rule and its execution history.

```php
$success = $service->deleteRule($rule);
```

### Test Rule

Test a rule against a ticket without executing actions.

```php
$testResult = $service->testRule($rule, $ticket);
```

**Returns:** Test result array
```php
[
    'evaluated' => true,
    'executed' => false,
    'conditions_met' => true,
    'actions_performed' => [
        ['type' => 'assign', 'success' => true]
    ],
    'errors' => []
]
```

### Apply Rule Template

Create a rule from a predefined template.

```php
$rule = $service->applyTemplate('urgent_escalation', [
    'name' => 'Custom Urgent Escalation'
]);
```

### Get Rule Statistics

Retrieve execution statistics for a rule.

```php
$stats = $service->getRuleStatistics($rule);
```

**Returns:** Statistics array
```php
[
    'total_executions' => 150,
    'successful_executions' => 145,
    'failed_executions' => 5,
    'success_rate' => 96.67,
    'first_execution' => Carbon::instance,
    'last_execution' => Carbon::instance,
]
```

## AutomationRule Model

The automation rule model stores rule definitions and metadata.

### Model Properties

```php
class AutomationRule extends Model
{
    protected $casts = [
        'conditions' => AsArrayObject::class,  // Rule conditions
        'actions' => AsArrayObject::class,     // Rule actions
        'is_active' => 'boolean',             // Whether rule is active
        'stop_processing' => 'boolean',       // Stop processing other rules
        'last_executed_at' => 'datetime',     // Last execution timestamp
    ];
}
```

### Model Methods

```php
// Evaluate rule conditions against a ticket
$matches = $rule->evaluate($ticket);

// Execute rule actions on a ticket
$success = $rule->execute($ticket);
```

### Relationships

```php
// Get execution history
$rule->executions;
```

### Scopes

```php
// Get only active rules
AutomationRule::active()->get();

// Get rules by trigger type
AutomationRule::byTrigger('ticket_created')->get();

// Get rules ordered by priority
AutomationRule::ordered()->get();
```

## AutomationExecution Model

Tracks individual rule executions for auditing and statistics.

```php
class AutomationExecution extends Model
{
    protected $fillable = [
        'automation_rule_id',
        'ticket_id',
        'executed_at',
        'conditions_snapshot',  // Rule conditions at execution time
        'actions_snapshot',     // Rule actions at execution time
        'success',             // Whether execution succeeded
    ];
}
```

## Configuration

Configure automation in `config/helpdesk.php`:

```php
'automation' => [
    'enabled' => true,

    'triggers' => [
        'ticket_created',
        'ticket_updated',
        'status_changed',
        'comment_added',
        'assigned',
        'manual',
        'batch',
    ],

    'templates' => [
        'urgent_escalation' => [
            'name' => 'Urgent Ticket Escalation',
            'trigger' => 'ticket_created',
            'conditions' => [
                ['field' => 'priority', 'operator' => 'equals', 'value' => 'urgent']
            ],
            'actions' => [
                ['type' => 'notify', 'recipient' => 'manager@company.com']
            ]
        ]
    ]
],
```

## Usage Examples

### Priority-Based Assignment

```php
$service->createRule([
    'name' => 'High Priority Auto-Assignment',
    'trigger' => 'ticket_created',
    'conditions' => [
        ['field' => 'priority', 'operator' => 'in', 'value' => ['high', 'urgent']]
    ],
    'actions' => [
        ['type' => 'assign', 'assignee_type' => 'team', 'assignee_id' => 'senior-support']
    ],
    'priority' => 5,
]);
```

### SLA Escalation

```php
$service->createRule([
    'name' => 'SLA Breach Escalation',
    'trigger' => 'sla_warning',
    'conditions' => [
        ['field' => 'sla_status', 'operator' => 'equals', 'value' => 'approaching']
    ],
    'actions' => [
        ['type' => 'change_priority', 'value' => 'urgent'],
        ['type' => 'notify', 'recipient' => 'escalation@company.com'],
        ['type' => 'add_comment', 'body' => 'SLA breach imminent - escalated automatically']
    ],
]);
```

### Category-Based Routing

```php
$service->createRule([
    'name' => 'Technical Issues Routing',
    'trigger' => 'ticket_created',
    'conditions' => [
        ['field' => 'category', 'operator' => 'contains', 'value' => 'technical']
    ],
    'actions' => [
        ['type' => 'assign', 'assignee_type' => 'team', 'assignee_id' => 'tech-support'],
        ['type' => 'add_tags', 'tags' => ['technical', 'auto-routed']]
    ],
]);
```

### Auto-Resolution for Simple Issues

```php
$service->createRule([
    'name' => 'Password Reset Auto-Resolution',
    'trigger' => 'ticket_created',
    'conditions' => [
        ['field' => 'subject', 'operator' => 'contains', 'value' => 'password reset'],
        ['field' => 'description', 'operator' => 'contains', 'value' => 'forgot password']
    ],
    'actions' => [
        ['type' => 'add_comment', 'body' => 'Please use the password reset link in your email.'],
        ['type' => 'change_status', 'value' => 'resolved'],
        ['type' => 'apply_template', 'template' => 'password-reset-instructions']
    ],
    'stop_processing' => true, // Don't run other rules
]);
```

### Integration with Ticket Events

Automatically process rules when tickets change:

```php
// In a Laravel event listener
class ProcessAutomationRules
{
    public function handle(TicketCreated $event): void
    {
        $automationService = app(AutomationService::class);
        $automationService->processTicket($event->ticket, 'ticket_created');
    }
}
```

### Condition Operators

The automation system supports various condition operators:

```php
'conditions' => [
    ['field' => 'status', 'operator' => 'equals', 'value' => 'open'],
    ['field' => 'priority', 'operator' => 'in', 'value' => ['high', 'urgent']],
    ['field' => 'subject', 'operator' => 'contains', 'value' => 'refund'],
    ['field' => 'created_at', 'operator' => 'older_than', 'value' => '24 hours'],
    ['field' => 'assignee', 'operator' => 'is_null'],
    ['field' => 'customer_email', 'operator' => 'ends_with', 'value' => '@vip.com'],
]
```

### Action Types

Available automation actions:

```php
'actions' => [
    // Assignment
    ['type' => 'assign', 'assignee_type' => 'User', 'assignee_id' => 1],

    // Status changes
    ['type' => 'change_status', 'value' => 'in_progress'],
    ['type' => 'change_priority', 'value' => 'high'],

    // Communication
    ['type' => 'add_comment', 'body' => 'Automated response', 'is_internal' => false],
    ['type' => 'apply_template', 'template' => 'welcome'],

    // Organization
    ['type' => 'add_tags', 'tags' => ['automated', 'urgent']],
    ['type' => 'remove_tags', 'tags' => ['pending']],
    ['type' => 'set_category', 'category_id' => 5],

    // Notifications
    ['type' => 'notify', 'recipient' => 'manager@company.com'],
    ['type' => 'escalate', 'level' => 2],
]
```

## Best Practices

1. **Start Simple**: Begin with basic rules and gradually add complexity
2. **Test Rules**: Always test rules before activating them
3. **Rule Priority**: Use priority to control execution order
4. **Stop Processing**: Use `stop_processing` for definitive rules
5. **Monitor Performance**: Track rule statistics and adjust as needed
6. **Error Handling**: Rules are wrapped in try-catch blocks
7. **Condition Logic**: Use multiple conditions for precise targeting
8. **Documentation**: Document complex rules for maintenance

## Advanced Features

### Rule Templates

Define reusable rule templates in configuration:

```php
'templates' => [
    'vip_customer' => [
        'name' => 'VIP Customer Priority',
        'conditions' => [
            ['field' => 'customer_email', 'operator' => 'ends_with', 'value' => '@vip.com']
        ],
        'actions' => [
            ['type' => 'change_priority', 'value' => 'urgent'],
            ['type' => 'assign', 'assignee_type' => 'team', 'assignee_id' => 'vip-support']
        ]
    ]
]
```

### Batch Processing

Process multiple tickets efficiently:

```php
// Process all open tickets daily
$openTickets = Ticket::where('status', 'open')->get();
$results = $service->processBatch($openTickets, 'daily_review');

// Log batch results
Log::info('Daily automation batch completed', $results);
```

### Rule Dependencies

Control rule execution order with priorities and stop processing:

```php
// High priority rule that stops further processing
$service->createRule([
    'name' => 'Spam Detection',
    'priority' => 100,
    'stop_processing' => true,
    'conditions' => [...],
    'actions' => [['type' => 'change_status', 'value' => 'spam']]
]);

// Lower priority rules run only if spam rule doesn't match
$service->createRule([
    'name' => 'Regular Processing',
    'priority' => 10,
    'conditions' => [...],
    'actions' => [...]
]);
```