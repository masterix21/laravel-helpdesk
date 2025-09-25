# Custom Automation

Create custom automation conditions and actions for your specific needs.

## Understanding Automation

The automation system consists of:
- **Triggers**: Events that start automation
- **Conditions**: Rules to evaluate
- **Actions**: Operations to perform

## Creating Custom Conditions

### Basic Condition

```php
namespace App\Automation\Conditions;

use LucaLongo\LaravelHelpdesk\Services\Automation\ConditionEvaluator;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class BusinessHoursCondition implements ConditionEvaluator
{
    public function evaluate(Ticket $ticket, array $config): bool
    {
        $now = now();
        $dayOfWeek = $now->dayOfWeek;
        $hour = $now->hour;

        // Monday-Friday, 9 AM - 5 PM
        $isBusinessDay = $dayOfWeek >= 1 && $dayOfWeek <= 5;
        $isBusinessHour = $hour >= 9 && $hour < 17;

        return $isBusinessDay && $isBusinessHour;
    }
}
```

### Complex Condition with Database

```php
class CustomerContractCondition implements ConditionEvaluator
{
    public function evaluate(Ticket $ticket, array $config): bool
    {
        $customer = $ticket->opener;

        if (!$customer) {
            return false;
        }

        $contract = Contract::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->first();

        if (!$contract) {
            return false;
        }

        return match($config['type']) {
            'gold' => $contract->level === 'gold',
            'has_sla' => $contract->has_sla,
            'expires_soon' => $contract->expires_at->diffInDays(now()) <= 30,
            default => false,
        };
    }
}
```

## Creating Custom Actions

### Simple Action

```php
namespace App\Automation\Actions;

use LucaLongo\LaravelHelpdesk\Services\Automation\ActionExecutor;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class AddInternalNoteAction implements ActionExecutor
{
    public function execute(Ticket $ticket, array $config): void
    {
        $ticket->comments()->create([
            'content' => $config['note'],
            'is_public' => false,
            'is_system' => true,
            'metadata' => [
                'automation_rule' => $config['rule_name'] ?? 'unknown',
                'executed_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
```

### External Integration Action

```php
class CreateTrelloCardAction implements ActionExecutor
{
    public function __construct(
        private TrelloService $trello
    ) {}

    public function execute(Ticket $ticket, array $config): void
    {
        $card = $this->trello->createCard([
            'name' => "[{$ticket->id}] {$ticket->subject}",
            'desc' => $ticket->description,
            'idList' => $config['list_id'],
            'idLabels' => $this->mapPriorityToLabel($ticket->priority),
            'due' => $ticket->due_at?->toIso8601String(),
        ]);

        // Store reference
        $ticket->meta = array_merge($ticket->meta ?? [], [
            'trello_card_id' => $card['id'],
            'trello_card_url' => $card['url'],
        ]);
        $ticket->save();

        // Log action
        activity()
            ->performedOn($ticket)
            ->withProperties(['trello_card' => $card['id']])
            ->log('Trello card created by automation');
    }

    private function mapPriorityToLabel(TicketPriority $priority): array
    {
        return match($priority) {
            TicketPriority::Urgent => [$this->trello->getLabel('red')],
            TicketPriority::High => [$this->trello->getLabel('orange')],
            TicketPriority::Normal => [$this->trello->getLabel('yellow')],
            TicketPriority::Low => [$this->trello->getLabel('green')],
        };
    }
}
```

## Registering Custom Components

### Service Provider Registration

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use LucaLongo\LaravelHelpdesk\Services\AutomationService;

class AutomationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!$this->app->bound(AutomationService::class)) {
            return;
        }

        $automation = $this->app->make(AutomationService::class);

        $this->registerConditions($automation);
        $this->registerActions($automation);
    }

    private function registerConditions(AutomationService $automation): void
    {
        $automation->registerCondition('business_hours', BusinessHoursCondition::class);
        $automation->registerCondition('customer_contract', CustomerContractCondition::class);
        $automation->registerCondition('has_attachment', HasAttachmentCondition::class);
        $automation->registerCondition('keyword_match', KeywordMatchCondition::class);
    }

    private function registerActions(AutomationService $automation): void
    {
        $automation->registerAction('add_internal_note', AddInternalNoteAction::class);
        $automation->registerAction('create_trello_card', CreateTrelloCardAction::class);
        $automation->registerAction('send_webhook', SendWebhookAction::class);
        $automation->registerAction('escalate_to_manager', EscalateToManagerAction::class);
    }
}
```

## Using Custom Automation

### Creating Rules with Custom Components

```php
use LucaLongo\LaravelHelpdesk\Models\AutomationRule;

$rule = AutomationRule::create([
    'name' => 'Handle VIP After Hours',
    'description' => 'Escalate VIP tickets outside business hours',
    'trigger' => 'ticket_created',
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'customer_contract',
                'config' => ['type' => 'gold'],
            ],
            [
                'type' => 'business_hours',
                'negate' => true, // NOT business hours
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'escalate_to_manager',
            'config' => [
                'notify_customer' => true,
                'message' => 'Your request has been escalated for immediate attention.',
            ],
        ],
        [
            'type' => 'send_webhook',
            'config' => [
                'url' => 'https://api.example.com/urgent-ticket',
                'method' => 'POST',
            ],
        ],
    ],
    'priority' => 100,
    'is_active' => true,
]);
```

### Programmatic Execution

```php
use LucaLongo\LaravelHelpdesk\Services\AutomationService;

$automationService = app(AutomationService::class);

// Process specific tickets
$tickets = Ticket::where('priority', TicketPriority::Urgent)->get();
$automationService->processTickets($tickets, 'manual');

// Execute specific rule
$rule = AutomationRule::find($ruleId);
$automationService->executeRule($rule, collect([$ticket]));
```

## Advanced Patterns

### Condition with External API

```php
class WeatherBasedCondition implements ConditionEvaluator
{
    public function __construct(
        private WeatherService $weather
    ) {}

    public function evaluate(Ticket $ticket, array $config): bool
    {
        if (!$ticket->customer_location) {
            return false;
        }

        $conditions = $this->weather->getCurrentConditions(
            $ticket->customer_location
        );

        // Escalate if severe weather affects customer
        return $conditions['severity'] >= $config['min_severity']
            && $ticket->type === TicketType::ServiceOutage;
    }
}
```

### Batch Action

```php
class BulkReassignAction implements ActionExecutor
{
    public function execute(Ticket $ticket, array $config): void
    {
        // Find all related tickets
        $relatedTickets = Ticket::where('customer_email', $ticket->customer_email)
            ->open()
            ->get();

        $newAssignee = User::find($config['assignee_id']);

        foreach ($relatedTickets as $related) {
            $related->assignTo($newAssignee);

            $related->comments()->create([
                'content' => "Bulk reassigned with ticket #{$ticket->id}",
                'is_public' => false,
                'is_system' => true,
            ]);
        }
    }
}
```

### Conditional Action

```php
class SmartAssignAction implements ActionExecutor
{
    public function execute(Ticket $ticket, array $config): void
    {
        $assignee = $this->findBestAssignee($ticket);

        if (!$assignee) {
            // Fallback to team lead
            $assignee = User::role('team_lead')->first();
        }

        $ticket->assignTo($assignee);

        // Add explanation
        $ticket->comments()->create([
            'content' => "Auto-assigned to {$assignee->name} based on expertise and availability",
            'is_public' => false,
            'is_system' => true,
        ]);
    }

    private function findBestAssignee(Ticket $ticket): ?User
    {
        return User::role('agent')
            ->withCount(['tickets' => function ($query) {
                $query->open();
            }])
            ->whereHas('skills', function ($query) use ($ticket) {
                $query->where('category_id', $ticket->categories->first()?->id);
            })
            ->orderBy('tickets_count')
            ->first();
    }
}
```

## Testing Custom Automation

```php
use LucaLongo\LaravelHelpdesk\Services\AutomationService;

test('business hours condition works correctly', function () {
    $condition = new BusinessHoursCondition();
    $ticket = Ticket::factory()->create();

    // Mock time to business hours
    $this->travelTo('2024-01-15 10:00:00'); // Monday 10 AM
    expect($condition->evaluate($ticket, []))->toBeTrue();

    // Mock time to after hours
    $this->travelTo('2024-01-15 20:00:00'); // Monday 8 PM
    expect($condition->evaluate($ticket, []))->toBeFalse();

    // Weekend
    $this->travelTo('2024-01-13 10:00:00'); // Saturday 10 AM
    expect($condition->evaluate($ticket, []))->toBeFalse();
});

test('trello card action creates card', function () {
    $trelloMock = Mockery::mock(TrelloService::class);
    $trelloMock->shouldReceive('createCard')
        ->once()
        ->andReturn(['id' => 'card123', 'url' => 'https://trello.com/card123']);

    $action = new CreateTrelloCardAction($trelloMock);
    $ticket = Ticket::factory()->create();

    $action->execute($ticket, ['list_id' => 'list456']);

    expect($ticket->meta['trello_card_id'])->toBe('card123');
});
```