<?php

namespace LucaLongo\LaravelHelpdesk\Enums;

use LucaLongo\LaravelHelpdesk\Concerns\ProvidesEnumValues;

enum VoiceNoteStatus: string
{
    use ProvidesEnumValues;

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case TRANSCRIBED = 'transcribed';
    case ANALYZED = 'analyzed';
    case FAILED = 'failed';
    case RETRY = 'retry';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::PROCESSING => __('Processing'),
            self::TRANSCRIBED => __('Transcribed'),
            self::ANALYZED => __('Analyzed'),
            self::FAILED => __('Failed'),
            self::RETRY => __('Retry'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::PROCESSING => 'blue',
            self::TRANSCRIBED => 'yellow',
            self::ANALYZED => 'green',
            self::FAILED => 'red',
            self::RETRY => 'orange',
        };
    }

    public function isCompleted(): bool
    {
        return in_array($this, [
            self::TRANSCRIBED,
            self::ANALYZED,
        ]);
    }

    public function canProcess(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::RETRY,
        ]);
    }
}
