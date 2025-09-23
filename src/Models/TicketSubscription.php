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

    protected $fillable = [
        'ticket_id',
        'subscriber_type',
        'subscriber_id',
        'notify_on',
    ];

    protected $casts = [
        'notify_on' => TicketStatus::class,
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = ['subscriber'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function subscriber(): MorphTo
    {
        return $this->morphTo('subscriber');
    }
}
