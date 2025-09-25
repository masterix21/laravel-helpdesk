# Voice Notes & Transcription

The Laravel Helpdesk package includes a comprehensive voice notes system that allows users to create and respond to tickets using audio recordings, with automatic transcription and emotional tone analysis.

## Overview

The voice notes feature enables:
- Creating tickets from voice recordings
- Adding voice responses to existing tickets
- Automatic transcription using AI providers
- Optional emotional tone analysis
- Multi-provider support with fallback
- Automatic retry logic for failed transcriptions

## Configuration

Configure voice notes in `config/helpdesk.php`:

```php
'voice_notes' => [
    'enabled' => env('HELPDESK_VOICE_NOTES_ENABLED', true),
    'storage_disk' => env('HELPDESK_VOICE_STORAGE_DISK', 'local'),
    'storage_path' => 'helpdesk/voice-notes',
    'max_file_size' => env('HELPDESK_VOICE_MAX_FILE_SIZE', 25600), // KB (default 25MB)
    'max_duration' => env('HELPDESK_VOICE_MAX_DURATION', 300), // seconds (default 5 minutes)
    'allowed_formats' => ['mp3', 'wav', 'ogg', 'm4a', 'webm'],
    'analyze_tone_by_default' => env('HELPDESK_VOICE_ANALYZE_TONE', true),
    'auto_retry_failed' => env('HELPDESK_VOICE_AUTO_RETRY', true),
    'retry_after_minutes' => 30,
    'cleanup_after_days' => env('HELPDESK_VOICE_CLEANUP_DAYS', 90),

    'transcription' => [
        'queue' => env('HELPDESK_VOICE_QUEUE', 'default'),
        'language' => env('HELPDESK_VOICE_LANGUAGE', null), // null for auto-detect
    ],
],
```

## Basic Usage

### Creating a Ticket from Voice Note

```php
use LucaLongo\LaravelHelpdesk\Services\VoiceNoteService;

$voiceNoteService = app(VoiceNoteService::class);

// Create ticket from uploaded audio file
$result = $voiceNoteService->createTicketFromVoiceNote(
    file: $request->file('audio'),
    userId: auth()->id(),
    ticketData: [
        'type' => TicketType::ProductSupport,
        'priority' => TicketPriority::Normal,
    ]
);

$ticket = $result['ticket'];
$voiceNote = $result['voice_note'];
```

### Adding Voice Response to Ticket

```php
// Add voice reply to existing ticket
$result = $voiceNoteService->addVoiceReplyToTicket(
    ticket: $ticket,
    file: $request->file('audio'),
    userId: auth()->id(),
    isInternal: false
);

$comment = $result['comment'];
$voiceNote = $result['voice_note'];
```

### Direct Voice Note Creation

```php
// For a ticket
$voiceNote = $voiceNoteService->createFromUpload(
    file: $audioFile,
    userId: auth()->id(),
    notable: $ticket,
    analyzeTone: true
);

// For a comment
$voiceNote = $voiceNoteService->createFromUpload(
    file: $audioFile,
    userId: auth()->id(),
    notable: $comment,
    analyzeTone: false
);
```

## Voice Note Model

### Relationships

Voice notes use polymorphic relationships to attach to tickets or comments:

```php
// Get voice note for a ticket
$voiceNote = $ticket->voiceNote;

// Get voice note for a comment
$voiceNote = $comment->voiceNote;

// Get the parent model
$parent = $voiceNote->notable; // Ticket or TicketComment

// Get associated ticket (works for both ticket and comment voice notes)
$ticket = $voiceNote->ticket;
```

### Status Management

Voice notes track their processing status:

```php
use LucaLongo\LaravelHelpdesk\Enums\VoiceNoteStatus;

// Check status
if ($voiceNote->status === VoiceNoteStatus::ANALYZED) {
    echo $voiceNote->transcription;
    echo $voiceNote->emotional_tone->label();
}

// Status helper methods
$voiceNote->isProcessed();  // TRANSCRIBED or ANALYZED
$voiceNote->isFailed();      // FAILED status
$voiceNote->canProcess();    // PENDING or RETRY
$voiceNote->canRetry();      // Can be retried
```

## Emotional Tone Analysis

### Available Tones

The system can detect 14 different emotional tones:

```php
use LucaLongo\LaravelHelpdesk\Enums\EmotionalTone;

EmotionalTone::NEUTRAL      // Neutral
EmotionalTone::HAPPY        // Happy
EmotionalTone::FRUSTRATED   // Frustrated
EmotionalTone::ANGRY        // Angry
EmotionalTone::SAD          // Sad
EmotionalTone::ANXIOUS      // Anxious
EmotionalTone::CONFUSED     // Confused
EmotionalTone::EXCITED      // Excited
EmotionalTone::DISAPPOINTED // Disappointed
EmotionalTone::GRATEFUL     // Grateful
EmotionalTone::PROFESSIONAL // Professional
EmotionalTone::URGENT       // Urgent
EmotionalTone::CONCERNED    // Concerned
EmotionalTone::SATISFIED    // Satisfied
```

### Tone Analysis Features

```php
$tone = $voiceNote->emotional_tone;

// Helper methods
$tone->isPositive();        // Happy, Excited, Grateful, Satisfied
$tone->isNegative();        // Frustrated, Angry, Sad, Anxious, Disappointed
$tone->requiresAttention(); // Angry, Urgent, Frustrated, Anxious

// Display properties
$tone->label();  // Localized label
$tone->color();  // UI color (green, red, yellow, etc.)
```

### Tone Shift Detection

Track emotional changes across multiple voice notes:

```php
use LucaLongo\LaravelHelpdesk\AI\Services\ToneAnalysisService;

$toneService = app(ToneAnalysisService::class);

$analysis = $toneService->detectToneShift($ticket);

// Returns:
// [
//     'tones' => [...],      // Historical tones
//     'shifts' => [...],     // Tone changes
//     'overall_trend' => 'improving|stable|deteriorating'
// ]
```

## Processing & Jobs

### Automatic Processing

Voice notes are automatically processed in the background:

```php
use LucaLongo\LaravelHelpdesk\Jobs\ProcessVoiceNoteJob;

// Dispatched automatically when creating voice note
ProcessVoiceNoteJob::dispatch($voiceNote)
    ->onQueue('voice-transcription');
```

### Manual Processing

```php
// Process synchronously
ProcessVoiceNoteJob::dispatchSync($voiceNote);

// Re-process failed notes
$voiceNote->update(['status' => VoiceNoteStatus::RETRY]);
ProcessVoiceNoteJob::dispatch($voiceNote);
```

### Retry Logic

Failed transcriptions are automatically retried:
- Maximum 3 attempts
- Exponential backoff: 60s, 180s, 600s
- Automatic retry after 30 minutes (configurable)

```php
// Manual retry of failed notes
$count = $voiceNoteService->retryFailed();
```

## AI Provider Integration

### Supported Providers

Voice notes integrate with existing AI providers:

```php
// In config/helpdesk.php
'ai' => [
    'providers' => [
        'openai' => [
            'whisper_model' => 'whisper-1',
            'capabilities' => [
                'transcribe_audio' => true,
                'analyze_tone' => true,
            ],
        ],
        'claude' => [
            'capabilities' => [
                'transcribe_audio' => false,
                'analyze_tone' => true,
            ],
        ],
    ],
],
```

### Custom Transcription Adapter

Create custom adapters for other providers:

```php
use LucaLongo\LaravelHelpdesk\AI\Contracts\TranscribableContract;

class CustomTranscriptionAdapter implements TranscribableContract
{
    public function transcribe(string $filePath, ?string $language = null): TranscriptionResult
    {
        // Custom transcription logic
    }

    public function supportsFormat(string $mimeType): bool
    {
        return in_array($mimeType, ['audio/mpeg', 'audio/wav']);
    }

    public function getMaxDuration(): int
    {
        return 300; // seconds
    }

    public function getMaxFileSize(): int
    {
        return 25 * 1024 * 1024; // bytes
    }
}
```

## Events

Listen to voice note processing events:

```php
use LucaLongo\LaravelHelpdesk\Events\VoiceNoteProcessed;

Event::listen(VoiceNoteProcessed::class, function ($event) {
    $voiceNote = $event->voiceNote;
    $results = $event->results;

    if ($voiceNote->emotional_tone?->requiresAttention()) {
        // Escalate ticket priority
        $voiceNote->ticket->update([
            'priority' => TicketPriority::High
        ]);
    }
});
```

## File Management

### Storage Configuration

```php
// Custom storage disk
'storage_disk' => 's3',
'storage_path' => 'voice-notes/helpdesk',
```

### Automatic Cleanup

Old audio files are automatically deleted after transcription:

```php
// Cleanup files older than 90 days
$deletedCount = $voiceNoteService->cleanupOldAudioFiles();

// Disable cleanup
'cleanup_after_days' => 0,
```

## API Integration

### Upload Endpoint

```php
Route::post('/tickets/{ticket}/voice-note', function (Request $request, Ticket $ticket) {
    $request->validate([
        'audio' => 'required|file|mimes:mp3,wav,ogg,m4a,webm|max:25600',
        'analyze_tone' => 'boolean',
    ]);

    $result = app(VoiceNoteService::class)->addVoiceReplyToTicket(
        ticket: $ticket,
        file: $request->file('audio'),
        userId: auth()->id()
    );

    return response()->json([
        'comment_id' => $result['comment']->id,
        'voice_note_id' => $result['voice_note']->id,
        'status' => 'processing',
    ]);
});
```

### Status Polling

```php
Route::get('/voice-notes/{voiceNote}/status', function (VoiceNote $voiceNote) {
    return response()->json([
        'status' => $voiceNote->status->value,
        'transcription' => $voiceNote->transcription,
        'emotional_tone' => $voiceNote->emotional_tone?->value,
        'processed_at' => $voiceNote->processed_at,
    ]);
});
```

## Testing

```php
use LucaLongo\LaravelHelpdesk\Models\VoiceNote;
use Illuminate\Http\UploadedFile;

// Create test voice note
$voiceNote = VoiceNote::factory()
    ->analyzed()
    ->create([
        'emotional_tone' => EmotionalTone::FRUSTRATED,
    ]);

// Test file upload
$file = UploadedFile::fake()->create('audio.mp3', 1000, 'audio/mpeg');

$response = $this->post('/api/tickets/1/voice-note', [
    'audio' => $file,
]);

$response->assertStatus(200);
```

## Performance Considerations

1. **Queue Configuration**: Use dedicated queue for voice processing
2. **File Size Limits**: Configure based on your infrastructure
3. **Storage**: Consider using cloud storage for audio files
4. **Cleanup**: Enable automatic cleanup to manage storage
5. **Retry Strategy**: Adjust retry timing based on provider reliability

## Security

1. **File Validation**: Always validate file type and size
2. **User Permissions**: Check user can access ticket/comment
3. **Storage Security**: Use private disks for audio files
4. **API Rate Limiting**: Implement rate limits for upload endpoints
5. **Content Moderation**: Consider implementing content checks

## Troubleshooting

### Common Issues

1. **Transcription Fails**
   - Check AI provider credentials
   - Verify file format is supported
   - Check file size and duration limits

2. **Tone Analysis Missing**
   - Ensure `analyze_tone` is enabled
   - Check provider supports tone analysis
   - Verify transcription succeeded first

3. **Files Not Cleaned Up**
   - Check cleanup is enabled in config
   - Verify storage disk permissions
   - Check cleanup job is running

4. **Queue Processing Issues**
   - Ensure queue workers are running
   - Check queue connection configuration
   - Monitor failed jobs table