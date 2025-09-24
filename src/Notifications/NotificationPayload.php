<?php

declare(strict_types=1);

namespace LucaLongo\LaravelHelpdesk\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

final class NotificationPayload
{
    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $event,
        public readonly ?Model $actor = null,
        public readonly array $context = [],
    ) {}

    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->context, $key, $default);
    }
}
