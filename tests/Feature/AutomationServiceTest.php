<?php

use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Models\AutomationRule;
use LucaLongo\LaravelHelpdesk\Models\Category;
use LucaLongo\LaravelHelpdesk\Models\Tag;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Services\AutomationService;
use LucaLongo\LaravelHelpdesk\Tests\Fakes\User;

it('can create automation rules', function () {
    $service = app(AutomationService::class);

    $rule = $service->createRule([
        'name' => 'Test Rule',
        'description' => 'A test automation rule',
        'trigger' => 'ticket_created',
        'conditions' => [
            'operator' => 'and',
            'rules' => [
                ['type' => 'ticket_priority', 'operator' => 'equals', 'value' => 'high'],
            ],
        ],
        'actions' => [
            ['type' => 'change_status', 'status' => 'in_progress'],
        ],
        'priority' => 100,
    ]);

    expect($rule)->toBeInstanceOf(AutomationRule::class)
        ->and($rule->name)->toBe('Test Rule')
        ->and($rule->trigger)->toBe('ticket_created')
        ->and($rule->priority)->toBe(100);
});

it('evaluates conditions correctly', function () {
    $ticket = Ticket::factory()->create([
        'priority' => TicketPriority::High,
        'type' => TicketType::ProductSupport,
    ]);

    $rule = AutomationRule::create([
        'name' => 'High Priority Rule',
        'trigger' => 'manual',
        'conditions' => [
            'operator' => 'and',
            'rules' => [
                ['type' => 'ticket_priority', 'operator' => 'equals', 'value' => 'high'],
                ['type' => 'ticket_type', 'operator' => 'equals', 'value' => 'product_support'],
            ],
        ],
        'actions' => [],
        'is_active' => true,
    ]);

    expect($rule->evaluate($ticket))->toBeTrue();

    $ticket->priority = TicketPriority::Low;
    $ticket->save();

    expect($rule->evaluate($ticket))->toBeFalse();
});

it('executes actions on tickets', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Normal,
    ]);

    $rule = AutomationRule::create([
        'name' => 'Action Test Rule',
        'trigger' => 'manual',
        'conditions' => [],
        'actions' => [
            ['type' => 'change_priority', 'priority' => 'high'],
            ['type' => 'assign_to_user', 'user_id' => $user->id],
        ],
        'is_active' => true,
    ]);

    $result = $rule->execute($ticket);
    $ticket->refresh();

    expect($result)->toBeTrue()
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->assignee)->toBeInstanceOf(User::class)
        ->and($ticket->assignee->id)->toBe($user->id);
});

it('processes tickets with automation rules', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::High,
    ]);

    AutomationRule::create([
        'name' => 'Process Rule 1',
        'trigger' => 'manual',
        'conditions' => [
            'operator' => 'and',
            'rules' => [
                ['type' => 'ticket_priority', 'operator' => 'equals', 'value' => 'high'],
            ],
        ],
        'actions' => [
            ['type' => 'change_status', 'status' => 'in_progress'],
        ],
        'priority' => 100,
        'is_active' => true,
    ]);

    AutomationRule::create([
        'name' => 'Process Rule 2',
        'trigger' => 'manual',
        'conditions' => [],
        'actions' => [
            ['type' => 'add_internal_note', 'note' => 'Processed by automation'],
        ],
        'priority' => 50,
        'is_active' => true,
    ]);

    $service = app(AutomationService::class);
    $results = $service->processTicket($ticket, 'manual');

    $ticket->refresh();

    expect($results['executed'])->toHaveCount(2)
        ->and($results['failed'])->toBeEmpty()
        ->and($ticket->status)->toBe(TicketStatus::InProgress)
        ->and($ticket->comments)->toHaveCount(1)
        ->and($ticket->comments->first()->body)->toContain('Processed by automation');
});

it('respects stop_processing flag', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::High]);

    AutomationRule::create([
        'name' => 'Stop Rule',
        'trigger' => 'manual',
        'conditions' => [],
        'actions' => [
            ['type' => 'change_priority', 'priority' => 'urgent'],
        ],
        'priority' => 100,
        'is_active' => true,
        'stop_processing' => true,
    ]);

    AutomationRule::create([
        'name' => 'Should Not Execute',
        'trigger' => 'manual',
        'conditions' => [],
        'actions' => [
            ['type' => 'change_priority', 'priority' => 'low'],
        ],
        'priority' => 50,
        'is_active' => true,
    ]);

    $service = app(AutomationService::class);
    $results = $service->processTicket($ticket, 'manual');

    $ticket->refresh();

    expect($results['executed'])->toHaveCount(1)
        ->and($ticket->priority)->toBe(TicketPriority::Urgent);
});

it('evaluates category conditions with descendants', function () {
    $parentCategory = Category::factory()->create(['name' => 'Hardware']);
    $childCategory = Category::factory()->create([
        'name' => 'Laptops',
        'parent_id' => $parentCategory->id,
    ]);

    $ticket = Ticket::factory()->create();
    $ticket->categories()->attach($childCategory);

    $rule = AutomationRule::create([
        'name' => 'Category Rule',
        'trigger' => 'manual',
        'conditions' => [
            'operator' => 'and',
            'rules' => [
                [
                    'type' => 'has_category',
                    'value' => $parentCategory->id,
                    'include_descendants' => true,
                ],
            ],
        ],
        'actions' => [],
        'is_active' => true,
    ]);

    expect($rule->evaluate($ticket))->toBeTrue();
});

it('evaluates tag conditions', function () {
    $tag1 = Tag::factory()->create(['name' => 'urgent']);
    $tag2 = Tag::factory()->create(['name' => 'bug']);
    $tag3 = Tag::factory()->create(['name' => 'feature']);

    $ticket = Ticket::factory()->create();
    $ticket->tags()->attach([$tag1->id, $tag2->id]);

    $ruleAny = AutomationRule::create([
        'name' => 'Tag Any Rule',
        'trigger' => 'manual',
        'conditions' => [
            'operator' => 'and',
            'rules' => [
                [
                    'type' => 'has_tag',
                    'value' => [$tag1->id, $tag3->id],
                    'operator' => 'any',
                ],
            ],
        ],
        'actions' => [],
        'is_active' => true,
    ]);

    $ruleAll = AutomationRule::create([
        'name' => 'Tag All Rule',
        'trigger' => 'manual',
        'conditions' => [
            'operator' => 'and',
            'rules' => [
                [
                    'type' => 'has_tag',
                    'value' => [$tag1->id, $tag2->id],
                    'operator' => 'all',
                ],
            ],
        ],
        'actions' => [],
        'is_active' => true,
    ]);

    $ruleNone = AutomationRule::create([
        'name' => 'Tag None Rule',
        'trigger' => 'manual',
        'conditions' => [
            'operator' => 'and',
            'rules' => [
                [
                    'type' => 'has_tag',
                    'value' => [$tag3->id],
                    'operator' => 'none',
                ],
            ],
        ],
        'actions' => [],
        'is_active' => true,
    ]);

    expect($ruleAny->evaluate($ticket))->toBeTrue()
        ->and($ruleAll->evaluate($ticket))->toBeTrue()
        ->and($ruleNone->evaluate($ticket))->toBeTrue();
});

it('can apply templates', function () {
    $service = app(AutomationService::class);

    $rule = $service->applyTemplate('auto_close_resolved', [
        'priority' => 200,
    ]);

    expect($rule)->toBeInstanceOf(AutomationRule::class)
        ->and($rule->name)->toBe('Auto-close Resolved Tickets')
        ->and($rule->trigger)->toBe('time_based')
        ->and($rule->priority)->toBe(200);
});

it('tracks execution history', function () {
    $ticket = Ticket::factory()->create();

    $rule = AutomationRule::create([
        'name' => 'History Rule',
        'trigger' => 'manual',
        'conditions' => [],
        'actions' => [
            ['type' => 'change_priority', 'priority' => 'high'],
        ],
        'is_active' => true,
    ]);

    $rule->execute($ticket);

    expect($rule->executions)->toHaveCount(1)
        ->and($rule->executions->first()->ticket_id)->toBe($ticket->id)
        ->and($rule->executions->first()->success)->toBeTrue();
});
