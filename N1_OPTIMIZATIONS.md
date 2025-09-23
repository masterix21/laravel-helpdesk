# N+1 Query Optimizations

## Summary of Optimizations

This document outlines the N+1 query optimizations implemented in the Laravel Helpdesk package to improve database performance.

## Fixed Issues

### 1. Category Duplication Service
**File:** `src/Services/CategoryService.php`
**Fix:** Added eager loading with `->with('children')` and used already loaded relationships instead of querying again.

### 2. Ticket Model Scopes
**File:** `src/Models/Ticket.php`
**Added:** Two new scopes for eager loading relationships:
- `withAllRelations()` - Loads all ticket relationships
- `withEssentialRelations()` - Loads only essential relationships

### 3. TicketComment Model
**File:** `src/Models/TicketComment.php`
**Fix:** Added default eager loading of `author` relationship via `$with` property.

### 4. TicketSubscription Model
**File:** `src/Models/TicketSubscription.php`
**Fix:** Added default eager loading of `subscriber` relationship via `$with` property.

### 5. TicketService
**File:** `src/Services/TicketService.php`
**Fixes:**
- Added eager loading when returning fresh models after create/update
- Return `$ticket->fresh(['opener', 'assignee'])` after creation
- Return `$comment->fresh(['author'])` after comment creation

## Usage Examples

### Loading Tickets with All Relations
```php
$tickets = Ticket::withAllRelations()->get();
// No N+1 when accessing $ticket->opener, $ticket->comments, etc.
```

### Loading Tickets with Essential Relations
```php
$tickets = Ticket::withEssentialRelations()->paginate(20);
// Loads only opener, assignee, categories, and tags
```

### Working with Comments
```php
// Author is automatically loaded
$comment = TicketComment::find($id);
echo $comment->author->name; // No additional query
```

### Working with Subscriptions
```php
// Subscriber is automatically loaded
$subscriptions = $ticket->subscriptions;
foreach ($subscriptions as $subscription) {
    echo $subscription->subscriber->email; // No N+1 queries
}
```

## Performance Impact

These optimizations reduce database queries significantly:

- **Category duplication**: From O(n²) queries to O(n) queries
- **Ticket listings**: From 1 + N*6 queries to 2-7 queries total
- **Comment listings**: From 1 + N queries to 1 query
- **Subscription notifications**: From 1 + N queries to 1 query

## Best Practices

1. **Use eager loading scopes** when fetching multiple tickets:
   ```php
   Ticket::withEssentialRelations()->where('status', 'open')->get();
   ```

2. **Avoid accessing relationships in loops** without eager loading:
   ```php
   // Bad
   foreach ($tickets as $ticket) {
       echo $ticket->opener->name; // N+1 query
   }

   // Good
   $tickets = Ticket::with('opener')->get();
   foreach ($tickets as $ticket) {
       echo $ticket->opener->name; // No N+1
   }
   ```

3. **Use `loadMissing()`** for conditional loading:
   ```php
   if ($needComments) {
       $ticket->loadMissing('comments');
   }
   ```

## Monitoring

To monitor for N+1 queries in development:

1. Use Laravel Debugbar or Telescope
2. Enable query logging:
   ```php
   DB::enableQueryLog();
   // Your code
   dd(DB::getQueryLog());
   ```
3. Use the `beyondcode/laravel-query-detector` package for automatic N+1 detection

## Future Improvements

Consider implementing:
- Query result caching for frequently accessed data
- Database indexes on foreign keys and commonly queried columns
- Lazy eager loading for rarely accessed relationships
- Pagination for large collections