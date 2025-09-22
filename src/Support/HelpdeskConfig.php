<?php

namespace LucaLongo\LaravelHelpdesk\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;

class HelpdeskConfig
{
    public static function typeFor(TicketType|string|null $type): TicketType
    {
        if ($type instanceof TicketType) {
            return $type;
        }

        if (is_string($type)) {
            return TicketType::tryFrom($type) ?? TicketType::ProductSupport;
        }

        return TicketType::ProductSupport;
    }

    public static function defaultPriorityFor(TicketType|string|null $type): TicketPriority
    {
        $resolvedType = self::typeFor($type);
        $types = Config::get('helpdesk.types', []);
        $priority = Arr::get($types, $resolvedType->value.'.default_priority');

        if (! is_string($priority)) {
            return TicketPriority::default();
        }

        $enum = TicketPriority::tryFrom($priority);

        if ($enum !== null) {
            return $enum;
        }

        return TicketPriority::default();
    }

    public static function dueMinutesFor(TicketType|string|null $type): ?int
    {
        $resolvedType = self::typeFor($type);
        $types = Config::get('helpdesk.types', []);
        $minutes = Arr::get($types, $resolvedType->value.'.due_minutes');

        if ($minutes === null) {
            return Config::get('helpdesk.defaults.due_minutes');
        }

        return is_numeric($minutes) ? (int) $minutes : null;
    }
}
