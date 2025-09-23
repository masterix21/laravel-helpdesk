<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use LucaLongo\LaravelHelpdesk\Events\TimeEntryStarted;
use LucaLongo\LaravelHelpdesk\Events\TimeEntryStopped;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketTimeEntry;
use LucaLongo\LaravelHelpdesk\Services\TimeTrackingService;

beforeEach(function () {
    $this->service = new TimeTrackingService;
    $this->ticket = Ticket::factory()->create();
});

it('can start a timer for a ticket', function () {
    Event::fake();

    $entry = $this->service->startTimer(
        ticketId: $this->ticket->id,
        userId: 1,
        description: 'Working on issue'
    );

    expect($entry)->toBeInstanceOf(TicketTimeEntry::class);
    expect($entry->ticket_id)->toBe($this->ticket->id);
    expect($entry->user_id)->toBe(1);
    expect($entry->description)->toBe('Working on issue');
    expect($entry->started_at)->toBeInstanceOf(Carbon::class);
    expect($entry->ended_at)->toBeNull();
    expect($entry->isRunning())->toBeTrue();

    Event::assertDispatched(TimeEntryStarted::class, function ($event) use ($entry) {
        return $event->timeEntry->id === $entry->id;
    });
});

it('stops running timers when starting a new one', function () {
    $running = TicketTimeEntry::factory()
        ->running()
        ->create([
            'user_id' => 1,
            'ticket_id' => $this->ticket->id,
        ]);

    $new = $this->service->startTimer(
        ticketId: $this->ticket->id,
        userId: 1,
        stopRunning: true
    );

    $running->refresh();
    expect($running->isRunning())->toBeFalse();
    expect($running->ended_at)->not->toBeNull();
    expect($new->isRunning())->toBeTrue();
});

it('can stop a running timer', function () {
    Event::fake();

    $entry = TicketTimeEntry::factory()
        ->running()
        ->create(['started_at' => now()->subMinutes(30)]);

    $stopped = $this->service->stopTimer($entry->id);

    expect($stopped)->not->toBeNull();
    expect($stopped->isRunning())->toBeFalse();
    expect($stopped->ended_at)->toBeInstanceOf(Carbon::class);
    expect($stopped->duration_minutes)->toBeGreaterThan(0);

    Event::assertDispatched(TimeEntryStopped::class, function ($event) use ($entry) {
        return $event->timeEntry->id === $entry->id;
    });
});

it('returns null when stopping non-existent timer', function () {
    $result = $this->service->stopTimer(999);
    expect($result)->toBeNull();
});

it('can log time manually', function () {
    $entry = $this->service->logTime(
        ticketId: $this->ticket->id,
        durationMinutes: 120,
        userId: 1,
        description: 'Fixed bug',
        isBillable: true,
        hourlyRate: 75.50
    );

    expect($entry->ticket_id)->toBe($this->ticket->id);
    expect($entry->duration_minutes)->toBe(120);
    expect($entry->user_id)->toBe(1);
    expect($entry->description)->toBe('Fixed bug');
    expect($entry->is_billable)->toBeTrue();
    expect($entry->hourly_rate)->toBe('75.50');
    expect($entry->ended_at)->not->toBeNull();
});

it('can get all time entries for a ticket', function () {
    TicketTimeEntry::factory()
        ->count(3)
        ->create(['ticket_id' => $this->ticket->id]);

    $entries = $this->service->getTicketTimeEntries($this->ticket->id);

    expect($entries)->toHaveCount(3);
    expect($entries->first())->toBeInstanceOf(TicketTimeEntry::class);
});

it('calculates total time for a ticket', function () {
    TicketTimeEntry::factory()->create([
        'ticket_id' => $this->ticket->id,
        'duration_minutes' => 60,
        'is_billable' => true,
    ]);

    TicketTimeEntry::factory()->create([
        'ticket_id' => $this->ticket->id,
        'duration_minutes' => 30,
        'is_billable' => false,
    ]);

    $total = $this->service->getTicketTotalTime($this->ticket->id);
    expect($total)->toBe(90);

    $billableTotal = $this->service->getTicketTotalTime($this->ticket->id, billableOnly: true);
    expect($billableTotal)->toBe(60);
});

it('calculates total cost for a ticket', function () {
    TicketTimeEntry::factory()->create([
        'ticket_id' => $this->ticket->id,
        'duration_minutes' => 60,
        'is_billable' => true,
        'hourly_rate' => 100,
    ]);

    TicketTimeEntry::factory()->create([
        'ticket_id' => $this->ticket->id,
        'duration_minutes' => 30,
        'is_billable' => true,
        'hourly_rate' => 80,
    ]);

    TicketTimeEntry::factory()->create([
        'ticket_id' => $this->ticket->id,
        'duration_minutes' => 60,
        'is_billable' => false,
        'hourly_rate' => 100,
    ]);

    $totalCost = $this->service->getTicketTotalCost($this->ticket->id);
    expect($totalCost)->toBe(140.00);
});

it('generates user time report', function () {
    $userId = 1;
    $startDate = now()->subDays(7);
    $endDate = now();

    TicketTimeEntry::factory()->create([
        'user_id' => $userId,
        'ticket_id' => $this->ticket->id,
        'started_at' => now()->subDays(5),
        'duration_minutes' => 120,
        'is_billable' => true,
        'hourly_rate' => 100,
    ]);

    TicketTimeEntry::factory()->create([
        'user_id' => $userId,
        'ticket_id' => $this->ticket->id,
        'started_at' => now()->subDays(3),
        'duration_minutes' => 60,
        'is_billable' => false,
    ]);

    $report = $this->service->getUserTimeReport($userId, $startDate, $endDate);

    expect($report['user_id'])->toBe($userId);
    expect($report['total_minutes'])->toBe(180);
    expect($report['total_hours'])->toBe(3.0);
    expect($report['billable_minutes'])->toBe(120);
    expect($report['billable_hours'])->toBe(2.0);
    expect($report['total_cost'])->toBe(200.00);
    expect($report['entries_count'])->toBe(2);
    expect($report['by_ticket'])->toHaveCount(1);
});

it('generates project time report', function () {
    $projectId = 'PROJECT-123';
    $startDate = now()->subDays(7);
    $endDate = now();

    $ticket1 = Ticket::factory()->create(['meta' => ['project' => $projectId]]);
    $ticket2 = Ticket::factory()->create(['meta' => ['project' => $projectId]]);

    TicketTimeEntry::factory()->create([
        'ticket_id' => $ticket1->id,
        'user_id' => 1,
        'started_at' => now()->subDays(5),
        'duration_minutes' => 120,
        'is_billable' => true,
        'hourly_rate' => 100,
    ]);

    TicketTimeEntry::factory()->create([
        'ticket_id' => $ticket2->id,
        'user_id' => 2,
        'started_at' => now()->subDays(3),
        'duration_minutes' => 60,
        'is_billable' => true,
        'hourly_rate' => 80,
    ]);

    $report = $this->service->getProjectTimeReport($projectId, $startDate, $endDate);

    expect($report['project'])->toBe($projectId);
    expect($report['total_minutes'])->toBe(180);
    expect($report['total_hours'])->toBe(3.0);
    expect($report['billable_minutes'])->toBe(180);
    expect($report['total_cost'])->toBe(280.00);
    expect($report['entries_count'])->toBe(2);
    expect($report['by_user'])->toHaveCount(2);
    expect($report['by_ticket'])->toHaveCount(2);
});

it('can update a time entry', function () {
    $entry = TicketTimeEntry::factory()->create([
        'description' => 'Original',
        'is_billable' => false,
    ]);

    $updated = $this->service->updateEntry($entry->id, [
        'description' => 'Updated',
        'is_billable' => true,
        'hourly_rate' => 90,
    ]);

    expect($updated->description)->toBe('Updated');
    expect($updated->is_billable)->toBeTrue();
    expect($updated->hourly_rate)->toBe('90.00');
});

it('can delete a time entry', function () {
    $entry = TicketTimeEntry::factory()->create();

    $result = $this->service->deleteEntry($entry->id);

    expect($result)->toBeTrue();
    expect(TicketTimeEntry::find($entry->id))->toBeNull();
});

it('stops all user running timers', function () {
    $userId = 1;

    $running1 = TicketTimeEntry::factory()->running()->create(['user_id' => $userId]);
    $running2 = TicketTimeEntry::factory()->running()->create(['user_id' => $userId]);
    $otherUser = TicketTimeEntry::factory()->running()->create(['user_id' => 2]);

    $stopped = $this->service->stopUserRunningTimers($userId);

    expect($stopped)->toHaveCount(2);

    $running1->refresh();
    $running2->refresh();
    $otherUser->refresh();

    expect($running1->isRunning())->toBeFalse();
    expect($running2->isRunning())->toBeFalse();
    expect($otherUser->isRunning())->toBeTrue();
});

it('calculates duration hours correctly', function () {
    $entry = TicketTimeEntry::factory()->create(['duration_minutes' => 90]);
    expect($entry->duration_hours)->toBe(1.5);

    $entry = TicketTimeEntry::factory()->create(['duration_minutes' => 125]);
    expect($entry->duration_hours)->toBe(2.08);
});

it('calculates total cost correctly', function () {
    $entry = TicketTimeEntry::factory()->create([
        'duration_minutes' => 90,
        'is_billable' => true,
        'hourly_rate' => 100,
    ]);
    expect($entry->total_cost)->toBe(150.00);

    $nonBillable = TicketTimeEntry::factory()->create([
        'duration_minutes' => 90,
        'is_billable' => false,
        'hourly_rate' => 100,
    ]);
    expect($nonBillable->total_cost)->toBeNull();
});
