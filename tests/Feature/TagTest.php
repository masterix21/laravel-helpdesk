<?php

use Illuminate\Support\Facades\Event;
use LucaLongo\LaravelHelpdesk\Events\TagCreated;
use LucaLongo\LaravelHelpdesk\Events\TagDeleted;
use LucaLongo\LaravelHelpdesk\Events\TagUpdated;
use LucaLongo\LaravelHelpdesk\Models\Tag;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Services\TagService;

beforeEach(function () {
    $this->tagService = new TagService;
});

it('can create a tag', function () {
    Event::fake();

    $tag = $this->tagService->create([
        'name' => 'urgent',
        'description' => 'Urgent issues',
        'color' => '#ff0000',
        'is_active' => true,
    ]);

    expect($tag)->toBeInstanceOf(Tag::class)
        ->name->toBe('urgent')
        ->slug->toBe('urgent')
        ->description->toBe('Urgent issues')
        ->color->toBe('#ff0000')
        ->is_active->toBe(true);

    Event::assertDispatched(TagCreated::class);
});

it('can update a tag', function () {
    Event::fake();

    $tag = Tag::factory()->create(['name' => 'old-name']);

    $updated = $this->tagService->update($tag, [
        'name' => 'new-name',
        'color' => '#00ff00',
    ]);

    expect($updated->name)->toBe('new-name')
        ->and($updated->color)->toBe('#00ff00');

    Event::assertDispatched(TagUpdated::class);
});

it('can delete a tag', function () {
    Event::fake();

    $tag = Tag::factory()->create();

    $result = $this->tagService->delete($tag);

    expect($result)->toBeTrue()
        ->and(Tag::find($tag->id))->toBeNull();

    Event::assertDispatched(TagDeleted::class);
});

it('can find or create tag by name', function () {
    $tag1 = $this->tagService->findOrCreateByName('bug');
    $tag2 = $this->tagService->findOrCreateByName('bug');
    $tag3 = $this->tagService->findOrCreateByName('feature');

    expect($tag1->id)->toBe($tag2->id)
        ->and($tag1->slug)->toBe('bug')
        ->and($tag3->slug)->toBe('feature')
        ->and(Tag::count())->toBe(2);
});

it('can attach tags to ticket', function () {
    $ticket = Ticket::factory()->create();
    $tag1 = Tag::factory()->create(['name' => 'bug']);
    $tag2 = Tag::factory()->create(['name' => 'urgent']);

    $this->tagService->attachTagsToTicket($ticket, [
        $tag1->id,
        'new-tag',
        $tag2,
    ]);

    expect($ticket->tags)->toHaveCount(3)
        ->and($ticket->tags->pluck('name')->toArray())
        ->toContain('bug', 'urgent', 'new-tag');
});

it('can add single tag to ticket', function () {
    $ticket = Ticket::factory()->create();
    $tag1 = Tag::factory()->create();

    $ticket->tags()->attach($tag1);
    $this->tagService->addTagToTicket($ticket, 'additional-tag');

    expect($ticket->fresh()->tags)->toHaveCount(2);
});

it('can remove tag from ticket', function () {
    $ticket = Ticket::factory()->create();
    $tag1 = Tag::factory()->create(['name' => 'keep']);
    $tag2 = Tag::factory()->create(['name' => 'remove']);

    $ticket->tags()->attach([$tag1->id, $tag2->id]);

    $this->tagService->removeTagFromTicket($ticket, $tag2);

    expect($ticket->fresh()->tags)->toHaveCount(1)
        ->and($ticket->fresh()->tags->first()->id)->toBe($tag1->id);
});

it('can get popular tags', function () {
    $popularTag = Tag::factory()->create(['name' => 'popular']);
    $unpopularTag = Tag::factory()->create(['name' => 'unpopular']);

    Ticket::factory(5)->create()->each(fn ($ticket) => $ticket->tags()->attach($popularTag));
    Ticket::factory(1)->create()->each(fn ($ticket) => $ticket->tags()->attach($unpopularTag));

    $popular = $this->tagService->getPopularTags(1);

    expect($popular)->toHaveCount(1)
        ->and($popular->first()->id)->toBe($popularTag->id);
});

it('can get unused tags', function () {
    $usedTag = Tag::factory()->create();
    $unusedTag = Tag::factory()->create();

    $ticket = Ticket::factory()->create();
    $ticket->tags()->attach($usedTag);

    $unused = $this->tagService->getUnusedTags();

    expect($unused)->toHaveCount(1)
        ->and($unused->first()->id)->toBe($unusedTag->id);
});

it('can search tags', function () {
    Tag::factory()->active()->create(['name' => 'bug']);
    Tag::factory()->active()->create(['name' => 'feature']);
    Tag::factory()->active()->create(['name' => 'bugfix']);

    $results = $this->tagService->searchTags('bug');

    expect($results)->toHaveCount(2);
});

it('can get suggested tags based on similar tickets', function () {
    $ticket1 = Ticket::factory()->create(['type' => \LucaLongo\LaravelHelpdesk\Enums\TicketType::ProductSupport]);
    $ticket2 = Ticket::factory()->create(['type' => \LucaLongo\LaravelHelpdesk\Enums\TicketType::ProductSupport]);

    $commonTag = Tag::factory()->create(['name' => 'common']);
    $suggestedTag = Tag::factory()->create(['name' => 'suggested']);

    $ticket1->tags()->attach($commonTag);
    $ticket2->tags()->attach([$commonTag->id, $suggestedTag->id]);

    $suggestions = $this->tagService->getSuggestedTags($ticket1);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions->first()->id)->toBe($suggestedTag->id);
});

it('can merge tags', function () {
    $source = Tag::factory()->create(['name' => 'bug']);
    $target = Tag::factory()->create(['name' => 'defect']);

    $ticket1 = Ticket::factory()->create();
    $ticket2 = Ticket::factory()->create();

    $ticket1->tags()->attach($source);
    $ticket2->tags()->attach([$source->id, $target->id]);

    $this->tagService->mergeTags($source, $target);

    expect(Tag::find($source->id))->toBeNull()
        ->and($ticket1->fresh()->tags)->toHaveCount(1)
        ->and($ticket1->fresh()->tags->first()->id)->toBe($target->id)
        ->and($ticket2->fresh()->tags)->toHaveCount(1);
});

it('can cleanup unused tags', function () {
    $usedTag = Tag::factory()->create();
    Tag::factory(3)->create();

    $ticket = Ticket::factory()->create();
    $ticket->tags()->attach($usedTag);

    $count = $this->tagService->cleanupUnusedTags();

    expect($count)->toBe(3)
        ->and(Tag::count())->toBe(1);
});

it('can generate tag cloud', function () {
    $tag1 = Tag::factory()->active()->create();
    $tag2 = Tag::factory()->active()->create();
    $tag3 = Tag::factory()->active()->create();

    Ticket::factory(10)->create()->each(fn ($ticket) => $ticket->tags()->attach($tag1));
    Ticket::factory(5)->create()->each(fn ($ticket) => $ticket->tags()->attach($tag2));
    Ticket::factory(1)->create()->each(fn ($ticket) => $ticket->tags()->attach($tag3));

    $cloud = $this->tagService->getTagCloud();

    expect($cloud)->toHaveCount(3)
        ->and($cloud->firstWhere('id', $tag1->id)->cloud_size)->toBeGreaterThan(
            $cloud->firstWhere('id', $tag2->id)->cloud_size
        );
});

it('can filter tickets by tags', function () {
    $tag1 = Tag::factory()->create();
    $tag2 = Tag::factory()->create();

    $ticket1 = Ticket::factory()->create();
    $ticket2 = Ticket::factory()->create();
    $ticket3 = Ticket::factory()->create();

    $ticket1->tags()->attach($tag1);
    $ticket2->tags()->attach($tag2);
    $ticket3->tags()->attach([$tag1->id, $tag2->id]);

    $ticketsWithTag1 = Ticket::query()->withTags($tag1->id)->get();
    $ticketsWithBothTags = Ticket::query()->withAllTags([$tag1->id, $tag2->id])->get();

    expect($ticketsWithTag1)->toHaveCount(2)
        ->and($ticketsWithBothTags)->toHaveCount(1)
        ->and($ticketsWithBothTags->first()->id)->toBe($ticket3->id);
});

it('prevents merging tag with itself', function () {
    $tag = Tag::factory()->create();

    expect(fn () => $this->tagService->mergeTags($tag, $tag))
        ->toThrow(\InvalidArgumentException::class, 'Cannot merge tag with itself');
});
