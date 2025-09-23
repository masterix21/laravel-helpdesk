<?php

namespace LucaLongo\LaravelHelpdesk\Exceptions;

use Exception;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class SlaBreachException extends Exception
{
    public static function firstResponse(Ticket $ticket): self
    {
        return new self(
            "SLA breach: First response overdue for ticket {$ticket->ulid}"
        );
    }

    public static function resolution(Ticket $ticket): self
    {
        return new self(
            "SLA breach: Resolution overdue for ticket {$ticket->ulid}"
        );
    }
}