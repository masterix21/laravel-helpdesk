# Common Use Cases

Real-world examples of using Laravel Helpdesk in production.

## Customer Support Portal

### Basic Implementation

```php
// app/Http/Controllers/SupportController.php
class SupportController extends Controller
{
    public function __construct(
        private TicketService $tickets,
        private CommentService $comments
    ) {}

    public function create()
    {
        return view('support.create', [
            'types' => TicketType::options(),
            'priorities' => TicketPriority::options(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => ['required', new Enum(TicketType::class)],
            'attachments.*' => 'file|max:10240',
        ]);

        $ticket = $this->tickets->open($validated, $request->user());

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $ticket->attachments()->create([
                    'filename' => $file->getClientOriginalName(),
                    'path' => $file->store('tickets/' . $ticket->id),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        return redirect()->route('support.show', $ticket)
            ->with('success', 'Your ticket has been submitted.');
    }

    public function show(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        return view('support.show', [
            'ticket' => $ticket->load(['comments.author', 'attachments']),
            'canComment' => $ticket->status !== TicketStatus::Closed,
        ]);
    }
}
```

## Internal Help Desk

### IT Support System

```php
class ITSupportService extends TicketService
{
    public function createIncident(array $data): Ticket
    {
        $ticket = $this->open(array_merge($data, [
            'type' => 'incident',
            'priority' => $this->calculatePriority($data),
        ]));

        // Auto-assign based on category
        $this->autoAssign($ticket);

        // Check for known issues
        $this->checkKnownIssues($ticket);

        return $ticket;
    }

    private function calculatePriority(array $data): TicketPriority
    {
        $impactedUsers = $data['impacted_users'] ?? 1;
        $systemCritical = $data['system_critical'] ?? false;

        if ($systemCritical || $impactedUsers > 100) {
            return TicketPriority::Urgent;
        }

        if ($impactedUsers > 10) {
            return TicketPriority::High;
        }

        return TicketPriority::Normal;
    }

    private function autoAssign(Ticket $ticket): void
    {
        $agent = match($ticket->categories->first()?->slug) {
            'network' => User::where('team', 'network')->inRandomOrder()->first(),
            'hardware' => User::where('team', 'hardware')->inRandomOrder()->first(),
            'software' => User::where('team', 'software')->inRandomOrder()->first(),
            default => User::where('role', 'support_lead')->first(),
        };

        if ($agent) {
            $this->assign($ticket, $agent);
        }
    }
}
```

## E-commerce Support

### Order Issue Tracking

```php
class OrderSupportService
{
    public function createOrderTicket(Order $order, array $issueData): Ticket
    {
        $ticket = LaravelHelpdesk::open([
            'subject' => "Order #{$order->number} - {$issueData['issue_type']}",
            'description' => $issueData['description'],
            'type' => 'order_issue',
            'meta' => [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'order_total' => $order->total,
                'customer_id' => $order->customer_id,
            ],
        ], $order->customer);

        // Link order history
        $this->attachOrderHistory($ticket, $order);

        // Check refund eligibility
        if ($issueData['issue_type'] === 'refund_request') {
            $this->processRefundRequest($ticket, $order);
        }

        return $ticket;
    }

    private function processRefundRequest(Ticket $ticket, Order $order): void
    {
        $eligible = $order->created_at->diffInDays(now()) <= 30;

        if ($eligible) {
            $ticket->update(['priority' => TicketPriority::High]);
            
            LaravelHelpdesk::comment(
                $ticket,
                'Refund request is eligible. Order is within 30-day return period.',
                null,
                false // Internal note
            );
        }
    }
}
```

## SaaS Platform Support

### Multi-tenant Support System

```php
class TenantSupportService
{
    public function handleTenantTicket(Tenant $tenant, array $data): Ticket
    {
        $ticket = LaravelHelpdesk::open(array_merge($data, [
            'meta' => [
                'tenant_id' => $tenant->id,
                'tenant_plan' => $tenant->plan,
                'tenant_mrr' => $tenant->monthly_revenue,
            ],
        ]));

        // Prioritize based on plan
        $priority = match($tenant->plan) {
            'enterprise' => TicketPriority::Urgent,
            'pro' => TicketPriority::High,
            'starter' => TicketPriority::Normal,
            default => TicketPriority::Low,
        };

        $ticket->update(['priority' => $priority]);

        // Set SLA based on plan
        $this->setCustomSla($ticket, $tenant->plan);

        return $ticket;
    }

    private function setCustomSla(Ticket $ticket, string $plan): void
    {
        $slaMinutes = match($plan) {
            'enterprise' => ['first_response' => 15, 'resolution' => 120],
            'pro' => ['first_response' => 60, 'resolution' => 480],
            default => null,
        };

        if ($slaMinutes) {
            $ticket->update([
                'first_response_due_at' => now()->addMinutes($slaMinutes['first_response']),
                'resolution_due_at' => now()->addMinutes($slaMinutes['resolution']),
            ]);
        }
    }
}
```

## Bug Tracking Integration

```php
class BugTrackingService
{
    public function reportBug(array $bugData): Ticket
    {
        $ticket = LaravelHelpdesk::open([
            'subject' => $bugData['title'],
            'description' => $this->formatBugReport($bugData),
            'type' => 'bug_report',
            'priority' => $this->calculateBugPriority($bugData),
            'meta' => [
                'environment' => $bugData['environment'],
                'version' => $bugData['version'],
                'stack_trace' => $bugData['stack_trace'] ?? null,
                'reproduction_steps' => $bugData['steps'],
            ],
        ]);

        // Auto-categorize
        $this->categorizeBug($ticket, $bugData);

        // Check for duplicates
        $this->checkDuplicateBugs($ticket);

        return $ticket;
    }

    private function formatBugReport(array $data): string
    {
        return view('tickets.bug-report', $data)->render();
    }

    private function checkDuplicateBugs(Ticket $ticket): void
    {
        $similar = Ticket::where('type', 'bug_report')
            ->where('id', '!=', $ticket->id)
            ->where(function ($query) use ($ticket) {
                $keywords = explode(' ', $ticket->subject);
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) > 3) {
                        $query->orWhere('subject', 'like', "%{$keyword}%");
                    }
                }
            })
            ->take(5)
            ->get();

        if ($similar->isNotEmpty()) {
            LaravelHelpdesk::comment(
                $ticket,
                "Possible duplicate bugs found: " . $similar->pluck('id')->join(', '),
                null,
                false
            );
        }
    }
}
```

## API Support Tickets

```php
// routes/api.php
Route::prefix('support')->group(function () {
    Route::post('tickets', [ApiTicketController::class, 'store']);
    Route::get('tickets/{ticket}', [ApiTicketController::class, 'show']);
    Route::post('tickets/{ticket}/comments', [ApiTicketController::class, 'comment']);
});

// app/Http/Controllers/Api/ApiTicketController.php
class ApiTicketController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => ['nullable', new Enum(TicketPriority::class)],
            'api_key' => 'required|exists:api_keys,key',
        ]);

        $apiKey = ApiKey::where('key', $validated['api_key'])->first();

        $ticket = LaravelHelpdesk::open([
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'] ?? TicketPriority::Normal,
            'type' => 'api_issue',
            'meta' => [
                'api_key_id' => $apiKey->id,
                'api_version' => $request->header('API-Version'),
                'user_agent' => $request->userAgent(),
            ],
        ], $apiKey->user);

        return response()->json([
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status->value,
            'created_at' => $ticket->created_at,
        ], 201);
    }
}
```

## Automated Escalation

```php
class EscalationService
{
    public function checkAndEscalate(): void
    {
        // Find tickets needing escalation
        $tickets = Ticket::open()
            ->where(function ($query) {
                $query->whereNotNull('first_response_due_at')
                    ->where('first_response_due_at', '<', now())
                    ->whereNull('first_response_at');
            })
            ->orWhere(function ($query) {
                $query->where('priority', TicketPriority::Urgent)
                    ->where('created_at', '<', now()->subHours(2))
                    ->whereNull('assignee_id');
            })
            ->get();

        foreach ($tickets as $ticket) {
            $this->escalate($ticket);
        }
    }

    private function escalate(Ticket $ticket): void
    {
        // Update priority
        if ($ticket->priority !== TicketPriority::Urgent) {
            $ticket->update(['priority' => TicketPriority::Urgent]);
        }

        // Assign to manager
        $manager = User::role('support_manager')->first();
        if ($manager && !$ticket->assignee) {
            LaravelHelpdesk::assign($ticket, $manager);
        }

        // Add escalation note
        LaravelHelpdesk::comment(
            $ticket,
            'Ticket escalated due to SLA breach or high priority timeout.',
            null,
            false
        );

        // Send notifications
        event(new TicketEscalated($ticket, 'Automatic escalation'));
    }
}
```