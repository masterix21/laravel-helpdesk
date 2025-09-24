<?php

declare(strict_types=1);

namespace LucaLongo\LaravelHelpdesk\Notifications\Channels;

use Illuminate\Support\Facades\Log;
use LucaLongo\LaravelHelpdesk\Contracts\HelpdeskNotificationChannel;
use LucaLongo\LaravelHelpdesk\Notifications\NotificationPayload;

final class LoggingNotificationChannel implements HelpdeskNotificationChannel
{
    /**
     * @var array<int, string>
     */
    private array $allowedLevels = [
        'debug',
        'info',
        'notice',
        'warning',
        'error',
        'critical',
        'alert',
        'emergency',
    ];

    public function shouldSend(NotificationPayload $payload): bool
    {
        return true;
    }

    public function send(NotificationPayload $payload): void
    {
        $level = strtolower((string) config('helpdesk.notifications.channels.log.level', 'info'));

        if (! in_array($level, $this->allowedLevels, true)) {
            $level = 'info';
        }

        $eventLabel = $this->eventLabel($payload);

        $message = __('laravel-helpdesk::notifications.log.message', [
            'event' => $eventLabel,
            'ticket' => $payload->ticket->getKey(),
        ]);

        Log::log($level, $message, [
            'ticket_id' => $payload->ticket->getKey(),
            'ticket_subject' => $payload->ticket->subject,
            'event' => $payload->event,
            'event_label' => $eventLabel,
            'context' => $payload->context,
        ]);
    }

    private function eventLabel(NotificationPayload $payload): string
    {
        $label = __('laravel-helpdesk::notifications.events.'.$payload->event);

        return $label !== 'laravel-helpdesk::notifications.events.'.$payload->event
            ? $label
            : $payload->event;
    }
}
