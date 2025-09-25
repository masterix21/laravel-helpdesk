<?php

namespace LucaLongo\LaravelHelpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LucaLongo\LaravelHelpdesk\Enums\EmotionalTone;
use LucaLongo\LaravelHelpdesk\Enums\VoiceNoteStatus;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\VoiceNote;

class VoiceNoteFactory extends Factory
{
    protected $model = VoiceNote::class;

    public function definition(): array
    {
        $userModel = config('helpdesk.user_model');
        $ticket = Ticket::factory();

        return [
            'notable_type' => Ticket::class,
            'notable_id' => $ticket,
            'ticket_id' => $ticket,
            'user_id' => $userModel::factory(),
            'file_path' => 'helpdesk/voice-notes/'.$this->faker->uuid().'.mp3',
            'mime_type' => $this->faker->randomElement(['audio/mpeg', 'audio/wav', 'audio/ogg']),
            'duration_seconds' => $this->faker->numberBetween(5, 300),
            'file_size' => $this->faker->numberBetween(50000, 5000000),
            'status' => $this->faker->randomElement(VoiceNoteStatus::cases()),
            'transcription' => $this->faker->optional(0.7)->paragraph(),
            'emotional_tone' => $this->faker->optional(0.5)->randomElement(EmotionalTone::cases()),
            'analyze_tone' => $this->faker->boolean(80),
            'ai_provider' => $this->faker->optional(0.7)->randomElement(['openai', 'claude', 'gemini']),
            'ai_model' => $this->faker->optional(0.7)->randomElement(['whisper-1', 'gpt-4o-mini']),
            'processing_attempts' => 0,
            'last_error' => null,
            'processed_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoiceNoteStatus::PENDING,
            'transcription' => null,
            'emotional_tone' => null,
            'processed_at' => null,
        ]);
    }

    public function processing(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoiceNoteStatus::PROCESSING,
            'transcription' => null,
            'emotional_tone' => null,
            'processed_at' => null,
        ]);
    }

    public function transcribed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoiceNoteStatus::TRANSCRIBED,
            'transcription' => $this->faker->paragraph(),
            'emotional_tone' => null,
            'ai_provider' => 'openai',
            'ai_model' => 'whisper-1',
            'processed_at' => null,
        ]);
    }

    public function analyzed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoiceNoteStatus::ANALYZED,
            'transcription' => $this->faker->paragraph(),
            'emotional_tone' => $this->faker->randomElement(EmotionalTone::cases()),
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'processed_at' => now(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoiceNoteStatus::FAILED,
            'last_error' => $this->faker->sentence(),
            'processing_attempts' => 3,
        ]);
    }
}
