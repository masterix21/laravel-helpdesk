<?php

use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Models\Category;
use LucaLongo\LaravelHelpdesk\Models\Tag;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Services\BulkActionService;
use LucaLongo\LaravelHelpdesk\Tests\Fakes\User;

it('can change status for multiple tickets', function () {
    $tickets = Ticket::factory()->count(3)->create([
        'status' => TicketStatus::Open,
    ]);

    $service = app(BulkActionService::class);
    $results = $service->applyAction('change_status', $tickets->pluck('id')->toArray(), [
        'status' => 'in_progress',
    ]);

    expect($results['success'])->toBe(3)
        ->and($results['failed'])->toBe(0);

    foreach ($tickets as $ticket) {
        expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
    }
});

it('can change priority for multiple tickets', function () {
    $tickets = Ticket::factory()->count(2)->create([
        'priority' => TicketPriority::Normal,
    ]);

    $service = app(BulkActionService::class);
    $results = $service->applyAction('change_priority', $tickets->pluck('id')->toArray(), [
        'priority' => 'high',
    ]);

    expect($results['success'])->toBe(2);

    foreach ($tickets as $ticket) {
        expect($ticket->fresh()->priority)->toBe(TicketPriority::High);
    }
});

it('can assign tickets to a user', function () {
    $user = User::factory()->create();
    $tickets = Ticket::factory()->count(2)->create();

    $service = app(BulkActionService::class);
    $results = $service->applyAction('assign', $tickets->pluck('id')->toArray(), [
        'assignee_id' => $user->id,
        'assignee_type' => get_class($user),
    ]);

    expect($results['success'])->toBe(2);

    foreach ($tickets as $ticket) {
        $ticket->refresh();
        expect($ticket->assignee)->toBeInstanceOf(User::class)
            ->and($ticket->assignee->id)->toBe($user->id);
    }
});

it('can unassign tickets', function () {
    $user = User::factory()->create();
    $tickets = Ticket::factory()->count(2)->create();

    foreach ($tickets as $ticket) {
        $ticket->assignTo($user);
    }

    $service = app(BulkActionService::class);
    $results = $service->applyAction('unassign', $tickets->pluck('id')->toArray());

    expect($results['success'])->toBe(2);

    foreach ($tickets as $ticket) {
        expect($ticket->fresh()->assignee)->toBeNull();
    }
});

it('can add tags to multiple tickets', function () {
    $tag1 = Tag::factory()->create();
    $tag2 = Tag::factory()->create();
    $tickets = Ticket::factory()->count(2)->create();

    $service = app(BulkActionService::class);
    $results = $service->applyAction('add_tags', $tickets->pluck('id')->toArray(), [
        'tag_ids' => [$tag1->id, $tag2->id],
    ]);

    expect($results['success'])->toBe(2);

    foreach ($tickets as $ticket) {
        expect($ticket->tags)->toHaveCount(2);
    }
});

it('can remove tags from multiple tickets', function () {
    $tag1 = Tag::factory()->create();
    $tag2 = Tag::factory()->create();
    $tickets = Ticket::factory()->count(2)->create();

    foreach ($tickets as $ticket) {
        $ticket->tags()->attach([$tag1->id, $tag2->id]);
    }

    $service = app(BulkActionService::class);
    $results = $service->applyAction('remove_tags', $tickets->pluck('id')->toArray(), [
        'tag_ids' => [$tag1->id],
    ]);

    expect($results['success'])->toBe(2);

    foreach ($tickets as $ticket) {
        $ticket->refresh();
        expect($ticket->tags)->toHaveCount(1)
            ->and($ticket->tags->first()->id)->toBe($tag2->id);
    }
});

it('can add category to multiple tickets', function () {
    $category = Category::factory()->create();
    $tickets = Ticket::factory()->count(2)->create();

    $service = app(BulkActionService::class);
    $results = $service->applyAction('add_category', $tickets->pluck('id')->toArray(), [
        'category_id' => $category->id,
    ]);

    expect($results['success'])->toBe(2);

    foreach ($tickets as $ticket) {
        expect($ticket->categories)->toHaveCount(1)
            ->and($ticket->categories->first()->id)->toBe($category->id);
    }
});

it('can close multiple tickets', function () {
    $tickets = Ticket::factory()->count(3)->create([
        'status' => TicketStatus::Resolved,
    ]);

    $service = app(BulkActionService::class);
    $results = $service->applyAction('close', $tickets->pluck('id')->toArray(), [
        'add_comment' => true,
        'comment' => 'Bulk closed',
    ]);

    expect($results['success'])->toBe(3);

    foreach ($tickets as $ticket) {
        $ticket->refresh();
        expect($ticket->status)->toBe(TicketStatus::Closed)
            ->and($ticket->comments)->toHaveCount(1)
            ->and($ticket->comments->first()->body)->toBe('Bulk closed');
    }
});

it('can filter tickets for bulk actions', function () {
    $highPriority = Ticket::factory()->count(2)->create([
        'priority' => TicketPriority::High,
    ]);

    $normalPriority = Ticket::factory()->count(3)->create([
        'priority' => TicketPriority::Normal,
    ]);

    $service = app(BulkActionService::class);
    $query = $service->buildFilterQuery([
        'priority' => ['high'],
    ]);

    expect($query->count())->toBe(2);

    $results = $service->applyActionWithFilter('change_status', $query, [
        'status' => 'in_progress',
    ]);

    expect($results['success'])->toBe(2);

    foreach ($highPriority as $ticket) {
        expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
    }

    foreach ($normalPriority as $ticket) {
        expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
    }
});

it('can filter tickets by categories', function () {
    $category1 = Category::factory()->create();
    $category2 = Category::factory()->create();

    $ticketsWithCategory1 = Ticket::factory()->count(2)->create();
    foreach ($ticketsWithCategory1 as $ticket) {
        $ticket->categories()->attach($category1);
    }

    $ticketsWithCategory2 = Ticket::factory()->count(1)->create();
    foreach ($ticketsWithCategory2 as $ticket) {
        $ticket->categories()->attach($category2);
    }

    $service = app(BulkActionService::class);
    $query = $service->buildFilterQuery([
        'category_ids' => [$category1->id],
    ]);

    expect($query->count())->toBe(2);
});

it('can filter tickets by tags', function () {
    $tag1 = Tag::factory()->create();
    $tag2 = Tag::factory()->create();

    $ticketsWithTag = Ticket::factory()->count(2)->create();
    foreach ($ticketsWithTag as $ticket) {
        $ticket->tags()->attach($tag1);
    }

    $ticketsWithoutTag = Ticket::factory()->count(1)->create();

    $service = app(BulkActionService::class);
    $query = $service->buildFilterQuery([
        'tag_ids' => [$tag1->id],
    ]);

    expect($query->count())->toBe(2);
});

it('handles errors gracefully', function () {
    $tickets = Ticket::factory()->count(2)->create();

    $service = app(BulkActionService::class);
    $results = $service->applyAction('change_status', $tickets->pluck('id')->toArray(), [
        // Missing required 'status' parameter
    ]);

    expect($results['success'])->toBe(0)
        ->and($results['failed'])->toBe(2)
        ->and($results['details'])->toHaveCount(2);

    foreach ($results['details'] as $detail) {
        expect($detail['status'])->toBe('failed')
            ->and($detail)->toHaveKey('error');
    }
});

it('validates allowed actions', function () {
    $service = app(BulkActionService::class);

    expect(fn () => $service->applyAction('invalid_action', [], []))
        ->toThrow(\InvalidArgumentException::class, 'Action invalid_action is not allowed');
});
