<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\TicketComment;

readonly class TicketCommentAdded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public TicketComment $comment)
    {
    }
}
