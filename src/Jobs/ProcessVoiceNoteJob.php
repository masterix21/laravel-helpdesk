<?php

namespace LucaLongo\LaravelHelpdesk\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LucaLongo\LaravelHelpdesk\AI\AIService;
use LucaLongo\LaravelHelpdesk\Enums\VoiceNoteStatus;
use LucaLongo\LaravelHelpdesk\Events\VoiceNoteProcessed;
use LucaLongo\LaravelHelpdesk\Models\VoiceNote;

class ProcessVoiceNoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 180, 600];

    public $timeout = 300;

    public function __construct(
        public VoiceNote $voiceNote
    ) {}

    public function handle(AIService $aiService): void
    {
        if (! $this->voiceNote->canProcess()) {
            return;
        }

        $this->voiceNote->markAsProcessing();
        $this->voiceNote->incrementAttempts();

        try {
            $results = $aiService->processVoiceNote($this->voiceNote);

            if (! empty($results['errors'])) {
                foreach ($results['errors'] as $error) {
                    report(new Exception($error));
                }
            }

            event(new VoiceNoteProcessed($this->voiceNote, $results));

        } catch (Exception $e) {
            $this->handleFailure($e);
            throw $e;
        }
    }

    protected function handleFailure(Exception $e): void
    {
        if ($this->attempts() >= $this->tries) {
            $this->voiceNote->markAsFailed($e->getMessage());
        } else {
            $this->voiceNote->update([
                'status' => VoiceNoteStatus::RETRY,
                'last_error' => $e->getMessage(),
            ]);
        }

        report($e);
    }

    public function failed(Exception $exception): void
    {
        $this->voiceNote->markAsFailed($exception->getMessage());
        report($exception);
    }

    public function tags(): array
    {
        return [
            'voice-note',
            'voice-note:'.$this->voiceNote->id,
            'ticket:'.$this->voiceNote->ticket_id,
        ];
    }
}
