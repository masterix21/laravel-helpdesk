<?php

namespace LucaLongo\LaravelHelpdesk\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\Models\VoiceNote;

class VoiceNoteProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public VoiceNote $voiceNote,
        public array $results
    ) {}
}