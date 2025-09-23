<?php

use Illuminate\Support\Facades\Event;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Events\TicketAssigned;
use LucaLongo\LaravelHelpdesk\Events\TicketCommentAdded;
use LucaLongo\LaravelHelpdesk\Events\TicketCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketStatusChanged;
use LucaLongo\LaravelHelpdesk\Events\TicketSubscriptionCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketSubscriptionTriggered;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketSubscription;
use LucaLongo\LaravelHelpdesk\Services\CommentService;
use LucaLongo\LaravelHelpdesk\Services\SubscriptionService;
use LucaLongo\LaravelHelpdesk\Services\TicketService;
use LucaLongo\LaravelHelpdesk\Tests\Fakes\Agent;

it('opens a ticket with configured defaults', function (): void {
    Event::fake();

    $service = app(TicketService::class);

    $ticket = $service->open([
        'subject' => 'Richiesta preventivo',
        'description' => 'Serve un’offerta aggiornata',
        'type' => TicketType::Commercial->value,
    ]);

    expect($ticket->ulid)->toBeString()
        ->and($ticket->ulid)->not->toBe('')
        ->and($ticket->type)->toBe(TicketType::Commercial)
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->meta)->toBeInstanceOf(ArrayObject::class);

    Event::assertDispatched(TicketCreated::class);
});

it('transitions status and fires event only on valid changes', function (): void {
    Event::fake([
        TicketStatusChanged::class,
    ]);

    $ticket = Ticket::factory()->create();

    $service = app(TicketService::class);

    $service->transition($ticket, TicketStatus::Resolved);

    Event::assertDispatched(TicketStatusChanged::class, 1);
    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved);

    expect(fn() => $service->transition($ticket->fresh(), TicketStatus::Resolved))
        ->toThrow(\LucaLongo\LaravelHelpdesk\Exceptions\InvalidTransitionException::class);

    Event::assertDispatchedTimes(TicketStatusChanged::class, 1);
});

it('assigns and prevents duplicate assignment events', function (): void {
    Event::fake([
        TicketAssigned::class,
    ]);

    $agent = Agent::query()->create(['name' => 'Mario']);
    $ticket = Ticket::factory()->create();
    $service = app(TicketService::class);

    $service->assign($ticket, $agent);

    Event::assertDispatched(TicketAssigned::class, 1);
    expect($ticket->fresh()->assignee?->is($agent))->toBeTrue();

    $service->assign($ticket->fresh(), $agent);

    Event::assertDispatchedTimes(TicketAssigned::class, 1);
});

it('stores comments with metadata', function (): void {
    Event::fake([
        TicketCommentAdded::class,
    ]);

    $ticket = Ticket::factory()->create();
    $service = app(CommentService::class);

    $comment = $service->addComment($ticket, 'Risposta veloce', null, ['visibility' => 'internal']);

    expect($comment->meta)->toBeInstanceOf(ArrayObject::class)
        ->and($comment->meta->getArrayCopy())
        ->toHaveKey('visibility', 'internal');

    Event::assertDispatched(TicketCommentAdded::class);
});

it('subscribes to all status updates and triggers notifications', function (): void {
    Event::fake([
        TicketSubscriptionCreated::class,
        TicketSubscriptionTriggered::class,
    ]);

    $ticket = Ticket::factory()->create();
    $subscriber = Agent::query()->create(['name' => 'Laura']);

    $subscriptionService = app(SubscriptionService::class);

    $subscription = $subscriptionService->subscribe($ticket, $subscriber);

    expect($subscription)->toBeInstanceOf(TicketSubscription::class)
        ->and($subscription->notify_on)->toBeNull();

    Event::assertDispatched(TicketSubscriptionCreated::class);

    $ticketService = app(TicketService::class);
    $ticketService->transition($ticket, TicketStatus::InProgress);

    Event::assertDispatched(TicketSubscriptionTriggered::class, function (TicketSubscriptionTriggered $event) use ($subscriber, $ticket): bool {
        return $event->ticket->is($ticket->fresh())
            && $event->status === TicketStatus::InProgress
            && $event->subscriptions->firstWhere('subscriber_id', $subscriber->getKey()) !== null;
    });
});

it('subscribes to a specific status and skips unmatched updates', function (): void {
    Event::fake([
        TicketSubscriptionTriggered::class,
    ]);

    $ticket = Ticket::factory()->create();
    $subscriber = Agent::query()->create(['name' => 'Chiara']);
    $ticketService = app(TicketService::class);
    $subscriptionService = app(SubscriptionService::class);

    $subscriptionService->subscribe($ticket, $subscriber, TicketStatus::Resolved);

    $ticketService->transition($ticket, TicketStatus::InProgress);

    Event::assertNotDispatched(TicketSubscriptionTriggered::class);

    $ticketService->transition($ticket->fresh(), TicketStatus::Resolved);

    Event::assertDispatched(TicketSubscriptionTriggered::class, function (TicketSubscriptionTriggered $event): bool {
        return $event->status === TicketStatus::Resolved
            && $event->subscriptions->count() === 1;
    });
});

it('prevents duplicate subscriptions and allows unsubscribe', function (): void {
    $ticket = Ticket::factory()->create();
    $subscriber = Agent::query()->create(['name' => 'Giulia']);
    $service = app(SubscriptionService::class);

    $first = $service->subscribe($ticket, $subscriber);
    $second = $service->subscribe($ticket, $subscriber);

    expect($first->is($second))->toBeTrue()
        ->and($ticket->subscriptions()->count())->toBe(1);

    $removed = $service->unsubscribe($ticket, $subscriber);

    expect($removed)->toBeTrue()
        ->and($ticket->subscriptions()->count())->toBe(0);
});
