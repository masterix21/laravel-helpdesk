<?php

namespace LucaLongo\LaravelHelpdesk\AI;

use Exception;
use LucaLongo\LaravelHelpdesk\AI\Services\ToneAnalysisService;
use LucaLongo\LaravelHelpdesk\AI\Services\TranscriptionService;
use LucaLongo\LaravelHelpdesk\Events\TicketAnalyzedByAI;
use LucaLongo\LaravelHelpdesk\Models\AIAnalysis;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\VoiceNote;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

class AIService
{
    public function __construct(
        private AIProviderSelector $selector,
        private ?TranscriptionService $transcription = null,
        private ?ToneAnalysisService $toneAnalysis = null
    ) {
        $this->transcription = $transcription ?? app(TranscriptionService::class);
        $this->toneAnalysis = $toneAnalysis ?? app(ToneAnalysisService::class);
    }

    public function analyze(Ticket $ticket): ?AIAnalysis
    {
        if (! config('helpdesk.ai.enabled')) {
            return null;
        }

        $provider = $this->selector->selectProvider();

        if (! $provider) {
            return null;
        }

        $config = config("helpdesk.ai.providers.{$provider}");

        $prompt = $this->buildPrompt($ticket, $config['capabilities']);

        if (! $prompt) {
            return null;
        }

        try {
            $startTime = microtime(true);

            $prismProvider = $this->getPrismProvider($provider);
            $model = $config['model'];

            $result = Prism::text()
                ->using($prismProvider, $model)
                ->withPrompt($prompt)
                ->withMaxTokens(1000)
                ->withTemperature(0.3)
                ->generate();

            $processingTime = (int) ((microtime(true) - $startTime) * 1000);

            $analysis = AIAnalysis::fromResponse(
                $result->text,
                $config['capabilities'],
                $provider,
                $model,
                $processingTime
            );

            $analysis->ticket_id = $ticket->id;
            $analysis->save();

            event(new TicketAnalyzedByAI($ticket, $analysis));

            return $analysis;

        } catch (Exception $e) {
            report($e);

            return null;
        }
    }

    public function generateSuggestion(Ticket $ticket): ?string
    {
        $provider = $this->selector->selectProvider('suggest_response');

        if (! $provider) {
            return null;
        }

        $config = config("helpdesk.ai.providers.{$provider}");

        try {
            $prismProvider = $this->getPrismProvider($provider);
            $model = $config['model'];

            $result = Prism::text()
                ->using($prismProvider, $model)
                ->withPrompt("Suggest a professional and helpful response for this support ticket (max 150 words):
                    Subject: {$ticket->subject}
                    Description: {$ticket->description}

                    Provide only the suggested response text without any preamble.")
                ->withMaxTokens(300)
                ->withTemperature(0.7)
                ->generate();

            return $result->text;

        } catch (Exception $e) {
            report($e);

            return null;
        }
    }

    public function findSimilarTickets(Ticket $ticket): ?array
    {
        $analysis = $ticket->aiAnalyses()->latest()->first();

        if (! $analysis || ! $analysis->keywords) {
            $analysis = $this->analyze($ticket);
        }

        if (! $analysis || ! $analysis->keywords) {
            return null;
        }

        return Ticket::query()
            ->where('id', '!=', $ticket->id)
            ->where(function ($query) use ($analysis) {
                foreach ($analysis->keywords as $keyword) {
                    $query->orWhere('subject', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                }
            })
            ->take(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'status' => $t->status,
                'created_at' => $t->created_at,
            ])
            ->toArray();
    }

    private function buildPrompt(Ticket $ticket, array $capabilities): ?string
    {
        $tasks = [];

        if ($capabilities['analyze_sentiment'] ?? false) {
            $tasks[] = '"sentiment": "positive" | "neutral" | "negative"';
        }

        if ($capabilities['suggest_response'] ?? false) {
            $tasks[] = '"suggested_response": "string (max 150 words)"';
        }

        if ($capabilities['auto_categorize'] ?? false) {
            $tasks[] = '"category": "bug" | "feature_request" | "support" | "billing" | "other"';
        }

        if ($capabilities['find_similar'] ?? false) {
            $tasks[] = '"keywords": ["keyword1", "keyword2", "keyword3"] (3-5 keywords for searching similar tickets)';
        }

        if (empty($tasks)) {
            return null;
        }

        $jsonStructure = "{\n  ".implode(",\n  ", $tasks)."\n}";

        return "Analyze this support ticket and provide a JSON response with the following structure:

{$jsonStructure}

Ticket Subject: {$ticket->subject}
Ticket Description: {$ticket->description}

Return only valid JSON without any markdown formatting or additional text.";
    }

    public function processVoiceNote(VoiceNote $voiceNote): array
    {
        if (! config('helpdesk.voice_notes.enabled')) {
            throw new Exception('Voice notes feature is not enabled');
        }

        $results = [
            'transcription' => null,
            'tone_analysis' => null,
            'errors' => [],
        ];

        try {
            $transcriptionResult = $this->transcription->transcribe($voiceNote);
            $results['transcription'] = $transcriptionResult;

            $voiceNote->markAsTranscribed(
                $transcriptionResult->text,
                $transcriptionResult->provider,
                $transcriptionResult->model
            );

            if ($voiceNote->analyze_tone && $transcriptionResult->text) {
                try {
                    $toneResult = $this->toneAnalysis->analyzeTone($transcriptionResult->text);
                    $results['tone_analysis'] = $toneResult;

                    $voiceNote->markAsAnalyzed($toneResult->tone);
                } catch (Exception $e) {
                    $results['errors'][] = "Tone analysis failed: {$e->getMessage()}";
                }
            }
        } catch (Exception $e) {
            $voiceNote->markAsFailed($e->getMessage());
            throw $e;
        }

        return $results;
    }

    public function transcribeVoiceNote(VoiceNote $voiceNote): ?string
    {
        try {
            $result = $this->transcription->transcribe($voiceNote);

            return $result->text;
        } catch (Exception $e) {
            report($e);

            return null;
        }
    }

    public function analyzeVoiceNoteTone(VoiceNote $voiceNote): ?string
    {
        if (! $voiceNote->transcription) {
            return null;
        }

        try {
            $result = $this->toneAnalysis->analyzeTone($voiceNote->transcription);

            return $result->tone->value;
        } catch (Exception $e) {
            report($e);

            return null;
        }
    }

    private function getPrismProvider(string $provider): Provider
    {
        return match ($provider) {
            'openai' => Provider::OpenAI,
            'claude' => Provider::Anthropic,
            'gemini' => Provider::Google,
            default => throw new Exception("Unknown AI provider: {$provider}")
        };
    }
}
