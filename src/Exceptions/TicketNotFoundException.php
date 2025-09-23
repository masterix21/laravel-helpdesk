<?php

namespace LucaLongo\LaravelHelpdesk\Exceptions;

use Exception;

class TicketNotFoundException extends Exception
{
    public static function withId(string|int $id): self
    {
        return new self("Ticket with ID {$id} not found");
    }

    public static function withUlid(string $ulid): self
    {
        return new self("Ticket with ULID {$ulid} not found");
    }
}
