<?php

use LucaLongo\LaravelHelpdesk\Database\Factories\CategoryFactory;
use LucaLongo\LaravelHelpdesk\Database\Factories\TagFactory;
use LucaLongo\LaravelHelpdesk\Database\Factories\TicketCommentFactory;
use LucaLongo\LaravelHelpdesk\Database\Factories\TicketFactory;
use LucaLongo\LaravelHelpdesk\Models\Category;
use LucaLongo\LaravelHelpdesk\Models\Tag;
use LucaLongo\LaravelHelpdesk\Services\Automation\ConditionEvaluator;

it('evaluates combined category and tag conditions when relations are eager loaded', function () {
    /** @var Category $root */
    $root = CategoryFactory::new()->create();
    /** @var Category $child */
    $child = CategoryFactory::new()->withParent($root)->create();

    /** @var Tag $primaryTag */
    $primaryTag = TagFactory::new()->create();
    /** @var Tag $secondaryTag */
    $secondaryTag = TagFactory::new()->create();

    $ticket = TicketFactory::new()->create();
    $ticket->categories()->attach($child);
    $ticket->tags()->attach([$primaryTag->id, $secondaryTag->id]);

    $ticket->load(['categories', 'tags']);

    $conditions = [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'has_category',
                'value' => $root->id,
                'include_descendants' => true,
            ],
            [
                'type' => 'has_tag',
                'value' => [$primaryTag->id, $secondaryTag->id],
                'operator' => 'all',
            ],
        ],
    ];

    $result = (new ConditionEvaluator)->evaluate($conditions, $ticket);

    expect($result)->toBeTrue();
});

it('short-circuits tag rules based on operator semantics', function () {
    $tag = TagFactory::new()->create();

    $ticket = TicketFactory::new()->create();
    $ticket->tags()->attach($tag);

    $conditions = [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'has_tag',
                'value' => [$tag->id],
                'operator' => 'none',
            ],
        ],
    ];

    $result = (new ConditionEvaluator)->evaluate($conditions, $ticket->load('tags'));

    expect($result)->toBeFalse();
});

it('uses latest public comment timestamp when evaluating last activity rules', function () {
    $ticket = TicketFactory::new()->create();

    TicketCommentFactory::new()->for($ticket, 'ticket')->create([
        'is_internal' => false,
        'created_at' => now()->subMinutes(30),
    ]);

    $conditions = [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'time_since_last_update',
                'value' => 10,
                'operator' => 'greater_than',
            ],
            [
                'type' => 'comment_count',
                'value' => 1,
                'operator' => 'greater_or_equal',
            ],
        ],
    ];

    $result = (new ConditionEvaluator)->evaluate($conditions, $ticket->load('comments'));

    expect($result)->toBeTrue();
});
