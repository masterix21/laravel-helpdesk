<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use LucaLongo\LaravelHelpdesk\Contracts\HelpdeskNotificationChannel;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Events\TicketAssigned;
use LucaLongo\LaravelHelpdesk\Events\TicketCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketStatusChanged;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Notifications\NotificationDispatcher;
use LucaLongo\LaravelHelpdesk\Notifications\NotificationPayload;
use LucaLongo\LaravelHelpdesk\Tests\Fakes\User;

it('does not throw when no channels are registered', function (): void {
    config()->set('helpdesk.notifications.ticket_created', true);

    $ticket = Ticket::factory()->create();

    expect(fn () => app(NotificationDispatcher::class)->onTicketCreated(new TicketCreated($ticket)))
        ->not->toThrow(\Throwable::class);
});

it('forwards payload to tagged channels', function (): void {
    config()->set('helpdesk.notifications.ticket_assigned', true);
    CollectingNotificationChannel::$lastPayload = null;

    $this->app->singleton(CollectingNotificationChannel::class);
    $this->app->tag(CollectingNotificationChannel::class, 'helpdesk.notification_channels');

    $ticket = Ticket::factory()->create();
    $assignee = User::factory()->create();

    app(NotificationDispatcher::class)->onTicketAssigned(new TicketAssigned($ticket, $assignee));

    $payload = CollectingNotificationChannel::$lastPayload;

    expect($payload)
        ->toBeInstanceOf(NotificationPayload::class)
        ->and($payload->event)->toBe('ticket_assigned')
        ->and($payload->actor)->toBe($assignee)
        ->and($payload->context['assignee'])->toBe($assignee);
});

it('logs failures when a channel throws an exception', function (): void {
    config()->set('helpdesk.notifications.ticket_status_changed', true);
    FailingNotificationChannel::$sendAttempts = 0;

    Log::spy();

    $this->app->singleton(FailingNotificationChannel::class);
    $this->app->tag(FailingNotificationChannel::class, 'helpdesk.notification_channels');

    $ticket = Ticket::factory()->create();

    app(NotificationDispatcher::class)->onTicketStatusChanged(
        new TicketStatusChanged($ticket, TicketStatus::Open, TicketStatus::Resolved)
    );

    expect(FailingNotificationChannel::$sendAttempts)->toBe(1);

    $expectedMessage = __('laravel-helpdesk::notifications.dispatcher.channel_failed');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($ticket, $expectedMessage): bool {
            return $message === $expectedMessage
                && ($context['channel'] ?? null) === FailingNotificationChannel::class
                && ($context['ticket_id'] ?? null) === $ticket->getKey();
        });
});

final class CollectingNotificationChannel implements HelpdeskNotificationChannel
{
    public static ?NotificationPayload $lastPayload = null;

    public function shouldSend(NotificationPayload $payload): bool
    {
        return true;
    }

    public function send(NotificationPayload $payload): void
    {
        self::$lastPayload = $payload;
    }
}

final class FailingNotificationChannel implements HelpdeskNotificationChannel
{
    public static int $sendAttempts = 0;

    public function shouldSend(NotificationPayload $payload): bool
    {
        return true;
    }

    public function send(NotificationPayload $payload): void
    {
        self::$sendAttempts++;

        throw new \RuntimeException('Channel failure');
    }
}
