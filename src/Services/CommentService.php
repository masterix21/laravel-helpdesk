<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Database\Eloquent\Model;
use LucaLongo\LaravelHelpdesk\Events\TicketCommentAdded;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketComment;

class CommentService
{
    public function addComment(
        Ticket $ticket,
        string $body,
        ?Model $author = null,
        array $meta = []
    ): TicketComment {
        $comment = $ticket->comments()->make([
            'body' => $body,
            'meta' => $meta,
        ]);

        if ($author !== null) {
            $comment->author()->associate($author);
        }

        $comment->save();

        if ($ticket->first_response_at === null && $this->isInternalComment($author)) {
            $ticket->markFirstResponse();
        }

        event(new TicketCommentAdded($comment));

        return $comment->fresh(['author']);
    }

    private function isInternalComment(?Model $author): bool
    {
        // Override this method in your application to determine if the comment
        // is from internal support staff (vs customer)
        return $author !== null;
    }
}