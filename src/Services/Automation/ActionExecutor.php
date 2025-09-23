<?php

namespace LucaLongo\LaravelHelpdesk\Services\Automation;

use Illuminate\Support\Facades\Log;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Events\TicketEscalated;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketComment;

class ActionExecutor
{
    protected array $executors = [];

    public function __construct()
    {
        $this->registerDefaultExecutors();
    }

    public function registerExecutor(string $type, callable $executor): void
    {
        $this->executors[$type] = $executor;
    }

    public function execute($actions, Ticket $ticket): bool
    {
        if (empty($actions)) {
            return true;
        }

        try {
            foreach ($actions as $action) {
                if (! $this->executeAction($action, $ticket)) {
                    Log::warning('Automation action failed', [
                        'action' => $action,
                        'ticket_id' => $ticket->id,
                    ]);

                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Automation execution error', [
                'error' => $e->getMessage(),
                'ticket_id' => $ticket->id,
            ]);

            return false;
        }
    }

    protected function executeAction(array $action, Ticket $ticket): bool
    {
        $type = $action['type'] ?? null;

        if (! isset($this->executors[$type])) {
            return false;
        }

        return call_user_func($this->executors[$type], $action, $ticket);
    }

    protected function registerDefaultExecutors(): void
    {
        $this->registerExecutor('assign_to_user', function ($action, $ticket) {
            $userId = $action['user_id'] ?? null;

            if (! $userId) {
                return false;
            }

            $userModel = config('helpdesk.user_model', 'App\\Models\\User');
            $user = $userModel::find($userId);

            if (! $user) {
                return false;
            }

            return $ticket->assignTo($user);
        });

        $this->registerExecutor('assign_to_team', function ($action, $ticket) {
            $strategy = $action['strategy'] ?? 'round_robin';
            $teamId = $action['team_id'] ?? null;

            if (! $teamId) {
                return false;
            }

            $userModel = config('helpdesk.user_model', 'App\\Models\\User');

            $teamUsers = $userModel::where('team_id', $teamId)
                ->where('is_available', true)
                ->get();

            if ($teamUsers->isEmpty()) {
                return false;
            }

            $assignee = match ($strategy) {
                'least_busy' => $this->findLeastBusyUser($teamUsers),
                'round_robin' => $this->findNextRoundRobinUser($teamUsers, $teamId),
                'random' => $teamUsers->random(),
                default => $teamUsers->first(),
            };

            return $ticket->assignTo($assignee);
        });

        $this->registerExecutor('change_status', function ($action, $ticket) {
            $status = $action['status'] ?? null;

            if (! $status) {
                return false;
            }

            try {
                $statusEnum = TicketStatus::from($status);

                return $ticket->transitionTo($statusEnum);
            } catch (\Exception $e) {
                return false;
            }
        });

        $this->registerExecutor('change_priority', function ($action, $ticket) {
            $priority = $action['priority'] ?? null;

            if (! $priority) {
                return false;
            }

            try {
                $ticket->priority = TicketPriority::from($priority);

                return $ticket->save();
            } catch (\Exception $e) {
                return false;
            }
        });

        $this->registerExecutor('add_tags', function ($action, $ticket) {
            $tagIds = (array) ($action['tag_ids'] ?? []);

            if (empty($tagIds)) {
                return false;
            }

            $ticket->tags()->syncWithoutDetaching($tagIds);

            return true;
        });

        $this->registerExecutor('remove_tags', function ($action, $ticket) {
            $tagIds = (array) ($action['tag_ids'] ?? []);

            if (empty($tagIds)) {
                return false;
            }

            $ticket->tags()->detach($tagIds);

            return true;
        });

        $this->registerExecutor('add_category', function ($action, $ticket) {
            $categoryId = $action['category_id'] ?? null;

            if (! $categoryId) {
                return false;
            }

            $ticket->categories()->syncWithoutDetaching([$categoryId]);

            return true;
        });

        $this->registerExecutor('send_notification', function ($action, $ticket) {
            $recipients = $action['recipients'] ?? [];
            $template = $action['template'] ?? null;
            $channels = $action['channels'] ?? ['mail'];

            if (empty($recipients) || ! $template) {
                return false;
            }

            $notificationClass = config("helpdesk.notifications.{$template}");

            if (! $notificationClass) {
                return false;
            }

            foreach ($recipients as $recipientType) {
                $recipient = match ($recipientType) {
                    'assignee' => $ticket->assignee,
                    'opener' => $ticket->opener,
                    'subscribers' => $ticket->subscriptions->pluck('subscriber'),
                    default => null,
                };

                if ($recipient) {
                    try {
                        $recipient->notify(new $notificationClass($ticket, $channels));
                    } catch (\Exception $e) {
                        Log::error('Failed to send notification', [
                            'error' => $e->getMessage(),
                            'ticket_id' => $ticket->id,
                        ]);
                    }
                }
            }

            return true;
        });

        $this->registerExecutor('add_internal_note', function ($action, $ticket) {
            $note = $action['note'] ?? null;

            if (! $note) {
                return false;
            }

            TicketComment::create([
                'ticket_id' => $ticket->id,
                'body' => $note,
                'is_internal' => true,
                'author_type' => null,
                'author_id' => null,
                'meta' => [
                    'automation_rule_id' => $action['rule_id'] ?? null,
                    'type' => 'automation_note',
                ],
            ]);

            return true;
        });

        $this->registerExecutor('escalate', function ($action, $ticket) {
            $escalationLevel = $action['level'] ?? 1;
            $notifyManager = $action['notify_manager'] ?? true;

            $ticket->meta = array_merge($ticket->meta ?? [], [
                'escalation_level' => $escalationLevel,
                'escalated_at' => now()->toIso8601String(),
            ]);

            if ($action['priority'] ?? null) {
                $ticket->priority = TicketPriority::from($action['priority']);
            }

            if ($action['assignee_id'] ?? null) {
                $userModel = config('helpdesk.user_model', 'App\\Models\\User');
                $manager = $userModel::find($action['assignee_id']);

                if ($manager) {
                    $ticket->assignTo($manager);
                }
            }

            $ticket->save();

            event(new TicketEscalated($ticket, $escalationLevel));

            return true;
        });

        $this->registerExecutor('update_sla', function ($action, $ticket) {
            $firstResponseMinutes = $action['first_response_minutes'] ?? null;
            $resolutionMinutes = $action['resolution_minutes'] ?? null;

            if ($firstResponseMinutes !== null) {
                $ticket->first_response_due_at = $ticket->opened_at->addMinutes($firstResponseMinutes);
            }

            if ($resolutionMinutes !== null) {
                $ticket->resolution_due_at = $ticket->opened_at->addMinutes($resolutionMinutes);
            }

            return $ticket->save();
        });

        $this->registerExecutor('set_custom_field', function ($action, $ticket) {
            $field = $action['field'] ?? null;
            $value = $action['value'] ?? null;

            if (! $field) {
                return false;
            }

            $meta = $ticket->meta ?? [];
            $meta[$field] = $value;
            $ticket->meta = $meta;

            return $ticket->save();
        });

        $this->registerExecutor('trigger_webhook', function ($action, $ticket) {
            $url = $action['url'] ?? null;
            $method = $action['method'] ?? 'POST';
            $headers = $action['headers'] ?? [];

            if (! $url) {
                return false;
            }

            $payload = [
                'ticket' => $ticket->toArray(),
                'trigger' => $action['trigger'] ?? 'automation',
                'timestamp' => now()->toIso8601String(),
            ];

            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
                    ->timeout(10)
                    ->{strtolower($method)}($url, $payload);

                return $response->successful();
            } catch (\Exception $e) {
                Log::error('Webhook failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        });

        $this->registerExecutor('create_followup_task', function ($action, $ticket) {
            $delayMinutes = $action['delay_minutes'] ?? 1440;
            $taskType = $action['task_type'] ?? 'follow_up';

            \Illuminate\Support\Facades\Queue::later(
                now()->addMinutes($delayMinutes),
                new \LucaLongo\LaravelHelpdesk\Jobs\ProcessFollowupTask($ticket->id, $taskType, $action)
            );

            return true;
        });
    }

    protected function findLeastBusyUser($users)
    {
        return $users->sortBy(function ($user) {
            return Ticket::where('assigned_to_id', $user->id)
                ->where('assigned_to_type', get_class($user))
                ->whereNotIn('status', [TicketStatus::Closed->value, TicketStatus::Resolved->value])
                ->count();
        })->first();
    }

    protected function findNextRoundRobinUser($users, $teamId)
    {
        $cacheKey = "helpdesk.round_robin.team.{$teamId}";
        $lastIndex = cache($cacheKey, -1);
        $nextIndex = ($lastIndex + 1) % $users->count();

        cache([$cacheKey => $nextIndex], now()->addDay());

        return $users->values()->get($nextIndex);
    }
}
