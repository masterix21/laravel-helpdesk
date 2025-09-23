<?php

declare(strict_types=1);

namespace LucaLongo\LaravelHelpdesk\Enums;

use LucaLongo\LaravelHelpdesk\Concerns\ProvidesEnumValues;

enum TicketRelationType: string
{
    use ProvidesEnumValues;

    case Related = 'related';
    case Duplicate = 'duplicate';
    case Blocks = 'blocks';
    case BlockedBy = 'blocked_by';

    public function label(): string
    {
        return match ($this) {
            self::Related => __('Related to'),
            self::Duplicate => __('Duplicate of'),
            self::Blocks => __('Blocks'),
            self::BlockedBy => __('Blocked by'),
        };
    }

    public function inverseType(): self
    {
        return match ($this) {
            self::Related => self::Related,
            self::Duplicate => self::Duplicate,
            self::Blocks => self::BlockedBy,
            self::BlockedBy => self::Blocks,
        };
    }
}