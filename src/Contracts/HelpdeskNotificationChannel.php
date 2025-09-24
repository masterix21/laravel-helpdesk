<?php

declare(strict_types=1);

namespace LucaLongo\LaravelHelpdesk\Contracts;

use LucaLongo\LaravelHelpdesk\Notifications\NotificationPayload;

interface HelpdeskNotificationChannel
{
    public function shouldSend(NotificationPayload $payload): bool;

    public function send(NotificationPayload $payload): void;
}
