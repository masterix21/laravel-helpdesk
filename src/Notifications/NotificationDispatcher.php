<?php

declare(strict_types=1);

namespace LucaLongo\LaravelHelpdesk\Notifications;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use LucaLongo\LaravelHelpdesk\Contracts\HelpdeskNotificationChannel;
use LucaLongo\LaravelHelpdesk\Events\TicketAssigned;
use LucaLongo\LaravelHelpdesk\Events\TicketCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketStatusChanged;
use Throwable;

class NotificationDispatcher
{
    public function __construct(protected Container $container) {}

    public function onTicketCreated(TicketCreated $event): void
    {
        if (! $this->isEventEnabled('ticket_created')) {
            return;
        }

        $payload = new NotificationPayload(
            ticket: $event->ticket,
            event: 'ticket_created',
            context: [
                'trigger' => 'TicketCreated',
            ],
        );

        $this->dispatch($payload);
    }

    public function onTicketAssigned(TicketAssigned $event): void
    {
        if (! $this->isEventEnabled('ticket_assigned')) {
            return;
        }

        $payload = new NotificationPayload(
            ticket: $event->ticket,
            event: 'ticket_assigned',
            actor: $event->assignee,
            context: [
                'assignee' => $event->assignee,
                'trigger' => 'TicketAssigned',
            ],
        );

        $this->dispatch($payload);
    }

    public function onTicketStatusChanged(TicketStatusChanged $event): void
    {
        if (! $this->isEventEnabled('ticket_status_changed')) {
            return;
        }

        $payload = new NotificationPayload(
            ticket: $event->ticket,
            event: 'ticket_status_changed',
            context: [
                'previous_status' => $event->previous,
                'next_status' => $event->next,
                'trigger' => 'TicketStatusChanged',
            ],
        );

        $this->dispatch($payload);
    }

    protected function dispatch(NotificationPayload $payload): void
    {
        $channels = iterator_to_array($this->resolveChannels());

        if ($channels === []) {
            Log::debug(__('laravel-helpdesk::notifications.dispatcher.no_channels'), [
                'event' => $payload->event,
            ]);

            return;
        }

        foreach ($channels as $channel) {
            if (! $channel->shouldSend($payload)) {
                continue;
            }

            try {
                $channel->send($payload);
            } catch (Throwable $exception) {
                Log::warning(__('laravel-helpdesk::notifications.dispatcher.channel_failed'), [
                    'channel' => $channel::class,
                    'event' => $payload->event,
                    'ticket_id' => $payload->ticket->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return iterable<HelpdeskNotificationChannel>
     */
    protected function resolveChannels(): iterable
    {
        return $this->container->tagged('helpdesk.notification_channels');
    }

    protected function isEventEnabled(string $configKey): bool
    {
        return (bool) config("helpdesk.notifications.{$configKey}", false);
    }
}
