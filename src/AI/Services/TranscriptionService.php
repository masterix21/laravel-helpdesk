<?php

namespace LucaLongo\LaravelHelpdesk\AI\Services;

use Exception;
use Illuminate\Support\Facades\Storage;
use LucaLongo\LaravelHelpdesk\AI\AIProviderSelector;
use LucaLongo\LaravelHelpdesk\AI\Contracts\TranscribableContract;
use LucaLongo\LaravelHelpdesk\AI\Results\TranscriptionResult;
use LucaLongo\LaravelHelpdesk\Models\VoiceNote;

class TranscriptionService
{
    public function __construct(
        private AIProviderSelector $selector,
        private array $config = []
    ) {
        $this->config = array_merge(
            config('helpdesk.voice_notes', []),
            $this->config
        );
    }

    public function transcribe(VoiceNote $voiceNote): TranscriptionResult
    {
        $provider = $this->selector->selectProvider('transcribe_audio');

        if (!$provider) {
            throw new Exception('No AI provider available for audio transcription');
        }

        $adapter = $this->getAdapter($provider);

        if (!$adapter->supportsFormat($voiceNote->mime_type)) {
            throw new Exception("Audio format {$voiceNote->mime_type} not supported by {$provider}");
        }

        if ($voiceNote->duration_seconds > $adapter->getMaxDuration()) {
            throw new Exception("Audio duration exceeds maximum of {$adapter->getMaxDuration()} seconds");
        }

        if ($voiceNote->file_size > $adapter->getMaxFileSize()) {
            throw new Exception("File size exceeds maximum of {$adapter->getMaxFileSize()} bytes");
        }

        $filePath = $this->getFilePath($voiceNote);

        if (!file_exists($filePath)) {
            throw new Exception("Audio file not found at: {$filePath}");
        }

        $startTime = microtime(true);

        try {
            $result = $adapter->transcribe(
                $filePath,
                $this->config['transcription']['language'] ?? null
            );

            $processingTime = (int)((microtime(true) - $startTime) * 1000);

            return new TranscriptionResult(
                text: $result->text,
                language: $result->language,
                confidence: $result->confidence,
                alternatives: $result->alternatives,
                segments: $result->segments,
                provider: $provider,
                model: config("helpdesk.ai.providers.{$provider}.whisper_model") ?? config("helpdesk.ai.providers.{$provider}.model"),
                processingTime: $processingTime,
                metadata: $result->metadata
            );
        } catch (Exception $e) {
            throw new Exception("Transcription failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function transcribeBatch(array $voiceNotes): array
    {
        $results = [];

        foreach ($voiceNotes as $voiceNote) {
            try {
                $results[$voiceNote->id] = $this->transcribe($voiceNote);
            } catch (Exception $e) {
                $results[$voiceNote->id] = [
                    'error' => $e->getMessage(),
                    'voice_note_id' => $voiceNote->id,
                ];
            }
        }

        return $results;
    }

    public function validateAudioFile(string $filePath, string $mimeType): array
    {
        $errors = [];

        if (!file_exists($filePath)) {
            $errors[] = 'File not found';
        }

        $fileSize = filesize($filePath);
        $maxSize = ($this->config['max_file_size'] ?? 10240) * 1024;

        if ($fileSize > $maxSize) {
            $errors[] = sprintf('File size exceeds maximum of %d MB', $maxSize / 1024 / 1024);
        }

        if (!in_array($mimeType, $this->config['allowed_formats'] ?? [])) {
            $errors[] = sprintf('Format %s not allowed', $mimeType);
        }

        return $errors;
    }

    private function getAdapter(string $provider): TranscribableContract
    {
        $adapterClass = "LucaLongo\\LaravelHelpdesk\\AI\\Adapters\\" . ucfirst($provider) . "TranscriptionAdapter";

        if (!class_exists($adapterClass)) {
            throw new Exception("Adapter not found for provider: {$provider}");
        }

        return app($adapterClass);
    }

    private function getFilePath(VoiceNote $voiceNote): string
    {
        $disk = Storage::disk($this->config['storage_disk'] ?? 'local');

        return $disk->path($voiceNote->file_path);
    }

    public function cleanupOldFiles(int $daysOld = 90): int
    {
        $cutoffDate = now()->subDays($daysOld);

        $voiceNotes = VoiceNote::where('processed_at', '<', $cutoffDate)
            ->whereNotNull('transcription')
            ->get();

        $deleted = 0;

        foreach ($voiceNotes as $voiceNote) {
            try {
                $disk = Storage::disk($this->config['storage_disk'] ?? 'local');
                if ($disk->exists($voiceNote->file_path)) {
                    $disk->delete($voiceNote->file_path);
                    $deleted++;
                }
            } catch (Exception $e) {
                report($e);
            }
        }

        return $deleted;
    }
}