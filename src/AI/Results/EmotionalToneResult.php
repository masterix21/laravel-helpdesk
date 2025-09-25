<?php

namespace LucaLongo\LaravelHelpdesk\AI\Results;

use LucaLongo\LaravelHelpdesk\Enums\EmotionalTone;

class EmotionalToneResult
{
    public function __construct(
        public readonly EmotionalTone $tone,
        public readonly float $confidence,
        public readonly ?array $indicators,
        public readonly ?array $secondaryTones,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $processingTime,
        public readonly ?string $reasoning = null
    ) {}

    public function hasSecondaryTones(): bool
    {
        return ! empty($this->secondaryTones);
    }

    public function hasIndicators(): bool
    {
        return ! empty($this->indicators);
    }

    public function isHighConfidence(): bool
    {
        return $this->confidence >= 0.8;
    }

    public function requiresHumanReview(): bool
    {
        return $this->confidence < 0.6 || $this->tone->requiresAttention();
    }

    public function getDominantIndicators(int $limit = 3): array
    {
        if (! $this->hasIndicators()) {
            return [];
        }

        return array_slice($this->indicators, 0, $limit);
    }

    public function toArray(): array
    {
        return [
            'tone' => $this->tone->value,
            'tone_label' => $this->tone->label(),
            'confidence' => $this->confidence,
            'indicators' => $this->indicators,
            'secondary_tones' => $this->secondaryTones,
            'provider' => $this->provider,
            'model' => $this->model,
            'processing_time' => $this->processingTime,
            'reasoning' => $this->reasoning,
            'requires_attention' => $this->tone->requiresAttention(),
        ];
    }
}
