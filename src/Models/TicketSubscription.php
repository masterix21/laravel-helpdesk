<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;

class TicketSubscription extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_ticket_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'notify_on' => TicketStatus::class,
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function subscriber(): MorphTo
    {
        return $this->morphTo('subscriber');
    }
}
