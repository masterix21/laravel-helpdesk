<?php

namespace LucaLongo\LaravelHelpdesk\Services\Automation;

use LucaLongo\LaravelHelpdesk\Models\Ticket;

class ConditionEvaluator
{
    protected array $evaluators = [];

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
        if (empty($conditions)) {
            return true;
        }

        $operator = $conditions['operator'] ?? 'and';
        $rules = $conditions['rules'] ?? [];

        if ($operator === 'and') {
            foreach ($rules as $rule) {
                if (! $this->evaluateRule($rule, $ticket)) {
                    return false;
                }
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
            $currentIndex = array_search($ticket->priority?->value, $priorities);
            $expectedIndex = array_search($expectedPriority, $priorities);

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
            $categoryId = $rule['value'] ?? null;
            $includeDescendants = $rule['include_descendants'] ?? false;

            if ($includeDescendants) {
                $category = \LucaLongo\LaravelHelpdesk\Models\Category::find($categoryId);
                if (! $category) {
                    return false;
                }

                $categoryIds = array_merge(
                    [$categoryId],
                    $category->getAllDescendants()->pluck('id')->toArray()
                );

                return $ticket->categories()->whereIn('category_id', $categoryIds)->exists();
            }

            return $ticket->categories()->where('category_id', $categoryId)->exists();
        });

        $this->registerEvaluator('has_tag', function ($rule, $ticket) {
            $tagIds = (array) ($rule['value'] ?? []);
            $operator = $rule['operator'] ?? 'any';

            if (empty($tagIds)) {
                return false;
            }

            if ($operator === 'any') {
                return $ticket->tags()->whereIn('tag_id', $tagIds)->exists();
            }

            if ($operator === 'all') {
                foreach ($tagIds as $tagId) {
                    if (! $ticket->tags()->where('tag_id', $tagId)->exists()) {
                        return false;
                    }
                }

                return true;
            }

            if ($operator === 'none') {
                return ! $ticket->tags()->whereIn('tag_id', $tagIds)->exists();
            }

            return false;
        });

        $this->registerEvaluator('time_since_created', function ($rule, $ticket) {
            $minutes = $rule['value'] ?? 0;
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
            $minutes = $rule['value'] ?? 0;
            $operator = $rule['operator'] ?? 'greater_than';

            $lastActivity = $ticket->comments()->latest()->first()?->created_at ?? $ticket->updated_at;
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
            $count = $rule['value'] ?? 0;
            $operator = $rule['operator'] ?? 'equals';
            $commentCount = $ticket->comments()->count();

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

            if (empty($keywords)) {
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
}