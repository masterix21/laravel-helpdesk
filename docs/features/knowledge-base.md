# Knowledge Base

The Laravel Helpdesk package includes a comprehensive knowledge base system that provides intelligent article suggestions for tickets, automated FAQ generation from resolved issues, and powerful search capabilities.

## Overview

The `KnowledgeService` provides functionality to:
- Suggest relevant knowledge articles for tickets using multiple matching strategies
- Generate FAQ articles automatically from frequently resolved tickets
- Track article effectiveness and usage statistics
- Search articles by content, keywords, and sections
- Manage article relationships and hierarchical sections

## Service Methods

### Suggest Articles for Ticket

Automatically suggest relevant knowledge base articles for a ticket using multiple matching algorithms.

```php
use LucaLongo\LaravelHelpdesk\Services\KnowledgeService;
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeArticleStatus;
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeArticleRelationType;
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeSuggestionMatchType;

$service = app(KnowledgeService::class);

$suggestions = $service->suggestArticlesForTicket($ticket, $limit = 5);
```

**Parameters:**
- `$ticket` (Ticket) - The ticket to find suggestions for
- `$limit` (int) - Maximum number of suggestions (0 to disable suggestions)

**Returns:** `Collection` of `KnowledgeSuggestion` models ordered by relevance score

The suggestion algorithm uses multiple matching strategies:
- **Keyword Matching**: Searches article titles and content for keywords from the ticket
- **Category Matching**: Finds articles in sections matching the ticket's categories
- **Similar Tickets**: Locates articles that helped resolve similar tickets
- **Tag Matching**: Matches articles with keywords corresponding to ticket tags

### Generate FAQ from Resolved Tickets

Automatically create FAQ articles from patterns in successfully resolved tickets.

```php
$faqArticles = $service->generateFAQFromResolvedTickets(
    minRating: 4,        // Minimum customer rating
    minOccurrences: 3    // Minimum times issue occurred
);
```

**Parameters:**
- `$minRating` (int) - Minimum customer satisfaction rating (1-5)
- `$minOccurrences` (int) - Minimum number of similar tickets required

**Returns:** `Collection` of generated `KnowledgeArticle` models

### Track Article View

Record when an article is viewed and update statistics.

```php
$service->trackArticleView($article, $ticket);
```

This method:
- Increments the article's view count
- Marks the suggestion as viewed if linked to a ticket
- Fires a `KnowledgeArticleViewed` event

### Search Articles

Search knowledge base articles by query text.

```php
$searchQuery = $service->searchArticles(
    query: 'password reset',
    section: $section,      // Optional section filter
    publicOnly: true        // Only include published articles
);

$results = $searchQuery->paginate(20);
```

**Returns:** `Builder` instance for further query refinement

## KnowledgeArticle Model

The main model for knowledge base articles with rich functionality and relationships.

### Model Properties

```php
class KnowledgeArticle extends Model
{
    protected $casts = [
        'status' => KnowledgeArticleStatus::class,
        'is_featured' => 'boolean',
        'is_faq' => 'boolean',
        'is_public' => 'boolean',
        'keywords' => 'array',
        'meta' => 'array',
        'published_at' => 'datetime',
        'last_reviewed_at' => 'datetime',
    ];
}
```

### Key Properties

- `title` - Article title
- `slug` - URL-friendly identifier (auto-generated)
- `ulid` - Unique identifier
- `content` - Main article content (Markdown/HTML)
- `excerpt` - Short description/summary
- `status` - Publication status (Draft, Published, Archived)
- `is_featured` - Whether article is prominently displayed
- `is_faq` - Whether article is part of FAQ section
- `is_public` - Whether article is publicly accessible
- `keywords` - Array of searchable keywords
- `view_count` - Number of times article was viewed
- `helpful_count` - Number of "helpful" votes
- `not_helpful_count` - Number of "not helpful" votes
- `effectiveness_score` - Calculated effectiveness percentage

### Model Methods

```php
// Check publication status
$article->isPublished();

// Check if article needs review (90+ days old)
$article->needsReview();

// Increment view counter
$article->incrementViewCount();

// Mark as helpful/not helpful
$article->markAsHelpful();
$article->markAsNotHelpful();

// Calculate effectiveness score
$score = $article->getEffectivenessScore();

// Link article to ticket
$article->attachToTicket($ticket, ['was_helpful' => true, 'resolved_issue' => true]);

// Add related article
$article->addRelatedArticle($relatedArticle, KnowledgeArticleRelationType::Related);
```

### Relationships

```php
// Article author (polymorphic)
$article->author;

// Sections containing this article
$article->sections;

// Tickets linked to this article
$article->tickets;

// Related articles
$article->relatedArticles;
$article->relatedTo;

// Article suggestions for tickets
$article->suggestions;
```

### Scopes

```php
// Get published articles only
KnowledgeArticle::published()->get();

// Get featured articles
KnowledgeArticle::featured()->get();

// Get FAQ articles
KnowledgeArticle::faq()->get();

// Order by effectiveness
KnowledgeArticle::mostHelpful()->get();

// Order by popularity
KnowledgeArticle::popular()->get();

// Get recent articles
KnowledgeArticle::recent()->get();

// Find articles needing review
KnowledgeArticle::needingReview()->get();

// Search articles
KnowledgeArticle::search('password')->get();

// Filter by section
KnowledgeArticle::inSection($section)->get();
```

## KnowledgeSection Model

Hierarchical sections for organizing articles into categories and subcategories.

### Model Properties

```php
class KnowledgeSection extends Model
{
    protected $casts = [
        'meta' => 'array',
        'is_visible' => 'boolean',
    ];
}
```

### Key Properties

- `name` - Section name
- `slug` - URL-friendly identifier
- `description` - Section description
- `parent_id` - Parent section for hierarchy
- `position` - Sort order within parent
- `is_visible` - Whether section is publicly visible

### Model Methods

```php
// Hierarchical navigation
$section->parent;
$section->children;
$section->visibleChildren();

// Get section hierarchy
$ancestors = $section->getAncestors();
$descendants = $section->getDescendants();
$breadcrumb = $section->getBreadcrumb();

// Section properties
$level = $section->getLevel();
$isRoot = $section->isRoot();
$isLeaf = $section->isLeaf();

// Content checks
$hasContent = $section->hasVisibleContent();
```

### Relationships

```php
// Articles in this section
$section->articles;
$section->publishedArticles;
```

### Scopes

```php
// Get root sections
KnowledgeSection::roots()->get();

// Get visible sections
KnowledgeSection::visible()->get();

// Include article counts
KnowledgeSection::withArticleCount()->get();
```

## KnowledgeSuggestion Model

Links between tickets and suggested articles with tracking data.

### Model Properties

```php
class KnowledgeSuggestion extends Model
{
    protected $casts = [
        'match_type' => KnowledgeSuggestionMatchType::class,
        'matched_terms' => 'array',
        'was_viewed' => 'boolean',
        'was_helpful' => 'boolean',
        'viewed_at' => 'datetime',
        'relevance_score' => 'float',
    ];
}
```

### Model Methods

```php
// Track user interaction
$suggestion->markAsViewed();
$suggestion->markAsHelpful(true);

// Calculate weighted score based on match type
$weightedScore = $suggestion->getWeightedScore();
```

### Scopes

```php
// Filter by interaction
KnowledgeSuggestion::viewed()->get();
KnowledgeSuggestion::helpful()->get();
KnowledgeSuggestion::notHelpful()->get();

// Filter by match type
KnowledgeSuggestion::byMatchType(KnowledgeSuggestionMatchType::Keyword)->get();

// Get top suggestions
KnowledgeSuggestion::topSuggestions(10)->get();

// Filter by ticket
KnowledgeSuggestion::forTicket($ticket)->get();
```

## Events

### KnowledgeSuggestionGenerated

Fired when suggestions are generated for a ticket.

```php
use LucaLongo\LaravelHelpdesk\Events\KnowledgeSuggestionGenerated;

class KnowledgeSuggestionGenerated
{
    public function __construct(
        public Ticket $ticket,
        public int $suggestionCount
    ) {}
}
```

### KnowledgeArticleViewed

Fired when an article is viewed.

```php
use LucaLongo\LaravelHelpdesk\Events\KnowledgeArticleViewed;

class KnowledgeArticleViewed
{
    public function __construct(
        public KnowledgeArticle $article,
        public ?Ticket $ticket = null
    ) {}
}
```

## Usage Examples

### Basic Article Suggestions

```php
$service = app(KnowledgeService::class);

// Get suggestions when ticket is created
$suggestions = $service->suggestArticlesForTicket($ticket);

foreach ($suggestions as $suggestion) {
    echo "Article: {$suggestion->article->title}\n";
    echo "Relevance: {$suggestion->relevance_score}\n";
    echo "Match Type: {$suggestion->match_type->value}\n";
    echo "Matched Terms: " . implode(', ', $suggestion->matched_terms ?? []) . "\n\n";
}
```

### Integration with Ticket Workflow

```php
class TicketService
{
    public function createTicket(array $data): Ticket
    {
        $ticket = Ticket::create($data);

        // Generate knowledge suggestions
        $suggestions = $this->knowledgeService->suggestArticlesForTicket($ticket);

        if ($suggestions->isNotEmpty()) {
            // Notify agent about available articles
            event(new TicketCreatedWithSuggestions($ticket, $suggestions));
        }

        return $ticket;
    }
}
```

### Agent Interface Integration

```php
class TicketController extends Controller
{
    public function show(Ticket $ticket)
    {
        $suggestions = $this->knowledgeService->suggestArticlesForTicket($ticket);

        return view('tickets.show', compact('ticket', 'suggestions'));
    }

    public function markSuggestionHelpful(KnowledgeSuggestion $suggestion)
    {
        $suggestion->markAsHelpful(true);

        return response()->json(['message' => 'Thank you for your feedback!']);
    }
}
```

### Customer Portal Integration

```php
class CustomerPortalController extends Controller
{
    public function showTicket(Ticket $ticket)
    {
        // Only show public suggestions to customers
        $suggestions = $ticket->knowledgeSuggestions()
            ->whereHas('article', function ($query) {
                $query->published();
            })
            ->with('article')
            ->get();

        return view('portal.tickets.show', compact('ticket', 'suggestions'));
    }

    public function viewArticle(KnowledgeArticle $article, ?Ticket $ticket = null)
    {
        if (!$article->isPublished()) {
            abort(404);
        }

        $this->knowledgeService->trackArticleView($article, $ticket);

        return view('portal.articles.show', compact('article'));
    }
}
```

### Automated FAQ Generation

```php
class FAQGenerationCommand extends Command
{
    protected $signature = 'helpdesk:generate-faqs {--min-rating=4} {--min-occurrences=3}';

    public function handle(KnowledgeService $service)
    {
        $faqArticles = $service->generateFAQFromResolvedTickets(
            minRating: $this->option('min-rating'),
            minOccurrences: $this->option('min-occurrences')
        );

        $this->info("Generated {$faqArticles->count()} FAQ articles");

        foreach ($faqArticles as $article) {
            $this->line("- {$article->title}");
        }

        // Auto-publish high-confidence FAQs
        $highConfidenceFAQs = $faqArticles->filter(function ($article) {
            $sourceTickets = $article->meta['source_tickets'] ?? [];
            return count($sourceTickets) >= 5 && $article->meta['average_rating'] >= 4.5;
        });

        $highConfidenceFAQs->each(function ($article) {
            $article->update([
                'status' => KnowledgeArticleStatus::Published,
                'published_at' => now(),
            ]);
        });

        $this->info("Auto-published {$highConfidenceFAQs->count()} high-confidence FAQs");
    }
}
```

### Article Management

```php
class KnowledgeController extends Controller
{
    public function index()
    {
        $articles = KnowledgeArticle::with(['sections', 'author'])
            ->when(request('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->when(request('section'), function ($query, $sectionId) {
                $query->inSection($sectionId);
            })
            ->when(request('search'), function ($query, $search) {
                $query->search($search);
            })
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        $sections = KnowledgeSection::roots()->with('children')->get();

        return view('admin.knowledge.index', compact('articles', 'sections'));
    }

    public function analytics()
    {
        $popularArticles = KnowledgeArticle::popular()
            ->published()
            ->limit(10)
            ->get();

        $needingReview = KnowledgeArticle::needingReview()
            ->published()
            ->count();

        $recentSuggestions = KnowledgeSuggestion::with(['article', 'ticket'])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('relevance_score', 'desc')
            ->limit(20)
            ->get();

        return view('admin.knowledge.analytics', compact(
            'popularArticles',
            'needingReview',
            'recentSuggestions'
        ));
    }
}
```

### Search Functionality

```php
class ArticleSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');
        $sectionId = $request->input('section');

        $section = $sectionId ? KnowledgeSection::find($sectionId) : null;

        $articles = $this->knowledgeService
            ->searchArticles($query, $section, publicOnly: true)
            ->with(['sections'])
            ->paginate(10);

        // Track search for analytics
        SearchQuery::create([
            'query' => $query,
            'section_id' => $sectionId,
            'results_count' => $articles->total(),
            'ip_address' => $request->ip(),
        ]);

        return view('knowledge.search', compact('articles', 'query', 'section'));
    }
}
```

### Article Effectiveness Tracking

```php
class ArticleEffectivenessService
{
    public function trackArticleUsage(KnowledgeArticle $article, Ticket $ticket, bool $resolvedIssue = false)
    {
        $article->attachToTicket($ticket, [
            'was_helpful' => $resolvedIssue,
            'resolved_issue' => $resolvedIssue,
            'linked_by_type' => auth()->user()?->getMorphClass(),
            'linked_by_id' => auth()->id(),
        ]);

        if ($resolvedIssue) {
            $article->markAsHelpful();
        } else {
            $article->markAsNotHelpful();
        }
    }

    public function getArticlePerformance(KnowledgeArticle $article): array
    {
        $linkedTickets = $article->tickets()
            ->withPivot(['was_helpful', 'resolved_issue'])
            ->get();

        $totalLinks = $linkedTickets->count();
        $helpfulLinks = $linkedTickets->where('pivot.was_helpful', true)->count();
        $resolvedIssues = $linkedTickets->where('pivot.resolved_issue', true)->count();

        return [
            'total_views' => $article->view_count,
            'total_links' => $totalLinks,
            'helpful_percentage' => $totalLinks > 0 ? round(($helpfulLinks / $totalLinks) * 100, 2) : 0,
            'resolution_percentage' => $totalLinks > 0 ? round(($resolvedIssues / $totalLinks) * 100, 2) : 0,
            'effectiveness_score' => $article->getEffectivenessScore(),
        ];
    }
}
```

### Suggestion Improvement

```php
class SuggestionAnalytics
{
    public function analyzeSuggestionQuality()
    {
        $suggestions = KnowledgeSuggestion::with(['article', 'ticket'])
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $byMatchType = $suggestions->groupBy('match_type');

        $analysis = [];
        foreach ($byMatchType as $matchType => $typeSuggestions) {
            $viewed = $typeSuggestions->where('was_viewed', true);
            $helpful = $typeSuggestions->where('was_helpful', true);

            $analysis[$matchType] = [
                'total' => $typeSuggestions->count(),
                'view_rate' => $viewed->count() / $typeSuggestions->count() * 100,
                'helpful_rate' => $helpful->count() / max($viewed->count(), 1) * 100,
                'avg_relevance_score' => $typeSuggestions->avg('relevance_score'),
            ];
        }

        return $analysis;
    }

    public function identifyImprovementOpportunities()
    {
        // Find tickets without suggestions
        $ticketsWithoutSuggestions = Ticket::whereDoesntHave('knowledgeSuggestions')
            ->where('created_at', '>=', now()->subDays(30))
            ->with(['categories', 'tags'])
            ->get();

        // Find common patterns in tickets without suggestions
        $missingKeywords = [];
        $missingCategories = [];

        foreach ($ticketsWithoutSuggestions as $ticket) {
            $keywords = $this->extractKeywords($ticket->subject . ' ' . $ticket->description);
            $missingKeywords = array_merge($missingKeywords, $keywords);

            $categories = $ticket->categories->pluck('name')->toArray();
            $missingCategories = array_merge($missingCategories, $categories);
        }

        return [
            'tickets_without_suggestions' => $ticketsWithoutSuggestions->count(),
            'common_missing_keywords' => array_count_values($missingKeywords),
            'common_missing_categories' => array_count_values($missingCategories),
        ];
    }
}
```

## Best Practices

1. **Content Quality**: Maintain high-quality, up-to-date articles
2. **Categorization**: Use proper sections and categories for organization
3. **Keywords**: Include relevant keywords for better matching
4. **Regular Review**: Review and update articles regularly
5. **Effectiveness Tracking**: Monitor article performance and usage
6. **User Feedback**: Collect and act on helpfulness feedback
7. **Search Optimization**: Optimize content for search discoverability
8. **Integration**: Integrate suggestions seamlessly into agent workflow

## Advanced Features

### Machine Learning Integration

```php
class MLSuggestionService extends KnowledgeService
{
    public function getSuggestionML(Ticket $ticket): Collection
    {
        // Use ML service to get semantic similarity
        $mlSuggestions = Http::post('https://ml-api.company.com/suggestions', [
            'text' => $ticket->subject . ' ' . $ticket->description,
            'metadata' => [
                'category' => $ticket->categories->pluck('name'),
                'priority' => $ticket->priority->value,
            ]
        ])->json();

        // Combine with traditional suggestions
        $traditionalSuggestions = parent::suggestArticlesForTicket($ticket, 10);

        return $this->mergeSuggestions($traditionalSuggestions, $mlSuggestions);
    }
}
```

### Real-time Suggestions

```php
// WebSocket integration for real-time suggestions
class RealTimeSuggestions
{
    public function broadcastSuggestions(Ticket $ticket)
    {
        $suggestions = app(KnowledgeService::class)
            ->suggestArticlesForTicket($ticket);

        broadcast(new TicketSuggestionsUpdated($ticket, $suggestions))
            ->toOthers();
    }
}
```

## Database Schema

The knowledge base system uses several interconnected tables:

```php
// Knowledge articles
Schema::create('helpdesk_knowledge_articles', function (Blueprint $table) {
    $table->id();
    $table->string('ulid')->unique();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('excerpt')->nullable();
    $table->longText('content');
    $table->string('status')->default(KnowledgeArticleStatus::Draft->value);
    $table->boolean('is_featured')->default(false);
    $table->boolean('is_faq')->default(false);
    $table->boolean('is_public')->default(true);
    $table->json('keywords')->nullable();
    $table->unsignedInteger('view_count')->default(0);
    $table->unsignedInteger('helpful_count')->default(0);
    $table->unsignedInteger('not_helpful_count')->default(0);
    $table->decimal('effectiveness_score', 5, 2)->nullable();
    $table->string('author_type')->nullable();
    $table->unsignedBigInteger('author_id')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->timestamp('last_reviewed_at')->nullable();
    $table->json('meta')->nullable();
    $table->timestamps();

    $table->index(['status', 'is_public']);
    $table->index('published_at');
    $table->fullText(['title', 'content']);
});

// Knowledge sections
Schema::create('helpdesk_knowledge_sections', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->foreignId('parent_id')->nullable()->constrained('helpdesk_knowledge_sections');
    $table->unsignedInteger('position')->default(0);
    $table->boolean('is_visible')->default(true);
    $table->json('meta')->nullable();
    $table->timestamps();

    $table->index(['parent_id', 'position']);
});

// Article-section relationships
Schema::create('helpdesk_knowledge_article_sections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('article_id')->constrained('helpdesk_knowledge_articles');
    $table->foreignId('section_id')->constrained('helpdesk_knowledge_sections');
    $table->unsignedInteger('position')->default(0);
    $table->timestamps();

    $table->unique(['article_id', 'section_id']);
});

// Knowledge suggestions
Schema::create('helpdesk_knowledge_suggestions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->onDelete('cascade');
    $table->foreignId('article_id')->constrained('helpdesk_knowledge_articles');
    $table->decimal('relevance_score', 5, 2);
    $table->string('match_type');
    $table->json('matched_terms')->nullable();
    $table->boolean('was_viewed')->default(false);
    $table->boolean('was_helpful')->nullable();
    $table->timestamp('viewed_at')->nullable();
    $table->timestamps();

    $table->index(['ticket_id', 'relevance_score']);
    $table->index(['article_id', 'was_helpful']);
});
```