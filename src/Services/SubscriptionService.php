<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Database\Eloquent\Model;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Events\TicketSubscriptionCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketSubscriptionTriggered;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketSubscription;

class SubscriptionService
{
    public function subscribe(
        Ticket $ticket,
        Model $subscriber,
        TicketStatus|string|null $status = null
    ): TicketSubscription {
        $statusEnum = $this->resolveStatus($status);
        $attributes = $this->buildAttributes($ticket, $subscriber, $statusEnum);

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

    public function unsubscribe(
        Ticket $ticket,
        Model $subscriber,
        TicketStatus|string|null $status = null
    ): bool {
        $statusEnum = $this->resolveStatus($status);
        $attributes = $this->buildAttributes($ticket, $subscriber, $statusEnum);

        /** @var TicketSubscription|null $subscription */
        $subscription = $ticket->subscriptions()
            ->where($attributes)
            ->first();

        if ($subscription === null) {
            return false;
        }

        return (bool) $subscription->delete();
    }

    public function notifySubscribers(Ticket $ticket, TicketStatus $status): void
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

    private function buildAttributes(Ticket $ticket, Model $subscriber, ?TicketStatus $status): array
    {
        return [
            'ticket_id' => $ticket->getKey(),
            'subscriber_type' => $subscriber->getMorphClass(),
            'subscriber_id' => $subscriber->getKey(),
            'notify_on' => $status?->value,
        ];
    }
}