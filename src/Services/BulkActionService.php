<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class BulkActionService
{
    protected array $allowedActions = [
        'change_status',
        'change_priority',
        'assign',
        'unassign',
        'add_tags',
        'remove_tags',
        'add_category',
        'remove_category',
        'close',
        'delete',
        'apply_automation',
    ];

    public function applyAction(string $action, array $ticketIds, array $params = []): array
    {
        if (! in_array($action, $this->allowedActions)) {
            throw new \InvalidArgumentException("Action {$action} is not allowed");
        }

        $method = 'handle'.str_replace('_', '', ucwords($action, '_'));

        if (! method_exists($this, $method)) {
            throw new \InvalidArgumentException("Handler for action {$action} not found");
        }

        return DB::transaction(function () use ($action, $method, $ticketIds, $params) {
            $results = [
                'success' => 0,
                'failed' => 0,
                'details' => [],
            ];

            $tickets = Ticket::whereIn('id', $ticketIds)->get();

            foreach ($tickets as $ticket) {
                try {
                    $result = $this->$method($ticket, $params);

                    if ($result) {
                        $results['success']++;
                        $results['details'][$ticket->id] = ['status' => 'success'];
                    } else {
                        $results['failed']++;
                        $results['details'][$ticket->id] = ['status' => 'failed', 'reason' => 'Action returned false'];
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][$ticket->id] = ['status' => 'failed', 'error' => $e->getMessage()];

                    Log::error('Bulk action failed', [
                        'action' => $action,
                        'ticket_id' => $ticket->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $results;
        });
    }

    public function applyActionWithFilter(string $action, Builder $query, array $params = []): array
    {
        $ticketIds = $query->pluck('id')->toArray();

        return $this->applyAction($action, $ticketIds, $params);
    }

    protected function handleChangeStatus(Ticket $ticket, array $params): bool
    {
        if (! isset($params['status'])) {
            throw new \InvalidArgumentException('Status is required');
        }

        $status = TicketStatus::from($params['status']);

        return $ticket->transitionTo($status);
    }

    protected function handleChangePriority(Ticket $ticket, array $params): bool
    {
        if (! isset($params['priority'])) {
            throw new \InvalidArgumentException('Priority is required');
        }

        $ticket->priority = TicketPriority::from($params['priority']);

        return $ticket->save();
    }

    protected function handleAssign(Ticket $ticket, array $params): bool
    {
        if (! isset($params['assignee_id']) || ! isset($params['assignee_type'])) {
            throw new \InvalidArgumentException('Assignee ID and type are required');
        }

        $assigneeClass = $params['assignee_type'];
        $assignee = $assigneeClass::find($params['assignee_id']);

        if (! $assignee) {
            throw new \InvalidArgumentException('Assignee not found');
        }

        return $ticket->assignTo($assignee);
    }

    protected function handleUnassign(Ticket $ticket, array $params): bool
    {
        return $ticket->releaseAssignment();
    }

    protected function handleAddTags(Ticket $ticket, array $params): bool
    {
        if (! isset($params['tag_ids'])) {
            throw new \InvalidArgumentException('Tag IDs are required');
        }

        $ticket->tags()->syncWithoutDetaching($params['tag_ids']);

        return true;
    }

    protected function handleRemoveTags(Ticket $ticket, array $params): bool
    {
        if (! isset($params['tag_ids'])) {
            throw new \InvalidArgumentException('Tag IDs are required');
        }

        $ticket->tags()->detach($params['tag_ids']);

        return true;
    }

    protected function handleAddCategory(Ticket $ticket, array $params): bool
    {
        if (! isset($params['category_id'])) {
            throw new \InvalidArgumentException('Category ID is required');
        }

        $ticket->categories()->syncWithoutDetaching([$params['category_id']]);

        return true;
    }

    protected function handleRemoveCategory(Ticket $ticket, array $params): bool
    {
        if (! isset($params['category_id'])) {
            throw new \InvalidArgumentException('Category ID is required');
        }

        $ticket->categories()->detach($params['category_id']);

        return true;
    }

    protected function handleClose(Ticket $ticket, array $params): bool
    {
        $comment = $params['comment'] ?? 'Ticket closed via bulk action';

        if ($params['add_comment'] ?? false) {
            $ticket->comments()->create([
                'body' => $comment,
                'is_internal' => true,
                'author_type' => null,
                'author_id' => null,
            ]);
        }

        return $ticket->transitionTo(TicketStatus::Closed);
    }

    protected function handleDelete(Ticket $ticket, array $params): bool
    {
        if ($params['soft_delete'] ?? true) {
            return $ticket->delete();
        }

        return $ticket->forceDelete();
    }

    protected function handleApplyAutomation(Ticket $ticket, array $params): bool
    {
        if (! isset($params['rule_id'])) {
            throw new \InvalidArgumentException('Automation rule ID is required');
        }

        $automationService = app(AutomationService::class);
        $rule = \LucaLongo\LaravelHelpdesk\Models\AutomationRule::find($params['rule_id']);

        if (! $rule) {
            throw new \InvalidArgumentException('Automation rule not found');
        }

        return $rule->execute($ticket);
    }

    public function getAvailableActions(): array
    {
        return array_map(function ($action) {
            return [
                'key' => $action,
                'label' => ucwords(str_replace('_', ' ', $action)),
            ];
        }, $this->allowedActions);
    }

    public function buildFilterQuery(array $filters): Builder
    {
        $query = Ticket::query();

        if (isset($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        if (isset($filters['priority'])) {
            $query->whereIn('priority', (array) $filters['priority']);
        }

        if (isset($filters['type'])) {
            $query->whereIn('type', (array) $filters['type']);
        }

        if (isset($filters['assigned'])) {
            if ($filters['assigned'] === true) {
                $query->whereNotNull('assigned_to_id');
            } elseif ($filters['assigned'] === false) {
                $query->whereNull('assigned_to_id');
            }
        }

        if (isset($filters['category_ids'])) {
            $query->withCategories((array) $filters['category_ids']);
        }

        if (isset($filters['tag_ids'])) {
            $query->withTags((array) $filters['tag_ids']);
        }

        if (isset($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (isset($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }

        if (isset($filters['sla_status'])) {
            switch ($filters['sla_status']) {
                case 'breached':
                    $query->breachedSla();
                    break;
                case 'approaching':
                    $query->approachingSla($filters['sla_threshold_minutes'] ?? 60);
                    break;
                case 'within':
                    $query->withinSla();
                    break;
            }
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('ulid', $search);
            });
        }

        return $query;
    }
}
