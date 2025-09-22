<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class SlaService
{
    public function calculateSlaDueDates(Ticket $ticket): void
    {
        if (! config('helpdesk.sla.enabled', false)) {
            return;
        }

        $slaRules = $this->getSlaRules($ticket->type, $ticket->priority);

        if (empty($slaRules)) {
            return;
        }

        $baseTime = $ticket->opened_at ?? now();

        if (isset($slaRules['first_response'])) {
            $ticket->first_response_due_at = $baseTime->copy()->addMinutes($slaRules['first_response']);
        }

        if (isset($slaRules['resolution'])) {
            $ticket->resolution_due_at = $baseTime->copy()->addMinutes($slaRules['resolution']);
        }
    }

    public function checkSlaCompliance(Ticket $ticket): array
    {
        $compliance = [
            'first_response' => [
                'status' => 'pending',
                'percentage' => null,
                'overdue' => false,
            ],
            'resolution' => [
                'status' => 'pending',
                'percentage' => null,
                'overdue' => false,
            ],
        ];

        // Check first response SLA
        if ($ticket->first_response_due_at) {
            if ($ticket->first_response_at) {
                $compliance['first_response']['status'] = $ticket->first_response_at->lessThanOrEqualTo($ticket->first_response_due_at)
                    ? 'met'
                    : 'breached';
            } else {
                $compliance['first_response']['overdue'] = now()->greaterThan($ticket->first_response_due_at);
            }
            $compliance['first_response']['percentage'] = $ticket->getSlaCompliancePercentage('first_response');
        }

        // Check resolution SLA
        if ($ticket->resolution_due_at) {
            if ($ticket->status->isTerminal()) {
                $compliance['resolution']['status'] = ($ticket->closed_at ?? now())->lessThanOrEqualTo($ticket->resolution_due_at)
                    ? 'met'
                    : 'breached';
            } else {
                $compliance['resolution']['overdue'] = now()->greaterThan($ticket->resolution_due_at);
            }
            $compliance['resolution']['percentage'] = $ticket->getSlaCompliancePercentage('resolution');
        }

        return $compliance;
    }

    public function recordSlaBreachIfNeeded(Ticket $ticket): bool
    {
        $breached = false;
        $breachType = null;

        if ($ticket->first_response_due_at &&
            ! $ticket->first_response_at &&
            now()->greaterThan($ticket->first_response_due_at)) {
            $breached = true;
            $breachType = 'first_response';
        }

        if ($ticket->resolution_due_at &&
            ! $ticket->status->isTerminal() &&
            now()->greaterThan($ticket->resolution_due_at)) {
            $breached = true;
            $breachType = $breachType ?? 'resolution';
        }

        if ($breached && ! $ticket->sla_breached) {
            $ticket->sla_breached = true;
            $ticket->sla_breach_type = $breachType;

            return $ticket->save();
        }

        return false;
    }

    protected function getSlaRules(TicketType $type, TicketPriority $priority): array
    {
        // Check for type-specific overrides first
        $typeOverrides = config("helpdesk.sla.type_overrides.{$type->value}.{$priority->value}");

        if ($typeOverrides) {
            return $typeOverrides;
        }

        // Fall back to general priority rules
        return config("helpdesk.sla.rules.{$priority->value}", []);
    }
}
