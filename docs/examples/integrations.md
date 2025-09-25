# Third-Party Integrations

Examples of integrating Laravel Helpdesk with popular services and platforms.

## Slack Integration

### Real-time Notifications

```php
namespace App\Integrations\Slack;

use Illuminate\Support\Facades\Http;
use LucaLongo\LaravelHelpdesk\Events\TicketCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketStatusChanged;

class SlackNotifier
{
    private string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.slack.webhook_url');
    }

    public function notifyTicketCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket;

        Http::post($this->webhookUrl, [
            'text' => "New ticket created: #{$ticket->id}",
            'attachments' => [
                [
                    'color' => $this->getPriorityColor($ticket->priority),
                    'fields' => [
                        [
                            'title' => 'Subject',
                            'value' => $ticket->subject,
                            'short' => false,
                        ],
                        [
                            'title' => 'Priority',
                            'value' => $ticket->priority->label(),
                            'short' => true,
                        ],
                        [
                            'title' => 'Type',
                            'value' => $ticket->type->label(),
                            'short' => true,
                        ],
                    ],
                    'actions' => [
                        [
                            'type' => 'button',
                            'text' => 'View Ticket',
                            'url' => route('tickets.show', $ticket),
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function getPriorityColor(TicketPriority $priority): string
    {
        return match($priority) {
            TicketPriority::Urgent => 'danger',
            TicketPriority::High => 'warning',
            TicketPriority::Normal => 'good',
            TicketPriority::Low => '#808080',
        };
    }
}
```

### Slash Commands

```php
namespace App\Http\Controllers\Integrations;

use Illuminate\Http\Request;
use LucaLongo\LaravelHelpdesk\Facades\LaravelHelpdesk;

class SlackCommandController extends Controller
{
    public function handle(Request $request)
    {
        $command = $request->input('command');
        $text = $request->input('text');
        $userId = $request->input('user_id');

        return match($command) {
            '/ticket' => $this->handleTicketCommand($text, $userId),
            '/ticket-status' => $this->handleStatusCommand($text),
            '/my-tickets' => $this->handleMyTickets($userId),
            default => ['text' => 'Unknown command'],
        };
    }

    private function handleTicketCommand(string $text, string $userId): array
    {
        // Parse: /ticket create "Subject" "Description"
        if (str_starts_with($text, 'create')) {
            preg_match('/create "([^"]+)" "([^"]+)"/', $text, $matches);

            $ticket = LaravelHelpdesk::open([
                'subject' => $matches[1] ?? 'Slack ticket',
                'description' => $matches[2] ?? $text,
                'type' => 'support',
                'meta' => ['slack_user_id' => $userId],
            ]);

            return [
                'text' => "Ticket #{$ticket->id} created successfully",
                'attachments' => [
                    [
                        'text' => $ticket->subject,
                        'color' => 'good',
                    ],
                ],
            ];
        }

        return ['text' => 'Usage: /ticket create "Subject" "Description"'];
    }
}
```

## Jira Integration

### Two-way Sync

```php
namespace App\Integrations\Jira;

use Atlassian\JiraRest\Facades\Jira;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class JiraSyncService
{
    public function syncTicketToJira(Ticket $ticket): string
    {
        $issue = Jira::issues()->create([
            'project' => ['key' => config('services.jira.project_key')],
            'summary' => "[Helpdesk #{$ticket->id}] {$ticket->subject}",
            'description' => $this->formatDescription($ticket),
            'issuetype' => ['name' => $this->mapTicketType($ticket->type)],
            'priority' => ['name' => $this->mapPriority($ticket->priority)],
            'customfield_10001' => $ticket->id, // Helpdesk ticket ID
        ]);

        // Store Jira issue key
        $ticket->update([
            'meta' => array_merge($ticket->meta ?? [], [
                'jira_issue_key' => $issue->key,
                'jira_issue_id' => $issue->id,
            ]),
        ]);

        return $issue->key;
    }

    public function syncJiraToTicket(string $issueKey): ?Ticket
    {
        $issue = Jira::issues()->get($issueKey);

        // Check if ticket exists
        $ticket = Ticket::where('meta->jira_issue_key', $issueKey)->first();

        if ($ticket) {
            // Update existing ticket
            $ticket->update([
                'status' => $this->mapJiraStatus($issue->fields->status->name),
                'priority' => $this->mapJiraPriority($issue->fields->priority->name),
            ]);
        } else {
            // Create new ticket from Jira
            $ticket = LaravelHelpdesk::open([
                'subject' => $issue->fields->summary,
                'description' => $issue->fields->description,
                'type' => $this->mapJiraIssueType($issue->fields->issuetype->name),
                'priority' => $this->mapJiraPriority($issue->fields->priority->name),
                'meta' => [
                    'jira_issue_key' => $issue->key,
                    'jira_issue_id' => $issue->id,
                ],
            ]);
        }

        // Sync comments
        $this->syncComments($ticket, $issue);

        return $ticket;
    }

    private function syncComments(Ticket $ticket, $issue): void
    {
        foreach ($issue->fields->comment->comments as $jiraComment) {
            $exists = $ticket->comments()
                ->where('meta->jira_comment_id', $jiraComment->id)
                ->exists();

            if (!$exists) {
                LaravelHelpdesk::comment(
                    $ticket,
                    $jiraComment->body,
                    null,
                    true,
                    ['jira_comment_id' => $jiraComment->id]
                );
            }
        }
    }
}
```

## Stripe Integration

### Customer Support Context

```php
namespace App\Integrations\Stripe;

use Stripe\StripeClient;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class StripeContextProvider
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function enrichTicketWithCustomerData(Ticket $ticket): void
    {
        $customer = $ticket->opener;

        if (!$customer || !$customer->stripe_customer_id) {
            return;
        }

        try {
            $stripeCustomer = $this->stripe->customers->retrieve(
                $customer->stripe_customer_id,
                ['expand' => ['subscriptions', 'sources']]
            );

            $ticket->update([
                'meta' => array_merge($ticket->meta ?? [], [
                    'stripe' => [
                        'customer_id' => $stripeCustomer->id,
                        'subscription_status' => $stripeCustomer->subscriptions->data[0]->status ?? null,
                        'plan' => $stripeCustomer->subscriptions->data[0]->items->data[0]->price->nickname ?? null,
                        'balance' => $stripeCustomer->balance,
                        'currency' => $stripeCustomer->currency,
                        'delinquent' => $stripeCustomer->delinquent,
                        'lifetime_value' => $this->calculateLifetimeValue($stripeCustomer->id),
                    ],
                ]),
            ]);

            // Auto-prioritize based on subscription
            $this->adjustPriorityBasedOnPlan($ticket, $stripeCustomer);

        } catch (\Exception $e) {
            Log::error('Failed to enrich ticket with Stripe data', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function adjustPriorityBasedOnPlan(Ticket $ticket, $stripeCustomer): void
    {
        if (!isset($stripeCustomer->subscriptions->data[0])) {
            return;
        }

        $plan = $stripeCustomer->subscriptions->data[0]->items->data[0]->price->nickname;

        $priority = match($plan) {
            'Enterprise' => TicketPriority::Urgent,
            'Professional' => TicketPriority::High,
            'Starter' => TicketPriority::Normal,
            default => $ticket->priority,
        };

        if ($priority !== $ticket->priority) {
            $ticket->update(['priority' => $priority]);
        }
    }

    private function calculateLifetimeValue(string $customerId): float
    {
        $charges = $this->stripe->charges->all([
            'customer' => $customerId,
            'limit' => 100,
        ]);

        return collect($charges->data)
            ->where('status', 'succeeded')
            ->sum('amount') / 100; // Convert cents to dollars
    }
}
```

## Twilio Integration

### SMS Notifications

```php
namespace App\Integrations\Twilio;

use Twilio\Rest\Client;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class TwilioNotificationService
{
    private Client $twilio;

    public function __construct()
    {
        $this->twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
    }

    public function sendTicketCreatedSms(Ticket $ticket): void
    {
        if (!$ticket->customer_phone) {
            return;
        }

        $message = "Your support ticket #{$ticket->id} has been created. " .
                  "We'll respond within {$this->getResponseTime($ticket->priority)}.";

        $this->twilio->messages->create(
            $ticket->customer_phone,
            [
                'from' => config('services.twilio.from'),
                'body' => $message,
            ]
        );
    }

    public function sendStatusUpdateSms(Ticket $ticket, TicketStatus $oldStatus): void
    {
        if (!$ticket->customer_phone || !$this->shouldNotifyStatus($oldStatus, $ticket->status)) {
            return;
        }

        $message = match($ticket->status) {
            TicketStatus::InProgress => "Your ticket #{$ticket->id} is being worked on.",
            TicketStatus::Resolved => "Your ticket #{$ticket->id} has been resolved. Reply if you need further assistance.",
            TicketStatus::Closed => "Your ticket #{$ticket->id} has been closed. Thank you for contacting support.",
            default => null,
        };

        if ($message) {
            $this->twilio->messages->create(
                $ticket->customer_phone,
                [
                    'from' => config('services.twilio.from'),
                    'body' => $message,
                ]
            );
        }
    }

    private function shouldNotifyStatus(TicketStatus $old, TicketStatus $new): bool
    {
        // Only notify on significant status changes
        return in_array($new, [
            TicketStatus::InProgress,
            TicketStatus::Resolved,
            TicketStatus::Closed,
        ]);
    }

    private function getResponseTime(TicketPriority $priority): string
    {
        return match($priority) {
            TicketPriority::Urgent => '1 hour',
            TicketPriority::High => '4 hours',
            TicketPriority::Normal => '24 hours',
            TicketPriority::Low => '48 hours',
        };
    }
}
```

## GitHub Integration

### Issue Tracking

```php
namespace App\Integrations\GitHub;

use GitHub\Client;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class GitHubIssueService
{
    private Client $github;
    private string $owner;
    private string $repo;

    public function __construct()
    {
        $this->github = new Client();
        $this->github->authenticate(
            config('services.github.token'),
            null,
            Client::AUTH_ACCESS_TOKEN
        );
        $this->owner = config('services.github.owner');
        $this->repo = config('services.github.repo');
    }

    public function createIssueFromTicket(Ticket $ticket): array
    {
        $issue = $this->github->api('issue')->create(
            $this->owner,
            $this->repo,
            [
                'title' => "[Ticket #{$ticket->id}] {$ticket->subject}",
                'body' => $this->formatIssueBody($ticket),
                'labels' => $this->getLabels($ticket),
                'assignees' => $this->getAssignees($ticket),
            ]
        );

        // Store GitHub issue number
        $ticket->update([
            'meta' => array_merge($ticket->meta ?? [], [
                'github_issue_number' => $issue['number'],
                'github_issue_url' => $issue['html_url'],
            ]),
        ]);

        return $issue;
    }

    private function formatIssueBody(Ticket $ticket): string
    {
        $body = "## Description\n\n{$ticket->description}\n\n";
        $body .= "## Details\n\n";
        $body .= "- **Priority**: {$ticket->priority->label()}\n";
        $body .= "- **Type**: {$ticket->type->label()}\n";
        $body .= "- **Status**: {$ticket->status->label()}\n";
        $body .= "- **Created**: {$ticket->created_at->format('Y-m-d H:i:s')}\n";

        if ($ticket->customer_email) {
            $body .= "- **Customer**: {$ticket->customer_email}\n";
        }

        $body .= "\n---\n";
        $body .= "[View in Helpdesk](" . route('tickets.show', $ticket) . ")";

        return $body;
    }

    private function getLabels(Ticket $ticket): array
    {
        $labels = ['helpdesk'];

        // Add priority label
        $labels[] = "priority:{$ticket->priority->value}";

        // Add type label
        $labels[] = "type:{$ticket->type->value}";

        // Add bug label if applicable
        if (str_contains(strtolower($ticket->subject), 'bug') ||
            str_contains(strtolower($ticket->description), 'error')) {
            $labels[] = 'bug';
        }

        return $labels;
    }

    private function getAssignees(Ticket $ticket): array
    {
        if (!$ticket->assignee) {
            return [];
        }

        // Map helpdesk user to GitHub username
        $mapping = config('services.github.user_mapping', []);

        $githubUsername = $mapping[$ticket->assignee->email] ?? null;

        return $githubUsername ? [$githubUsername] : [];
    }
}
```

## Webhook System

### Generic Webhook Handler

```php
namespace App\Integrations\Webhooks;

use Illuminate\Support\Facades\Http;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class WebhookDispatcher
{
    public function dispatch(string $event, Ticket $ticket, array $additionalData = []): void
    {
        $webhooks = config('helpdesk.webhooks', []);

        foreach ($webhooks as $webhook) {
            if (!$this->shouldDispatch($webhook, $event)) {
                continue;
            }

            $this->sendWebhook($webhook, $event, $ticket, $additionalData);
        }
    }

    private function shouldDispatch(array $webhook, string $event): bool
    {
        if (!($webhook['enabled'] ?? true)) {
            return false;
        }

        if (isset($webhook['events']) && !in_array($event, $webhook['events'])) {
            return false;
        }

        return true;
    }

    private function sendWebhook(array $webhook, string $event, Ticket $ticket, array $data): void
    {
        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'type' => $ticket->type->value,
                'created_at' => $ticket->created_at->toIso8601String(),
                'updated_at' => $ticket->updated_at->toIso8601String(),
            ],
            'data' => $data,
        ];

        // Add signature for security
        $signature = $this->generateSignature($payload, $webhook['secret'] ?? '');

        try {
            Http::withHeaders([
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event' => $event,
            ])
            ->timeout($webhook['timeout'] ?? 30)
            ->retry($webhook['retries'] ?? 3, $webhook['retry_delay'] ?? 100)
            ->post($webhook['url'], $payload);

            Log::info('Webhook dispatched', [
                'url' => $webhook['url'],
                'event' => $event,
                'ticket_id' => $ticket->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook dispatch failed', [
                'url' => $webhook['url'],
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateSignature(array $payload, string $secret): string
    {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }
}
```

## Configuration Example

```php
// config/helpdesk.php
return [
    // ... other config

    'webhooks' => [
        [
            'url' => env('WEBHOOK_URL_1'),
            'secret' => env('WEBHOOK_SECRET_1'),
            'enabled' => true,
            'events' => ['ticket.created', 'ticket.resolved'],
            'timeout' => 30,
            'retries' => 3,
        ],
    ],
];

// config/services.php
return [
    // ... other services

    'slack' => [
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],

    'jira' => [
        'host' => env('JIRA_HOST'),
        'username' => env('JIRA_USERNAME'),
        'token' => env('JIRA_API_TOKEN'),
        'project_key' => env('JIRA_PROJECT_KEY'),
    ],

    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'owner' => env('GITHUB_OWNER'),
        'repo' => env('GITHUB_REPO'),
        'user_mapping' => [
            // Map email to GitHub username
            'john@example.com' => 'johndoe',
        ],
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],
];
```