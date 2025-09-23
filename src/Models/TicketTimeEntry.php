<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TicketTimeEntry extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_ticket_time_entries';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'description',
        'is_billable',
        'hourly_rate',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_billable' => 'boolean',
        'hourly_rate' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (TicketTimeEntry $entry) {
            if ($entry->ended_at && $entry->started_at && !$entry->duration_minutes) {
                $entry->duration_minutes = $entry->started_at->diffInMinutes($entry->ended_at);
            }
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        $userModel = config('helpdesk.user_model', 'App\Models\User');

        return $this->belongsTo($userModel);
    }

    public function isRunning(): bool
    {
        return $this->started_at && !$this->ended_at;
    }

    public function stop(): self
    {
        if (!$this->isRunning()) {
            return $this;
        }

        $this->ended_at = now();
        $this->duration_minutes = $this->started_at->diffInMinutes($this->ended_at);
        $this->save();

        return $this;
    }

    public function getDurationHoursAttribute(): float
    {
        return round(($this->duration_minutes ?? 0) / 60, 2);
    }

    public function getTotalCostAttribute(): ?float
    {
        if (!$this->is_billable || !$this->hourly_rate) {
            return null;
        }

        return round($this->duration_hours * $this->hourly_rate, 2);
    }

    public function scopeRunning($query)
    {
        return $query->whereNull('ended_at');
    }

    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    public function scopeNonBillable($query)
    {
        return $query->where('is_billable', false);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('started_at', [$startDate, $endDate]);
    }
}