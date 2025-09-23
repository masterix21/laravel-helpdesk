<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class WorkflowService
{
    protected array $workflows = [];

    protected array $guards = [];

    protected array $actions = [];

    public function __construct()
    {
        $this->loadDefaultWorkflow();
    }

    public function registerWorkflow(string $name, array $definition): void
    {
        $this->workflows[$name] = $definition;
    }

    public function registerGuard(string $name, callable $guard): void
    {
        $this->guards[$name] = $guard;
    }

    public function registerAction(string $name, callable $action): void
    {
        $this->actions[$name] = $action;
    }

    public function canTransition(Ticket $ticket, TicketStatus $toStatus, ?string $workflow = null): bool
    {
        $workflow = $this->getWorkflow($workflow ?? 'default');
        $fromStatus = $ticket->status;

        $transitionKey = "{$fromStatus->value}:{$toStatus->value}";

        if (! isset($workflow['transitions'][$transitionKey])) {
            return false;
        }

        $transition = $workflow['transitions'][$transitionKey];

        if (! isset($transition['guards'])) {
            return true;
        }

        foreach ($transition['guards'] as $guardName) {
            if (! isset($this->guards[$guardName])) {
                continue;
            }

            if (! call_user_func($this->guards[$guardName], $ticket, $fromStatus, $toStatus)) {
                return false;
            }
        }

        return true;
    }

    public function transition(Ticket $ticket, TicketStatus $toStatus, ?string $workflow = null): bool
    {
        if (! $this->canTransition($ticket, $toStatus, $workflow)) {
            return false;
        }

        $workflow = $this->getWorkflow($workflow ?? 'default');
        $fromStatus = $ticket->status;
        $transitionKey = "{$fromStatus->value}:{$toStatus->value}";

        $transition = $workflow['transitions'][$transitionKey];

        DB::transaction(function () use ($ticket, $toStatus, $transition, $fromStatus) {
            $this->executeBeforeActions($transition, $ticket, $ticket->status, $toStatus);

            $ticket->status = $toStatus;

            if ($toStatus->isTerminal()) {
                $ticket->closed_at = now();
                $ticket->markResolution();
            }

            $ticket->save();

            $this->executeAfterActions($transition, $ticket, $fromStatus, $toStatus);

            if ($transition['trigger_automations'] ?? false) {
                $automationService = app(AutomationService::class);
                $automationService->processTicket($ticket, 'ticket_status_changed');
            }

            Log::info('Ticket transitioned', [
                'ticket_id' => $ticket->id,
                'from_status' => $fromStatus->value,
                'to_status' => $toStatus->value,
            ]);
        });

        return true;
    }

    protected function executeBeforeActions(array $transition, Ticket $ticket, TicketStatus $fromStatus, TicketStatus $toStatus): void
    {
        if (! isset($transition['before_actions'])) {
            return;
        }

        foreach ($transition['before_actions'] as $actionName) {
            if (! isset($this->actions[$actionName])) {
                continue;
            }

            call_user_func($this->actions[$actionName], $ticket, $fromStatus, $toStatus);
        }
    }

    protected function executeAfterActions(array $transition, Ticket $ticket, TicketStatus $fromStatus, TicketStatus $toStatus): void
    {
        if (! isset($transition['after_actions'])) {
            return;
        }

        foreach ($transition['after_actions'] as $actionName) {
            if (! isset($this->actions[$actionName])) {
                continue;
            }

            call_user_func($this->actions[$actionName], $ticket, $fromStatus, $toStatus);
        }
    }

    public function getAvailableTransitions(Ticket $ticket, ?string $workflow = null): array
    {
        $workflow = $this->getWorkflow($workflow ?? 'default');
        $currentStatus = $ticket->status;
        $available = [];

        foreach (TicketStatus::cases() as $status) {
            if ($status === $currentStatus) {
                continue;
            }

            if (! $this->canTransition($ticket, $status, $workflow)) {
                continue;
            }

            $transitionKey = "{$currentStatus->value}:{$status->value}";
            $transition = $workflow['transitions'][$transitionKey] ?? [];

            $available[] = [
                'status' => $status,
                'label' => $status->label(),
                'description' => $transition['description'] ?? null,
                'requires_comment' => $transition['requires_comment'] ?? false,
                'requires_resolution' => $transition['requires_resolution'] ?? false,
            ];
        }

        return $available;
    }

    protected function getWorkflow(string $name): array
    {
        if (! isset($this->workflows[$name])) {
            throw new \InvalidArgumentException("Workflow {$name} not found");
        }

        return $this->workflows[$name];
    }

    protected function loadDefaultWorkflow(): void
    {
        $this->workflows['default'] = [
            'name' => 'Default Helpdesk Workflow',
            'transitions' => [
                'open:in_progress' => [
                    'description' => 'Start working on the ticket',
                    'guards' => ['must_be_assigned'],
                    'after_actions' => ['mark_first_response'],
                ],
                'open:pending' => [
                    'description' => 'Waiting for customer response',
                    'requires_comment' => true,
                ],
                'open:resolved' => [
                    'description' => 'Mark as resolved',
                    'requires_resolution' => true,
                    'after_actions' => ['send_resolution_notification'],
                ],
                'open:closed' => [
                    'description' => 'Close without resolution',
                    'requires_comment' => true,
                ],
                'in_progress:pending' => [
                    'description' => 'Waiting for customer response',
                    'requires_comment' => true,
                ],
                'in_progress:resolved' => [
                    'description' => 'Mark as resolved',
                    'requires_resolution' => true,
                    'after_actions' => ['send_resolution_notification'],
                ],
                'in_progress:on_hold' => [
                    'description' => 'Put on hold',
                    'requires_comment' => true,
                ],
                'pending:open' => [
                    'description' => 'Customer responded',
                    'trigger_automations' => true,
                ],
                'pending:in_progress' => [
                    'description' => 'Resume work',
                    'guards' => ['must_be_assigned'],
                ],
                'pending:resolved' => [
                    'description' => 'Mark as resolved',
                    'requires_resolution' => true,
                ],
                'on_hold:in_progress' => [
                    'description' => 'Resume work',
                    'guards' => ['must_be_assigned'],
                ],
                'on_hold:closed' => [
                    'description' => 'Close ticket',
                    'requires_comment' => true,
                ],
                'resolved:closed' => [
                    'description' => 'Close resolved ticket',
                    'after_actions' => ['request_rating'],
                ],
                'resolved:open' => [
                    'description' => 'Reopen ticket',
                    'requires_comment' => true,
                    'after_actions' => ['notify_reopened'],
                ],
                'closed:open' => [
                    'description' => 'Reopen closed ticket',
                    'requires_comment' => true,
                    'guards' => ['can_reopen'],
                    'after_actions' => ['notify_reopened'],
                ],
            ],
        ];

        $this->guards['must_be_assigned'] = function (Ticket $ticket) {
            return $ticket->assigned_to_id !== null;
        };

        $this->guards['can_reopen'] = function (Ticket $ticket) {
            $daysSinceClosed = $ticket->closed_at?->diffInDays(now()) ?? 0;

            return $daysSinceClosed <= 30;
        };

        $this->actions['mark_first_response'] = function (Ticket $ticket) {
            $ticket->markFirstResponse();
        };

        $this->actions['send_resolution_notification'] = function (Ticket $ticket) {
            // Send notification logic
        };

        $this->actions['request_rating'] = function (Ticket $ticket) {
            // Request rating logic
        };

        $this->actions['notify_reopened'] = function (Ticket $ticket) {
            // Notify about reopening
        };
    }

    public function loadWorkflowFromConfig(): void
    {
        $workflowConfig = config('helpdesk.workflow', []);

        if (empty($workflowConfig)) {
            return;
        }

        foreach ($workflowConfig as $name => $definition) {
            $this->registerWorkflow($name, $definition);
        }
    }
}