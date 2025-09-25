<?php

namespace LucaLongo\LaravelHelpdesk\AI\Services;

use Exception;
use LucaLongo\LaravelHelpdesk\AI\AIProviderSelector;
use LucaLongo\LaravelHelpdesk\AI\Results\EmotionalToneResult;
use LucaLongo\LaravelHelpdesk\Enums\EmotionalTone;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\VoiceNote;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;

class ToneAnalysisService
{
    public function __construct(
        private AIProviderSelector $selector
    ) {}

    public function analyzeTone(string $text): EmotionalToneResult
    {
        $provider = $this->selector->selectProvider('analyze_tone');

        if (!$provider) {
            throw new Exception('No AI provider available for tone analysis');
        }

        $config = config("helpdesk.ai.providers.{$provider}");
        $minLength = 10;
        $maxLength = 5000;

        $textLength = strlen($text);
        if ($textLength < $minLength) {
            throw new Exception("Text too short for analysis (minimum {$minLength} characters)");
        }

        if ($textLength > $maxLength) {
            $text = substr($text, 0, $maxLength);
        }

        $startTime = microtime(true);

        try {
            $prismProvider = $this->getPrismProvider($provider);
            $model = $config['model'];

            $prompt = $this->buildToneAnalysisPrompt($text);

            $result = Prism::text()
                ->using($prismProvider, $model)
                ->withPrompt($prompt)
                ->withMaxTokens(500)
                ->withTemperature(0.3)
                ->generate();

            $processingTime = (int)((microtime(true) - $startTime) * 1000);

            $analysis = json_decode($result->text, true);

            if (!$analysis || !isset($analysis['tone'])) {
                throw new Exception('Invalid response from AI provider');
            }

            $tone = EmotionalTone::tryFrom($analysis['tone']) ?? EmotionalTone::NEUTRAL;

            return new EmotionalToneResult(
                tone: $tone,
                confidence: $analysis['confidence'] ?? 0.5,
                indicators: $analysis['indicators'] ?? [],
                secondaryTones: $analysis['secondary_tones'] ?? null,
                provider: $provider,
                model: $model,
                processingTime: $processingTime,
                reasoning: $analysis['reasoning'] ?? null
            );

        } catch (Exception $e) {
            throw new Exception("Tone analysis failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function analyzeMultiple(array $texts): array
    {
        $results = [];

        foreach ($texts as $key => $text) {
            try {
                $results[$key] = $this->analyzeTone($text);
            } catch (Exception $e) {
                $results[$key] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function detectToneShift(Ticket $ticket): ?array
    {
        $voiceNotes = $ticket->voiceNotes()
            ->whereNotNull('transcription')
            ->orderBy('created_at')
            ->get();

        if ($voiceNotes->count() < 2) {
            return null;
        }

        $tones = [];
        foreach ($voiceNotes as $note) {
            if ($note->emotional_tone) {
                $tones[] = [
                    'tone' => $note->emotional_tone,
                    'timestamp' => $note->created_at,
                    'voice_note_id' => $note->id,
                ];
            }
        }

        if (count($tones) < 2) {
            return null;
        }

        $shifts = [];
        for ($i = 1; $i < count($tones); $i++) {
            $previous = EmotionalTone::from($tones[$i - 1]['tone']);
            $current = EmotionalTone::from($tones[$i]['tone']);

            if ($previous !== $current) {
                $shifts[] = [
                    'from' => $previous->value,
                    'to' => $current->value,
                    'timestamp' => $tones[$i]['timestamp'],
                    'is_improvement' => $this->isImprovement($previous, $current),
                    'is_deterioration' => $this->isDeterioration($previous, $current),
                ];
            }
        }

        return [
            'tones' => $tones,
            'shifts' => $shifts,
            'overall_trend' => $this->calculateTrend($tones),
        ];
    }

    public function analyzeVoiceNote(VoiceNote $voiceNote): EmotionalToneResult
    {
        if (!$voiceNote->transcription) {
            throw new Exception('Voice note has not been transcribed yet');
        }

        return $this->analyzeTone($voiceNote->transcription);
    }

    private function buildToneAnalysisPrompt(string $text): string
    {
        $tones = implode(', ', array_map(fn($case) => $case->value, EmotionalTone::cases()));

        return "Analyze the emotional tone of the following text and provide a JSON response with this structure:
{
    \"tone\": \"<primary_tone>\",
    \"confidence\": 0.0-1.0,
    \"indicators\": [\"key phrase 1\", \"key phrase 2\"],
    \"secondary_tones\": [\"tone1\", \"tone2\"] or null,
    \"reasoning\": \"Brief explanation\"
}

Available tones: {$tones}

Text to analyze:
{$text}

Return only valid JSON without any markdown formatting or additional text.";
    }

    private function getPrismProvider(string $provider): Provider
    {
        return match($provider) {
            'openai' => Provider::OpenAI,
            'claude' => Provider::Anthropic,
            'gemini' => Provider::Google,
            default => throw new Exception("Unknown AI provider: {$provider}")
        };
    }

    private function isImprovement(EmotionalTone $from, EmotionalTone $to): bool
    {
        return $from->isNegative() && ($to->isPositive() || $to === EmotionalTone::NEUTRAL);
    }

    private function isDeterioration(EmotionalTone $from, EmotionalTone $to): bool
    {
        return ($from->isPositive() || $from === EmotionalTone::NEUTRAL) && $to->isNegative();
    }

    private function calculateTrend(array $tones): string
    {
        if (count($tones) < 2) {
            return 'stable';
        }

        $first = EmotionalTone::from($tones[0]['tone']);
        $last = EmotionalTone::from($tones[count($tones) - 1]['tone']);

        if ($this->isImprovement($first, $last)) {
            return 'improving';
        }

        if ($this->isDeterioration($first, $last)) {
            return 'deteriorating';
        }

        return 'stable';
    }
}