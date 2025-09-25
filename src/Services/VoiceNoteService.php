<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LucaLongo\LaravelHelpdesk\Jobs\ProcessVoiceNoteJob;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketComment;
use LucaLongo\LaravelHelpdesk\Models\VoiceNote;
use LucaLongo\LaravelHelpdesk\Enums\VoiceNoteStatus;

class VoiceNoteService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('helpdesk.voice_notes', []);
    }

    public function createFromUpload(
        UploadedFile $file,
        int $userId,
        Model $notable,
        bool $analyzeTone = null
    ): VoiceNote {
        $this->validateFile($file);

        $analyzeTone = $analyzeTone ?? $this->config['analyze_tone_by_default'] ?? true;

        return DB::transaction(function () use ($file, $userId, $notable, $analyzeTone) {
            $path = $this->storeFile($file);

            $ticketId = null;
            if ($notable instanceof Ticket) {
                $ticketId = $notable->id;
            } elseif ($notable instanceof TicketComment) {
                $ticketId = $notable->ticket_id;
            }

            $voiceNote = VoiceNote::create([
                'notable_type' => get_class($notable),
                'notable_id' => $notable->id,
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'duration_seconds' => $this->getAudioDuration($file),
                'status' => VoiceNoteStatus::PENDING,
                'analyze_tone' => $analyzeTone,
            ]);

            if ($this->config['enabled'] ?? true) {
                ProcessVoiceNoteJob::dispatch($voiceNote)
                    ->onQueue($this->config['transcription']['queue'] ?? 'default');
            }

            return $voiceNote;
        });
    }

    public function createTicketFromVoiceNote(
        UploadedFile $file,
        int $userId,
        array $ticketData = []
    ): array {
        $ticket = Ticket::create(array_merge([
            'subject' => 'Voice Note Ticket',
            'description' => 'Processing voice note...',
            'reporter_id' => $userId,
        ], $ticketData));

        $voiceNote = $this->createFromUpload($file, $userId, $ticket);

        ProcessVoiceNoteJob::dispatchSync($voiceNote);

        $voiceNote->refresh();

        if (!$voiceNote->transcription) {
            throw new Exception('Failed to transcribe voice note');
        }

        $ticket->update([
            'subject' => $this->generateSubjectFromTranscription($voiceNote->transcription),
            'description' => $voiceNote->transcription,
        ]);

        return [
            'ticket' => $ticket,
            'voice_note' => $voiceNote,
        ];
    }

    public function addVoiceReplyToTicket(
        Ticket $ticket,
        UploadedFile $file,
        int $userId,
        bool $isInternal = false
    ): array {
        $comment = $ticket->comments()->create([
            'author_type' => config('helpdesk.user_model'),
            'author_id' => $userId,
            'body' => 'Processing voice note...',
        ]);

        $voiceNote = $this->createFromUpload($file, $userId, $comment);

        ProcessVoiceNoteJob::dispatchSync($voiceNote);

        $voiceNote->refresh();

        if (!$voiceNote->transcription) {
            throw new Exception('Failed to transcribe voice note');
        }

        $comment->update(['body' => $voiceNote->transcription]);

        return [
            'comment' => $comment,
            'voice_note' => $voiceNote,
        ];
    }

    public function retryFailed(): int
    {
        if (!($this->config['auto_retry_failed'] ?? true)) {
            return 0;
        }

        $retryAfterMinutes = $this->config['retry_after_minutes'] ?? 30;
        $cutoffTime = now()->subMinutes($retryAfterMinutes);

        $failedNotes = VoiceNote::where('status', VoiceNoteStatus::FAILED)
            ->where('processing_attempts', '<', 3)
            ->where('updated_at', '<=', $cutoffTime)
            ->get();

        foreach ($failedNotes as $voiceNote) {
            $voiceNote->update(['status' => VoiceNoteStatus::RETRY]);
            ProcessVoiceNoteJob::dispatch($voiceNote)
                ->onQueue($this->config['transcription']['queue'] ?? 'default');
        }

        return $failedNotes->count();
    }

    public function cleanupOldAudioFiles(): int
    {
        $cleanupDays = $this->config['cleanup_after_days'] ?? 90;

        if (!$cleanupDays) {
            return 0;
        }

        $cutoffDate = now()->subDays($cleanupDays);

        $voiceNotes = VoiceNote::where('processed_at', '<', $cutoffDate)
            ->whereNotNull('transcription')
            ->get();

        $deleted = 0;
        $disk = Storage::disk($this->config['storage_disk'] ?? 'local');

        foreach ($voiceNotes as $voiceNote) {
            if ($disk->exists($voiceNote->file_path)) {
                $disk->delete($voiceNote->file_path);
                $deleted++;
            }
        }

        return $deleted;
    }

    protected function validateFile(UploadedFile $file): void
    {
        $maxSize = ($this->config['max_file_size'] ?? 25600) * 1024;
        $allowedFormats = $this->config['allowed_formats'] ?? ['mp3', 'wav', 'ogg', 'm4a', 'webm'];

        if ($file->getSize() > $maxSize) {
            throw new Exception(sprintf(
                'File size exceeds maximum of %d MB',
                $maxSize / 1024 / 1024
            ));
        }

        $extension = $file->getClientOriginalExtension();
        if (!in_array(strtolower($extension), $allowedFormats)) {
            throw new Exception(sprintf(
                'File format %s is not allowed. Allowed formats: %s',
                $extension,
                implode(', ', $allowedFormats)
            ));
        }
    }

    protected function storeFile(UploadedFile $file): string
    {
        $disk = $this->config['storage_disk'] ?? 'local';
        $path = $this->config['storage_path'] ?? 'helpdesk/voice-notes';

        $fileName = sprintf(
            '%s_%s.%s',
            now()->format('YmdHis'),
            uniqid(),
            $file->getClientOriginalExtension()
        );

        return $file->storeAs($path, $fileName, $disk);
    }

    protected function getAudioDuration(UploadedFile $file): ?int
    {
        return null;
    }

    protected function generateSubjectFromTranscription(string $transcription): string
    {
        $words = str_word_count($transcription, 1);
        $firstWords = array_slice($words, 0, 10);
        $subject = implode(' ', $firstWords);

        if (count($words) > 10) {
            $subject .= '...';
        }

        return $subject ?: 'Voice Note Ticket';
    }
}