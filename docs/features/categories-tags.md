# Categories & Tags

## Overview

Categories and tags provide flexible organization and classification for tickets. Categories use a hierarchical tree structure (using nested sets), while tags offer flat, flexible labeling.

## Categories

### CategoryService

The `CategoryService` manages hierarchical categorization of tickets.

#### Creating Categories

```php
use LucaLongo\LaravelHelpdesk\Services\CategoryService;

$categoryService = app(CategoryService::class);

// Create root category
$category = $categoryService->create([
    'name' => 'Technical Support',
    'slug' => 'technical-support',  // Auto-generated if not provided
    'description' => 'All technical issues',
    'icon' => 'wrench',
    'color' => '#3B82F6',
    'is_active' => true,
    'sort_order' => 1,
]);

// Create subcategory
$subcategory = $categoryService->create([
    'name' => 'API Issues',
    'parent_id' => $category->id,
    'description' => 'API and integration problems',
]);
```

#### Managing Category Tree

```php
// Get full category tree
$tree = $categoryService->getTree();

// Get tree starting from specific parent
$subtree = $categoryService->getTree($parentId);

// Get flat tree with depth indicators
$flatTree = $categoryService->getFlatTree();
foreach ($flatTree as $category) {
    $indent = str_repeat('--', $category->depth);
    echo "{$indent} {$category->name}";
}

// Get root categories only
$roots = $categoryService->getRootCategories();

// Get active categories
$active = $categoryService->getActiveCategories();
```

#### Moving Categories

```php
// Move category to new parent
$categoryService->moveCategory(
    $category,
    $newParent,
    $sortOrder = 5
);

// Move to root level
$categoryService->moveCategory($category, null, 0);

// Validates against circular references
```

#### Category Path & Breadcrumbs

```php
// Get full path from root to category
$path = $categoryService->getCategoryPath($category);
// Returns: Collection[Root > Parent > Current]

// Display breadcrumbs
foreach ($path as $ancestor) {
    echo $ancestor->name . ' > ';
}
```

#### Searching Categories

```php
// Search by name or description
$results = $categoryService->searchCategories('api');

// Find by slug
$category = $categoryService->findBySlug('technical-support');

// Get categories with ticket count
$withCounts = $categoryService->getCategoriesWithTicketCount();
```

### Category Model

```php
use LucaLongo\LaravelHelpdesk\Models\Category;

// Relationships
$parent = $category->parent;
$children = $category->children;
$ancestors = $category->getAllAncestors();
$descendants = $category->getAllDescendants();
$tickets = $category->tickets;

// Scopes
$rootCategories = Category::root()->get();
$activeCategories = Category::active()->get();
$leafCategories = Category::leaf()->get();

// Helper methods
if ($category->isRoot()) { }
if ($category->isLeaf()) { }
if ($category->hasChildren()) { }
if ($category->isDescendantOf($other)) { }
if ($category->isAncestorOf($other)) { }

// Get level in tree (0 = root)
$level = $category->getLevel();
```

## Tags

### TagService

The `TagService` provides flexible tagging functionality.

#### Creating & Managing Tags

```php
use LucaLongo\LaravelHelpdesk\Services\TagService;

$tagService = app(TagService::class);

// Create tag
$tag = $tagService->create([
    'name' => 'Bug',
    'slug' => 'bug',  // Auto-generated if not provided
    'color' => '#EF4444',
    'description' => 'Software defects',
    'is_active' => true,
]);

// Find or create by name
$tag = $tagService->findOrCreateByName('urgent');

// Update tag
$tag = $tagService->update($tag, [
    'color' => '#DC2626',
    'description' => 'Updated description',
]);

// Delete tag
$tagService->delete($tag);
```

#### Tagging Tickets

```php
// Attach multiple tags (replaces existing)
$tagService->attachTagsToTicket($ticket, [
    'bug',           // Creates if not exists
    'urgent',        // String names
    $existingTag,    // Tag instance
    23,              // Tag ID
]);

// Add single tag (keeps existing)
$tagService->addTagToTicket($ticket, 'api-issue');

// Remove tag
$tagService->removeTagFromTicket($ticket, 'resolved');

// Access ticket tags
$tags = $ticket->tags;
```

#### Tag Discovery

```php
// Get popular tags
$popular = $tagService->getPopularTags(10);

// Get unused tags (for cleanup)
$unused = $tagService->getUnusedTags();

// Search tags
$results = $tagService->searchTags('api', $limit = 10);

// Get suggested tags based on similar tickets
$suggestions = $tagService->getSuggestedTags($ticket, 5);

// Get tag cloud with relative sizes
$cloud = $tagService->getTagCloud();
foreach ($cloud as $tag) {
    // $tag->cloud_size ranges from 1-5
    echo "<span class='size-{$tag->cloud_size}'>{$tag->name}</span>";
}
```

#### Tag Maintenance

```php
// Merge duplicate tags
$tagService->mergeTags($sourceTag, $targetTag);
// All tickets from source are moved to target

// Cleanup unused tags
$deletedCount = $tagService->cleanupUnusedTags();
```

### Tag Model

```php
use LucaLongo\LaravelHelpdesk\Models\Tag;

// Relationships
$tickets = $tag->tickets;

// Scopes
$activeTags = Tag::active()->get();
$popularTags = Tag::popular(20)->get();
$unusedTags = Tag::unused()->get();
$tagsBySlug = Tag::forSlug('bug')->first();

// With counts
$tags = Tag::withCount('tickets')->get();
foreach ($tags as $tag) {
    echo "{$tag->name} ({$tag->tickets_count} tickets)";
}
```

## Using Categories & Tags Together

```php
// Create ticket with category and tags
$ticket = $ticketService->open([
    'subject' => 'API returns 500 error',
    'category_id' => $apiCategory->id,
]);

$tagService->attachTagsToTicket($ticket, [
    'bug', 'api', 'high-priority'
]);

// Query tickets by category and tags
$tickets = Ticket::query()
    ->where('category_id', $category->id)
    ->whereHas('tags', function ($q) {
        $q->where('slug', 'bug');
    })
    ->get();

// Get category-specific popular tags
$categoryTickets = $category->tickets()->with('tags')->get();
$categoryTags = $categoryTickets->pluck('tags')->flatten()->unique('id');
```

## Events

```php
use LucaLongo\LaravelHelpdesk\Events\*;

// Category events
CategoryCreated::class
CategoryUpdated::class
CategoryDeleted::class

// Tag events
TagCreated::class
TagUpdated::class
TagDeleted::class

// Listen to events
Event::listen(CategoryCreated::class, function ($event) {
    Log::info("Category created: {$event->category->name}");
});
```

## Best Practices

1. **Use categories for hierarchical organization** (e.g., Department > Team > Issue Type)
2. **Use tags for flexible labeling** (e.g., urgent, bug, feature-request)
3. **Limit category depth** to 3-4 levels for usability
4. **Create tag naming conventions** (e.g., status:pending, priority:high)
5. **Regular cleanup** of unused tags to maintain organization
6. **Cache category trees** as they change infrequently
7. **Use suggested tags** to maintain consistency