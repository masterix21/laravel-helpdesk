<?php

use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

it('filters tickets approaching SLA deadline', function () {
    // Ticket approaching SLA (due in 30 minutes)
    $approachingTicket = Ticket::factory()->create([
        'first_response_at' => null,
        'first_response_due_at' => now()->addMinutes(30),
        'sla_breached' => false,
    ]);

    // Ticket not approaching SLA (due in 2 hours)
    $notApproachingTicket = Ticket::factory()->create([
        'first_response_at' => null,
        'first_response_due_at' => now()->addHours(2),
        'sla_breached' => false,
    ]);

    // Ticket already responded
    $respondedTicket = Ticket::factory()->create([
        'first_response_at' => now(),
        'first_response_due_at' => now()->addMinutes(30),
        'sla_breached' => false,
    ]);

    $results = Ticket::query()->approachingSla(60)->pluck('id');

    expect($results)->toContain($approachingTicket->id)
        ->and($results)->not->toContain($notApproachingTicket->id)
        ->and($results)->not->toContain($respondedTicket->id);
});

it('filters overdue SLA tickets', function () {
    // Overdue first response
    $overdueFirstResponse = Ticket::factory()->create([
        'first_response_at' => null,
        'first_response_due_at' => now()->subHours(1),
        'sla_breached' => false,
    ]);

    // Overdue resolution
    $overdueResolution = Ticket::factory()->create([
        'first_response_at' => now()->subHours(2),
        'first_response_due_at' => now()->subHours(3),
        'closed_at' => null,
        'resolution_due_at' => now()->subHours(1),
        'sla_breached' => false,
    ]);

    // Not overdue
    $notOverdue = Ticket::factory()->create([
        'first_response_at' => null,
        'first_response_due_at' => now()->addHours(1),
        'sla_breached' => false,
    ]);

    // Already breached (should not be included)
    $alreadyBreached = Ticket::factory()->create([
        'first_response_at' => null,
        'first_response_due_at' => now()->subHours(1),
        'sla_breached' => true,
    ]);

    $results = Ticket::query()->overdueSla()->pluck('id');

    expect($results)->toContain($overdueFirstResponse->id)
        ->and($results)->toContain($overdueResolution->id)
        ->and($results)->not->toContain($notOverdue->id)
        ->and($results)->not->toContain($alreadyBreached->id);
});

it('filters breached SLA tickets', function () {
    $breachedTicket = Ticket::factory()->create([
        'sla_breached' => true,
        'sla_breach_type' => 'first_response',
    ]);

    $notBreachedTicket = Ticket::factory()->create([
        'sla_breached' => false,
    ]);

    $results = Ticket::query()->breachedSla()->pluck('id');

    expect($results)->toContain($breachedTicket->id)
        ->and($results)->not->toContain($notBreachedTicket->id);
});

it('filters tickets within SLA', function () {
    // Within SLA - no due date set
    $noDueDate = Ticket::factory()->create([
        'first_response_due_at' => null,
        'resolution_due_at' => null,
        'sla_breached' => false,
    ]);

    // Within SLA - already responded
    $responded = Ticket::factory()->create([
        'first_response_at' => now(),
        'first_response_due_at' => now()->addHours(1),
        'sla_breached' => false,
    ]);

    // Within SLA - not yet due
    $notDue = Ticket::factory()->create([
        'first_response_at' => null,
        'first_response_due_at' => now()->addHours(1),
        'sla_breached' => false,
    ]);

    // Overdue but not breached yet
    $overdue = Ticket::factory()->create([
        'first_response_at' => null,
        'first_response_due_at' => now()->subHours(1),
        'sla_breached' => false,
    ]);

    // Already breached
    $breached = Ticket::factory()->create([
        'sla_breached' => true,
    ]);

    $results = Ticket::query()->withinSla()->pluck('id');

    expect($results)->toContain($noDueDate->id)
        ->and($results)->toContain($responded->id)
        ->and($results)->toContain($notDue->id)
        ->and($results)->not->toContain($overdue->id)
        ->and($results)->not->toContain($breached->id);
});

it('combines SLA scopes with other scopes', function () {
    // Open ticket with Normal priority approaching SLA
    $openNormalApproaching = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Normal,
        'first_response_at' => null,
        'first_response_due_at' => now()->addMinutes(30),
        'sla_breached' => false,
    ]);

    // Closed ticket approaching SLA
    $closedApproaching = Ticket::factory()->create([
        'status' => TicketStatus::Closed,
        'first_response_at' => null,
        'first_response_due_at' => now()->addMinutes(30),
        'sla_breached' => false,
    ]);

    // Open ticket with Urgent priority approaching SLA
    $urgentApproaching = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Urgent,
        'first_response_at' => null,
        'first_response_due_at' => now()->addMinutes(30),
        'sla_breached' => false,
    ]);

    $openResults = Ticket::query()->open()->approachingSla(60)->pluck('id');
    $urgentResults = Ticket::query()->withPriority(TicketPriority::Urgent)->approachingSla(60)->pluck('id');

    expect($openResults)->toContain($openNormalApproaching->id)
        ->and($openResults)->toContain($urgentApproaching->id)
        ->and($openResults)->not->toContain($closedApproaching->id)
        ->and($urgentResults)->toContain($urgentApproaching->id)
        ->and($urgentResults)->not->toContain($openNormalApproaching->id);
});
