<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Services\TicketService;
use LucaLongo\LaravelHelpdesk\Tests\Fakes\Agent;

beforeEach(function () {
    config([
        'helpdesk.sla.enabled' => true,
        'helpdesk.sla.rules' => [
            TicketPriority::Normal->value => [
                'first_response' => 240,
                'resolution' => 1440,
            ],
        ],
    ]);
});

it('automatically calculates SLA dates when creating a ticket', function () {
    $service = app(TicketService::class);

    $ticket = $service->open([
        'subject' => 'Test SLA Ticket',
        'description' => 'Testing SLA calculation',
        'priority' => TicketPriority::Normal->value,
    ]);

    expect($ticket->first_response_due_at)
        ->toBeInstanceOf(Carbon::class)
        ->and($ticket->opened_at->diffInMinutes($ticket->first_response_due_at))
        ->toBeGreaterThanOrEqual(239.9)
        ->toBeLessThanOrEqual(240.1)
        ->and($ticket->opened_at->diffInMinutes($ticket->resolution_due_at))
        ->toBeGreaterThanOrEqual(1439.9)
        ->toBeLessThanOrEqual(1440.1);
});

it('marks first response when support comments', function () {
    $ticket = Ticket::factory()->create([
        'first_response_at' => null,
        'first_response_due_at' => now()->addHours(4),
    ]);

    expect($ticket->markFirstResponse())->toBeTrue()
        ->and($ticket->fresh()->first_response_at)->toBeInstanceOf(Carbon::class)
        ->and($ticket->fresh()->response_time_minutes)->toBeGreaterThanOrEqual(0);
});

it('does not mark first response twice', function () {
    $ticket = Ticket::factory()->create([
        'first_response_at' => now(),
        'first_response_due_at' => now()->addHours(4),
    ]);

    expect($ticket->markFirstResponse())->toBeFalse();
});

it('marks resolution when ticket is closed', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Closed,
        'closed_at' => now(),
        'resolution_due_at' => now()->addHours(24),
    ]);

    expect($ticket->markResolution())->toBeTrue()
        ->and($ticket->fresh()->resolution_time_minutes)->toBeGreaterThanOrEqual(0);
});

it('detects SLA breach on first response', function () {
    $ticket = Ticket::factory()->create([
        'opened_at' => now()->subHours(5),
        'first_response_at' => null,
        'first_response_due_at' => now()->subHours(1),
        'sla_breached' => false,
    ]);

    $ticket->markFirstResponse();

    expect($ticket->fresh()->sla_breached)->toBeTrue()
        ->and($ticket->fresh()->sla_breach_type)->toBe('first_response');
});

it('calculates SLA compliance percentage correctly', function () {
    $ticket = Ticket::factory()->create([
        'opened_at' => now()->subHours(2),
        'first_response_due_at' => now()->addHours(2),
        'first_response_at' => null,
    ]);

    $percentage = $ticket->getSlaCompliancePercentage('first_response');

    expect($percentage)
        ->toBeFloat()
        ->toBeGreaterThanOrEqual(49.9)
        ->toBeLessThanOrEqual(50.1);
});

it('returns null percentage when no SLA is set', function () {
    $ticket = Ticket::factory()->create([
        'first_response_due_at' => null,
    ]);

    expect($ticket->getSlaCompliancePercentage('first_response'))->toBeNull();
});

it('correctly identifies first response overdue', function () {
    $overdueTicket = Ticket::factory()->create([
        'first_response_at' => null,
        'first_response_due_at' => now()->subHours(1),
    ]);

    $onTimeTicket = Ticket::factory()->create([
        'first_response_at' => null,
        'first_response_due_at' => now()->addHours(1),
    ]);

    expect($overdueTicket->isFirstResponseOverdue())->toBeTrue()
        ->and($onTimeTicket->isFirstResponseOverdue())->toBeFalse();
});

it('correctly identifies resolution overdue', function () {
    $overdueTicket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'resolution_due_at' => now()->subHours(1),
    ]);

    $onTimeTicket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'resolution_due_at' => now()->addHours(1),
    ]);

    $closedTicket = Ticket::factory()->create([
        'status' => TicketStatus::Closed,
        'resolution_due_at' => now()->subHours(1),
    ]);

    expect($overdueTicket->isResolutionOverdue())->toBeTrue()
        ->and($onTimeTicket->isResolutionOverdue())->toBeFalse()
        ->and($closedTicket->isResolutionOverdue())->toBeFalse();
});