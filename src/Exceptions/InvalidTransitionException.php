<?php

namespace LucaLongo\LaravelHelpdesk\Exceptions;

use Exception;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;

class InvalidTransitionException extends Exception
{
    public static function make(TicketStatus $from, TicketStatus $to): self
    {
        return new self(
            "Cannot transition ticket from {$from->value} to {$to->value}"
        );
    }
}