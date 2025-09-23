<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_ticket_attachments';

    protected $fillable = [
        'ticket_id',
        'filename',
        'disk',
        'path',
        'mime_type',
        'size',
        'meta',
    ];

    protected $casts = [
        'meta' => AsArrayObject::class,
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
