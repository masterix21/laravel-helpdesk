<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class KnowledgeSection extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_knowledge_sections';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'is_visible' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeArticle::class,
            'helpdesk_knowledge_article_sections',
            'section_id',
            'article_id'
        )->withPivot('position')->withTimestamps()->orderByPivot('position');
    }

    public function visibleChildren(): HasMany
    {
        return $this->children()->where('is_visible', true);
    }

    public function publishedArticles(): BelongsToMany
    {
        return $this->articles()->where('status', 'published')->where('is_public', true);
    }

    public function getAncestors(): Collection
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors->reverse();
    }

    public function getDescendants(): Collection
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }

    public function getBreadcrumb(): Collection
    {
        return $this->getAncestors()->push($this);
    }

    public function getLevel(): int
    {
        return $this->getAncestors()->count();
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    public function hasVisibleContent(): bool
    {
        if (! $this->is_visible) {
            return false;
        }

        if ($this->publishedArticles()->exists()) {
            return true;
        }

        return $this->visibleChildren()->exists();
    }

    #[Scope]
    public function roots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    #[Scope]
    public function visible(Builder $query): void
    {
        $query->where('is_visible', true);
    }

    #[Scope]
    public function withArticleCount(Builder $query): void
    {
        $query->withCount(['articles' => function ($q) {
            $q->where('status', 'published')->where('is_public', true);
        }]);
    }
}
