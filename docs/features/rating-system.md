# Rating System

The Laravel Helpdesk package includes a comprehensive rating system that allows customers to rate their support experience and provides valuable metrics for measuring customer satisfaction.

## Overview

The `RatingService` enables you to:
- Collect customer satisfaction ratings (1-5 stars)
- Gather feedback comments from customers
- Calculate CSAT (Customer Satisfaction Score) and NPS (Net Promoter Score)
- Track rating trends over time
- Generate detailed satisfaction reports
- Control rating eligibility based on ticket status and timing

## Service Methods

### Submit Rating

Allow customers to rate their support experience.

```php
use LucaLongo\LaravelHelpdesk\Services\RatingService;

$service = app(RatingService::class);

$rating = $service->submitRating(
    ticket: $ticket,
    user: $customer,
    rating: 5,
    feedback: 'Excellent support! Issue resolved quickly.',
    metadata: ['channel' => 'email', 'agent_id' => 123]
);
```

**Parameters:**
- `$ticket` (Ticket) - The ticket being rated
- `$user` (Model) - User submitting the rating
- `$rating` (int) - Rating value (1-5 stars)
- `$feedback` (string|null) - Optional feedback text
- `$metadata` (array) - Additional metadata

**Returns:** `TicketRating|null` - The rating model, or null if user cannot rate

### Check Rating Eligibility

Determine if a user can rate a specific ticket.

```php
$canRate = $service->canRateTicket($ticket, $customer);
```

**Returns:** `bool` - Whether the user is eligible to rate this ticket

### Get User Rating

Retrieve an existing rating for a ticket by a specific user.

```php
$existingRating = $service->getUserRating($ticket, $customer);
```

**Returns:** `TicketRating|null`

### Calculate Average Rating

Get the average rating across all tickets.

```php
// All time average
$averageRating = $service->getAverageRating();

// Period-specific average
$averageRating = $service->getAverageRating(
    from: now()->startOfMonth(),
    to: now()->endOfMonth()
);
```

**Returns:** `float` - Average rating (0-5)

### Calculate CSAT Score

Calculate Customer Satisfaction Score (percentage of ratings 4+ stars).

```php
$csat = $service->getCSAT(
    from: now()->startOfQuarter(),
    to: now()->endOfQuarter()
);
```

**Returns:** `float` - CSAT percentage (0-100)

### Calculate NPS Score

Calculate Net Promoter Score based on ratings.

```php
$nps = $service->getNPS(
    from: now()->startOfYear(),
    to: now()->endOfYear()
);
```

**Returns:** `float` - NPS score (-100 to +100)

### Get Rating Distribution

Retrieve breakdown of ratings by star value.

```php
$distribution = $service->getRatingDistribution();
```

**Returns:** `Collection` with counts for each rating level
```php
[
    1 => 5,    // 5 one-star ratings
    2 => 3,    // 3 two-star ratings
    3 => 10,   // 10 three-star ratings
    4 => 25,   // 25 four-star ratings
    5 => 57,   // 57 five-star ratings
]
```

### Calculate Response Rate

Get the percentage of eligible tickets that received ratings.

```php
$responseRate = $service->getResponseRate(
    from: now()->startOfMonth(),
    to: now()->endOfMonth()
);
```

**Returns:** `float` - Response rate percentage (0-100)

### Get Average Response Time

Calculate average response time for rated tickets.

```php
$avgResponseTime = $service->getAverageResponseTime();
```

**Returns:** `float|null` - Average response time in hours

### Get Feedback Sentiment

Analyze sentiment distribution of ratings.

```php
$sentiment = $service->getFeedbackSentiment();
```

**Returns:** Sentiment breakdown
```php
[
    'positive' => 75.5,    // Percentage of positive ratings (4-5 stars)
    'neutral' => 15.0,     // Percentage of neutral ratings (3 stars)
    'negative' => 9.5,     // Percentage of negative ratings (1-2 stars)
]
```

### Get Recent Feedback

Retrieve recent feedback comments with optional rating filters.

```php
// Get 10 most recent feedback entries
$recentFeedback = $service->getRecentFeedback(limit: 10);

// Get recent negative feedback only
$negativeFeedback = $service->getRecentFeedback(
    limit: 20,
    minRating: null,
    maxRating: 2
);
```

**Returns:** `Collection` of `TicketRating` models with relationships loaded

### Generate Metrics Report

Get comprehensive satisfaction metrics.

```php
$metrics = $service->getMetrics(
    from: now()->startOfMonth(),
    to: now()->endOfMonth()
);
```

**Returns:** Complete metrics array
```php
[
    'average_rating' => 4.2,
    'csat' => 78.5,
    'nps' => 25.3,
    'response_rate' => 45.2,
    'average_response_time_hours' => 4.5,
    'sentiment' => ['positive' => 75.5, 'neutral' => 15.0, 'negative' => 9.5],
    'distribution' => [1 => 5, 2 => 3, 3 => 10, 4 => 25, 5 => 57]
]
```

### Get Rating Trends

Analyze rating trends over time.

```php
$trends = $service->getTrends(
    days: 30,
    interval: 'day'  // 'day', 'week', or 'month'
);
```

**Returns:** `Collection` of trend data points
```php
[
    [
        'period' => '2023-10-01',
        'average_rating' => 4.2,
        'total_ratings' => 15,
        'positive_count' => 12,
        'csat' => 80.0
    ],
    // ... more periods
]
```

## TicketRating Model

The rating model stores customer feedback and satisfaction data.

### Model Properties

```php
class TicketRating extends Model
{
    protected $fillable = [
        'ticket_id',              // ID of rated ticket
        'user_id',                // ID of user who rated
        'rating',                 // Rating value (1-5)
        'feedback',               // Optional feedback text
        'resolved_at',            // When ticket was resolved
        'rated_at',               // When rating was submitted
        'response_time_hours',    // Response time calculation
        'metadata',               // Additional data
    ];

    protected $casts = [
        'rating' => 'integer',
        'resolved_at' => 'datetime',
        'rated_at' => 'datetime',
        'response_time_hours' => 'integer',
        'metadata' => 'array',
    ];
}
```

### Model Methods

```php
// Check if rating is positive (4-5 stars)
$rating->isPositive();

// Check if rating is neutral (3 stars)
$rating->isNeutral();

// Check if rating is negative (1-2 stars)
$rating->isNegative();

// Get visual star representation
$rating->getStars(); // Returns "★★★★★" for 5-star rating

// Get satisfaction level description
$rating->getSatisfactionLevel(); // Returns "Very Satisfied", "Satisfied", etc.
```

### Relationships

```php
// Get the rated ticket
$rating->ticket;

// Get the user who submitted the rating
$rating->user;
```

### Scopes

```php
// Get only positive ratings
TicketRating::positive()->get();

// Get only negative ratings
TicketRating::negative()->get();

// Get ratings in specific period
TicketRating::inPeriod($startDate, $endDate)->get();
```

## Events

### TicketRated

Fired when a new rating is submitted.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketRated;

class TicketRated
{
    public function __construct(
        public TicketRating $rating
    ) {}
}
```

### TicketRatingUpdated

Fired when an existing rating is updated.

```php
use LucaLongo\LaravelHelpdesk\Events\TicketRatingUpdated;

class TicketRatingUpdated
{
    public function __construct(
        public TicketRating $rating
    ) {}
}
```

## Configuration

Configure the rating system in `config/helpdesk.php`:

```php
'rating' => [
    'enabled' => true,
    'allowed_statuses' => [
        \LucaLongo\LaravelHelpdesk\Enums\TicketStatus::Resolved,
        \LucaLongo\LaravelHelpdesk\Enums\TicketStatus::Closed,
    ],
    'rating_period_days' => 30,    // How long after closure ratings are allowed
    'required_fields' => ['rating'], // 'rating', 'feedback'
    'anonymous_ratings' => false,  // Allow anonymous ratings
],
```

## Usage Examples

### Basic Rating Workflow

```php
$ratingService = app(RatingService::class);

// Customer submits rating after ticket resolution
if ($ratingService->canRateTicket($ticket, $customer)) {
    $rating = $ratingService->submitRating(
        ticket: $ticket,
        user: $customer,
        rating: 4,
        feedback: 'Good support, but could be faster'
    );

    if ($rating) {
        // Send thank you email or redirect with success message
        return response()->json(['message' => 'Thank you for your feedback!']);
    }
}
```

### Rating Collection Form

```php
class RatingController extends Controller
{
    public function show(Ticket $ticket)
    {
        $user = auth()->user();

        if (!$this->ratingService->canRateTicket($ticket, $user)) {
            abort(403, 'You cannot rate this ticket');
        }

        $existingRating = $this->ratingService->getUserRating($ticket, $user);

        return view('tickets.rate', compact('ticket', 'existingRating'));
    }

    public function store(Request $request, Ticket $ticket)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:5000',
        ]);

        $rating = $this->ratingService->submitRating(
            ticket: $ticket,
            user: auth()->user(),
            rating: $request->input('rating'),
            feedback: $request->input('feedback'),
            metadata: [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Thank you for rating your support experience!');
    }
}
```

### Analytics Dashboard

```php
class AnalyticsDashboard
{
    public function getSatisfactionMetrics()
    {
        $ratingService = app(RatingService::class);

        $currentMonth = $ratingService->getMetrics(
            from: now()->startOfMonth(),
            to: now()->endOfMonth()
        );

        $lastMonth = $ratingService->getMetrics(
            from: now()->subMonth()->startOfMonth(),
            to: now()->subMonth()->endOfMonth()
        );

        $trends = $ratingService->getTrends(days: 30, interval: 'day');

        return [
            'current' => $currentMonth,
            'previous' => $lastMonth,
            'trends' => $trends,
            'improvement' => [
                'csat' => $currentMonth['csat'] - $lastMonth['csat'],
                'nps' => $currentMonth['nps'] - $lastMonth['nps'],
                'avg_rating' => $currentMonth['average_rating'] - $lastMonth['average_rating'],
            ]
        ];
    }
}
```

### Automatic Rating Requests

```php
// Event listener to request ratings when tickets are closed
class RequestTicketRating
{
    public function handle(TicketStatusChanged $event): void
    {
        if ($event->newStatus === TicketStatus::Closed) {
            // Send rating request email
            $this->sendRatingRequest($event->ticket);
        }
    }

    private function sendRatingRequest(Ticket $ticket): void
    {
        $ratingService = app(RatingService::class);

        if ($ratingService->canRateTicket($ticket, $ticket->customer)) {
            Mail::to($ticket->customer_email)
                ->send(new RatingRequestMail($ticket));
        }
    }
}
```

### Rating-Based Insights

```php
class SupportInsights
{
    public function getAgentPerformance()
    {
        // Get ratings for tickets by assigned agent
        $agentRatings = TicketRating::join('helpdesk_tickets', 'helpdesk_ticket_ratings.ticket_id', '=', 'helpdesk_tickets.id')
            ->join('users', 'helpdesk_tickets.assigned_to_id', '=', 'users.id')
            ->select('users.name', 'users.id')
            ->selectRaw('AVG(rating) as avg_rating')
            ->selectRaw('COUNT(*) as total_ratings')
            ->selectRaw('SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive_ratings')
            ->groupBy('users.id', 'users.name')
            ->get();

        return $agentRatings->map(function ($agent) {
            $agent->csat = ($agent->positive_ratings / $agent->total_ratings) * 100;
            return $agent;
        });
    }

    public function getIssueTypeRatings()
    {
        // Analyze ratings by ticket category
        return TicketRating::join('helpdesk_tickets', 'helpdesk_ticket_ratings.ticket_id', '=', 'helpdesk_tickets.id')
            ->join('helpdesk_ticket_categories', 'helpdesk_tickets.id', '=', 'helpdesk_ticket_categories.ticket_id')
            ->join('helpdesk_categories', 'helpdesk_ticket_categories.category_id', '=', 'helpdesk_categories.id')
            ->select('helpdesk_categories.name')
            ->selectRaw('AVG(rating) as avg_rating')
            ->selectRaw('COUNT(*) as total_ratings')
            ->groupBy('helpdesk_categories.id', 'helpdesk_categories.name')
            ->orderBy('avg_rating', 'desc')
            ->get();
    }
}
```

### Rating Validation and Business Rules

```php
class RatingBusinessRules
{
    public function validateRatingEligibility(Ticket $ticket, User $user): array
    {
        $errors = [];
        $ratingService = app(RatingService::class);

        // Check if user can rate this ticket
        if (!$ratingService->canRateTicket($ticket, $user)) {
            $errors[] = 'You are not eligible to rate this ticket';
        }

        // Check if ticket is in allowed status
        $allowedStatuses = config('helpdesk.rating.allowed_statuses', []);
        if (!in_array($ticket->status, $allowedStatuses)) {
            $errors[] = 'Ticket must be resolved or closed to rate';
        }

        // Check rating period
        $ratingPeriod = config('helpdesk.rating.rating_period_days', 30);
        if ($ticket->closed_at && $ticket->closed_at->addDays($ratingPeriod)->isPast()) {
            $errors[] = 'Rating period has expired';
        }

        // Check for existing rating
        if ($ratingService->getUserRating($ticket, $user)) {
            $errors[] = 'You have already rated this ticket';
        }

        return $errors;
    }
}
```

### Integration with Reporting

```php
class SatisfactionReport
{
    public function generateMonthlyReport(Carbon $month)
    {
        $ratingService = app(RatingService::class);

        $metrics = $ratingService->getMetrics(
            from: $month->startOfMonth(),
            to: $month->endOfMonth()
        );

        $recentFeedback = $ratingService->getRecentFeedback(50);
        $trends = $ratingService->getTrends(30, 'day');

        return [
            'period' => $month->format('F Y'),
            'summary' => $metrics,
            'feedback_samples' => $recentFeedback->take(10),
            'negative_feedback' => $recentFeedback->where('rating', '<=', 2),
            'trends' => $trends,
            'recommendations' => $this->generateRecommendations($metrics),
        ];
    }

    private function generateRecommendations(array $metrics): array
    {
        $recommendations = [];

        if ($metrics['csat'] < 70) {
            $recommendations[] = 'CSAT is below target (70%). Focus on response time and resolution quality.';
        }

        if ($metrics['response_rate'] < 30) {
            $recommendations[] = 'Low rating response rate. Consider follow-up campaigns.';
        }

        if ($metrics['average_response_time_hours'] > 8) {
            $recommendations[] = 'Response time is above target. Review staffing and processes.';
        }

        return $recommendations;
    }
}
```

## Best Practices

1. **Timing**: Request ratings soon after ticket resolution while experience is fresh
2. **Eligibility**: Only allow ratings from ticket creators and involved parties
3. **Follow-up**: Follow up on negative ratings to understand issues
4. **Incentives**: Consider incentives for rating participation
5. **Analysis**: Regularly analyze rating trends and patterns
6. **Response**: Respond to feedback, especially negative ratings
7. **Integration**: Integrate ratings with agent performance reviews
8. **Improvement**: Use rating data to improve support processes

## Rating Collection Strategies

### Email-Based Collection

```php
// In your ticket resolution workflow
if ($ticket->status === TicketStatus::Resolved) {
    // Send immediate rating request
    RatingRequestJob::dispatch($ticket)->delay(now()->addMinutes(30));

    // Send follow-up if no rating after 3 days
    RatingReminderJob::dispatch($ticket)->delay(now()->addDays(3));
}
```

### In-App Rating Widget

```blade
{{-- rating-widget.blade.php --}}
@if($canRate)
<div class="rating-widget">
    <h4>How was your support experience?</h4>
    <form action="{{ route('tickets.rate', $ticket) }}" method="POST">
        @csrf
        <div class="star-rating">
            @for($i = 1; $i <= 5; $i++)
                <button type="button" class="star" data-rating="{{ $i }}">★</button>
            @endfor
        </div>
        <input type="hidden" name="rating" required>
        <textarea name="feedback" placeholder="Tell us about your experience (optional)"></textarea>
        <button type="submit">Submit Rating</button>
    </form>
</div>
@endif
```

## Database Schema

The rating system uses the `helpdesk_ticket_ratings` table:

```php
Schema::create('helpdesk_ticket_ratings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->tinyInteger('rating')->unsigned(); // 1-5
    $table->text('feedback')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->timestamp('rated_at');
    $table->integer('response_time_hours')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->unique(['ticket_id', 'user_id']); // One rating per user per ticket
    $table->index(['rating', 'rated_at']);
    $table->index('rated_at');
});
```