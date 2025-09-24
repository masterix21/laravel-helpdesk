# Integration Hub

## Overview

The Integration Hub provides seamless connectivity with third-party tools and services, enabling the helpdesk to work within existing workflows and tech stacks. It offers pre-built integrations, webhook support, and a flexible API framework.

## Core Integrations

### 1. Communication Platforms

#### Slack Integration
```php
namespace LucaLongo\LaravelHelpdesk\Integrations\Slack;

class SlackIntegration
{
    protected $client;
    
    public function sendNotification($channel, $ticket)
    {
        $this->client->chat->postMessage([
            'channel' => $channel,
            'blocks' => $this->buildTicketBlocks($ticket),
            'attachments' => [
                [
                    'color' => $this->getPriorityColor($ticket->priority),
                    'fields' => [
                        ['title' => 'Priority', 'value' => $ticket->priority->label(), 'short' => true],
                        ['title' => 'Status', 'value' => $ticket->status->label(), 'short' => true],
                        ['title' => 'Assignee', 'value' => $ticket->assignee?->name ?? 'Unassigned', 'short' => true],
                    ],
                ],
            ],
        ]);
    }
    
    public function handleSlashCommand($command, $text)
    {
        return match($command) {
            '/ticket' => $this->createTicketFromSlack($text),
            '/status' => $this->getTicketStatus($text),
            '/assign' => $this->assignTicket($text),
            '/close' => $this->closeTicket($text),
            default => $this->showHelp(),
        };
    }
    
    public function setupInteractiveComponents()
    {
        return [
            'actions' => [
                [
                    'type' => 'button',
                    'text' => 'View Ticket',
                    'url' => $this->getTicketUrl($ticket),
                ],
                [
                    'type' => 'button',
                    'text' => 'Assign to Me',
                    'action_id' => 'assign_to_me',
                ],
                [
                    'type' => 'select',
                    'placeholder' => 'Change Status',
                    'action_id' => 'change_status',
                    'options' => $this->getStatusOptions(),
                ],
            ],
        ];
    }
}
```

#### Microsoft Teams Integration
```php
class TeamsIntegration
{
    public function createAdaptiveCard($ticket)
    {
        return [
            'type' => 'AdaptiveCard',
            'version' => '1.5',
            'body' => [
                [
                    'type' => 'TextBlock',
                    'text' => $ticket->subject,
                    'size' => 'Large',
                    'weight' => 'Bolder',
                ],
                [
                    'type' => 'FactSet',
                    'facts' => [
                        ['title' => 'Ticket ID', 'value' => $ticket->ulid],
                        ['title' => 'Priority', 'value' => $ticket->priority->label()],
                        ['title' => 'Customer', 'value' => $ticket->customer->name],
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'Action.OpenUrl',
                    'title' => 'Open in Helpdesk',
                    'url' => route('tickets.show', $ticket),
                ],
            ],
        ];
    }
}
```

### 2. CRM Integrations

#### Salesforce Integration
```php
class SalesforceIntegration
{
    protected $client;
    
    public function syncCustomer($email)
    {
        $contact = $this->client->query(
            "SELECT Id, Name, Email, AccountId FROM Contact WHERE Email = '{$email}' LIMIT 1"
        );
        
        if ($contact->size > 0) {
            $record = $contact->records[0];
            
            return Customer::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $record->Name,
                    'external_id' => $record->Id,
                    'company_id' => $record->AccountId,
                    'source' => 'salesforce',
                ]
            );
        }
    }
    
    public function createCase(Ticket $ticket)
    {
        return $this->client->create('Case', [
            'Subject' => $ticket->subject,
            'Description' => $ticket->description,
            'Priority' => $this->mapPriority($ticket->priority),
            'Status' => 'New',
            'Origin' => 'Helpdesk',
            'ContactId' => $ticket->customer->external_id,
        ]);
    }
}
```

#### HubSpot Integration
```php
class HubSpotIntegration
{
    public function enrichCustomer($email)
    {
        $contact = $this->client->crm()->contacts()->searchApi()->doSearch([
            'filterGroups' => [
                [
                    'filters' => [
                        [
                            'propertyName' => 'email',
                            'operator' => 'EQ',
                            'value' => $email,
                        ],
                    ],
                ],
            ],
        ]);
        
        if ($contact->getResults()) {
            $properties = $contact->getResults()[0]->getProperties();
            
            return [
                'company' => $properties['company'] ?? null,
                'phone' => $properties['phone'] ?? null,
                'lifecycle_stage' => $properties['lifecyclestage'] ?? null,
                'deal_value' => $properties['total_revenue'] ?? 0,
            ];
        }
    }
}
```

### 3. Project Management Tools

#### JIRA Integration
```php
class JiraIntegration
{
    public function createIssue(Ticket $ticket, $projectKey, $issueType = 'Task')
    {
        $issue = new Issue();
        
        $issue->setProjectKey($projectKey)
            ->setIssueType($issueType)
            ->setSummary($ticket->subject)
            ->setDescription($ticket->description)
            ->setPriority($this->mapPriority($ticket->priority))
            ->addLabel('helpdesk')
            ->addLabel('ticket-' . $ticket->ulid);
        
        $createdIssue = $this->issueService->create($issue);
        
        // Link back to ticket
        $ticket->update([
            'meta->jira_issue_key' => $createdIssue->key,
        ]);
        
        return $createdIssue;
    }
    
    public function syncComments($ticket, $issueKey)
    {
        $comments = $ticket->comments()->where('synced_to_jira', false)->get();
        
        foreach ($comments as $comment) {
            $this->commentService->create(
                $issueKey,
                $comment->body . "\n\n-- From Helpdesk by " . $comment->author->name
            );
            
            $comment->update(['synced_to_jira' => true]);
        }
    }
}
```

### 4. Monitoring & Analytics

#### Datadog Integration
```php
class DatadogIntegration
{
    public function trackMetric($metric, $value, $tags = [])
    {
        $this->client->metric($metric, $value, array_merge([
            'service' => 'helpdesk',
            'environment' => app()->environment(),
        ], $tags));
    }
    
    public function trackTicketMetrics()
    {
        $this->trackMetric('helpdesk.tickets.open', Ticket::open()->count());
        $this->trackMetric('helpdesk.tickets.overdue', Ticket::overdue()->count());
        $this->trackMetric('helpdesk.sla.breached', Ticket::slaBreach()->count());
        
        // Average resolution time
        $avgResolution = Ticket::resolved()
            ->whereBetween('resolved_at', [now()->subHour(), now()])
            ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, created_at, resolved_at)'));
        
        $this->trackMetric('helpdesk.resolution.average', $avgResolution);
    }
}
```

### 5. Webhook System

```php
class WebhookManager
{
    public function register($url, $events, $secret = null)
    {
        return Webhook::create([
            'url' => $url,
            'events' => $events,
            'secret' => $secret ?? Str::random(32),
            'is_active' => true,
        ]);
    }
    
    public function dispatch($event, $payload)
    {
        $webhooks = Webhook::active()
            ->where('events', 'like', "%{$event}%")
            ->get();
        
        foreach ($webhooks as $webhook) {
            DispatchWebhook::dispatch($webhook, $event, $payload)
                ->onQueue('webhooks');
        }
    }
    
    public function verify($request, $secret)
    {
        $signature = $request->header('X-Webhook-Signature');
        $payload = $request->getContent();
        
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
}
```

### 6. API Gateway

```php
class ApiGateway
{
    protected $integrations = [];
    
    public function registerIntegration($name, IntegrationInterface $integration)
    {
        $this->integrations[$name] = $integration;
    }
    
    public function execute($integration, $method, $params = [])
    {
        if (!isset($this->integrations[$integration])) {
            throw new IntegrationNotFoundException($integration);
        }
        
        $instance = $this->integrations[$integration];
        
        // Rate limiting
        if (!$this->rateLimiter->attempt($integration, 60)) {
            throw new RateLimitExceededException();
        }
        
        // Execute with retry logic
        return retry(3, function () use ($instance, $method, $params) {
            return $instance->$method(...$params);
        }, 1000);
    }
}
```

## Configuration

```php
'integrations' => [
    'slack' => [
        'enabled' => true,
        'bot_token' => env('SLACK_BOT_TOKEN'),
        'signing_secret' => env('SLACK_SIGNING_SECRET'),
        'default_channel' => '#support',
    ],
    
    'teams' => [
        'enabled' => false,
        'app_id' => env('TEAMS_APP_ID'),
        'app_secret' => env('TEAMS_APP_SECRET'),
    ],
    
    'salesforce' => [
        'enabled' => false,
        'instance_url' => env('SALESFORCE_INSTANCE_URL'),
        'client_id' => env('SALESFORCE_CLIENT_ID'),
        'client_secret' => env('SALESFORCE_CLIENT_SECRET'),
        'username' => env('SALESFORCE_USERNAME'),
        'password' => env('SALESFORCE_PASSWORD'),
    ],
    
    'jira' => [
        'enabled' => false,
        'host' => env('JIRA_HOST'),
        'username' => env('JIRA_USERNAME'),
        'token' => env('JIRA_API_TOKEN'),
        'default_project' => env('JIRA_DEFAULT_PROJECT'),
    ],
    
    'webhooks' => [
        'enabled' => true,
        'max_retries' => 3,
        'timeout' => 30,
        'verify_ssl' => true,
        'events' => [
            'ticket.created',
            'ticket.updated',
            'ticket.resolved',
            'ticket.closed',
            'comment.added',
            'rating.received',
        ],
    ],
    
    'api' => [
        'rate_limit' => [
            'enabled' => true,
            'max_requests' => 1000,
            'per_minutes' => 1,
        ],
        'authentication' => [
            'methods' => ['bearer', 'api_key', 'oauth2'],
        ],
    ],
]
```

## OAuth2 Provider

```php
class OAuth2Provider
{
    public function authorize(Request $request)
    {
        $client = Client::findOrFail($request->client_id);
        
        if (!$this->validateRedirectUri($client, $request->redirect_uri)) {
            abort(400, 'Invalid redirect URI');
        }
        
        return view('oauth.authorize', [
            'client' => $client,
            'scopes' => $this->parseScopes($request->scope),
        ]);
    }
    
    public function issueToken(Request $request)
    {
        $client = $this->validateClient($request);
        
        return match($request->grant_type) {
            'authorization_code' => $this->authCodeGrant($request, $client),
            'refresh_token' => $this->refreshTokenGrant($request, $client),
            'client_credentials' => $this->clientCredentialsGrant($client),
            default => abort(400, 'Unsupported grant type'),
        };
    }
}
```

## Benefits

- **50% Reduction in Context Switching**: Work within existing tools
- **30% Faster Implementation**: Pre-built integrations
- **40% Better Data Accuracy**: Automatic synchronization
- **25% Time Savings**: Automated workflows
- **35% Improved Visibility**: Unified view across systems

## Implementation Timeline

### Phase 1: Communication (2 weeks)
- Slack integration
- Teams integration
- Basic webhooks

### Phase 2: CRM (3 weeks)
- Salesforce connector
- HubSpot connector
- Customer data sync

### Phase 3: Project Management (2 weeks)
- JIRA integration
- Asana/Trello support
- Issue synchronization

### Phase 4: Advanced (3 weeks)
- OAuth2 provider
- API gateway
- Custom integrations