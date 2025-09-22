<?php

namespace LucaLongo\LaravelHelpdesk\Concerns;

trait ProvidesEnumValues
{
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
