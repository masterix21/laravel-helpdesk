<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketRelationType;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Events\TicketAssigned;
use LucaLongo\LaravelHelpdesk\Events\TicketCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketStatusChanged;
use LucaLongo\LaravelHelpdesk\Exceptions\InvalidTransitionException;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketRelation;
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

        if (isset($attributes['ulid'])) {
            $ticket->ulid = $attributes['ulid'];
        } elseif (!$ticket->ulid) {
            $ticket->ulid = (string) Str::ulid();
        }

        if (!isset($attributes['opened_at'])) {
            $ticket->opened_at = now();
        }

        if ($openedBy !== null) {
            $this->associateActor($ticket, $openedBy, 'opened_by');
        }

        $this->slaService->calculateSlaDueDates($ticket);

        $ticket->save();

        event(new TicketCreated($ticket));

        return $ticket->load(['opener', 'assignee']);
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

        return $ticket->refresh();
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

        $ticket->refresh();

        event(new TicketStatusChanged($ticket, $previous, $next));
        $this->subscriptionService->notifySubscribers($ticket, $next);

        return $ticket;
    }

    public function assign(Ticket $ticket, ?Model $assignee): Ticket
    {
        $changed = $this->updateAssignment($ticket, $assignee);

        if ($changed === false) {
            return $ticket;
        }

        event(new TicketAssigned($ticket->refresh(), $assignee));

        return $ticket;
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

    public function mergeTickets(Ticket $target, Ticket|array $sources, ?string $reason = null): Ticket
    {
        $sourceTickets = is_array($sources) ? $sources : [$sources];

        DB::transaction(function () use ($target, $sourceTickets, $reason) {
            foreach ($sourceTickets as $source) {
                if ($source->id === $target->id) {
                    continue;
                }

                if ($source->isMerged()) {
                    throw new \InvalidArgumentException(__('Ticket :ticket is already merged', ['ticket' => $source->ticket_number]));
                }

                // Transfer comments
                $source->comments()->update(['ticket_id' => $target->id]);

                // Transfer attachments
                $source->attachments()->update(['ticket_id' => $target->id]);

                // Transfer subscriptions (avoiding duplicates)
                $source->subscriptions->each(function ($subscription) use ($target) {
                    $existing = $target->subscriptions()
                        ->where('subscriber_type', $subscription->subscriber_type)
                        ->where('subscriber_id', $subscription->subscriber_id)
                        ->exists();

                    if (! $existing) {
                        $subscription->update(['ticket_id' => $target->id]);
                    } else {
                        $subscription->delete();
                    }
                });

                // Move child tickets to target
                if ($source->hasChildren()) {
                    $source->children->each(function ($child) use ($target) {
                        $child->appendToNode($target)->save();
                    });
                }

                // Mark source as merged
                $source->update([
                    'merged_to_id' => $target->id,
                    'merged_at' => now(),
                    'merge_reason' => $reason,
                    'status' => TicketStatus::Closed,
                ]);

                event(new \LucaLongo\LaravelHelpdesk\Events\TicketMerged($source, $target, $reason));
            }
        });

        return $target->load(['comments', 'attachments', 'subscriptions']);
    }

    public function createRelation(Ticket $ticket, Ticket $relatedTicket, TicketRelationType $type, ?string $notes = null): TicketRelation
    {
        // Check if relation already exists
        $exists = TicketRelation::where('ticket_id', $ticket->id)
            ->where('related_ticket_id', $relatedTicket->id)
            ->where('relation_type', $type->value)
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException(__('Relation already exists between these tickets'));
        }

        $relation = TicketRelation::create([
            'ticket_id' => $ticket->id,
            'related_ticket_id' => $relatedTicket->id,
            'relation_type' => $type,
            'notes' => $notes,
        ]);

        // Create inverse relation if needed
        $inverseType = $type->inverseType();
        if ($type !== $inverseType) {
            TicketRelation::create([
                'ticket_id' => $relatedTicket->id,
                'related_ticket_id' => $ticket->id,
                'relation_type' => $inverseType,
                'notes' => $notes,
            ]);
        }

        event(new \LucaLongo\LaravelHelpdesk\Events\TicketRelationCreated($ticket, $relatedTicket, $type));

        return $relation;
    }

    public function removeRelation(Ticket $ticket, Ticket $relatedTicket, TicketRelationType $type): void
    {
        // Remove direct relation
        TicketRelation::where('ticket_id', $ticket->id)
            ->where('related_ticket_id', $relatedTicket->id)
            ->where('relation_type', $type->value)
            ->delete();

        // Remove inverse relation
        $inverseType = $type->inverseType();
        TicketRelation::where('ticket_id', $relatedTicket->id)
            ->where('related_ticket_id', $ticket->id)
            ->where('relation_type', $inverseType->value)
            ->delete();

        event(new \LucaLongo\LaravelHelpdesk\Events\TicketRelationRemoved($ticket, $relatedTicket, $type));
    }

    public function createChildTicket(Ticket $parent, array $attributes, ?Model $openedBy = null): Ticket
    {
        $child = $this->open($attributes, $openedBy);
        $child->appendToNode($parent)->save();

        event(new \LucaLongo\LaravelHelpdesk\Events\ChildTicketCreated($parent, $child));

        return $child;
    }

    public function moveToParent(Ticket $ticket, ?Ticket $newParent): Ticket
    {
        if ($newParent === null) {
            $ticket->makeRoot()->save();
        } else {
            $ticket->appendToNode($newParent)->save();
        }

        return $ticket->refresh();
    }

}
