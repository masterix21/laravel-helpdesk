# AI-Powered Analysis (unreleased)

Laravel Helpdesk integrates AI capabilities to provide intelligent ticket analysis, automatic categorization, and response suggestions through multiple AI providers.

## Overview

The AI system uses [Prism PHP](https://github.com/prism-php/prism) to provide a unified interface across multiple AI providers (OpenAI, Anthropic Claude, Google Gemini). The system automatically analyzes tickets upon creation and provides insights to support agents.

## Requirements

- At least one AI provider API key

## Configuration

### Environment Variables

Configure AI features in your `.env` file:

```env
# Enable/disable AI features globally
HELPDESK_AI_ENABLED=true

# Provider API Keys (configure at least one)
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GEMINI_API_KEY=...

# Optional: Override default models
OPENAI_MODEL=gpt-4o-mini
CLAUDE_MODEL=claude-3-haiku-20240307
GEMINI_MODEL=gemini-1.5-flash

# Optional: Enable/disable specific providers
OPENAI_ENABLED=true
CLAUDE_ENABLED=true
GEMINI_ENABLED=true
```

### Configuration File

The AI configuration is defined in `config/helpdesk.php`:

```php
'ai' => [
    'enabled' => env('HELPDESK_AI_ENABLED', false),

    'providers' => [
        'openai' => [
            'enabled' => env('OPENAI_ENABLED', true),
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'capabilities' => [
                'analyze_sentiment' => true,
                'suggest_response' => true,
                'auto_categorize' => true,
                'find_similar' => true,
            ],
        ],
        'claude' => [
            'enabled' => env('CLAUDE_ENABLED', true),
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-3-haiku-20240307'),
            'capabilities' => [
                'analyze_sentiment' => true,
                'suggest_response' => true,
                'auto_categorize' => true,
                'find_similar' => true,
            ],
        ],
        'gemini' => [
            'enabled' => env('GEMINI_ENABLED', true),
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
            'capabilities' => [
                'analyze_sentiment' => true,
                'suggest_response' => true,
                'auto_categorize' => true,
                'find_similar' => false, // Example: disable specific capability
            ],
        ],
    ],
],
```

## Features

### 1. Sentiment Analysis

Analyzes the emotional tone of ticket content to identify:
- **Positive**: Customer is satisfied or expressing gratitude
- **Neutral**: Informational or objective communication
- **Negative**: Customer is frustrated, angry, or dissatisfied

### 2. Automatic Categorization

Automatically classifies tickets into categories:
- **bug**: Technical issues or errors
- **feature_request**: New functionality requests
- **support**: General support questions
- **billing**: Payment or subscription issues
- **other**: Doesn't fit other categories

### 3. Response Suggestions

Generates professional, contextually appropriate responses that agents can use as templates or inspiration for their replies.

### 4. Similar Ticket Discovery

Extracts keywords from ticket content to identify related tickets, helping agents find previous solutions and identify recurring issues.

## Usage

### Automatic Analysis on Ticket Creation

When AI is enabled, tickets are automatically analyzed when created through the `TicketService`:

```php
use LucaLongo\LaravelHelpdesk\Services\TicketService;

$ticketService = app(TicketService::class);

// Create a ticket - AI analysis happens automatically
$ticket = $ticketService->open([
    'subject' => 'Login error after password reset',
    'description' => 'I reset my password but now I cannot login. Getting error 403.',
    'type' => 'support',
    'priority' => 'high',
], $user);

// Retrieve the AI analysis
$analysis = $ticket->getLatestAIAnalysis();

if ($analysis) {
    echo "Sentiment: " . $analysis->sentiment;              // "negative"
    echo "Category: " . $analysis->category;                // "bug"
    echo "Confidence: " . $analysis->confidence;            // 0.85
    echo "Provider: " . $analysis->provider;                // "openai"
    echo "Processing time: " . $analysis->processing_time_ms . "ms";

    // Suggested response
    echo $analysis->suggested_response;

    // Keywords for finding similar tickets
    print_r($analysis->keywords); // ["login", "password", "error", "403"]
}
```

### Manual Analysis

You can trigger AI analysis manually for existing tickets:

```php
use LucaLongo\LaravelHelpdesk\Models\Ticket;

$ticket = Ticket::find($ticketId);

// Trigger new AI analysis
$analysis = $ticket->analyzeWithAI();

// Get the most recent analysis (without triggering new one)
$latestAnalysis = $ticket->getLatestAIAnalysis();

// Get all analyses for this ticket
$allAnalyses = $ticket->aiAnalyses()->get();
```

### Generate Response Suggestions

Get AI-generated response suggestions:

```php
$ticket = Ticket::find($ticketId);

// Generate a new suggestion
$suggestion = $ticket->getAISuggestedResponse();

// Or use the suggestion from the analysis
$analysis = $ticket->getLatestAIAnalysis();
if ($analysis && $analysis->suggested_response) {
    $suggestion = $analysis->suggested_response;
}
```

### Find Similar Tickets

Find tickets with similar content:

```php
$ticket = Ticket::find($ticketId);

// Find similar tickets based on AI-extracted keywords
$similarTickets = $ticket->findSimilarTickets();

if ($similarTickets) {
    foreach ($similarTickets as $similar) {
        echo "Ticket #{$similar['id']}: {$similar['subject']}";
        echo "Status: {$similar['status']}";
        echo "Created: {$similar['created_at']}";
    }
}
```

### Using the AI Service Directly

For more control, use the `AIService` directly:

```php
use LucaLongo\LaravelHelpdesk\AI\AIService;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

$aiService = app(AIService::class);
$ticket = Ticket::find($ticketId);

// Analyze ticket
$analysis = $aiService->analyze($ticket);

// Generate response suggestion
$response = $aiService->generateSuggestion($ticket);

// Find similar tickets
$similarTickets = $aiService->findSimilarTickets($ticket);
```

### Helper Methods

The `AIHelper` class provides utility methods:

```php
use LucaLongo\LaravelHelpdesk\AI\AIHelper;

// Check if AI is enabled
if (AIHelper::isEnabled()) {
    // Check specific capabilities
    if (AIHelper::canAnalyzeSentiment()) {
        // Sentiment analysis is available
    }

    if (AIHelper::canSuggestResponse()) {
        // Response suggestions are available
    }

    if (AIHelper::canCategorize()) {
        // Auto-categorization is available
    }

    if (AIHelper::canFindSimilar()) {
        // Similar ticket search is available
    }

    // Get list of available providers
    $providers = AIHelper::availableProviders(); // ['openai', 'claude']

    // Get active capabilities for current provider
    $capabilities = AIHelper::activeCapabilities();
    // ['analyze_sentiment', 'suggest_response', 'auto_categorize', 'find_similar']
}
```

## Database Schema

AI analyses are stored in the `helpdesk_ai_analyses` table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | ULID | Primary key |
| `ticket_id` | ULID | Foreign key to tickets |
| `provider` | VARCHAR(50) | AI provider used (openai, claude, gemini) |
| `model` | VARCHAR(100) | Specific model used |
| `sentiment` | VARCHAR(20) | Detected sentiment |
| `category` | VARCHAR(50) | Detected category |
| `suggested_response` | TEXT | Generated response suggestion |
| `keywords` | JSON | Extracted keywords array |
| `raw_response` | JSON | Complete AI response |
| `confidence` | DECIMAL(3,2) | Confidence score (0.00-1.00) |
| `processing_time_ms` | INTEGER | Processing time in milliseconds |
| `created_at` | TIMESTAMP | Analysis timestamp |
| `updated_at` | TIMESTAMP | Last update timestamp |

## Events

The system dispatches the `TicketAnalyzedByAI` event after successful analysis:

```php
use LucaLongo\LaravelHelpdesk\Events\TicketAnalyzedByAI;
use Illuminate\Support\Facades\Event;

Event::listen(TicketAnalyzedByAI::class, function (TicketAnalyzedByAI $event) {
    $ticket = $event->ticket;
    $analysis = $event->analysis;

    // Example: Escalate negative sentiment tickets
    if ($analysis->sentiment === 'negative' && $analysis->confidence > 0.8) {
        $ticket->update(['priority' => TicketPriority::Urgent]);

        // Notify manager
        $ticket->assignee?->notify(new UrgentTicketNotification($ticket));
    }

    // Example: Auto-assign based on category
    if ($analysis->category === 'billing') {
        $billingTeam = User::where('department', 'billing')->first();
        $ticket->assignTo($billingTeam);
    }
});
```

## Provider Selection

The system uses a **round-robin** algorithm to distribute requests across enabled providers:

1. **Filtering**: Only providers with valid API keys and enabled status are considered
2. **Capability Check**: Providers must support the required capability
3. **Round-Robin**: Requests are distributed evenly across available providers
4. **Cache Persistence**: The rotation index is cached to maintain consistency across requests

Example flow:
```
Request 1 → OpenAI
Request 2 → Claude
Request 3 → Gemini
Request 4 → OpenAI (cycle repeats)
```

## Error Handling

AI operations are designed to fail gracefully:

```php
$analysis = $ticket->analyzeWithAI();

if ($analysis === null) {
    // AI analysis failed - handle gracefully
    // Possible reasons:
    // - AI is disabled
    // - No providers available
    // - API error
    // - Rate limit exceeded

    // Fall back to manual processing
    Log::warning('AI analysis failed for ticket', ['ticket_id' => $ticket->id]);
} else {
    // Use AI insights
    $this->processAIInsights($analysis);
}
```

Errors are automatically logged but don't interrupt the ticket creation process.

## Best Practices

### 1. Configure Multiple Providers

For better reliability and cost optimization, configure at least two providers:

```env
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
```

### 2. Monitor Usage

Track AI usage through the analysis records:

```php
use LucaLongo\LaravelHelpdesk\Models\AIAnalysis;

// Get usage statistics
$stats = AIAnalysis::selectRaw('
    provider,
    COUNT(*) as total_requests,
    AVG(processing_time_ms) as avg_time_ms,
    AVG(confidence) as avg_confidence
')
->groupBy('provider')
->get();

foreach ($stats as $stat) {
    echo "{$stat->provider}: {$stat->total_requests} requests, ";
    echo "avg {$stat->avg_time_ms}ms, ";
    echo "confidence {$stat->avg_confidence}";
}
```

### 3. Use Caching

AI analyses are automatically stored, avoiding repeated API calls:

```php
// First call - makes API request
$analysis1 = $ticket->analyzeWithAI();

// Subsequent call - returns cached result
$analysis2 = $ticket->getLatestAIAnalysis();

// Force new analysis only when needed
if ($ticket->updated_at > $analysis2->created_at) {
    $newAnalysis = $ticket->analyzeWithAI();
}
```

### 4. Handle Provider Capabilities

Not all providers support all features. Always check capabilities:

```php
if (AIHelper::canFindSimilar()) {
    $similar = $ticket->findSimilarTickets();
} else {
    // Use traditional search
    $similar = Ticket::where('subject', 'LIKE', "%{$searchTerm}%")->get();
}
```

## Troubleshooting

### AI Not Working

1. **Check if AI is enabled:**
   ```php
   dd(config('helpdesk.ai.enabled')); // Should be true
   ```

2. **Verify API keys are set:**
   ```php
   dd(config('helpdesk.ai.providers.openai.api_key')); // Should not be null
   ```

3. **Check provider availability:**
   ```php
   dd(\LucaLongo\LaravelHelpdesk\AI\AIHelper::availableProviders());
   ```

4. **Review Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Wrong Provider Being Used

The system uses round-robin by design. To use a specific provider:

1. Disable other providers:
   ```env
   OPENAI_ENABLED=true
   CLAUDE_ENABLED=false
   GEMINI_ENABLED=false
   ```

2. Or modify capabilities in config to make only one provider support what you need

### Rate Limiting

If experiencing rate limits:

1. Add more providers to distribute load
2. Implement request queuing (not included in current implementation)
3. Increase delays between requests in your application logic

## Security Considerations

1. **API Keys**: Always store API keys in environment variables, never commit them
2. **Data Privacy**: Be aware that ticket content is sent to third-party AI providers
3. **Sanitization**: AI responses are stored as-is; sanitize before displaying to users
4. **Audit Trail**: All AI analyses are logged with timestamp and provider information

## Migration

To add AI capabilities to an existing installation:

```bash
# Publish and run the migration
php artisan vendor:publish --tag="laravel-helpdesk-migrations"
php artisan migrate

# Add API keys to .env
echo "HELPDESK_AI_ENABLED=true" >> .env
echo "OPENAI_API_KEY=your-key-here" >> .env

# Clear config cache
php artisan config:clear
```

## Performance Considerations

- AI analysis is performed synchronously during ticket creation
- Average processing time: 500-2000ms depending on provider
- Consider queuing for high-volume scenarios (implementation not included)
- Each analysis is stored to avoid repeated API calls

## Limitations

- Maximum prompt size depends on model (typically 4000-8000 tokens)
- Response suggestions are limited to 150 words
- Similar ticket search is limited to 5 results
- No support for custom prompt templates (uses hardcoded prompts)
- No batch processing capabilities
- Synchronous processing only (no queue support)
