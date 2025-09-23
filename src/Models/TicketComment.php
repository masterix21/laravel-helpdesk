<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TicketComment extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_ticket_comments';

    protected $fillable = [
        'ticket_id',
        'body',
        'meta',
        'author_type',
        'author_id',
    ];

    protected $casts = [
        'meta' => AsArrayObject::class,
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = ['author'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author(): MorphTo
    {
        return $this->morphTo('author');
    }
}
