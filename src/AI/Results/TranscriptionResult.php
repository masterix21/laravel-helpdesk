<?php

namespace LucaLongo\LaravelHelpdesk\AI\Results;

class TranscriptionResult
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $language,
        public readonly float $confidence,
        public readonly ?array $alternatives,
        public readonly ?array $segments,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $processingTime,
        public readonly ?array $metadata = null
    ) {}

    public function hasAlternatives(): bool
    {
        return ! empty($this->alternatives);
    }

    public function hasSegments(): bool
    {
        return ! empty($this->segments);
    }

    public function getHighConfidenceText(): string
    {
        if ($this->confidence >= 0.95) {
            return $this->text;
        }

        if ($this->hasAlternatives()) {
            $bestAlternative = collect($this->alternatives)
                ->sortByDesc('confidence')
                ->first();

            if ($bestAlternative && $bestAlternative['confidence'] > $this->confidence) {
                return $bestAlternative['text'];
            }
        }

        return $this->text;
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'language' => $this->language,
            'confidence' => $this->confidence,
            'alternatives' => $this->alternatives,
            'segments' => $this->segments,
            'provider' => $this->provider,
            'model' => $this->model,
            'processing_time' => $this->processingTime,
            'metadata' => $this->metadata,
        ];
    }
}
