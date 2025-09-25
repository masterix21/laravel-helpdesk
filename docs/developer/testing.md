# Testing

Comprehensive testing guide for Laravel Helpdesk.

## Setup

The package uses Pest PHP with Orchestra Testbench for Laravel package testing.

### Test Environment

```php
// tests/TestCase.php
namespace LucaLongo\LaravelHelpdesk\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use LucaLongo\LaravelHelpdesk\LaravelHelpdeskServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setUpDatabase();
        $this->seedTestData();
    }
    
    protected function getPackageProviders($app): array
    {
        return [
            LaravelHelpdeskServiceProvider::class,
        ];
    }
    
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('helpdesk.ai.enabled', false);
    }
}
```

## Writing Tests

### Feature Tests

```php
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;

test('ticket can be created with required fields', function () {
    $ticket = Ticket::factory()->create([
        'subject' => 'Test Ticket',
        'status' => TicketStatus::Open,
    ]);
    
    expect($ticket)
        ->subject->toBe('Test Ticket')
        ->status->toBe(TicketStatus::Open)
        ->id->toBeString();
});

test('ticket transitions follow rules', function () {
    $ticket = Ticket::factory()->open()->create();
    
    // Valid transition
    $ticket->transitionTo(TicketStatus::InProgress);
    expect($ticket->status)->toBe(TicketStatus::InProgress);
    
    // Invalid transition
    expect(fn() => $ticket->transitionTo(TicketStatus::Open))
        ->toThrow(InvalidTransitionException::class);
});
```

### Testing Services

```php
use LucaLongo\LaravelHelpdesk\Services\TicketService;

beforeEach(function () {
    $this->service = app(TicketService::class);
    $this->user = User::factory()->create();
});

test('service creates ticket with SLA', function () {
    config(['helpdesk.sla.enabled' => true]);
    
    $ticket = $this->service->open([
        'subject' => 'Urgent Issue',
        'type' => 'support',
        'priority' => 'urgent',
    ], $this->user);
    
    expect($ticket)
        ->first_response_due_at->toBeInstanceOf(Carbon::class)
        ->resolution_due_at->toBeInstanceOf(Carbon::class);
    
    // Urgent = 30 min first response
    $expectedDue = now()->addMinutes(30);
    expect($ticket->first_response_due_at->timestamp)
        ->toBeLessThanOrEqual($expectedDue->timestamp);
});
```

### Testing with Factories

```php
// Create ticket with relationships
$ticket = Ticket::factory()
    ->has(TicketComment::factory()->count(3))
    ->has(TicketAttachment::factory()->count(2))
    ->hasSubscriptions(2)
    ->create();

expect($ticket->comments)->toHaveCount(3);
expect($ticket->attachments)->toHaveCount(2);
expect($ticket->subscriptions)->toHaveCount(2);

// Use states
$urgentTicket = Ticket::factory()->urgent()->create();
$resolvedTicket = Ticket::factory()->resolved()->create();
$overdueTicket = Ticket::factory()->overdue()->create();
```

## Mocking

### Mocking Services

```php
use Mockery;

test('AI analysis can be mocked', function () {
    $aiService = Mockery::mock(AIService::class);
    $aiService->shouldReceive('analyze')
        ->once()
        ->andReturn(new AIAnalysis([
            'sentiment' => 'negative',
            'category' => 'bug',
        ]));
    
    $this->app->instance(AIService::class, $aiService);
    
    $ticket = Ticket::factory()->create();
    $analysis = $aiService->analyze($ticket);
    
    expect($analysis->sentiment)->toBe('negative');
});
```

### Mocking External APIs

```php
use Illuminate\Support\Facades\Http;

test('webhook action sends HTTP request', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['success' => true], 200),
    ]);
    
    $action = new SendWebhookAction();
    $ticket = Ticket::factory()->create();
    
    $action->execute($ticket, [
        'url' => 'https://api.example.com/webhook',
        'method' => 'POST',
    ]);
    
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/webhook'
            && $request->method() === 'POST';
    });
});
```

## Database Testing

### Using Transactions

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ticket can be soft deleted', function () {
    $ticket = Ticket::factory()->create();
    $ticketId = $ticket->id;
    
    $ticket->delete();
    
    expect(Ticket::find($ticketId))->toBeNull();
    expect(Ticket::withTrashed()->find($ticketId))->not->toBeNull();
});
```

### Testing Relationships

```php
test('ticket relationships work correctly', function () {
    $parent = Ticket::factory()->create();
    $child1 = Ticket::factory()->create(['parent_id' => $parent->id]);
    $child2 = Ticket::factory()->create(['parent_id' => $parent->id]);
    
    expect($parent->children)->toHaveCount(2);
    expect($child1->parent->id)->toBe($parent->id);
    
    // Test nested set
    expect($parent->descendants)->toHaveCount(2);
});
```

## Event Testing

```php
use Illuminate\Support\Facades\Event;

test('events are dispatched correctly', function () {
    Event::fake();
    
    $ticket = Ticket::factory()->create();
    $ticket->transitionTo(TicketStatus::Resolved);
    
    Event::assertDispatched(TicketStatusChanged::class, function ($event) use ($ticket) {
        return $event->ticket->id === $ticket->id
            && $event->previous === TicketStatus::Open
            && $event->next === TicketStatus::Resolved;
    });
});
```

## Testing Commands

```php
test('metrics command generates report', function () {
    Ticket::factory()->count(10)->create();
    
    $this->artisan('helpdesk:metrics', [
        '--format' => 'json',
        '--from' => now()->subDays(7)->format('Y-m-d'),
    ])
    ->assertSuccessful()
    ->expectsOutput('Report generated successfully');
    
    $this->assertFileExists(storage_path('app/metrics/report.json'));
});
```

## Performance Testing

```php
test('bulk operations are performant', function () {
    $tickets = Ticket::factory()->count(100)->create();
    
    $startTime = microtime(true);
    
    app(BulkActionService::class)->performAction(
        $tickets,
        'change_status',
        ['status' => TicketStatus::Closed]
    );
    
    $duration = microtime(true) - $startTime;
    
    expect($duration)->toBeLessThan(5.0); // Should complete in under 5 seconds
});
```

## Custom Assertions

```php
// tests/Pest.php
expect()->extend('toBeOpenTicket', function () {
    return $this->status->toBe(TicketStatus::Open)
        ->closed_at->toBeNull();
});

expect()->extend('toHaveSla', function () {
    return $this->first_response_due_at->not->toBeNull()
        ->resolution_due_at->not->toBeNull();
});

// Usage
test('new tickets are open with SLA', function () {
    $ticket = Ticket::factory()->create();
    
    expect($ticket)
        ->toBeOpenTicket()
        ->toHaveSla();
});
```

## Running Tests

```bash
# Run all tests
composer test

# Run specific test file
composer test tests/Feature/TicketServiceTest.php

# Run with coverage
composer test-coverage

# Run in parallel
composer test -- --parallel

# Run specific test
composer test -- --filter="ticket can be created"
```

## CI/CD Integration

### GitHub Actions

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring, pdo_sqlite
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist
      
      - name: Run tests
        run: composer test-coverage
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
```