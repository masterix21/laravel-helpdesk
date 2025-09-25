# Extending the Package

Guide for extending and customizing Laravel Helpdesk functionality.

## Custom Models

### Extending Core Models

Create your own model extending the package models:

```php
namespace App\Models;

use LucaLongo\LaravelHelpdesk\Models\Ticket as BaseTicket;
use App\Traits\HasCustomFields;

class Ticket extends BaseTicket
{
    use HasCustomFields;

    // Add custom attributes
    protected $appends = ['custom_priority_score'];

    // Add custom casts
    protected $casts = [
        'custom_data' => 'array',
        'verified_at' => 'datetime',
    ];

    // Custom relationships
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // Custom methods
    public function getCustomPriorityScoreAttribute(): int
    {
        return $this->calculatePriorityScore();
    }

    private function calculatePriorityScore(): int
    {
        $score = $this->priority->order() * 10;

        if ($this->customer->is_vip) {
            $score += 50;
        }

        if ($this->isOverdue()) {
            $score += 20;
        }

        return $score;
    }
}
```

### Binding Custom Models

Register your custom models in a service provider:

```php
namespace App\Providers;

use App\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\Ticket as BaseTicket;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BaseTicket::class, Ticket::class);
    }
}
```

## Custom Services

### Creating Custom Services

```php
namespace App\Services;

use LucaLongo\LaravelHelpdesk\Services\TicketService;
use App\Models\Ticket;
use App\Integrations\JiraClient;

class ExtendedTicketService extends TicketService
{
    public function __construct(
        SlaService $slaService,
        SubscriptionService $subscriptionService,
        private JiraClient $jira
    ) {
        parent::__construct($slaService, $subscriptionService);
    }

    public function open(array $attributes, ?Model $openedBy = null): Ticket
    {
        // Call parent method
        $ticket = parent::open($attributes, $openedBy);

        // Add custom logic
        if ($attributes['sync_to_jira'] ?? false) {
            $this->syncToJira($ticket);
        }

        // Custom notification
        if ($ticket->priority === TicketPriority::Urgent) {
            $this->notifyManagement($ticket);
        }

        return $ticket;
    }

    private function syncToJira(Ticket $ticket): void
    {
        $this->jira->createIssue([
            'summary' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => $this->mapPriority($ticket->priority),
        ]);
    }
}
```

### Registering Custom Services

```php
// app/Providers/HelpdeskServiceProvider.php
public function register(): void
{
    $this->app->singleton(TicketService::class, ExtendedTicketService::class);
}
```

## Custom Enums

### Adding New Enums

```php
namespace App\Enums;

use LucaLongo\LaravelHelpdesk\Concerns\Enums\ProvidesEnumValues;

enum TicketChannel: string
{
    use ProvidesEnumValues;

    case Email = 'email';
    case Phone = 'phone';
    case Chat = 'chat';
    case Api = 'api';
    case Portal = 'portal';

    public function label(): string
    {
        return match($this) {
            self::Email => 'Email',
            self::Phone => 'Phone Call',
            self::Chat => 'Live Chat',
            self::Api => 'API',
            self::Portal => 'Customer Portal',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Email => 'envelope',
            self::Phone => 'phone',
            self::Chat => 'chat-bubble',
            self::Api => 'code-bracket',
            self::Portal => 'globe',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Email => 'blue',
            self::Phone => 'green',
            self::Chat => 'purple',
            self::Api => 'gray',
            self::Portal => 'indigo',
        };
    }
}
```

### Using in Models

```php
class Ticket extends BaseTicket
{
    protected $casts = [
        'channel' => TicketChannel::class,
        // ... other casts
    ];
}
```

## Custom Automation

### Creating Custom Conditions

```php
namespace App\Automation\Conditions;

use LucaLongo\LaravelHelpdesk\Services\Automation\ConditionEvaluator;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class CustomerTypeCondition implements ConditionEvaluator
{
    public function evaluate(Ticket $ticket, array $config): bool
    {
        $customer = $ticket->opener;

        if (!$customer) {
            return false;
        }

        return match($config['operator']) {
            'is' => $customer->type === $config['value'],
            'is_not' => $customer->type !== $config['value'],
            'in' => in_array($customer->type, $config['value']),
            default => false,
        };
    }
}
```

### Creating Custom Actions

```php
namespace App\Automation\Actions;

use LucaLongo\LaravelHelpdesk\Services\Automation\ActionExecutor;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use App\Services\TwilioService;

class SendSmsAction implements ActionExecutor
{
    public function __construct(
        private TwilioService $twilio
    ) {}

    public function execute(Ticket $ticket, array $config): void
    {
        $message = $this->parseTemplate($config['message'], $ticket);

        $this->twilio->sendSms(
            $config['to'] ?? $ticket->customer_phone,
            $message
        );

        $ticket->comments()->create([
            'content' => "SMS sent: {$message}",
            'is_public' => false,
            'is_system' => true,
        ]);
    }

    private function parseTemplate(string $template, Ticket $ticket): string
    {
        return str_replace(
            ['{{ticket_id}}', '{{subject}}', '{{status}}'],
            [$ticket->id, $ticket->subject, $ticket->status->label()],
            $template
        );
    }
}
```

### Registering Custom Automation

```php
// app/Providers/AutomationServiceProvider.php
use LucaLongo\LaravelHelpdesk\Services\AutomationService;

public function boot(): void
{
    $automation = app(AutomationService::class);

    // Register conditions
    $automation->registerCondition('customer_type', CustomerTypeCondition::class);
    $automation->registerCondition('has_attachment', HasAttachmentCondition::class);

    // Register actions
    $automation->registerAction('send_sms', SendSmsAction::class);
    $automation->registerAction('create_jira_issue', CreateJiraIssueAction::class);
}
```

## Custom Notifications

### Creating Custom Channels

```php
namespace App\Notifications\Channels;

use LucaLongo\LaravelHelpdesk\Notifications\Channels\NotificationChannel;
use Illuminate\Database\Eloquent\Model;
use App\Services\SlackService;

class SlackChannel implements NotificationChannel
{
    public function __construct(
        private SlackService $slack
    ) {}

    public function getName(): string
    {
        return 'slack';
    }

    public function getTags(): array
    {
        return ['ticket.created', 'ticket.urgent', 'sla.breach'];
    }

    public function send(Model $notifiable, array $data): void
    {
        $this->slack->sendMessage(
            channel: '#support',
            message: $this->formatMessage($data),
            attachments: $this->buildAttachments($data)
        );
    }

    private function formatMessage(array $data): string
    {
        return "🎫 *New Ticket:* {$data['ticket']['subject']}";
    }

    private function buildAttachments(array $data): array
    {
        return [
            [
                'color' => $this->getColorForPriority($data['ticket']['priority']),
                'fields' => [
                    ['title' => 'ID', 'value' => $data['ticket']['id'], 'short' => true],
                    ['title' => 'Priority', 'value' => $data['ticket']['priority'], 'short' => true],
                    ['title' => 'Type', 'value' => $data['ticket']['type'], 'short' => true],
                    ['title' => 'Status', 'value' => $data['ticket']['status'], 'short' => true],
                ],
            ],
        ];
    }
}
```

### Registering Channels

```php
use LucaLongo\LaravelHelpdesk\Notifications\NotificationDispatcher;

public function boot(): void
{
    $dispatcher = app(NotificationDispatcher::class);
    $dispatcher->register(new SlackChannel($this->app->make(SlackService::class)));
    $dispatcher->register(new TeamsChannel());
    $dispatcher->register(new WebhookChannel());
}
```

## Custom Commands

### Creating Commands

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class ArchiveOldTickets extends Command
{
    protected $signature = 'helpdesk:archive
                            {--days=90 : Days after which to archive}
                            {--dry-run : Show what would be archived}';

    protected $description = 'Archive old closed tickets';

    public function handle(): void
    {
        $cutoffDate = now()->subDays($this->option('days'));

        $tickets = Ticket::closed()
            ->where('closed_at', '<', $cutoffDate)
            ->get();

        $this->info("Found {$tickets->count()} tickets to archive");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Subject', 'Closed At'],
                $tickets->map(fn($t) => [$t->id, $t->subject, $t->closed_at])
            );
            return;
        }

        $this->withProgressBar($tickets, function ($ticket) {
            $this->archiveTicket($ticket);
        });

        $this->info('✅ Archival complete');
    }

    private function archiveTicket(Ticket $ticket): void
    {
        // Move to archive table
        DB::table('archived_tickets')->insert($ticket->toArray());

        // Delete from main table
        $ticket->delete();
    }
}
```

## Custom Facades

### Creating a Facade

```php
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Helpdesk extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'custom-helpdesk';
    }

    // Add convenience methods
    public static function urgentTickets()
    {
        return static::getFacadeRoot()
            ->tickets()
            ->where('priority', TicketPriority::Urgent)
            ->open()
            ->get();
    }

    public static function myTickets()
    {
        return static::getFacadeRoot()
            ->tickets()
            ->where('assignee_id', auth()->id())
            ->open()
            ->get();
    }
}
```

## Custom Validation Rules

### Creating Rules

```php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class ValidTicketTransition implements Rule
{
    public function __construct(
        private Ticket $ticket,
        private string $newStatus
    ) {}

    public function passes($attribute, $value): bool
    {
        return $this->ticket->status->canTransitionTo(
            TicketStatus::from($value)
        );
    }

    public function message(): string
    {
        return "Cannot transition from {$this->ticket->status->label()} to {$this->newStatus}";
    }
}
```

## Middleware

### Creating Ticket Middleware

```php
namespace App\Http\Middleware;

use Closure;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class EnsureTicketAccess
{
    public function handle($request, Closure $next)
    {
        $ticket = $request->route('ticket');

        if (!$ticket instanceof Ticket) {
            $ticket = Ticket::findOrFail($ticket);
        }

        // Check access
        if (!$this->canAccess($ticket, $request->user())) {
            abort(403, 'Access denied to this ticket');
        }

        // Add to view data
        view()->share('currentTicket', $ticket);

        return $next($request);
    }

    private function canAccess(Ticket $ticket, $user): bool
    {
        // Custom access logic
        return $ticket->opener_id === $user->id
            || $ticket->assignee_id === $user->id
            || $user->hasRole('admin');
    }
}
```

## Package Configuration

### Publishing and Customizing

```bash
# Publish config
php artisan vendor:publish --tag=helpdesk-config

# Publish migrations
php artisan vendor:publish --tag=helpdesk-migrations

# Publish views (if available)
php artisan vendor:publish --tag=helpdesk-views
```

### Overriding Configuration

```php
// config/helpdesk.php
return [
    // Override defaults
    'defaults' => [
        'due_minutes' => 720, // 12 hours instead of 24
        'priority' => TicketPriority::High->value, // High by default
    ],

    // Add custom types
    'types' => [
        'bug_report' => [
            'label' => 'Bug Report',
            'default_priority' => TicketPriority::Urgent->value,
            'due_minutes' => 240,
            'auto_assign_to' => 'dev-team',
        ],
    ],

    // Custom AI provider
    'ai' => [
        'providers' => [
            'custom' => [
                'enabled' => true,
                'api_key' => env('CUSTOM_AI_KEY'),
                'endpoint' => env('CUSTOM_AI_ENDPOINT'),
                'model' => 'custom-model',
                'capabilities' => [
                    'analyze_sentiment' => true,
                    'suggest_response' => true,
                ],
            ],
        ],
    ],
];
```