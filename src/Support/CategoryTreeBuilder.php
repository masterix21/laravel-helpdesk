<?php

namespace LucaLongo\LaravelHelpdesk\Support;

use Illuminate\Support\Collection;
use LucaLongo\LaravelHelpdesk\Models\Category;

class CategoryTreeBuilder
{
    protected Collection $categories;

    protected array $tree = [];

    protected array $flatTree = [];

    public function __construct()
    {
        $this->loadCategories();
    }

    protected function loadCategories(): void
    {
        $this->categories = Category::query()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getTree(): array
    {
        if (empty($this->tree)) {
            $this->buildTree();
        }

        return $this->tree;
    }

    public function getFlatTree(): array
    {
        if (empty($this->flatTree)) {
            $this->buildFlatTree();
        }

        return $this->flatTree;
    }

    protected function buildTree(): void
    {
        $categoriesByParent = $this->categories->groupBy('parent_id');

        $this->tree = $this->buildTreeRecursive($categoriesByParent, null);
    }

    protected function buildTreeRecursive(Collection $categoriesByParent, ?int $parentId): array
    {
        $tree = [];

        if (! $categoriesByParent->has($parentId)) {
            return $tree;
        }

        foreach ($categoriesByParent->get($parentId) as $category) {
            $node = [
                'category' => $category,
                'children' => $this->buildTreeRecursive($categoriesByParent, $category->id),
            ];

            $tree[] = $node;
        }

        return $tree;
    }

    protected function buildFlatTree(): void
    {
        $this->flatTree = [];
        $tree = $this->getTree();

        $this->flattenTree($tree, 0);
    }

    protected function flattenTree(array $nodes, int $depth): void
    {
        foreach ($nodes as $node) {
            $category = $node['category'];
            $category->depth = $depth;

            $this->flatTree[] = $category;

            if (! empty($node['children'])) {
                $this->flattenTree($node['children'], $depth + 1);
            }
        }
    }

    public function getAncestors(int $categoryId): Collection
    {
        $ancestors = collect();
        $category = $this->categories->firstWhere('id', $categoryId);

        if (! $category) {
            return $ancestors;
        }

        while ($category->parent_id !== null) {
            $parent = $this->categories->firstWhere('id', $category->parent_id);
            if (! $parent) {
                break;
            }
            $ancestors->push($parent);
            $category = $parent;
        }

        return $ancestors;
    }

    public function getDescendants(int $categoryId): Collection
    {
        $descendants = collect();
        $stack = collect([$categoryId]);

        while ($stack->isNotEmpty()) {
            $currentId = $stack->pop();
            $children = $this->categories->where('parent_id', $currentId);

            foreach ($children as $child) {
                $descendants->push($child);
                $stack->push($child->id);
            }
        }

        return $descendants;
    }

    public function getPath(int $categoryId): string
    {
        $category = $this->categories->firstWhere('id', $categoryId);

        if (! $category) {
            return '';
        }

        $path = collect([$category->name]);
        $ancestors = $this->getAncestors($categoryId);

        foreach ($ancestors->reverse() as $ancestor) {
            $path->prepend($ancestor->name);
        }

        return $path->implode(' > ');
    }

    public function refresh(): void
    {
        $this->loadCategories();
        $this->tree = [];
        $this->flatTree = [];
    }
}