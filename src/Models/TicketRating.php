<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'rating',
        'feedback',
        'resolved_at',
        'rated_at',
        'response_time_hours',
        'metadata',
    ];

    protected $casts = [
        'rating' => 'integer',
        'resolved_at' => 'datetime',
        'rated_at' => 'datetime',
        'response_time_hours' => 'integer',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'metadata' => '[]',
    ];

    public function getTable(): string
    {
        return config('helpdesk.database.prefix', 'helpdesk_').'ticket_ratings';
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        $userModel = config('helpdesk.user_model', \LucaLongo\LaravelHelpdesk\Tests\Fakes\User::class);

        return $this->belongsTo($userModel);
    }

    public function isPositive(): bool
    {
        return $this->rating >= 4;
    }

    public function isNeutral(): bool
    {
        return $this->rating === 3;
    }

    public function isNegative(): bool
    {
        return $this->rating <= 2;
    }

    public function getStars(): string
    {
        return str_repeat('★', $this->rating).str_repeat('☆', 5 - $this->rating);
    }

    public function getSatisfactionLevel(): string
    {
        return match (true) {
            $this->rating === 5 => 'Very Satisfied',
            $this->rating === 4 => 'Satisfied',
            $this->rating === 3 => 'Neutral',
            $this->rating === 2 => 'Dissatisfied',
            $this->rating === 1 => 'Very Dissatisfied',
            default => 'Not Rated',
        };
    }

    public function scopePositive($query)
    {
        return $query->where('rating', '>=', 4);
    }

    public function scopeNegative($query)
    {
        return $query->where('rating', '<=', 2);
    }

    public function scopeInPeriod($query, \DateTimeInterface $start, \DateTimeInterface $end)
    {
        return $query->whereBetween('rated_at', [$start, $end]);
    }
}
