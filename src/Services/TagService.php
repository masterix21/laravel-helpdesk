<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LucaLongo\LaravelHelpdesk\Events\TagCreated;
use LucaLongo\LaravelHelpdesk\Events\TagDeleted;
use LucaLongo\LaravelHelpdesk\Events\TagUpdated;
use LucaLongo\LaravelHelpdesk\Models\Tag;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class TagService
{
    public function create(array $data): Tag
    {
        if (!isset($data['slug']) && isset($data['name'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        }

        $tag = Tag::create($data);

        event(new TagCreated($tag));

        return $tag;
    }

    public function update(Tag $tag, array $data): Tag
    {
        $tag->update($data);

        event(new TagUpdated($tag));

        return $tag;
    }

    public function delete(Tag $tag): bool
    {
        $result = $tag->delete();

        if ($result) {
            event(new TagDeleted($tag));
        }

        return $result;
    }

    public function findBySlug(string $slug): ?Tag
    {
        return Tag::query()->forSlug($slug)->first();
    }

    public function findOrCreateByName(string $name): Tag
    {
        $slug = \Illuminate\Support\Str::slug($name);

        return Tag::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name]
        );
    }

    public function attachTagsToTicket(Ticket $ticket, array $tags): void
    {
        $tagIds = collect($tags)->map(function ($tag) {
            if (is_numeric($tag)) {
                return $tag;
            }

            if ($tag instanceof Tag) {
                return $tag->id;
            }

            return $this->findOrCreateByName($tag)->id;
        })->filter()->unique();

        $ticket->tags()->sync($tagIds);
    }

    public function addTagToTicket(Ticket $ticket, string|Tag $tag): void
    {
        if (is_string($tag)) {
            $tag = $this->findOrCreateByName($tag);
        }

        $ticket->tags()->syncWithoutDetaching([$tag->id]);
    }

    public function removeTagFromTicket(Ticket $ticket, string|Tag $tag): void
    {
        if (is_string($tag)) {
            $tag = $this->findBySlug(\Illuminate\Support\Str::slug($tag));
            if (! $tag) {
                return;
            }
        }

        $ticket->tags()->detach($tag->id);
    }

    public function getActiveTags(): Collection
    {
        return Tag::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function getPopularTags(int $limit = 10): Collection
    {
        return Tag::query()
            ->popular($limit)
            ->active()
            ->get();
    }

    public function getUnusedTags(): Collection
    {
        return Tag::query()
            ->unused()
            ->orderBy('name')
            ->get();
    }

    public function searchTags(string $query, int $limit = 10): Collection
    {
        return Tag::query()
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('slug', 'like', "%{$query}%");
            })
            ->active()
            ->limit($limit)
            ->get();
    }

    public function getSuggestedTags(Ticket $ticket, int $limit = 5): Collection
    {
        // Load tags if not already loaded to avoid N+1
        $ticket->loadMissing('tags');
        $existingTagIds = $ticket->tags->pluck('id')->toArray();

        $relatedTickets = Ticket::query()
            ->where('id', '!=', $ticket->id)
            ->where(function (Builder $q) use ($ticket) {
                $q->where('type', $ticket->type)
                    ->orWhere('priority', $ticket->priority);
            })
            ->with('tags')
            ->limit(20)
            ->get();

        $suggestedTags = collect();

        foreach ($relatedTickets as $relatedTicket) {
            $suggestedTags = $suggestedTags->merge($relatedTicket->tags);
        }

        return $suggestedTags
            ->whereNotIn('id', $existingTagIds)
            ->groupBy('id')
            ->map(fn ($tags) => ['tag' => $tags->first(), 'count' => $tags->count()])
            ->sortByDesc('count')
            ->take($limit)
            ->pluck('tag')
            ->values();
    }

    public function mergeTags(Tag $source, Tag $target): bool
    {
        if ($source->id === $target->id) {
            throw new \InvalidArgumentException('Cannot merge tag with itself');
        }

        // Bulk transfer all tickets from source to target
        $sourceTicketIds = $source->tickets()->pluck('ticket_id')->toArray();

        if (! empty($sourceTicketIds)) {
            $target->tickets()->syncWithoutDetaching($sourceTicketIds);
        }

        return $this->delete($source);
    }

    public function cleanupUnusedTags(): int
    {
        $unusedTagIds = Tag::query()
            ->unused()
            ->pluck('id')
            ->toArray();

        $count = count($unusedTagIds);

        if ($count > 0) {
            Tag::whereIn('id', $unusedTagIds)->delete();
        }

        return $count;
    }

    public function getTagCloud(): Collection
    {
        $tags = Tag::query()
            ->withCount('tickets')
            ->active()
            ->orderByDesc('tickets_count')
            ->limit(30)
            ->get()
            ->filter(fn ($tag) => $tag->tickets_count > 0);

        $maxCount = $tags->max('tickets_count') ?? 1;
        $minCount = $tags->min('tickets_count') ?? 0;
        $spread = $maxCount - $minCount;

        if ($spread == 0) {
            $spread = 1;
        }

        return $tags->map(function ($tag) use ($minCount, $spread) {
            $size = (($tag->tickets_count - $minCount) / $spread);
            $tag->cloud_size = (int) (1 + $size * 4);

            return $tag;
        })->shuffle();
    }
}