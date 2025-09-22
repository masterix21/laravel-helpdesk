<?php

use Illuminate\Support\Facades\Event;
use LucaLongo\LaravelHelpdesk\Events\CategoryCreated;
use LucaLongo\LaravelHelpdesk\Events\CategoryDeleted;
use LucaLongo\LaravelHelpdesk\Events\CategoryUpdated;
use LucaLongo\LaravelHelpdesk\Models\Category;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Services\CategoryService;

beforeEach(function () {
    $this->categoryService = new CategoryService;
});

it('can create a category', function () {
    Event::fake();

    $category = $this->categoryService->create([
        'name' => 'Technical Support',
        'description' => 'Help with technical issues',
        'is_active' => true,
    ]);

    expect($category)->toBeInstanceOf(Category::class)
        ->name->toBe('Technical Support')
        ->slug->toBe('technical-support')
        ->description->toBe('Help with technical issues')
        ->is_active->toBeTrue()
        ->parent_id->toBeNull();

    Event::assertDispatched(CategoryCreated::class);
});

it('can create a subcategory', function () {
    $parent = Category::factory()->create(['name' => 'Support']);

    $child = $this->categoryService->create([
        'name' => 'Billing',
        'parent_id' => $parent->id,
    ]);

    expect($child->parent_id)->toBe($parent->id)
        ->and($child->parent->id)->toBe($parent->id);
});

it('can build hierarchical relationships', function () {
    $root = Category::factory()->create(['name' => 'Root']);
    $child1 = Category::factory()->create(['name' => 'Child 1', 'parent_id' => $root->id]);
    $child2 = Category::factory()->create(['name' => 'Child 2', 'parent_id' => $root->id]);
    $grandchild = Category::factory()->create(['name' => 'Grandchild', 'parent_id' => $child1->id]);

    expect($root->children)->toHaveCount(2)
        ->and($child1->parent->id)->toBe($root->id)
        ->and($grandchild->parent->id)->toBe($child1->id)
        ->and($grandchild->isDescendantOf($root))->toBeTrue()
        ->and($root->isAncestorOf($grandchild))->toBeTrue()
        ->and($child2->isDescendantOf($grandchild))->toBeFalse();
});

it('can get all descendants of a category', function () {
    $root = Category::factory()->create();
    $child1 = Category::factory()->create(['parent_id' => $root->id]);
    $child2 = Category::factory()->create(['parent_id' => $root->id]);
    $grandchild1 = Category::factory()->create(['parent_id' => $child1->id]);
    $grandchild2 = Category::factory()->create(['parent_id' => $child2->id]);

    $descendants = $root->getAllDescendants();

    expect($descendants)->toHaveCount(4)
        ->and($descendants->pluck('id')->sort()->values()->toArray())
        ->toBe(collect([$child1->id, $child2->id, $grandchild1->id, $grandchild2->id])->sort()->values()->toArray());
});

it('can get all ancestors of a category', function () {
    $root = Category::factory()->create();
    $parent = Category::factory()->create(['parent_id' => $root->id]);
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    $ancestors = $child->getAllAncestors();

    expect($ancestors)->toHaveCount(2)
        ->and($ancestors->pluck('id')->toArray())
        ->toBe([$parent->id, $root->id]);
});

it('can get category path', function () {
    $root = Category::factory()->create(['name' => 'Support']);
    $child = Category::factory()->create(['name' => 'Technical', 'parent_id' => $root->id]);
    $grandchild = Category::factory()->create(['name' => 'Hardware', 'parent_id' => $child->id]);

    expect($grandchild->getPath())->toBe('Support > Technical > Hardware')
        ->and($grandchild->getDepth())->toBe(2);
});

it('can update a category', function () {
    Event::fake();

    $category = Category::factory()->create(['name' => 'Old Name']);

    $updated = $this->categoryService->update($category, [
        'name' => 'New Name',
        'description' => 'Updated description',
    ]);

    expect($updated->name)->toBe('New Name')
        ->and($updated->description)->toBe('Updated description');

    Event::assertDispatched(CategoryUpdated::class);
});

it('can delete a category', function () {
    Event::fake();

    $category = Category::factory()->create();

    $result = $this->categoryService->delete($category);

    expect($result)->toBeTrue()
        ->and(Category::find($category->id))->toBeNull();

    Event::assertDispatched(CategoryDeleted::class);
});

it('can move a category to a new parent', function () {
    $root1 = Category::factory()->create();
    $root2 = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $root1->id]);

    $moved = $this->categoryService->moveCategory($child, $root2, 10);

    expect($moved->parent_id)->toBe($root2->id)
        ->and($moved->sort_order)->toBe(10);
});

it('prevents moving category to itself', function () {
    $category = Category::factory()->create();

    expect(fn () => $this->categoryService->moveCategory($category, $category))
        ->toThrow(\InvalidArgumentException::class, 'Category cannot be its own parent');
});

it('prevents moving category to its descendant', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    expect(fn () => $this->categoryService->moveCategory($parent, $child))
        ->toThrow(\InvalidArgumentException::class, 'Cannot move category to its own descendant');
});

it('can get category tree', function () {
    $root1 = Category::factory()->create(['sort_order' => 1]);
    $root2 = Category::factory()->create(['sort_order' => 2]);
    Category::factory()->create(['parent_id' => $root1->id]);
    Category::factory()->create(['parent_id' => $root1->id]);

    $tree = $this->categoryService->getTree();

    expect($tree)->toHaveCount(2)
        ->and($tree->first()->id)->toBe($root1->id)
        ->and($tree->first()->children)->toHaveCount(2);
});

it('can search categories', function () {
    Category::factory()->active()->create(['name' => 'Technical Support']);
    Category::factory()->active()->create(['name' => 'Billing Questions']);
    Category::factory()->active()->create(['description' => 'Support for technical issues']);

    $results = $this->categoryService->searchCategories('technical');

    expect($results)->toHaveCount(2);
});

it('can associate tickets with categories', function () {
    $category = Category::factory()->create();
    $ticket = Ticket::factory()->create();

    $ticket->categories()->attach($category);

    expect($ticket->categories)->toHaveCount(1)
        ->and($ticket->categories->first()->id)->toBe($category->id)
        ->and($category->tickets)->toHaveCount(1);
});

it('can filter tickets by category', function () {
    $category1 = Category::factory()->create();
    $category2 = Category::factory()->create();

    $ticket1 = Ticket::factory()->create();
    $ticket2 = Ticket::factory()->create();
    $ticket3 = Ticket::factory()->create();

    $ticket1->categories()->attach($category1);
    $ticket2->categories()->attach($category2);
    $ticket3->categories()->attach([$category1->id, $category2->id]);

    $ticketsInCategory1 = Ticket::query()->withCategories($category1->id)->get();
    $ticketsInBothCategories = Ticket::query()->withAllCategories([$category1->id, $category2->id])->get();

    expect($ticketsInCategory1)->toHaveCount(2)
        ->and($ticketsInBothCategories)->toHaveCount(1)
        ->and($ticketsInBothCategories->first()->id)->toBe($ticket3->id);
});

it('can filter tickets by category including descendants', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    $ticket1 = Ticket::factory()->create();
    $ticket2 = Ticket::factory()->create();

    $ticket1->categories()->attach($parent);
    $ticket2->categories()->attach($child);

    $tickets = Ticket::query()->inCategory($parent)->get();

    expect($tickets)->toHaveCount(2);
});

it('can merge categories', function () {
    $source = Category::factory()->create();
    $target = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $source->id]);

    $this->categoryService->mergeCategories($source, $target);

    expect(Category::find($source->id))->toBeNull()
        ->and($child->fresh()->parent_id)->toBe($target->id);
});

it('can duplicate a category with its children', function () {
    $original = Category::factory()->create(['name' => 'Original']);
    $child = Category::factory()->create(['name' => 'Child', 'parent_id' => $original->id]);

    $duplicate = $this->categoryService->duplicateCategory($original);

    expect($duplicate->name)->toBe('Original (Copy)')
        ->and($duplicate->id)->not->toBe($original->id)
        ->and(Category::where('name', 'Original')->count())->toBe(1)
        ->and(Category::where('name', 'Original (Copy)')->count())->toBe(1);
});
