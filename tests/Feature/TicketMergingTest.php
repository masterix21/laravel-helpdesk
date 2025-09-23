<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LucaLongo\LaravelHelpdesk\Enums\TicketRelationType;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Events\ChildTicketCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketMerged;
use LucaLongo\LaravelHelpdesk\Events\TicketRelationCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketRelationRemoved;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketComment;
use LucaLongo\LaravelHelpdesk\Models\TicketAttachment;
use LucaLongo\LaravelHelpdesk\Models\TicketRelation;
use LucaLongo\LaravelHelpdesk\Services\TicketService;
use LucaLongo\LaravelHelpdesk\Tests\Fakes\User;

beforeEach(function () {
    $this->ticketService = app(TicketService::class);
});

describe('ticket merging', function () {
    it('can merge one ticket into another', function () {
        Event::fake();

        $user = User::factory()->create();

        $target = Ticket::factory()->create(['subject' => 'Main ticket']);
        $target->fixTree(); // Fix tree structure for nestedset
        $source = Ticket::factory()->create(['subject' => 'Duplicate ticket']);
        $source->fixTree(); // Fix tree structure for nestedset

        // Add comments and attachments to source
        $comment = TicketComment::factory()->for($user, 'author')->create(['ticket_id' => $source->id]);
        $attachment = TicketAttachment::factory()->create(['ticket_id' => $source->id]);

        $result = $this->ticketService->mergeTickets($target, $source, 'Duplicate issue');

        expect($result->id)->toBe($target->id);

        // Check source ticket is marked as merged
        $source->refresh();
        expect($source->isMerged())->toBeTrue();
        expect($source->merged_to_id)->toBe($target->id);
        expect($source->merge_reason)->toBe('Duplicate issue');
        expect($source->status)->toBe(TicketStatus::Closed);

        // Check comments and attachments are transferred
        expect($comment->fresh()->ticket_id)->toBe($target->id);
        expect($attachment->fresh()->ticket_id)->toBe($target->id);

        Event::assertDispatched(TicketMerged::class, function ($event) use ($source, $target) {
            return $event->source->id === $source->id && $event->target->id === $target->id;
        });
    });

    it('can merge multiple tickets at once', function () {
        $target = Ticket::factory()->create();
        $sources = Ticket::factory()->count(3)->create();

        $result = $this->ticketService->mergeTickets($target, $sources->all());

        foreach ($sources as $source) {
            expect($source->fresh()->merged_to_id)->toBe($target->id);
        }
    });

    it('cannot merge already merged ticket', function () {
        $target = Ticket::factory()->create();
        $source = Ticket::factory()->create(['merged_to_id' => 999]);

        expect(fn() => $this->ticketService->mergeTickets($target, $source))
            ->toThrow(InvalidArgumentException::class);
    });

    it('moves child tickets when merging parent', function () {
        Event::fake();

        $target = Ticket::factory()->create();
        $source = Ticket::factory()->create();
        $child = Ticket::factory()->create();
        $child->appendToNode($source)->save();

        $this->ticketService->mergeTickets($target, $source);

        $child->refresh();
        expect($child->parent_id)->toBe($target->id);
    });
});

describe('ticket relations', function () {
    it('can create relation between tickets', function () {
        Event::fake();

        $ticket1 = Ticket::factory()->create();
        $ticket2 = Ticket::factory()->create();

        $relation = $this->ticketService->createRelation(
            $ticket1,
            $ticket2,
            TicketRelationType::Related,
            'These are related issues'
        );

        expect($relation)->toBeInstanceOf(TicketRelation::class);
        expect($relation->ticket_id)->toBe($ticket1->id);
        expect($relation->related_ticket_id)->toBe($ticket2->id);
        expect($relation->relation_type)->toBe(TicketRelationType::Related);
        expect($relation->notes)->toBe('These are related issues');

        Event::assertDispatched(TicketRelationCreated::class);
    });

    it('creates inverse relations for blocks/blocked_by', function () {
        $ticket1 = Ticket::factory()->create();
        $ticket2 = Ticket::factory()->create();

        $this->ticketService->createRelation($ticket1, $ticket2, TicketRelationType::Blocks);

        // Check inverse relation exists
        $inverse = TicketRelation::where('ticket_id', $ticket2->id)
            ->where('related_ticket_id', $ticket1->id)
            ->where('relation_type', TicketRelationType::BlockedBy->value)
            ->first();

        expect($inverse)->not->toBeNull();
    });

    it('prevents duplicate relations', function () {
        $ticket1 = Ticket::factory()->create();
        $ticket2 = Ticket::factory()->create();

        $this->ticketService->createRelation($ticket1, $ticket2, TicketRelationType::Related);

        expect(fn() => $this->ticketService->createRelation($ticket1, $ticket2, TicketRelationType::Related))
            ->toThrow(InvalidArgumentException::class);
    });

    it('can remove relation between tickets', function () {
        Event::fake();

        $ticket1 = Ticket::factory()->create();
        $ticket2 = Ticket::factory()->create();

        $this->ticketService->createRelation($ticket1, $ticket2, TicketRelationType::Blocks);
        $this->ticketService->removeRelation($ticket1, $ticket2, TicketRelationType::Blocks);

        expect(TicketRelation::where('ticket_id', $ticket1->id)
            ->where('related_ticket_id', $ticket2->id)
            ->exists())->toBeFalse();

        expect(TicketRelation::where('ticket_id', $ticket2->id)
            ->where('related_ticket_id', $ticket1->id)
            ->exists())->toBeFalse();

        Event::assertDispatched(TicketRelationRemoved::class);
    });
});

describe('parent-child hierarchy', function () {
    it('can create child ticket', function () {
        Event::fake();

        $parent = Ticket::factory()->create(['subject' => 'Parent task']);

        $child = $this->ticketService->createChildTicket($parent, [
            'type' => 'product_support',
            'subject' => 'Subtask',
            'description' => 'Child task description',
        ]);

        expect($child->parent_id)->toBe($parent->id);
        expect($child->isChild())->toBeTrue();

        $parent->refresh();
        expect($parent->children()->exists())->toBeTrue();

        Event::assertDispatched(ChildTicketCreated::class);
    });

    it('can move ticket to different parent', function () {
        $parent1 = Ticket::factory()->create();
        $parent2 = Ticket::factory()->create();
        $child = Ticket::factory()->create();

        $child->appendToNode($parent1)->save();
        expect($child->parent_id)->toBe($parent1->id);

        $this->ticketService->moveToParent($child, $parent2);
        expect($child->fresh()->parent_id)->toBe($parent2->id);
    });

    it('can make ticket root by removing parent', function () {
        $parent = Ticket::factory()->create();
        $child = Ticket::factory()->create();
        $child->appendToNode($parent)->save();

        $this->ticketService->moveToParent($child, null);
        expect($child->fresh()->parent_id)->toBeNull();
        expect($child->fresh()->isRoot())->toBeTrue();
    });

    it('can get all descendants using nestedset', function () {
        $root = Ticket::factory()->create();
        $child1 = Ticket::factory()->create();
        $child2 = Ticket::factory()->create();
        $grandchild = Ticket::factory()->create();

        $child1->appendToNode($root)->save();
        $child2->appendToNode($root)->save();
        $grandchild->appendToNode($child1)->save();

        $descendants = $root->descendants()->get();

        expect($descendants)->toHaveCount(3);
        expect($descendants->pluck('id')->toArray())
            ->toContain($child1->id, $child2->id, $grandchild->id);
    });
});