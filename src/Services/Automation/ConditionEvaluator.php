<?php

namespace LucaLongo\LaravelHelpdesk\Services\Automation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LucaLongo\LaravelHelpdesk\Models\Category;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class ConditionEvaluator
{
    protected array $evaluators = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $ticketCache = [];

    /**
     * @var array<int, Category|null>
     */
    protected array $categoryCache = [];

    /**
     * @var array<int, array<int>>
     */
    protected array $categoryDescendantsCache = [];

    public function __construct()
    {
        $this->registerDefaultEvaluators();
    }

    public function registerEvaluator(string $type, callable $evaluator): void
    {
        $this->evaluators[$type] = $evaluator;
    }

    public function evaluate($conditions, Ticket $ticket): bool
    {
        $this->resetCache();

        if (empty($conditions)) {
            return true;
        }

        $operator = strtolower($conditions['operator'] ?? 'and');
        $rules = $conditions['rules'] ?? [];

        if ($operator === 'and') {
            foreach ($rules as $rule) {
                if ($this->evaluateRule($rule, $ticket)) {
                    continue;
                }

                return false;
            }

            return true;
        }

        if ($operator === 'or') {
            foreach ($rules as $rule) {
                if ($this->evaluateRule($rule, $ticket)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    protected function evaluateRule(array $rule, Ticket $ticket): bool
    {
        $type = $rule['type'] ?? null;

        if (! isset($this->evaluators[$type])) {
            return false;
        }

        return call_user_func($this->evaluators[$type], $rule, $ticket);
    }

    protected function registerDefaultEvaluators(): void
    {
        $this->registerEvaluator('ticket_type', function ($rule, $ticket) {
            $expectedType = $rule['value'] ?? null;
            $operator = $rule['operator'] ?? 'equals';

            return $operator === 'equals'
                ? $ticket->type?->value === $expectedType
                : $ticket->type?->value !== $expectedType;
        });

        $this->registerEvaluator('ticket_priority', function ($rule, $ticket) {
            $expectedPriority = $rule['value'] ?? null;
            $operator = $rule['operator'] ?? 'equals';

            if ($operator === 'equals') {
                return $ticket->priority?->value === $expectedPriority;
            }

            if ($operator === 'not_equals') {
                return $ticket->priority?->value !== $expectedPriority;
            }

            $priorities = ['low', 'normal', 'high', 'urgent'];
            $currentIndex = array_search($ticket->priority?->value, $priorities, true);
            $expectedIndex = array_search($expectedPriority, $priorities, true);

            if ($currentIndex === false || $expectedIndex === false) {
                return false;
            }

            return match ($operator) {
                'greater_than' => $currentIndex > $expectedIndex,
                'less_than' => $currentIndex < $expectedIndex,
                'greater_or_equal' => $currentIndex >= $expectedIndex,
                'less_or_equal' => $currentIndex <= $expectedIndex,
                default => false,
            };
        });

        $this->registerEvaluator('ticket_status', function ($rule, $ticket) {
            $expectedStatus = $rule['value'] ?? null;
            $operator = $rule['operator'] ?? 'equals';

            return $operator === 'equals'
                ? $ticket->status?->value === $expectedStatus
                : $ticket->status?->value !== $expectedStatus;
        });

        $this->registerEvaluator('has_category', function ($rule, $ticket) {
            $categoryId = isset($rule['value']) ? (int) $rule['value'] : null;

            if ($categoryId === null) {
                return false;
            }

            if (($rule['include_descendants'] ?? false) === true) {
                $categoryIds = $this->categoryAndDescendantIds($categoryId);

                if ($categoryIds === []) {
                    return false;
                }

                return $this->ticketCategoryIds($ticket)
                    ->intersect($categoryIds)
                    ->isNotEmpty();
            }

            return $this->ticketCategoryIds($ticket)
                ->contains(fn ($id) => (int) $id === $categoryId);
        });

        $this->registerEvaluator('has_tag', function ($rule, $ticket) {
            $tagIds = collect((array) ($rule['value'] ?? []))
                ->filter()
                ->map(fn ($id) => (int) $id);

            if ($tagIds->isEmpty()) {
                return false;
            }

            $ticketTagIds = $this->ticketTagIds($ticket);
            $operator = $rule['operator'] ?? 'any';

            if ($operator === 'all') {
                return $tagIds->diff($ticketTagIds)->isEmpty();
            }

            if ($operator === 'none') {
                return $ticketTagIds->intersect($tagIds)->isEmpty();
            }

            return $ticketTagIds->intersect($tagIds)->isNotEmpty();
        });

        $this->registerEvaluator('time_since_created', function ($rule, $ticket) {
            $minutes = (int) ($rule['value'] ?? 0);
            $operator = $rule['operator'] ?? 'greater_than';

            $minutesSinceCreated = $ticket->created_at->diffInMinutes(now());

            return match ($operator) {
                'greater_than' => $minutesSinceCreated > $minutes,
                'less_than' => $minutesSinceCreated < $minutes,
                'greater_or_equal' => $minutesSinceCreated >= $minutes,
                'less_or_equal' => $minutesSinceCreated <= $minutes,
                default => false,
            };
        });

        $this->registerEvaluator('time_since_last_update', function ($rule, $ticket) {
            $minutes = (int) ($rule['value'] ?? 0);
            $operator = $rule['operator'] ?? 'greater_than';

            $lastActivity = $this->ticketLastActivityAt($ticket);
            $minutesSinceActivity = $lastActivity->diffInMinutes(now());

            return match ($operator) {
                'greater_than' => $minutesSinceActivity > $minutes,
                'less_than' => $minutesSinceActivity < $minutes,
                'greater_or_equal' => $minutesSinceActivity >= $minutes,
                'less_or_equal' => $minutesSinceActivity <= $minutes,
                default => false,
            };
        });

        $this->registerEvaluator('assignee_status', function ($rule, $ticket) {
            $status = $rule['value'] ?? null;

            return match ($status) {
                'assigned' => $ticket->assigned_to_id !== null,
                'unassigned' => $ticket->assigned_to_id === null,
                default => false,
            };
        });

        $this->registerEvaluator('customer_type', function ($rule, $ticket) {
            $customerType = $rule['value'] ?? null;
            $meta = $ticket->meta ?? [];

            return ($meta['customer_type'] ?? null) === $customerType;
        });

        $this->registerEvaluator('sla_status', function ($rule, $ticket) {
            $status = $rule['value'] ?? null;

            return match ($status) {
                'breached' => $ticket->sla_breached === true,
                'approaching' => $ticket->isFirstResponseOverdue() || $ticket->isResolutionOverdue(),
                'within' => ! $ticket->sla_breached && ! $ticket->isFirstResponseOverdue() && ! $ticket->isResolutionOverdue(),
                default => false,
            };
        });

        $this->registerEvaluator('comment_count', function ($rule, $ticket) {
            $count = (int) ($rule['value'] ?? 0);
            $operator = $rule['operator'] ?? 'equals';
            $commentCount = $this->ticketCommentCount($ticket);

            return match ($operator) {
                'equals' => $commentCount === $count,
                'not_equals' => $commentCount !== $count,
                'greater_than' => $commentCount > $count,
                'less_than' => $commentCount < $count,
                'greater_or_equal' => $commentCount >= $count,
                'less_or_equal' => $commentCount <= $count,
                default => false,
            };
        });

        $this->registerEvaluator('subject_contains', function ($rule, $ticket) {
            $keywords = (array) ($rule['value'] ?? []);
            $operator = $rule['operator'] ?? 'any';
            $subject = strtolower($ticket->subject ?? '');

            if ($keywords === []) {
                return false;
            }

            if ($operator === 'any') {
                foreach ($keywords as $keyword) {
                    if (str_contains($subject, strtolower($keyword))) {
                        return true;
                    }
                }

                return false;
            }

            if ($operator === 'all') {
                foreach ($keywords as $keyword) {
                    if (! str_contains($subject, strtolower($keyword))) {
                        return false;
                    }
                }

                return true;
            }

            return false;
        });

        $this->registerEvaluator('custom_field', function ($rule, $ticket) {
            $field = $rule['field'] ?? null;
            $value = $rule['value'] ?? null;
            $operator = $rule['operator'] ?? 'equals';
            $meta = $ticket->meta ?? [];
            $fieldValue = $meta[$field] ?? null;

            return match ($operator) {
                'equals' => $fieldValue === $value,
                'not_equals' => $fieldValue !== $value,
                'contains' => str_contains((string) $fieldValue, (string) $value),
                'not_contains' => ! str_contains((string) $fieldValue, (string) $value),
                'empty' => empty($fieldValue),
                'not_empty' => ! empty($fieldValue),
                default => false,
            };
        });
    }

    protected function resetCache(): void
    {
        $this->ticketCache = [];
        $this->categoryCache = [];
        $this->categoryDescendantsCache = [];
    }

    private function ticketCacheKey(Ticket $ticket): string
    {
        return (string) ($ticket->getKey() ?? spl_object_id($ticket));
    }

    private function rememberTicketData(Ticket $ticket, string $key, callable $resolver): mixed
    {
        $cacheKey = $this->ticketCacheKey($ticket);

        if (! isset($this->ticketCache[$cacheKey][$key])) {
            $this->ticketCache[$cacheKey][$key] = $resolver();
        }

        return $this->ticketCache[$cacheKey][$key];
    }

    private function rememberCategory(int $categoryId): ?Category
    {
        if (! array_key_exists($categoryId, $this->categoryCache)) {
            $this->categoryCache[$categoryId] = Category::find($categoryId);
        }

        return $this->categoryCache[$categoryId];
    }

    private function categoryAndDescendantIds(int $categoryId): array
    {
        if (! array_key_exists($categoryId, $this->categoryDescendantsCache)) {
            $category = $this->rememberCategory($categoryId);

            if (! $category) {
                $this->categoryDescendantsCache[$categoryId] = [];
            } else {
                $descendantIds = $category->getAllDescendants()->pluck('id')->all();
                $this->categoryDescendantsCache[$categoryId] = array_merge([$categoryId], $descendantIds);
            }
        }

        return $this->categoryDescendantsCache[$categoryId];
    }

    private function ticketCategoryIds(Ticket $ticket): Collection
    {
        return $this->rememberTicketData($ticket, 'category_ids', function () use ($ticket) {
            if ($ticket->relationLoaded('categories')) {
                return $ticket->categories->pluck('id');
            }

            return $ticket->categories()->get()->pluck('id');
        });
    }

    private function ticketTagIds(Ticket $ticket): Collection
    {
        return $this->rememberTicketData($ticket, 'tag_ids', function () use ($ticket) {
            if ($ticket->relationLoaded('tags')) {
                return $ticket->tags->pluck('id');
            }

            return $ticket->tags()->get()->pluck('id');
        });
    }

    private function ticketCommentCount(Ticket $ticket): int
    {
        return $this->rememberTicketData($ticket, 'comment_count', function () use ($ticket) {
            if ($ticket->relationLoaded('comments')) {
                return $ticket->comments->count();
            }

            return $ticket->comments()->count();
        });
    }

    private function ticketLatestCommentAt(Ticket $ticket): ?Carbon
    {
        return $this->rememberTicketData($ticket, 'latest_comment_at', function () use ($ticket) {
            if ($ticket->relationLoaded('comments')) {
                $latest = $ticket->comments->max('created_at');

                if ($latest instanceof Carbon) {
                    return $latest;
                }

                return $latest ? Carbon::parse($latest) : null;
            }

            $comment = $ticket->comments()->latest()->first();

            return $comment?->created_at;
        });
    }

    private function ticketLastActivityAt(Ticket $ticket): Carbon
    {
        return $this->rememberTicketData($ticket, 'last_activity_at', function () use ($ticket) {
            $latestCommentAt = $this->ticketLatestCommentAt($ticket);

            if ($latestCommentAt !== null) {
                return $latestCommentAt;
            }

            return $ticket->updated_at;
        });
    }
}
