<?php

use Illuminate\Support\Carbon;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Services\SlaService;

beforeEach(function () {
    config([
        'helpdesk.sla.enabled' => true,
        'helpdesk.sla.rules' => [
            TicketPriority::Urgent->value => [
                'first_response' => 30,
                'resolution' => 240,
            ],
            TicketPriority::High->value => [
                'first_response' => 120,
                'resolution' => 480,
            ],
            TicketPriority::Normal->value => [
                'first_response' => 240,
                'resolution' => 1440,
            ],
        ],
        'helpdesk.sla.type_overrides' => [
            TicketType::Commercial->value => [
                TicketPriority::High->value => [
                    'first_response' => 60,
                    'resolution' => 240,
                ],
            ],
        ],
    ]);
});

it('calculates SLA due dates based on priority', function () {
    $service = new SlaService();

    $ticket = Ticket::factory()->make([
        'priority' => TicketPriority::Urgent,
        'type' => TicketType::ProductSupport,
        'opened_at' => now(),
    ]);

    $service->calculateSlaDueDates($ticket);

    expect($ticket->first_response_due_at)
        ->toBeInstanceOf(Carbon::class)
        ->and($ticket->opened_at->diffInMinutes($ticket->first_response_due_at))
        ->toBeGreaterThanOrEqual(29.9)
        ->toBeLessThanOrEqual(30.1)
        ->and($ticket->opened_at->diffInMinutes($ticket->resolution_due_at))
        ->toBeGreaterThanOrEqual(239.9)
        ->toBeLessThanOrEqual(240.1);
});

it('applies type-specific overrides for SLA', function () {
    $service = new SlaService();

    $ticket = Ticket::factory()->make([
        'priority' => TicketPriority::High,
        'type' => TicketType::Commercial,
        'opened_at' => now(),
    ]);

    $service->calculateSlaDueDates($ticket);

    expect($ticket->opened_at->diffInMinutes($ticket->first_response_due_at))
        ->toBeGreaterThanOrEqual(59.9)
        ->toBeLessThanOrEqual(60.1)
        ->and($ticket->opened_at->diffInMinutes($ticket->resolution_due_at))
        ->toBeGreaterThanOrEqual(239.9)
        ->toBeLessThanOrEqual(240.1);
});

it('does not set SLA dates when disabled', function () {
    config(['helpdesk.sla.enabled' => false]);

    $service = new SlaService();

    $ticket = Ticket::factory()->make([
        'priority' => TicketPriority::Urgent,
        'opened_at' => now(),
    ]);

    $service->calculateSlaDueDates($ticket);

    expect($ticket->first_response_due_at)->toBeNull()
        ->and($ticket->resolution_due_at)->toBeNull();
});

it('checks SLA compliance correctly', function () {
    $service = new SlaService();

    $ticket = Ticket::factory()->create([
        'priority' => TicketPriority::Normal,
        'opened_at' => now()->subHours(2),
        'first_response_due_at' => now()->addHours(2),
        'resolution_due_at' => now()->addHours(22),
    ]);

    $compliance = $service->checkSlaCompliance($ticket);

    expect($compliance['first_response']['status'])->toBe('pending')
        ->and($compliance['first_response']['overdue'])->toBeFalse()
        ->and($compliance['first_response']['percentage'])->toBeGreaterThan(0)
        ->and($compliance['resolution']['status'])->toBe('pending')
        ->and($compliance['resolution']['overdue'])->toBeFalse();
});

it('detects overdue tickets', function () {
    $service = new SlaService();

    $ticket = Ticket::factory()->create([
        'opened_at' => now()->subHours(5),
        'first_response_due_at' => now()->subHours(1),
        'resolution_due_at' => now()->addHours(1),
        'first_response_at' => null,
    ]);

    $compliance = $service->checkSlaCompliance($ticket);

    expect($compliance['first_response']['overdue'])->toBeTrue()
        ->and($compliance['resolution']['overdue'])->toBeFalse();
});

it('records SLA breach when conditions are met', function () {
    $service = new SlaService();

    $ticket = Ticket::factory()->create([
        'opened_at' => now()->subHours(5),
        'first_response_due_at' => now()->subHours(1),
        'first_response_at' => null,
        'sla_breached' => false,
    ]);

    $breached = $service->recordSlaBreachIfNeeded($ticket);

    expect($breached)->toBeTrue()
        ->and($ticket->fresh()->sla_breached)->toBeTrue()
        ->and($ticket->fresh()->sla_breach_type)->toBe('first_response');
});

it('does not record breach if already breached', function () {
    $service = new SlaService();

    $ticket = Ticket::factory()->create([
        'opened_at' => now()->subHours(5),
        'first_response_due_at' => now()->subHours(1),
        'first_response_at' => null,
        'sla_breached' => true,
        'sla_breach_type' => 'first_response',
    ]);

    $breached = $service->recordSlaBreachIfNeeded($ticket);

    expect($breached)->toBeFalse();
});