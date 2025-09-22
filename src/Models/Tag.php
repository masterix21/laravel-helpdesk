<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_tags';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $tag): void {
            if ($tag->slug === null) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(
            Ticket::class,
            'helpdesk_ticket_tags',
            'tag_id',
            'ticket_id'
        )->withTimestamps();
    }

    #[Scope]
    public function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    public function forSlug(Builder $query, string $slug): void
    {
        $query->where('slug', $slug);
    }

    #[Scope]
    public function popular(Builder $query, int $limit = 10): void
    {
        $query->withCount('tickets')
            ->orderByDesc('tickets_count')
            ->limit($limit);
    }

    #[Scope]
    public function used(Builder $query): void
    {
        $query->has('tickets');
    }

    #[Scope]
    public function unused(Builder $query): void
    {
        $query->doesntHave('tickets');
    }
}
