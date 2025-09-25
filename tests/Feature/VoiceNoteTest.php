<?php

namespace LucaLongo\LaravelHelpdesk\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use LucaLongo\LaravelHelpdesk\Enums\VoiceNoteStatus;
use LucaLongo\LaravelHelpdesk\Enums\EmotionalTone;
use LucaLongo\LaravelHelpdesk\Jobs\ProcessVoiceNoteJob;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketComment;
use LucaLongo\LaravelHelpdesk\Models\VoiceNote;
use LucaLongo\LaravelHelpdesk\Services\VoiceNoteService;
use LucaLongo\LaravelHelpdesk\Tests\TestCase;

class VoiceNoteTest extends TestCase
{
    protected VoiceNoteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VoiceNoteService::class);
    }

    public function test_can_create_voice_note_for_ticket()
    {
        Queue::fake();

        $user = $this->createUser();
        $ticket = Ticket::factory()->create();
        $file = UploadedFile::fake()->create('audio.mp3', 1000, 'audio/mpeg');

        $voiceNote = $this->service->createFromUpload($file, $user->id, $ticket);

        $this->assertInstanceOf(VoiceNote::class, $voiceNote);
        $this->assertEquals(VoiceNoteStatus::PENDING, $voiceNote->status);
        $this->assertEquals($ticket->id, $voiceNote->ticket_id);
        $this->assertEquals(Ticket::class, $voiceNote->notable_type);
        $this->assertEquals($ticket->id, $voiceNote->notable_id);

        Queue::assertPushed(ProcessVoiceNoteJob::class);
    }

    public function test_can_create_voice_note_for_comment()
    {
        Queue::fake();

        $user = $this->createUser();
        $ticket = Ticket::factory()->create();
        $comment = TicketComment::factory()->create(['ticket_id' => $ticket->id]);
        $file = UploadedFile::fake()->create('audio.wav', 2000, 'audio/wav');

        $voiceNote = $this->service->createFromUpload($file, $user->id, $comment);

        $this->assertInstanceOf(VoiceNote::class, $voiceNote);
        $this->assertEquals($ticket->id, $voiceNote->ticket_id);
        $this->assertEquals(TicketComment::class, $voiceNote->notable_type);
        $this->assertEquals($comment->id, $voiceNote->notable_id);

        Queue::assertPushed(ProcessVoiceNoteJob::class);
    }

    public function test_polymorphic_relationship_works()
    {
        $ticket = Ticket::factory()->create();
        $voiceNote = VoiceNote::factory()->create([
            'notable_type' => Ticket::class,
            'notable_id' => $ticket->id,
            'ticket_id' => $ticket->id,
        ]);

        $this->assertInstanceOf(Ticket::class, $voiceNote->notable);
        $this->assertEquals($ticket->id, $voiceNote->notable->id);
        $this->assertNotNull($ticket->voiceNote);
        $this->assertEquals($voiceNote->id, $ticket->voiceNote->id);
    }

    public function test_voice_note_status_transitions()
    {
        $voiceNote = VoiceNote::factory()->create(['status' => VoiceNoteStatus::PENDING]);

        $this->assertTrue($voiceNote->canProcess());
        $this->assertFalse($voiceNote->isFailed());

        $voiceNote->markAsProcessing();
        $this->assertEquals(VoiceNoteStatus::PROCESSING, $voiceNote->status);

        $voiceNote->markAsTranscribed('Test transcription', 'openai', 'whisper-1');
        $this->assertNotNull($voiceNote->transcription);
        $this->assertEquals('openai', $voiceNote->ai_provider);

        $voiceNote->markAsAnalyzed(EmotionalTone::HAPPY);
        $this->assertEquals(VoiceNoteStatus::ANALYZED, $voiceNote->status);
        $this->assertEquals(EmotionalTone::HAPPY, $voiceNote->emotional_tone);
        $this->assertTrue($voiceNote->isProcessed());
    }

    public function test_emotional_tone_enum()
    {
        $tone = EmotionalTone::FRUSTRATED;

        $this->assertEquals('frustrated', $tone->value);
        $this->assertEquals(__('Frustrated'), $tone->label());
        $this->assertEquals('yellow', $tone->color());
        $this->assertTrue($tone->isNegative());
        $this->assertFalse($tone->isPositive());
        $this->assertTrue($tone->requiresAttention());
    }

    public function test_voice_note_retry_logic()
    {
        $voiceNote = VoiceNote::factory()->create([
            'status' => VoiceNoteStatus::FAILED,
            'processing_attempts' => 2,
        ]);

        $this->assertTrue($voiceNote->canRetry());

        $voiceNote->incrementAttempts();
        $this->assertEquals(3, $voiceNote->processing_attempts);

        $this->assertFalse($voiceNote->canRetry());
    }

    public function test_file_validation()
    {
        $user = $this->createUser();
        $ticket = Ticket::factory()->create();

        $oversizedFile = UploadedFile::fake()->create('audio.mp3', 30000000, 'audio/mpeg');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File size exceeds maximum');

        $this->service->createFromUpload($oversizedFile, $user->id, $ticket);
    }

    public function test_unsupported_format_validation()
    {
        $user = $this->createUser();
        $ticket = Ticket::factory()->create();

        $unsupportedFile = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File format pdf is not allowed');

        $this->service->createFromUpload($unsupportedFile, $user->id, $ticket);
    }

    protected function createUser()
    {
        $userModel = config('helpdesk.user_model');
        return $userModel::factory()->create();
    }
}