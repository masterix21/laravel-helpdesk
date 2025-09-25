<?php

namespace LucaLongo\LaravelHelpdesk\AI\Adapters;

use Exception;
use Illuminate\Support\Facades\Http;
use LucaLongo\LaravelHelpdesk\AI\Contracts\TranscribableContract;
use LucaLongo\LaravelHelpdesk\AI\Results\TranscriptionResult;

class OpenaiTranscriptionAdapter implements TranscribableContract
{
    private string $apiKey;
    private string $model;
    private array $supportedFormats = [
        'audio/mpeg',
        'audio/mp3',
        'audio/mp4',
        'audio/mpeg',
        'audio/mpga',
        'audio/m4a',
        'audio/wav',
        'audio/webm',
        'audio/ogg',
    ];

    public function __construct()
    {
        $this->apiKey = config('helpdesk.ai.providers.openai.api_key');
        $this->model = config('helpdesk.ai.providers.openai.whisper_model', 'whisper-1');

        if (!$this->apiKey) {
            throw new Exception('OpenAI API key not configured');
        }
    }

    public function transcribe(string $filePath, ?string $language = null): TranscriptionResult
    {
        $startTime = microtime(true);

        try {
            $response = Http::withToken($this->apiKey)
                ->asMultipart()
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post('https://api.openai.com/v1/audio/transcriptions', array_filter([
                    'model' => $this->model,
                    'language' => $language,
                    'response_format' => 'verbose_json',
                    'timestamp_granularities' => ['segment', 'word'],
                ]));

            if (!$response->successful()) {
                throw new Exception('OpenAI API error: ' . $response->body());
            }

            $data = $response->json();
            $processingTime = (int)((microtime(true) - $startTime) * 1000);

            $alternatives = null;
            if (isset($data['words'])) {
                $alternatives = $this->extractAlternatives($data['words']);
            }

            $segments = null;
            if (isset($data['segments'])) {
                $segments = array_map(fn($segment) => [
                    'start' => $segment['start'] ?? 0,
                    'end' => $segment['end'] ?? 0,
                    'text' => $segment['text'] ?? '',
                ], $data['segments']);
            }

            return new TranscriptionResult(
                text: $data['text'] ?? '',
                language: $data['language'] ?? $language,
                confidence: $this->calculateConfidence($data),
                alternatives: $alternatives,
                segments: $segments,
                provider: 'openai',
                model: $this->model,
                processingTime: $processingTime,
                metadata: [
                    'duration' => $data['duration'] ?? null,
                    'task' => $data['task'] ?? 'transcribe',
                ]
            );

        } catch (Exception $e) {
            throw new Exception("OpenAI transcription failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function supportsFormat(string $mimeType): bool
    {
        return in_array($mimeType, $this->supportedFormats);
    }

    public function getMaxDuration(): int
    {
        return config('helpdesk.voice_notes.max_duration', 300); // Default 5 minutes
    }

    public function getMaxFileSize(): int
    {
        $maxSizeKb = config('helpdesk.voice_notes.max_file_size', 25600); // Default 25 MB
        return $maxSizeKb * 1024;
    }

    private function calculateConfidence(array $data): float
    {
        if (!isset($data['words'])) {
            return 0.85;
        }

        $words = $data['words'];
        if (empty($words)) {
            return 0.85;
        }

        $totalConfidence = 0;
        $count = 0;

        foreach ($words as $word) {
            if (isset($word['probability'])) {
                $totalConfidence += $word['probability'];
                $count++;
            }
        }

        if ($count === 0) {
            return 0.85;
        }

        return round($totalConfidence / $count, 2);
    }

    private function extractAlternatives(array $words): ?array
    {
        $lowConfidenceWords = array_filter($words, function($word) {
            return isset($word['probability']) && $word['probability'] < 0.8;
        });

        if (empty($lowConfidenceWords)) {
            return null;
        }

        $alternatives = [];
        foreach (array_slice($lowConfidenceWords, 0, 3) as $word) {
            if (isset($word['word'])) {
                $alternatives[] = [
                    'word' => $word['word'],
                    'start' => $word['start'] ?? 0,
                    'end' => $word['end'] ?? 0,
                    'confidence' => $word['probability'] ?? 0,
                ];
            }
        }

        return $alternatives ?: null;
    }
}