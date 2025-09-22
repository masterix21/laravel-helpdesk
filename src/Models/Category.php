<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_categories';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $category): void {
            if ($category->slug === null && $category->name !== null) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(
            Ticket::class,
            'helpdesk_ticket_categories',
            'category_id',
            'ticket_id'
        )->withTimestamps();
    }

    public function getAllDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();
        $toProcess = collect([$this]);
        $processed = collect();

        // Load all categories with their children in a single query
        $allCategories = self::with('children')->get()->keyBy('id');

        while ($toProcess->isNotEmpty()) {
            $current = $toProcess->shift();

            if ($processed->contains('id', $current->id)) {
                continue;
            }

            $processed->push($current);

            $category = $allCategories->get($current->id) ?? $current;

            foreach ($category->children as $child) {
                if (! $descendants->contains('id', $child->id)) {
                    $descendants->push($child);
                    $toProcess->push($child);
                }
            }
        }

        return $descendants;
    }

    public function getAllAncestors(): \Illuminate\Support\Collection
    {
        if ($this->parent_id === null) {
            return collect();
        }

        $ancestors = collect();
        $parentIds = [];
        $currentParentId = $this->parent_id;
        $maxDepth = 100; // Prevent infinite loops
        $depth = 0;

        // First, collect all parent IDs we need to fetch
        $tempParentId = $this->parent_id;
        while ($tempParentId !== null && $depth < $maxDepth) {
            $parentIds[] = $tempParentId;
            $depth++;
            // We'll update this after fetching categories
            $tempParentId = null;
        }

        // Load all categories we might need in a single query
        if (! empty($parentIds)) {
            $allCategories = self::get()->keyBy('id');

            // Now traverse the ancestry chain
            $currentParentId = $this->parent_id;
            while ($currentParentId !== null) {
                $parent = $allCategories->get($currentParentId);

                if ($parent === null) {
                    break;
                }

                $ancestors->push($parent);
                $currentParentId = $parent->parent_id;
            }
        }

        return $ancestors;
    }

    public function isDescendantOf(self $category): bool
    {
        if ($this->parent_id === null) {
            return false;
        }

        $ancestors = $this->getAllAncestors();

        return $ancestors->contains('id', $category->id);
    }

    public function isAncestorOf(self $category): bool
    {
        return $category->isDescendantOf($this);
    }

    public function getPath(): string
    {
        $ancestors = $this->getAllAncestors();
        $path = $ancestors->reverse()->pluck('name');
        $path->push($this->name);

        return $path->implode(' > ');
    }

    public function getDepth(): int
    {
        return $this->getAllAncestors()->count();
    }

    #[Scope]
    public function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    public function root(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    #[Scope]
    public function withoutChildren(Builder $query): void
    {
        $query->doesntHave('children');
    }

    #[Scope]
    public function withChildren(Builder $query): void
    {
        $query->has('children');
    }

    #[Scope]
    public function forSlug(Builder $query, string $slug): void
    {
        $query->where('slug', $slug);
    }
}
