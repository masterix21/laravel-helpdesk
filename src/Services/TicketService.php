<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use ArrayAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Events\TicketAssigned;
use LucaLongo\LaravelHelpdesk\Events\TicketCommentAdded;
use LucaLongo\LaravelHelpdesk\Events\TicketCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketStatusChanged;
use LucaLongo\LaravelHelpdesk\Events\TicketSubscriptionCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketSubscriptionTriggered;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketComment;
use LucaLongo\LaravelHelpdesk\Models\TicketSubscription;
use LucaLongo\LaravelHelpdesk\Support\HelpdeskConfig;

class TicketService
{
    public function __construct(
        protected SlaService $slaService = new SlaService
    ) {}

    public function open(array $attributes, ?Model $openedBy = null): Ticket
    {
        $type = HelpdeskConfig::typeFor($attributes['type'] ?? null);
        $priority = $this->resolvePriority($attributes['priority'] ?? null, $type);

        $ticket = Ticket::query()->make([
            'ulid' => $attributes['ulid'] ?? (string) Str::ulid(),
            'type' => $type,
            'subject' => $attributes['subject'],
            'description' => $attributes['description'] ?? null,
            'priority' => $priority,
            'due_at' => $this->resolveDueDate($attributes['due_at'] ?? null, $type),
            'meta' => $this->resolveMeta($attributes['meta'] ?? []),
        ]);

        if ($openedBy !== null) {
            $this->associateActor($ticket, $openedBy, 'opened_by');
        }

        // Calculate SLA due dates before saving
        $this->slaService->calculateSlaDueDates($ticket);

        $ticket->save();

        event(new TicketCreated($ticket));

        return $ticket->fresh();
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
            $ticket->meta = $this->mergeMeta($ticket->meta?->getArrayCopy() ?? [], $attributes['meta']);
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
            return $ticket;
        }

        if ($ticket->transitionTo($next) === false) {
            return $ticket;
        }

        $freshTicket = $ticket->fresh();

        event(new TicketStatusChanged($freshTicket, $previous, $next));
        $this->notifySubscribers($freshTicket, $next);

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

    public function comment(Ticket $ticket, string $body, ?Model $author = null, array $meta = []): TicketComment
    {
        $comment = $ticket->comments()->make([
            'body' => $body,
            'meta' => $this->resolveMeta($meta),
        ]);

        if ($author !== null) {
            $comment->author()->associate($author);
        }

        $comment->save();

        // Mark first response for SLA if this is the first comment by support
        if ($ticket->first_response_at === null && $this->isInternalComment($author)) {
            $ticket->markFirstResponse();
        }

        event(new TicketCommentAdded($comment));

        return $comment->fresh();
    }

    private function isInternalComment(?Model $author): bool
    {
        // Override this method in your application to determine if the comment
        // is from internal support staff (vs customer)
        return $author !== null;
    }

    public function subscribe(Ticket $ticket, Model $subscriber, TicketStatus|string|null $status = null): TicketSubscription
    {
        $statusEnum = $this->resolveStatus($status);
        $attributes = $this->subscriptionAttributes($ticket, $subscriber, $statusEnum);

        /** @var TicketSubscription|null $existing */
        $existing = $ticket->subscriptions()
            ->where($attributes)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $subscription = $ticket->subscriptions()->create($attributes);

        event(new TicketSubscriptionCreated($subscription));

        return $subscription;
    }

    public function unsubscribe(Ticket $ticket, Model $subscriber, TicketStatus|string|null $status = null): bool
    {
        $statusEnum = $this->resolveStatus($status);
        $attributes = $this->subscriptionAttributes($ticket, $subscriber, $statusEnum);

        /** @var TicketSubscription|null $subscription */
        $subscription = $ticket->subscriptions()
            ->where($attributes)
            ->first();

        if ($subscription === null) {
            return false;
        }

        return (bool) $subscription->delete();
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

    private function resolveMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if ($meta instanceof \ArrayObject) {
            return $meta->getArrayCopy();
        }

        if ($meta instanceof ArrayAccess && method_exists($meta, 'toArray')) {
            return (array) $meta->toArray();
        }

        if (is_iterable($meta)) {
            return iterator_to_array($meta);
        }

        return [];
    }

    private function mergeMeta(array $current, mixed $incoming): array
    {
        $incomingArray = $this->resolveMeta($incoming);

        if ($incomingArray === []) {
            return $current;
        }

        return array_replace_recursive($current, $incomingArray);
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

    private function resolveStatus(TicketStatus|string|null $status): ?TicketStatus
    {
        if ($status instanceof TicketStatus) {
            return $status;
        }

        if (is_string($status)) {
            return TicketStatus::tryFrom($status);
        }

        return null;
    }

    private function subscriptionAttributes(Ticket $ticket, Model $subscriber, ?TicketStatus $status): array
    {
        return [
            'ticket_id' => $ticket->getKey(),
            'subscriber_type' => $subscriber->getMorphClass(),
            'subscriber_id' => $subscriber->getKey(),
            'notify_on' => $status?->value,
        ];
    }

    private function notifySubscribers(Ticket $ticket, TicketStatus $status): void
    {
        $subscriptions = $ticket->subscriptions()
            ->where(function ($query) use ($status): void {
                $query->whereNull('notify_on')
                    ->orWhere('notify_on', $status->value);
            })
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        event(new TicketSubscriptionTriggered($ticket, $status, $subscriptions));
    }
}
