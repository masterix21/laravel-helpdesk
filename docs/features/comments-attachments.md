# Comments & Attachments

## Overview

Comments and attachments enable communication and file sharing within tickets. Comments support threaded discussions with author tracking, while attachments allow file uploads with metadata storage.

## Comments

### CommentService

The `CommentService` handles adding comments to tickets.

```php
use LucaLongo\LaravelHelpdesk\Services\CommentService;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

$commentService = app(CommentService::class);

// Add comment with author
$comment = $commentService->addComment(
    $ticket,
    'This issue has been investigated. The problem is with the API rate limit.',
    $agent,  // Author model (User, Agent, etc.)
    [
        'internal' => false,
        'visible_to_customer' => true,
    ]
);

// Add comment without author (system comment)
$comment = $commentService->addComment(
    $ticket,
    'Status changed to In Progress',
    null,
    ['type' => 'system']
);

// Add internal note
$comment = $commentService->addComment(
    $ticket,
    'Customer seems frustrated, handle with care',
    $agent,
    [
        'internal' => true,
        'visible_to_customer' => false,
    ]
);
```

### TicketComment Model

```php
use LucaLongo\LaravelHelpdesk\Models\TicketComment;

// Relationships
$ticket = $comment->ticket;
$author = $comment->author;  // Polymorphic relation

// Access comment data
$body = $comment->body;
$meta = $comment->meta;  // ArrayObject
$createdAt = $comment->created_at;

// Check comment type
if ($comment->meta['internal'] ?? false) {
    // Internal note
}

// Get all ticket comments
$comments = $ticket->comments();

// Get visible comments only
$visibleComments = $ticket->comments()
    ->where('meta->visible_to_customer', true)
    ->get();

// Get comments with authors
$comments = $ticket->comments()->with('author')->latest()->get();

// Get comment count
$commentCount = $ticket->comments()->count();
```

### First Response Tracking

The system automatically tracks the first response time when an internal user comments:

```php
// First response is marked automatically
$comment = $commentService->addComment($ticket, 'Initial response', $agent);

// Check first response
if ($ticket->first_response_at) {
    $responseTime = $ticket->first_response_at->diffInMinutes($ticket->created_at);
}
```

## Attachments

### Managing Attachments

```php
use LucaLongo\LaravelHelpdesk\Models\TicketAttachment;
use Illuminate\Support\Facades\Storage;

// Upload and attach file to ticket
$file = $request->file('attachment');

$attachment = TicketAttachment::create([
    'ticket_id' => $ticket->id,
    'filename' => $file->getClientOriginalName(),
    'disk' => 'tickets',  // Storage disk name
    'path' => $file->store('tickets/' . $ticket->ulid, 'tickets'),
    'mime_type' => $file->getMimeType(),
    'size' => $file->getSize(),
    'meta' => [
        'uploaded_by' => auth()->id(),
        'ip_address' => request()->ip(),
    ],
]);

// Access ticket attachments
$attachments = $ticket->attachments;

foreach ($attachments as $attachment) {
    echo $attachment->filename;  // Original filename
    echo $attachment->size;       // File size in bytes
    echo $attachment->mime_type;  // MIME type
}

// Download attachment
$attachment = TicketAttachment::find($id);
$path = Storage::disk($attachment->disk)->path($attachment->path);

return response()->download($path, $attachment->filename);

// Stream attachment
return Storage::disk($attachment->disk)
    ->response($attachment->path, $attachment->filename);

// Delete attachment
Storage::disk($attachment->disk)->delete($attachment->path);
$attachment->delete();
```

### Attachment Validation

```php
// In your controller
public function uploadAttachment(Request $request, Ticket $ticket)
{
    $request->validate([
        'attachment' => [
            'required',
            'file',
            'max:10240',  // 10MB max
            'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt,zip',
        ],
    ]);
    
    $file = $request->file('attachment');
    
    // Check total attachments size for ticket
    $totalSize = $ticket->attachments()->sum('size');
    if ($totalSize + $file->getSize() > 52428800) {  // 50MB total
        return back()->withErrors(['attachment' => 'Total attachment size limit exceeded']);
    }
    
    // Store and create attachment record
    // ...
}
```

### Attachment with Comments

You can associate attachments with specific comments:

```php
// Add comment with attachments reference
$comment = $commentService->addComment(
    $ticket,
    'Please find the attached screenshots showing the issue',
    $customer,
    [
        'attachment_ids' => [$attachment1->id, $attachment2->id],
    ]
);

// Get attachments referenced in comment
$attachmentIds = $comment->meta['attachment_ids'] ?? [];
$attachments = TicketAttachment::whereIn('id', $attachmentIds)->get();
```

## Working with Comments & Attachments Together

```php
// Create ticket with initial comment and attachment
$ticket = $ticketService->open([
    'subject' => 'Application crash on startup',
    'description' => 'App crashes with error',
]);

// Add detailed comment
$comment = $commentService->addComment(
    $ticket,
    'I\'ve attached the crash log and screenshot',
    $customer
);

// Upload attachments
$crashLog = $request->file('crash_log');
$screenshot = $request->file('screenshot');

$attachments = [];
foreach ([$crashLog, $screenshot] as $file) {
    $attachments[] = TicketAttachment::create([
        'ticket_id' => $ticket->id,
        'filename' => $file->getClientOriginalName(),
        'disk' => 'tickets',
        'path' => $file->store("tickets/{$ticket->ulid}", 'tickets'),
        'mime_type' => $file->getMimeType(),
        'size' => $file->getSize(),
    ]);
}

// Link attachments to comment
$comment->meta = array_merge($comment->meta ?? [], [
    'attachment_ids' => collect($attachments)->pluck('id')->toArray(),
]);
$comment->save();
```

## Events

```php
use LucaLongo\LaravelHelpdesk\Events\TicketCommentAdded;

// Listen for comment events
Event::listen(TicketCommentAdded::class, function ($event) {
    $comment = $event->comment;
    $ticket = $comment->ticket;
    $author = $comment->author;
    
    // Send notification
    // Update ticket timestamp
    // Trigger automation
});
```

## Security Considerations

### File Upload Security

```php
// Validate file types
$allowedMimes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

// Scan for malware (implement your scanner)
if ($this->virusScanner->scan($file)) {
    throw new MalwareDetectedException();
}

// Generate secure filenames
$filename = Str::random(40) . '.' . $file->getClientOriginalExtension();

// Store outside public directory
$path = $file->storeAs(
    'tickets/' . $ticket->ulid,
    $filename,
    'private'
);
```

### Access Control

```php
// Check attachment access
public function downloadAttachment(TicketAttachment $attachment)
{
    $ticket = $attachment->ticket;
    
    // Check user can view ticket
    if (!auth()->user()->can('view', $ticket)) {
        abort(403);
    }
    
    // Check if internal attachment
    if ($attachment->meta['internal'] ?? false) {
        if (!auth()->user()->isAgent()) {
            abort(403);
        }
    }
    
    return Storage::disk($attachment->disk)
        ->download($attachment->path, $attachment->filename);
}
```

## Best Practices

1. **Always validate file uploads** - Check type, size, and scan for malware
2. **Store files securely** - Use private disk, not public
3. **Track comment authors** - Always associate comments with users
4. **Use metadata** - Store additional context in meta field
5. **Implement access control** - Check permissions before serving files
6. **Clean up orphaned files** - Remove files when tickets are deleted
7. **Set size limits** - Per file and per ticket total
8. **Use queues** - For large file processing and virus scanning