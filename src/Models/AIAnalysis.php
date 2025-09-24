<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAnalysis extends Model
{
    use HasUlids;

    protected $table = 'helpdesk_ai_analyses';

    protected $fillable = [
        'ticket_id',
        'provider',
        'model',
        'sentiment',
        'category',
        'suggested_response',
        'keywords',
        'raw_response',
        'confidence',
        'processing_time_ms',
    ];

    protected $casts = [
        'keywords' => 'array',
        'raw_response' => 'array',
        'confidence' => 'float',
        'processing_time_ms' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public static function fromResponse(
        string $response,
        array $capabilities,
        ?string $provider = null,
        ?string $model = null,
        ?int $processingTime = null
    ): self {
        $analysis = new self();
        $data = json_decode($response, true);

        if ($capabilities['analyze_sentiment'] ?? false) {
            $analysis->sentiment = $data['sentiment'] ?? null;
        }

        if ($capabilities['suggest_response'] ?? false) {
            $analysis->suggested_response = $data['suggested_response'] ?? null;
        }

        if ($capabilities['auto_categorize'] ?? false) {
            $analysis->category = $data['category'] ?? null;
        }

        if ($capabilities['find_similar'] ?? false) {
            $analysis->keywords = $data['keywords'] ?? null;
        }

        $analysis->provider = $provider;
        $analysis->model = $model;
        $analysis->raw_response = $data;
        $analysis->confidence = $data['confidence'] ?? null;
        $analysis->processing_time_ms = $processingTime;

        return $analysis;
    }

    public function toSimpleArray(): array
    {
        return [
            'sentiment' => $this->sentiment,
            'category' => $this->category,
            'suggested_response' => $this->suggested_response,
            'keywords' => $this->keywords,
            'confidence' => $this->confidence,
            'provider' => $this->provider,
        ];
    }
}