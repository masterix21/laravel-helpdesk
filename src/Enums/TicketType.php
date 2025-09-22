<?php

namespace LucaLongo\LaravelHelpdesk\Enums;

use LucaLongo\LaravelHelpdesk\Concerns\ProvidesEnumValues;

enum TicketType: string
{
    use ProvidesEnumValues;

    case ProductSupport = 'product_support';
    case Commercial = 'commercial';

    public function label(): string
    {
        return match ($this) {
            self::ProductSupport => 'Supporto prodotto',
            self::Commercial => 'Richiesta commerciale',
        };
    }
}
