<?php

declare(strict_types=1);

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LucaLongo\LaravelHelpdesk\Enums\TicketRelationType;

class TicketRelation extends Model
{
    protected $table = 'helpdesk_ticket_relations';

    protected $fillable = [
        'ticket_id',
        'related_ticket_id',
        'relation_type',
        'notes',
    ];

    protected $casts = [
        'relation_type' => TicketRelationType::class,
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function relatedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'related_ticket_id');
    }
}
