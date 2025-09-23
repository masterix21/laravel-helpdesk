<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LucaLongo\LaravelHelpdesk\Models\AutomationRule;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class AutomationService
{
    public function processTicket(Ticket $ticket, string $trigger = 'manual'): array
    {
        if (! config('helpdesk.automation.enabled', true)) {
            return [
                'executed' => [],
                'failed' => [],
                'skipped' => [],
            ];
        }

        $results = [
            'executed' => [],
            'failed' => [],
            'skipped' => [],
        ];

        $rules = AutomationRule::query()
            ->active()
            ->byTrigger($trigger)
            ->ordered()
            ->get();

        foreach ($rules as $rule) {
            try {
                if ($rule->evaluate($ticket)) {
                    if ($rule->execute($ticket)) {
                        $results['executed'][] = $rule->id;

                        if ($rule->stop_processing) {
                            break;
                        }
                    } else {
                        $results['failed'][] = $rule->id;
                    }
                } else {
                    $results['skipped'][] = $rule->id;
                }
            } catch (\Exception $e) {
                Log::error('Automation rule error', [
                    'rule_id' => $rule->id,
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);

                $results['failed'][] = $rule->id;
            }
        }

        return $results;
    }

    public function processBatch(Collection $tickets, string $trigger = 'batch'): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($tickets as $ticket) {
            $ticketResults = $this->processTicket($ticket, $trigger);

            if (! empty($ticketResults['executed'])) {
                $results['processed']++;
            } else {
                $results['failed']++;
            }

            $results['details'][$ticket->id] = $ticketResults;
        }

        return $results;
    }

    public function createRule(array $data): AutomationRule
    {
        return DB::transaction(function () use ($data) {
            $rule = AutomationRule::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'trigger' => $data['trigger'],
                'conditions' => $data['conditions'],
                'actions' => $data['actions'],
                'priority' => $data['priority'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'stop_processing' => $data['stop_processing'] ?? false,
            ]);

            Log::info('Automation rule created', ['rule_id' => $rule->id]);

            return $rule;
        });
    }

    public function updateRule(AutomationRule $rule, array $data): AutomationRule
    {
        return DB::transaction(function () use ($rule, $data) {
            $rule->update([
                'name' => $data['name'] ?? $rule->name,
                'description' => $data['description'] ?? $rule->description,
                'trigger' => $data['trigger'] ?? $rule->trigger,
                'conditions' => $data['conditions'] ?? $rule->conditions,
                'actions' => $data['actions'] ?? $rule->actions,
                'priority' => $data['priority'] ?? $rule->priority,
                'is_active' => $data['is_active'] ?? $rule->is_active,
                'stop_processing' => $data['stop_processing'] ?? $rule->stop_processing,
            ]);

            Log::info('Automation rule updated', ['rule_id' => $rule->id]);

            return $rule;
        });
    }

    public function deleteRule(AutomationRule $rule): bool
    {
        return DB::transaction(function () use ($rule) {
            $rule->executions()->delete();
            $result = $rule->delete();

            Log::info('Automation rule deleted', ['rule_id' => $rule->id]);

            return $result;
        });
    }

    public function getRuleTemplates(): array
    {
        return config('helpdesk.automation.templates', []);
    }

    public function applyTemplate(string $templateKey, array $overrides = []): AutomationRule
    {
        $templates = $this->getRuleTemplates();

        if (! isset($templates[$templateKey])) {
            throw new \InvalidArgumentException("Template {$templateKey} not found");
        }

        $data = array_merge($templates[$templateKey], $overrides);

        return $this->createRule($data);
    }

    public function getTriggers(): array
    {
        return config('helpdesk.automation.triggers', []);
    }

    public function testRule(AutomationRule $rule, Ticket $ticket): array
    {
        $result = [
            'evaluated' => false,
            'executed' => false,
            'conditions_met' => false,
            'actions_performed' => [],
            'errors' => [],
        ];

        try {
            $result['evaluated'] = true;
            $result['conditions_met'] = $rule->evaluate($ticket);

            if ($result['conditions_met']) {
                $evaluator = app('helpdesk.automation.evaluator');
                $executor = app('helpdesk.automation.executor');

                foreach ($rule->actions as $action) {
                    try {
                        $actionResult = $executor->executeAction($action, $ticket);
                        $result['actions_performed'][] = [
                            'type' => $action['type'] ?? 'unknown',
                            'success' => $actionResult,
                        ];
                    } catch (\Exception $e) {
                        $result['errors'][] = $e->getMessage();
                    }
                }

                $result['executed'] = empty($result['errors']);
            }
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    public function getRuleStatistics(AutomationRule $rule): array
    {
        $executions = $rule->executions();

        $total = $executions->count();
        $successful = $executions->where('success', true)->count();
        $failed = $total - $successful;

        $firstExecution = $executions->min('executed_at');
        $lastExecution = $executions->max('executed_at');

        return [
            'total_executions' => $total,
            'successful_executions' => $successful,
            'failed_executions' => $failed,
            'success_rate' => $total > 0
                ? round(($successful / $total) * 100, 2)
                : 0,
            'first_execution' => $firstExecution,
            'last_execution' => $lastExecution,
        ];
    }
}