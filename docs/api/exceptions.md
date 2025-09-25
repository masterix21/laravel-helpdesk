# Exceptions

Custom exception classes for handling specific error scenarios.

## Core Exceptions

### InvalidTransitionException

Thrown when attempting an invalid status transition.

```php
use LucaLongo\LaravelHelpdesk\Exceptions\InvalidTransitionException;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;

class InvalidTransitionException extends Exception
{
    public static function make(
        TicketStatus $from,
        TicketStatus $to
    ): self {
        return new self(
            "Cannot transition from {$from->label()} to {$to->label()}"
        );
    }
}

// Usage
try {
    $ticket->transitionTo(TicketStatus::Closed);
} catch (InvalidTransitionException $e) {
    // Handle invalid transition
    Log::error($e->getMessage());
    return back()->with('error', 'Invalid status transition');
}
```

### TicketNotFoundException

Thrown when a ticket cannot be found.

```php
use LucaLongo\LaravelHelpdesk\Exceptions\TicketNotFoundException;

class TicketNotFoundException extends Exception
{
    public static function withId(string $id): self
    {
        return new self("Ticket with ID {$id} not found");
    }

    public static function withNumber(string $number): self
    {
        return new self("Ticket with number {$number} not found");
    }
}

// Usage
$ticket = Ticket::find($id);

if (!$ticket) {
    throw TicketNotFoundException::withId($id);
}
```

### SlaBreachException

Thrown when SLA breach conditions are detected.

```php
use LucaLongo\LaravelHelpdesk\Exceptions\SlaBreachException;

class SlaBreachException extends Exception
{
    public function __construct(
        public Ticket $ticket,
        public string $breachType,
        public int $minutesOverdue
    ) {
        $message = "SLA breach on ticket {$ticket->id}: {$breachType} overdue by {$minutesOverdue} minutes";
        parent::__construct($message);
    }
}

// Usage
if ($ticket->isOverdueSla()) {
    throw new SlaBreachException(
        $ticket,
        'first_response',
        $ticket->getMinutesOverdue()
    );
}
```

## Handling Exceptions

### In Controllers

```php
class TicketController extends Controller
{
    public function update(Request $request, string $id)
    {
        try {
            $ticket = Ticket::findOrFail($id);

            $this->ticketService->transition(
                $ticket,
                TicketStatus::from($request->status)
            );

            return redirect()->route('tickets.show', $ticket)
                ->with('success', 'Status updated');

        } catch (InvalidTransitionException $e) {
            return back()
                ->withErrors(['status' => $e->getMessage()]);

        } catch (ModelNotFoundException $e) {
            throw TicketNotFoundException::withId($id);
        }
    }
}
```

### Global Exception Handler

```php
// app/Exceptions/Handler.php
public function register(): void
{
    $this->renderable(function (InvalidTransitionException $e, Request $request) {
        if ($request->wantsJson()) {
            return response()->json([
                'error' => 'Invalid status transition',
                'message' => $e->getMessage()
            ], 422);
        }

        return back()
            ->withInput()
            ->withErrors(['status' => $e->getMessage()]);
    });

    $this->renderable(function (TicketNotFoundException $e, Request $request) {
        if ($request->wantsJson()) {
            return response()->json([
                'error' => 'Ticket not found',
                'message' => $e->getMessage()
            ], 404);
        }

        return redirect()
            ->route('tickets.index')
            ->with('error', $e->getMessage());
    });
}
```

### In Service Classes

```php
class TicketService
{
    public function transition(Ticket $ticket, TicketStatus $next): Ticket
    {
        $previous = $ticket->status;

        if (!$previous->canTransitionTo($next)) {
            throw InvalidTransitionException::make($previous, $next);
        }

        $ticket->status = $next;
        $ticket->save();

        return $ticket;
    }

    public function findByNumber(string $number): Ticket
    {
        $ticket = Ticket::where('ticket_number', $number)->first();

        if (!$ticket) {
            throw TicketNotFoundException::withNumber($number);
        }

        return $ticket;
    }
}
```

## Creating Custom Exceptions

### Basic Custom Exception

```php
namespace App\Exceptions;

use Exception;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class TicketAlreadyAssignedException extends Exception
{
    public function __construct(
        public Ticket $ticket,
        public $assignee
    ) {
        $message = "Ticket {$ticket->id} is already assigned to {$assignee->name}";
        parent::__construct($message);
    }
}
```

### Exception with Context

```php
class AutomationFailedException extends Exception
{
    protected array $context = [];

    public function __construct(
        string $message,
        public AutomationRule $rule,
        public Ticket $ticket,
        array $context = []
    ) {
        parent::__construct($message);
        $this->context = $context;
    }

    public function context(): array
    {
        return array_merge($this->context, [
            'rule_id' => $this->rule->id,
            'ticket_id' => $this->ticket->id,
            'rule_name' => $this->rule->name,
        ]);
    }

    public function report(): void
    {
        Log::error($this->getMessage(), $this->context());
    }
}
```

### Reportable Exception

```php
class CriticalSlaBreachException extends Exception implements Reportable
{
    public function __construct(
        public Ticket $ticket,
        public int $hoursOverdue
    ) {
        parent::__construct(
            "Critical SLA breach: Ticket {$ticket->id} overdue by {$hoursOverdue} hours"
        );
    }

    public function report(): void
    {
        // Send notification to management
        Notification::send(
            User::managers()->get(),
            new CriticalSlaBreachNotification($this->ticket)
        );

        // Log to separate channel
        Log::channel('sla-breaches')->critical($this->getMessage(), [
            'ticket_id' => $this->ticket->id,
            'hours_overdue' => $this->hoursOverdue,
            'customer' => $this->ticket->customer_email,
        ]);
    }
}
```

## Exception Testing

```php
use LucaLongo\LaravelHelpdesk\Exceptions\InvalidTransitionException;

test('throws exception for invalid transition', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open
    ]);

    // Cannot go directly from Open to Closed
    expect(fn() => $ticket->transitionTo(TicketStatus::Closed))
        ->toThrow(InvalidTransitionException::class);
});

test('handles ticket not found exception', function () {
    $response = $this->get('/tickets/non-existent-id');

    $response->assertRedirect('/tickets');
    $response->assertSessionHas('error');
});

test('returns json error for api requests', function () {
    $response = $this->getJson('/api/tickets/non-existent-id');

    $response->assertStatus(404);
    $response->assertJson([
        'error' => 'Ticket not found'
    ]);
});
```

## Best Practices

1. **Use specific exceptions** rather than generic Exception class
2. **Include context** in exception messages
3. **Make exceptions reportable** for critical errors
4. **Handle at appropriate level** - service, controller, or global
5. **Provide factory methods** for common scenarios
6. **Test exception paths** in your test suite
7. **Log appropriately** based on severity

## Common Patterns

### Validation Exception Pattern

```php
if (!$ticket->canBeRated()) {
    throw ValidationException::withMessages([
        'rating' => 'This ticket cannot be rated in its current status'
    ]);
}
```

### Authorization Exception Pattern

```php
if (!$user->can('assign', $ticket)) {
    throw new AuthorizationException('You are not authorized to assign this ticket');
}
```

### Business Logic Exception Pattern

```php
if ($ticket->hasActiveTimer()) {
    throw new BusinessLogicException(
        'Cannot start new timer while another is running'
    );
}
```