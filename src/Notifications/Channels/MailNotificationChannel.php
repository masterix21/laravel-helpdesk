<?php

declare(strict_types=1);

namespace LucaLongo\LaravelHelpdesk\Notifications\Channels;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use LucaLongo\LaravelHelpdesk\Contracts\HelpdeskNotificationChannel;
use LucaLongo\LaravelHelpdesk\Notifications\NotificationPayload;

final class MailNotificationChannel implements HelpdeskNotificationChannel
{
    public function shouldSend(NotificationPayload $payload): bool
    {
        return $this->resolveRecipients() !== [];
    }

    public function send(NotificationPayload $payload): void
    {
        $recipients = $this->resolveRecipients();

        if ($recipients === []) {
            return;
        }

        $subject = $this->buildSubject($payload);
        $body = $this->buildBody($payload);

        foreach ($recipients as $recipient) {
            Mail::raw($body, static function ($message) use ($recipient, $subject): void {
                $message->to($recipient)->subject($subject);
            });
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveRecipients(): array
    {
        $to = config('helpdesk.notifications.channels.mail.to');

        if ($to === null) {
            return [];
        }

        if (is_string($to)) {
            return [$to];
        }

        if (is_array($to)) {
            return array_values(array_filter($to, static fn ($recipient) => is_string($recipient) && $recipient !== ''));
        }

        return [];
    }

    private function buildSubject(NotificationPayload $payload): string
    {
        $ticketIdentifier = $payload->ticket->ticket_number
            ?? $payload->ticket->ulid
            ?? (string) $payload->ticket->getKey();

        $eventLabel = $this->eventLabel($payload);

        return __('laravel-helpdesk::notifications.mail.subject', [
            'event' => $eventLabel,
            'ticket' => $ticketIdentifier,
        ]);
    }

    private function buildBody(NotificationPayload $payload): string
    {
        $eventLabel = $this->eventLabel($payload);

        $lines = [
            __('laravel-helpdesk::notifications.mail.body.event_line', ['event' => $eventLabel]),
            __('laravel-helpdesk::notifications.mail.body.ticket_line', ['subject' => $payload->ticket->subject ?? __('laravel-helpdesk::notifications.mail.body.subject_unavailable')]),
            __('laravel-helpdesk::notifications.mail.body.id_line', ['id' => $payload->ticket->getKey()]),
        ];

        if ($payload->context !== []) {
            $encodedContext = json_encode($payload->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $lines[] = __('laravel-helpdesk::notifications.mail.body.context_line', [
                'context' => $encodedContext === false
                    ? __('laravel-helpdesk::notifications.mail.body.context_unavailable')
                    : $encodedContext,
            ]);
        }

        return implode(PHP_EOL, $lines);
    }

    private function eventLabel(NotificationPayload $payload): string
    {
        $label = __('laravel-helpdesk::notifications.events.'.$payload->event);

        return $label !== 'laravel-helpdesk::notifications.events.'.$payload->event
            ? $label
            : Str::of($payload->event)->replace('_', ' ')->title();
    }
}
