# Enums

The package uses PHP enums to provide type-safe constants for various ticket properties.

## Ticket Enums

### TicketStatus

Defines the lifecycle states of a ticket.

```php
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;

enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    // Methods
    public function label(): string
    public function color(): string
    public function icon(): string
    public function canTransitionTo(self $status): bool
    public function allowedTransitions(): array
    public static function default(): self
}

// Usage
$status = TicketStatus::Open;
echo $status->label(); // "Open"
echo $status->color(); // "blue"

if ($status->canTransitionTo(TicketStatus::InProgress)) {
    $ticket->transitionTo(TicketStatus::InProgress);
}
```

### TicketPriority

Defines priority levels for tickets.

```php
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;

enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    // Methods
    public function label(): string
    public function color(): string
    public function icon(): string
    public function order(): int
    public static function default(): self
    public static function values(): array
    public static function options(): array
}

// Usage
$priority = TicketPriority::High;
echo $priority->label(); // "High"
echo $priority->color(); // "orange"
echo $priority->order(); // 3

// Get all options for select
$options = TicketPriority::options();
// ['low' => 'Low', 'normal' => 'Normal', ...]
```

### TicketType

Defines different types of tickets.

```php
use LucaLongo\LaravelHelpdesk\Enums\TicketType;

enum TicketType: string
{
    case ProductSupport = 'product_support';
    case Commercial = 'commercial';

    // Methods
    public function label(): string
    public function color(): string
    public function icon(): string
    public static function default(): self
    public static function values(): array
    public static function options(): array
}

// Usage
$type = TicketType::ProductSupport;
echo $type->label(); // "Product Support"
echo $type->color(); // "blue"
```

## Relation Enums

### TicketRelationType

Defines types of relationships between tickets.

```php
use LucaLongo\LaravelHelpdesk\Enums\TicketRelationType;

enum TicketRelationType: string
{
    case RelatedTo = 'related_to';
    case DuplicateOf = 'duplicate_of';
    case Blocks = 'blocks';
    case BlockedBy = 'blocked_by';
    case CausedBy = 'caused_by';
    case Causes = 'causes';

    // Methods
    public function label(): string
    public function inverseType(): self
    public static function values(): array
    public static function options(): array
}

// Usage
$type = TicketRelationType::Blocks;
echo $type->label(); // "Blocks"
$inverse = $type->inverseType(); // TicketRelationType::BlockedBy
```

## Knowledge Base Enums

### KnowledgeArticleStatus

Status for knowledge base articles.

```php
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeArticleStatus;

enum KnowledgeArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    // Methods
    public function label(): string
    public function color(): string
    public static function default(): self
    public static function values(): array
    public static function options(): array
}
```

### KnowledgeArticleRelationType

Types of relationships between articles and tickets.

```php
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeArticleRelationType;

enum KnowledgeArticleRelationType: string
{
    case ResolvesTicket = 'resolves_ticket';
    case RelatedToTicket = 'related_to_ticket';
    case CreatedFromTicket = 'created_from_ticket';

    // Methods
    public function label(): string
    public static function values(): array
}
```

### KnowledgeSuggestionMatchType

Types of matches for knowledge suggestions.

```php
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeSuggestionMatchType;

enum KnowledgeSuggestionMatchType: string
{
    case Exact = 'exact';
    case Similar = 'similar';
    case Related = 'related';

    // Methods
    public function label(): string
    public function minScore(): float
}
```

## Using Enums

### In Models

```php
class Ticket extends Model
{
    protected $casts = [
        'status' => TicketStatus::class,
        'priority' => TicketPriority::class,
        'type' => TicketType::class,
    ];
}

// Automatic casting
$ticket = Ticket::find(1);
$status = $ticket->status; // TicketStatus enum instance
```

### In Queries

```php
// Query by enum value
$openTickets = Ticket::where('status', TicketStatus::Open)->get();

// Query by multiple values
$activeTickets = Ticket::whereIn('status', [
    TicketStatus::Open,
    TicketStatus::InProgress,
    TicketStatus::Pending,
])->get();
```

### In Validation

```php
use Illuminate\Validation\Rules\Enum;

$request->validate([
    'status' => ['required', new Enum(TicketStatus::class)],
    'priority' => ['required', new Enum(TicketPriority::class)],
    'type' => ['required', new Enum(TicketType::class)],
]);
```

### In Forms

```php
// Generate select options
<select name="priority">
    @foreach(TicketPriority::options() as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
    @endforeach
</select>

// Or using cases
@foreach(TicketPriority::cases() as $priority)
    <option value="{{ $priority->value }}"
            class="text-{{ $priority->color() }}-600">
        {{ $priority->label() }}
    </option>
@endforeach
```

### State Transitions

```php
$ticket = Ticket::find(1);
$currentStatus = $ticket->status;

// Check allowed transitions
$allowedStatuses = $currentStatus->allowedTransitions();

// Validate transition
if ($currentStatus->canTransitionTo(TicketStatus::Resolved)) {
    $ticket->transitionTo(TicketStatus::Resolved);
} else {
    throw new InvalidTransitionException(
        "Cannot transition from {$currentStatus->label()} to Resolved"
    );
}
```

### Comparisons

```php
$ticket = Ticket::find(1);

// Direct comparison
if ($ticket->status === TicketStatus::Open) {
    // Handle open ticket
}

// Check multiple states
if (in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed])) {
    // Ticket is complete
}

// Priority comparison
if ($ticket->priority->order() >= TicketPriority::High->order()) {
    // High priority or urgent
}
```

## Custom Enum Methods

The package uses the `ProvidesEnumValues` concern to add common functionality:

```php
use LucaLongo\LaravelHelpdesk\Concerns\Enums\ProvidesEnumValues;

enum CustomStatus: string
{
    use ProvidesEnumValues;

    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match($this) {
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
        };
    }
}

// Automatically provides:
CustomStatus::values();  // ['active', 'inactive']
CustomStatus::options(); // ['active' => 'Active', 'inactive' => 'Inactive']
```

## Best Practices

1. **Always use enums instead of strings** for type safety
2. **Use the label() method** for display purposes
3. **Check transitions** before changing status
4. **Cast in models** for automatic conversion
5. **Use validation rules** to ensure valid values

## Extending Enums

You can add custom enums for your application:

```php
namespace App\Enums;

use LucaLongo\LaravelHelpdesk\Concerns\Enums\ProvidesEnumValues;

enum TicketSource: string
{
    use ProvidesEnumValues;

    case Email = 'email';
    case Phone = 'phone';
    case Chat = 'chat';
    case Portal = 'portal';
    case Api = 'api';

    public function label(): string
    {
        return match($this) {
            self::Email => 'Email',
            self::Phone => 'Phone Call',
            self::Chat => 'Live Chat',
            self::Portal => 'Customer Portal',
            self::Api => 'API Integration',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Email => 'envelope',
            self::Phone => 'phone',
            self::Chat => 'chat-bubble',
            self::Portal => 'globe',
            self::Api => 'code',
        };
    }
}
```