<?php

namespace LucaLongo\LaravelHelpdesk\Enums;

use LucaLongo\LaravelHelpdesk\Concerns\ProvidesEnumValues;

enum TicketStatus: string
{
    use ProvidesEnumValues;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * @var array<string, array<string>>
     */
    private const TRANSITIONS = [
        self::Open->value => [
            self::Open->value,
            self::InProgress->value,
            self::Resolved->value,
            self::Closed->value,
        ],
        self::InProgress->value => [
            self::InProgress->value,
            self::Resolved->value,
            self::Closed->value,
        ],
        self::Resolved->value => [
            self::Resolved->value,
            self::Closed->value,
            self::InProgress->value,
        ],
        self::Closed->value => [
            self::Closed->value,
        ],
    ];

    public static function default(): self
    {
        return self::Open;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    public function canTransitionTo(self $next): bool
    {
        $allowed = self::TRANSITIONS[$this->value] ?? [];

        return in_array($next->value, $allowed, true);
    }
}
