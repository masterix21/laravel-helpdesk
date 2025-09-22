<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LucaLongo\LaravelHelpdesk\Events\CategoryCreated;
use LucaLongo\LaravelHelpdesk\Events\CategoryDeleted;
use LucaLongo\LaravelHelpdesk\Events\CategoryUpdated;
use LucaLongo\LaravelHelpdesk\Models\Category;

class CategoryService
{
    public function create(array $data): Category
    {
        if (! isset($data['slug']) && isset($data['name'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        }

        $category = Category::create($data);

        event(new CategoryCreated($category));

        return $category;
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        event(new CategoryUpdated($category));

        return $category;
    }

    public function delete(Category $category): bool
    {
        $result = $category->delete();

        if ($result) {
            event(new CategoryDeleted($category));
        }

        return $result;
    }

    public function getTree(?int $parentId = null): Collection
    {
        return Category::query()
            ->where('parent_id', $parentId)
            ->with(['children' => function ($query) {
                $query->orderBy('sort_order')->orderBy('name');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getFlatTree(): Collection
    {
        $categories = Category::query()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->buildFlatTree($categories);
    }

    protected function buildFlatTree(Collection $categories, ?int $parentId = null, int $depth = 0): Collection
    {
        $result = collect();

        $categories->where('parent_id', $parentId)->each(function ($category) use ($categories, &$result, $depth) {
            $category->depth = $depth;
            $result->push($category);

            $children = $this->buildFlatTree($categories, $category->id, $depth + 1);
            $result = $result->merge($children);
        });

        return $result;
    }

    public function moveCategory(Category $category, ?Category $newParent = null, int $sortOrder = 0): Category
    {
        if ($newParent && $category->id === $newParent->id) {
            throw new \InvalidArgumentException('Category cannot be its own parent');
        }

        if ($newParent && $newParent->isDescendantOf($category)) {
            throw new \InvalidArgumentException('Cannot move category to its own descendant');
        }

        $category->parent_id = $newParent?->id;
        $category->sort_order = $sortOrder;
        $category->save();

        return $category;
    }

    public function findBySlug(string $slug): ?Category
    {
        return Category::query()->forSlug($slug)->first();
    }

    public function getActiveCategories(): Collection
    {
        return Category::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getRootCategories(): Collection
    {
        return Category::query()
            ->root()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getCategoryPath(Category $category): Collection
    {
        $path = collect([$category]);
        $ancestors = $category->getAllAncestors();

        return $ancestors->reverse()->push($category);
    }

    public function searchCategories(string $query): Collection
    {
        return Category::query()
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->active()
            ->limit(20)
            ->get();
    }

    public function getCategoriesWithTicketCount(): Collection
    {
        return Category::query()
            ->withCount('tickets')
            ->orderByDesc('tickets_count')
            ->get();
    }

    public function mergeCategories(Category $source, Category $target): bool
    {
        if ($source->id === $target->id) {
            throw new \InvalidArgumentException('Cannot merge category with itself');
        }

        // Transfer all tickets from source to target category in bulk
        $ticketIds = $source->tickets()->pluck('ticket_id')->toArray();
        if (! empty($ticketIds)) {
            $target->tickets()->syncWithoutDetaching($ticketIds);
            $source->tickets()->detach();
        }

        // Transfer all child categories
        $source->children()->update(['parent_id' => $target->id]);

        return $this->delete($source);
    }

    public function duplicateCategory(Category $category, ?string $newName = null): Category
    {
        $data = $category->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at']);

        $data['name'] = $newName ?? $category->name.' (Copy)';
        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);

        $newCategory = $this->create($data);

        // Eager load children to avoid N+1
        $children = $category->children()->get();
        foreach ($children as $child) {
            $childData = $child->toArray();
            unset($childData['id'], $childData['created_at'], $childData['updated_at'], $childData['slug']);
            $childData['parent_id'] = $newCategory->id;
            $this->create($childData);
        }

        return $newCategory;
    }
}
