# Workflow Management

The Laravel Helpdesk package includes a flexible workflow management system that controls ticket status transitions, enforces business rules, and automates actions during state changes.

## Overview

The `WorkflowService` provides functionality to:
- Define custom workflows with transition rules and guards
- Control which status transitions are allowed based on conditions
- Execute actions before and after status transitions
- Provide available transitions for tickets based on current state
- Support multiple workflows for different ticket types or scenarios

## Service Methods

### Check Transition Eligibility

Determine if a ticket can transition to a specific status.

```php
use LucaLongo\LaravelHelpdesk\Services\WorkflowService;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;

$service = app(WorkflowService::class);

$canTransition = $service->canTransition(
    ticket: $ticket,
    toStatus: TicketStatus::Resolved,
    workflow: 'default'  // Optional workflow name
);
```

**Parameters:**
- `$ticket` (Ticket) - The ticket to check
- `$toStatus` (TicketStatus) - Target status
- `$workflow` (string|null) - Workflow name (defaults to 'default')

**Returns:** `bool` - Whether transition is allowed

### Perform Status Transition

Execute a status transition with all associated rules and actions.

```php
$success = $service->transition(
    ticket: $ticket,
    toStatus: TicketStatus::InProgress,
    workflow: 'default'
);
```

**Returns:** `bool` - Whether transition was successful

The transition process:
1. Validates the transition is allowed
2. Executes before actions
3. Updates ticket status
4. Sets closure timestamps for terminal statuses
5. Executes after actions
6. Triggers automation rules if configured

### Get Available Transitions

Retrieve all possible transitions for a ticket from its current state.

```php
$availableTransitions = $service->getAvailableTransitions($ticket);
```

**Returns:** Array of available transitions
```php
[
    [
        'status' => TicketStatus::InProgress,
        'label' => 'In Progress',
        'description' => 'Start working on the ticket',
        'requires_comment' => false,
        'requires_resolution' => false,
    ],
    // ... more transitions
]
```

### Register Custom Workflow

Define a custom workflow with specific transition rules.

```php
$service->registerWorkflow('urgent_workflow', [
    'name' => 'Urgent Ticket Workflow',
    'transitions' => [
        'open:resolved' => [
            'description' => 'Fast-track resolution',
            'guards' => ['must_be_assigned'],
            'after_actions' => ['notify_manager'],
        ]
    ]
]);
```

### Register Guards

Add custom transition guards (conditions that must be met).

```php
$service->registerGuard('must_be_assigned', function (Ticket $ticket) {
    return $ticket->assigned_to_id !== null;
});

$service->registerGuard('customer_approved', function (Ticket $ticket) {
    return $ticket->meta['customer_approved'] ?? false;
});
```

### Register Actions

Add custom actions to execute during transitions.

```php
$service->registerAction('notify_manager', function (Ticket $ticket, TicketStatus $fromStatus, TicketStatus $toStatus) {
    // Send notification to manager
    Mail::to('manager@company.com')
        ->send(new TicketStatusChangedMail($ticket, $fromStatus, $toStatus));
});
```

## Default Workflow

The package includes a comprehensive default workflow that handles common helpdesk scenarios:

### Transition Rules

```php
'transitions' => [
    // From Open
    'open:in_progress' => [
        'description' => 'Start working on the ticket',
        'guards' => ['must_be_assigned'],
        'after_actions' => ['mark_first_response'],
    ],
    'open:pending' => [
        'description' => 'Waiting for customer response',
        'requires_comment' => true,
    ],
    'open:resolved' => [
        'description' => 'Mark as resolved',
        'requires_resolution' => true,
        'after_actions' => ['send_resolution_notification'],
    ],
    'open:closed' => [
        'description' => 'Close without resolution',
        'requires_comment' => true,
    ],

    // From In Progress
    'in_progress:pending' => [
        'description' => 'Waiting for customer response',
        'requires_comment' => true,
    ],
    'in_progress:resolved' => [
        'description' => 'Mark as resolved',
        'requires_resolution' => true,
        'after_actions' => ['send_resolution_notification'],
    ],
    'in_progress:on_hold' => [
        'description' => 'Put on hold',
        'requires_comment' => true,
    ],

    // From Pending
    'pending:open' => [
        'description' => 'Customer responded',
        'trigger_automations' => true,
    ],
    'pending:in_progress' => [
        'description' => 'Resume work',
        'guards' => ['must_be_assigned'],
    ],
    'pending:resolved' => [
        'description' => 'Mark as resolved',
        'requires_resolution' => true,
    ],

    // From On Hold
    'on_hold:in_progress' => [
        'description' => 'Resume work',
        'guards' => ['must_be_assigned'],
    ],
    'on_hold:closed' => [
        'description' => 'Close ticket',
        'requires_comment' => true,
    ],

    // From Resolved
    'resolved:closed' => [
        'description' => 'Close resolved ticket',
        'after_actions' => ['request_rating'],
    ],
    'resolved:open' => [
        'description' => 'Reopen ticket',
        'requires_comment' => true,
        'after_actions' => ['notify_reopened'],
    ],

    // From Closed
    'closed:open' => [
        'description' => 'Reopen closed ticket',
        'requires_comment' => true,
        'guards' => ['can_reopen'],
        'after_actions' => ['notify_reopened'],
    ],
]
```

### Built-in Guards

```php
// Ticket must be assigned to someone
'must_be_assigned' => function (Ticket $ticket) {
    return $ticket->assigned_to_id !== null;
},

// Tickets can only be reopened within 30 days of closure
'can_reopen' => function (Ticket $ticket) {
    $daysSinceClosed = $ticket->closed_at?->diffInDays(now()) ?? 0;
    return $daysSinceClosed <= 30;
},
```

### Built-in Actions

```php
// Mark first response timestamp
'mark_first_response' => function (Ticket $ticket) {
    $ticket->markFirstResponse();
},

// Send resolution notification
'send_resolution_notification' => function (Ticket $ticket) {
    // Implementation depends on notification system
},

// Request customer rating
'request_rating' => function (Ticket $ticket) {
    // Implementation depends on rating system
},

// Notify about reopened ticket
'notify_reopened' => function (Ticket $ticket) {
    // Implementation depends on notification system
},
```

## Configuration

Configure workflows in your `config/helpdesk.php` file:

```php
'workflow' => [
    'default' => [
        'name' => 'Standard Helpdesk Workflow',
        'transitions' => [
            // Define custom transitions
        ]
    ],
    'urgent' => [ // This is a workflow name, not a priority enum
        'name' => 'Urgent Ticket Workflow',
        'transitions' => [
            // Simplified workflow for urgent tickets
        ]
    ]
],
```

## Usage Examples

### Basic Workflow Integration

```php
class TicketService
{
    public function __construct(
        private WorkflowService $workflowService
    ) {}

    public function changeStatus(Ticket $ticket, TicketStatus $newStatus, ?string $comment = null): bool
    {
        // Check if transition is allowed
        if (!$this->workflowService->canTransition($ticket, $newStatus)) {
            throw new InvalidTransitionException(
                "Cannot transition from {$ticket->status->value} to {$newStatus->value}"
            );
        }

        // Add comment if required
        if ($this->requiresComment($ticket, $newStatus) && !$comment) {
            throw new CommentRequiredException('This transition requires a comment');
        }

        if ($comment) {
            $ticket->comments()->create([
                'body' => $comment,
                'author_type' => User::class,
                'author_id' => auth()->id(),
                'is_internal' => false,
            ]);
        }

        // Perform transition
        return $this->workflowService->transition($ticket, $newStatus);
    }

    private function requiresComment(Ticket $ticket, TicketStatus $newStatus): bool
    {
        $transitions = $this->workflowService->getAvailableTransitions($ticket);
        $transition = collect($transitions)->firstWhere('status', $newStatus);

        return $transition['requires_comment'] ?? false;
    }
}
```

### Custom Workflow for Different Ticket Types

```php
class WorkflowProvider extends ServiceProvider
{
    public function boot(WorkflowService $workflowService)
    {
        // Bug report workflow
        $workflowService->registerWorkflow('bug_workflow', [
            'name' => 'Bug Report Workflow',
            'transitions' => [
                'open:investigating' => [
                    'description' => 'Start investigation',
                    'guards' => ['assigned_to_developer'],
                ],
                'investigating:needs_reproduction' => [
                    'description' => 'Cannot reproduce issue',
                    'requires_comment' => true,
                ],
                'needs_reproduction:investigating' => [
                    'description' => 'Issue reproduced',
                ],
                'investigating:fixed' => [
                    'description' => 'Bug fixed',
                    'requires_comment' => true,
                    'after_actions' => ['deploy_fix'],
                ],
            ]
        ]);

        // Custom guards
        $workflowService->registerGuard('assigned_to_developer', function (Ticket $ticket) {
            return $ticket->assignee && $ticket->assignee->hasRole('developer');
        });

        // Custom actions
        $workflowService->registerAction('deploy_fix', function (Ticket $ticket) {
            // Trigger deployment pipeline
            event(new BugFixReadyForDeployment($ticket));
        });
    }
}
```

### Agent Interface Integration

```php
class TicketController extends Controller
{
    public function show(Ticket $ticket)
    {
        $availableTransitions = $this->workflowService->getAvailableTransitions($ticket);

        return view('tickets.show', compact('ticket', 'availableTransitions'));
    }

    public function transition(Request $request, Ticket $ticket)
    {
        $request->validate([
            'status' => 'required|string',
            'comment' => 'nullable|string|max:5000',
        ]);

        $newStatus = TicketStatus::from($request->status);

        try {
            $success = $this->ticketService->changeStatus(
                $ticket,
                $newStatus,
                $request->comment
            );

            if ($success) {
                return redirect()->route('tickets.show', $ticket)
                    ->with('success', "Ticket status changed to {$newStatus->label()}");
            }
        } catch (InvalidTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        } catch (CommentRequiredException $e) {
            return back()->withErrors(['comment' => $e->getMessage()]);
        }

        return back()->withErrors(['status' => 'Failed to change ticket status']);
    }
}
```

### Frontend Workflow Interface

```blade
{{-- ticket-transitions.blade.php --}}
<div class="transition-controls">
    <h4>Available Actions</h4>

    @if($availableTransitions)
        <form action="{{ route('tickets.transition', $ticket) }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="status">Change Status:</label>
                <select name="status" id="status" class="form-control" required>
                    <option value="">Select new status...</option>
                    @foreach($availableTransitions as $transition)
                        <option value="{{ $transition['status']->value }}"
                                data-requires-comment="{{ $transition['requires_comment'] ? 'true' : 'false' }}"
                                data-requires-resolution="{{ $transition['requires_resolution'] ? 'true' : 'false' }}">
                            {{ $transition['label'] }}
                            @if($transition['description'])
                                - {{ $transition['description'] }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" id="comment-group" style="display: none;">
                <label for="comment">Comment (required):</label>
                <textarea name="comment" id="comment" class="form-control" rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Update Status</button>
        </form>

        <script>
            document.getElementById('status').addEventListener('change', function() {
                const selected = this.options[this.selectedIndex];
                const requiresComment = selected.dataset.requiresComment === 'true';
                const commentGroup = document.getElementById('comment-group');
                const commentField = document.getElementById('comment');

                if (requiresComment) {
                    commentGroup.style.display = 'block';
                    commentField.required = true;
                } else {
                    commentGroup.style.display = 'none';
                    commentField.required = false;
                }
            });
        </script>
    @else
        <p class="text-muted">No status changes available for this ticket.</p>
    @endif
</div>
```

### Workflow Analytics

```php
class WorkflowAnalytics
{
    public function getTransitionMetrics(int $days = 30): array
    {
        // Get all status changes in the period
        $statusChanges = collect();

        // This would typically come from an audit log or status change history
        $tickets = Ticket::with('statusHistory')
            ->where('updated_at', '>=', now()->subDays($days))
            ->get();

        $transitionCounts = [];
        $transitionTimes = [];

        foreach ($tickets as $ticket) {
            $history = $ticket->statusHistory->sortBy('created_at');

            for ($i = 1; $i < $history->count(); $i++) {
                $from = $history[$i - 1]->status;
                $to = $history[$i]->status;
                $transitionKey = "{$from->value}:{$to->value}";

                $transitionCounts[$transitionKey] = ($transitionCounts[$transitionKey] ?? 0) + 1;

                $timeDiff = $history[$i]->created_at->diffInMinutes($history[$i - 1]->created_at);
                $transitionTimes[$transitionKey][] = $timeDiff;
            }
        }

        return [
            'transition_counts' => $transitionCounts,
            'avg_transition_times' => array_map(function ($times) {
                return round(array_sum($times) / count($times), 2);
            }, $transitionTimes),
        ];
    }

    public function getBottleneckAnalysis(): array
    {
        $metrics = $this->getTransitionMetrics();

        // Find transitions that take longest
        $slowTransitions = collect($metrics['avg_transition_times'])
            ->sortDesc()
            ->take(5);

        // Find most common transitions
        $commonTransitions = collect($metrics['transition_counts'])
            ->sortDesc()
            ->take(10);

        return [
            'slowest_transitions' => $slowTransitions,
            'most_common_transitions' => $commonTransitions,
        ];
    }
}
```

### Integration with Automation

```php
class WorkflowAutomationIntegration
{
    public function setupAutomationTriggers(WorkflowService $workflowService)
    {
        // Automatically assign urgent tickets when opened
        $workflowService->registerAction('auto_assign_urgent', function (Ticket $ticket) {
            if ($ticket->priority === TicketPriority::Urgent) {
                $availableAgent = User::role('senior-agent')
                    ->whereDoesntHave('assignedTickets', function ($query) {
                        $query->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value]);
                    })
                    ->first();

                if ($availableAgent) {
                    $ticket->assignTo($availableAgent);
                }
            }
        });

        // Escalate tickets that have been pending too long
        $workflowService->registerAction('check_escalation', function (Ticket $ticket) {
            if ($ticket->status === TicketStatus::Pending) {
                $hoursPending = $ticket->updated_at->diffInHours(now());

                if ($hoursPending > 48) {
                    app(WorkflowService::class)->transition($ticket, TicketStatus::Open);

                    $ticket->comments()->create([
                        'body' => 'Ticket automatically reopened due to extended pending time.',
                        'is_system' => true,
                        'is_internal' => true,
                    ]);
                }
            }
        });
    }
}
```

### Advanced Workflow Features

#### Conditional Transitions

```php
// Complex guard with multiple conditions
$workflowService->registerGuard('complex_conditions', function (Ticket $ticket, TicketStatus $fromStatus, TicketStatus $toStatus) {
    // Business hours check
    if (!$this->isDuringBusinessHours()) {
        return false;
    }

    // Priority-based restrictions
    if ($ticket->priority === TicketPriority::Low && $toStatus === TicketStatus::Urgent) {
        return false;
    }

    // Department-specific rules
    if ($ticket->department === 'billing' && $toStatus === TicketStatus::Resolved) {
        return $ticket->meta['billing_approved'] ?? false;
    }

    return true;
});
```

#### Parallel Workflows

```php
class ParallelWorkflowManager
{
    public function determineWorkflow(Ticket $ticket): string
    {
        // Choose workflow based on ticket properties
        if ($ticket->type === TicketType::Bug) {
            return 'bug_workflow';
        }

        if ($ticket->priority === TicketPriority::Urgent) {
            return 'urgent_workflow';
        }

        if ($ticket->customer && $ticket->customer->isVIP()) {
            return 'vip_workflow';
        }

        return 'default';
    }

    public function transitionWithWorkflow(Ticket $ticket, TicketStatus $newStatus): bool
    {
        $workflow = $this->determineWorkflow($ticket);

        return app(WorkflowService::class)->transition($ticket, $newStatus, $workflow);
    }
}
```

## Best Practices

1. **Keep It Simple**: Start with basic workflows and add complexity gradually
2. **Clear Descriptions**: Provide clear descriptions for each transition
3. **Guard Validation**: Use guards to enforce business rules consistently
4. **Action Separation**: Keep actions focused and single-purpose
5. **Error Handling**: Handle transition failures gracefully
6. **User Experience**: Make transitions intuitive for agents
7. **Documentation**: Document custom workflows and their purpose
8. **Testing**: Test workflow rules thoroughly with different scenarios

## Workflow Validation

```php
class WorkflowValidator
{
    public function validateWorkflow(array $workflow): array
    {
        $errors = [];

        foreach ($workflow['transitions'] as $transitionKey => $transition) {
            if (!$this->isValidTransitionKey($transitionKey)) {
                $errors[] = "Invalid transition key format: {$transitionKey}";
            }

            if (isset($transition['guards'])) {
                foreach ($transition['guards'] as $guard) {
                    if (!$this->guardExists($guard)) {
                        $errors[] = "Guard '{$guard}' does not exist";
                    }
                }
            }

            if (isset($transition['before_actions'])) {
                foreach ($transition['before_actions'] as $action) {
                    if (!$this->actionExists($action)) {
                        $errors[] = "Action '{$action}' does not exist";
                    }
                }
            }
        }

        return $errors;
    }

    private function isValidTransitionKey(string $key): bool
    {
        return preg_match('/^[a-z_]+:[a-z_]+$/', $key) === 1;
    }
}
```

The workflow management system provides a powerful foundation for controlling ticket lifecycle while maintaining flexibility for different business requirements and processes.