<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LucaLongo\LaravelHelpdesk\Database\Factories\VoiceNoteFactory;
use LucaLongo\LaravelHelpdesk\Enums\EmotionalTone;
use LucaLongo\LaravelHelpdesk\Enums\VoiceNoteStatus;

class VoiceNote extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_voice_notes';

    protected $fillable = [
        'notable_type',
        'notable_id',
        'ticket_id',
        'user_id',
        'file_path',
        'mime_type',
        'duration_seconds',
        'file_size',
        'status',
        'transcription',
        'emotional_tone',
        'analyze_tone',
        'ai_provider',
        'ai_model',
        'processing_attempts',
        'last_error',
        'processed_at',
    ];

    protected $casts = [
        'analyze_tone' => 'boolean',
        'duration_seconds' => 'integer',
        'file_size' => 'integer',
        'processing_attempts' => 'integer',
        'processed_at' => 'datetime',
        'status' => VoiceNoteStatus::class,
        'emotional_tone' => EmotionalTone::class,
    ];

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        $userModel = config('helpdesk.user_model');

        return $this->belongsTo($userModel);
    }

    public function isProcessed(): bool
    {
        return $this->status === VoiceNoteStatus::ANALYZED
            || $this->status === VoiceNoteStatus::TRANSCRIBED;
    }

    public function isFailed(): bool
    {
        return $this->status === VoiceNoteStatus::FAILED;
    }

    public function canProcess(): bool
    {
        return $this->status === VoiceNoteStatus::PENDING
            || $this->status === VoiceNoteStatus::RETRY;
    }

    public function canRetry(): bool
    {
        return $this->status === VoiceNoteStatus::RETRY
            || ($this->status === VoiceNoteStatus::FAILED && $this->processing_attempts < 3);
    }

    public function incrementAttempts(): void
    {
        $this->increment('processing_attempts');
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => VoiceNoteStatus::PROCESSING]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => VoiceNoteStatus::FAILED,
            'last_error' => $error,
        ]);
    }

    public function markAsTranscribed(string $transcription, ?string $provider = null, ?string $model = null): void
    {
        $this->update([
            'status' => $this->analyze_tone ? VoiceNoteStatus::TRANSCRIBED : VoiceNoteStatus::ANALYZED,
            'transcription' => $transcription,
            'ai_provider' => $provider,
            'ai_model' => $model,
            'processed_at' => $this->analyze_tone ? null : now(),
        ]);
    }

    public function markAsAnalyzed(EmotionalTone $tone): void
    {
        $this->update([
            'status' => VoiceNoteStatus::ANALYZED,
            'emotional_tone' => $tone,
            'processed_at' => now(),
        ]);
    }

    protected static function newFactory(): VoiceNoteFactory
    {
        return VoiceNoteFactory::new();
    }
}
