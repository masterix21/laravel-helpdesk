<?php

namespace LucaLongo\LaravelHelpdesk;

use Illuminate\Database\Eloquent\Model;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketComment;
use LucaLongo\LaravelHelpdesk\Services\CommentService;
use LucaLongo\LaravelHelpdesk\Services\SubscriptionService;
use LucaLongo\LaravelHelpdesk\Services\TicketService;

class LaravelHelpdesk
{
    public function __construct(
        protected readonly TicketService $tickets,
        protected readonly CommentService $comments,
        protected readonly SubscriptionService $subscriptions
    ) {}

    public function openTicket(array $attributes, ?Model $openedBy = null): Ticket
    {
        return $this->tickets->open($attributes, $openedBy);
    }

    public function updateTicket(Ticket $ticket, array $attributes): Ticket
    {
        return $this->tickets->update($ticket, $attributes);
    }

    public function transitionTicket(Ticket $ticket, TicketStatus $status): Ticket
    {
        return $this->tickets->transition($ticket, $status);
    }

    public function assignTicket(Ticket $ticket, ?Model $assignee): Ticket
    {
        return $this->tickets->assign($ticket, $assignee);
    }

    public function commentOnTicket(Ticket $ticket, string $body, ?Model $author = null, array $meta = []): TicketComment
    {
        return $this->comments->addComment($ticket, $body, $author, $meta);
    }
}
