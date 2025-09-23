<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Events\TicketAssigned;
use LucaLongo\LaravelHelpdesk\Events\TicketCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketStatusChanged;
use LucaLongo\LaravelHelpdesk\Exceptions\InvalidTransitionException;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Support\HelpdeskConfig;

class TicketService
{
    public function __construct(
        protected SlaService $slaService,
        protected SubscriptionService $subscriptionService
    ) {}

    public function open(array $attributes, ?Model $openedBy = null): Ticket
    {
        $type = HelpdeskConfig::typeFor($attributes['type'] ?? null);
        $priority = $this->resolvePriority($attributes['priority'] ?? null, $type);

        $ticket = new Ticket([
            'type' => $type,
            'subject' => $attributes['subject'],
            'description' => $attributes['description'] ?? null,
            'priority' => $priority,
            'due_at' => $this->resolveDueDate($attributes['due_at'] ?? null, $type),
            'meta' => $attributes['meta'] ?? [],
        ]);

        // Set ulid explicitly if provided (bypassing fillable)
        if (isset($attributes['ulid'])) {
            $ticket->ulid = $attributes['ulid'];
        } elseif (! $ticket->ulid) {
            $ticket->ulid = (string) Str::ulid();
        }

        // Set opened_at if not provided
        if (! isset($attributes['opened_at'])) {
            $ticket->opened_at = now();
        }

        if ($openedBy !== null) {
            $this->associateActor($ticket, $openedBy, 'opened_by');
        }

        // Calculate SLA due dates before saving
        $this->slaService->calculateSlaDueDates($ticket);

        $ticket->save();

        event(new TicketCreated($ticket));

        return $ticket->fresh(['opener', 'assignee']);
    }

    public function update(Ticket $ticket, array $attributes): Ticket
    {
        $changes = [];

        if (array_key_exists('subject', $attributes) && $ticket->subject !== $attributes['subject']) {
            $changes['subject'] = $attributes['subject'];
        }

        if (array_key_exists('description', $attributes) && $ticket->description !== $attributes['description']) {
            $changes['description'] = $attributes['description'];
        }

        if (array_key_exists('type', $attributes)) {
            $type = HelpdeskConfig::typeFor($attributes['type']);
            if ($ticket->type !== $type) {
                $changes['type'] = $type;
            }
        }

        if (array_key_exists('priority', $attributes)) {
            $priority = $this->resolvePriority($attributes['priority'], $changes['type'] ?? $ticket->type);
            if ($ticket->priority !== $priority) {
                $changes['priority'] = $priority;
            }
        }

        if (array_key_exists('due_at', $attributes)) {
            $computedDue = $this->resolveDueDate($attributes['due_at'], $changes['type'] ?? $ticket->type);
            if ($ticket->due_at?->equalTo($computedDue) === true) {
                $computedDue = $ticket->due_at;
            }

            if ($ticket->due_at !== $computedDue) {
                $changes['due_at'] = $computedDue;
            }
        }

        if (array_key_exists('meta', $attributes)) {
            $current = $ticket->meta?->getArrayCopy() ?? [];
            $ticket->meta = array_replace_recursive($current, $attributes['meta']);
        }

        if ($changes === []) {
            return $ticket;
        }

        $ticket->fill($changes);
        $ticket->save();

        return $ticket->fresh();
    }

    public function transition(Ticket $ticket, TicketStatus $next): Ticket
    {
        $previous = $ticket->status;

        if ($previous->canTransitionTo($next) === false) {
            throw InvalidTransitionException::make($previous, $next);
        }

        if ($ticket->transitionTo($next) === false) {
            throw InvalidTransitionException::make($previous, $next);
        }

        $freshTicket = $ticket->fresh();

        event(new TicketStatusChanged($freshTicket, $previous, $next));
        $this->subscriptionService->notifySubscribers($freshTicket, $next);

        return $freshTicket;
    }

    public function assign(Ticket $ticket, ?Model $assignee): Ticket
    {
        $changed = $this->updateAssignment($ticket, $assignee);

        if ($changed === false) {
            return $ticket;
        }

        event(new TicketAssigned($ticket->fresh(), $assignee));

        return $ticket->fresh();
    }

    private function resolvePriority(TicketPriority|string|null $priority, TicketType $type): TicketPriority
    {
        if ($priority instanceof TicketPriority) {
            return $priority;
        }

        if (is_string($priority)) {
            $enum = TicketPriority::tryFrom($priority);
            if ($enum !== null) {
                return $enum;
            }
        }

        return HelpdeskConfig::defaultPriorityFor($type);
    }

    private function resolveDueDate(mixed $value, TicketType $type): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value)) {
            return Carbon::parse($value);
        }

        $minutes = HelpdeskConfig::dueMinutesFor($type);

        if ($minutes === null) {
            return null;
        }

        return now()->addMinutes($minutes);
    }

    private function associateActor(Ticket $ticket, Model $actor, string $relation): void
    {
        $ticket->{$relation.'_type'} = $actor->getMorphClass();
        $ticket->{$relation.'_id'} = $actor->getKey();
    }

    private function updateAssignment(Ticket $ticket, ?Model $assignee): bool
    {
        if ($assignee === null) {
            return $ticket->releaseAssignment();
        }

        return $ticket->assignTo($assignee);
    }
}
