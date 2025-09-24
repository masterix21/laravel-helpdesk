# Customer Portal

## Overview

The Customer Portal is a self-service interface that empowers customers to manage their support tickets without direct agent intervention. This feature reduces support load while improving customer satisfaction through instant access to information.

## Key Features

### 1. Self-Service Ticket Management
- **Submit Tickets**: User-friendly form with guided categories and priority selection
- **Track Status**: Real-time ticket status updates and progress tracking
- **View History**: Complete ticket history with all communications
- **Attachments**: Upload and manage files related to tickets

### 2. Knowledge Base Integration
- **Search Before Submit**: AI-powered search suggests relevant articles before ticket creation
- **Contextual Help**: Display relevant knowledge articles based on ticket content
- **FAQ Section**: Most common issues and resolutions
- **Tutorial Videos**: Embedded video guides for common tasks

### 3. User Dashboard
- **Personalized Overview**: Custom dashboard showing open tickets, recent activity
- **Metrics Display**: Resolution times, satisfaction scores, ticket trends
- **Quick Actions**: One-click access to common operations
- **Notification Center**: All updates and communications in one place

### 4. Communication Features
- **Real-time Updates**: WebSocket-based live updates for ticket changes
- **Email Integration**: Reply to tickets via email
- **Chat Widget**: Embedded live chat for instant support
- **Feedback Loop**: Rate responses and provide feedback

## Technical Implementation

### Backend Architecture
```php
namespace LucaLongo\LaravelHelpdesk\Portal;

class PortalService
{
    public function authenticate($credentials);
    public function getUserTickets($userId, $filters = []);
    public function submitTicket($userId, $data);
    public function trackTicket($ticketId, $userId);
}
```

### API Endpoints
```php
Route::prefix('portal')->group(function () {
    Route::post('/auth/login', [PortalAuthController::class, 'login']);
    Route::middleware('portal.auth')->group(function () {
        Route::get('/tickets', [PortalTicketController::class, 'index']);
        Route::post('/tickets', [PortalTicketController::class, 'store']);
        Route::get('/tickets/{ticket}', [PortalTicketController::class, 'show']);
        Route::post('/tickets/{ticket}/comments', [PortalCommentController::class, 'store']);
        Route::get('/knowledge/search', [PortalKnowledgeController::class, 'search']);
    });
});
```

### Frontend Components
- **Vue.js/React Components**: Modular, reusable UI components
- **Responsive Design**: Mobile-first approach for all devices
- **Progressive Enhancement**: Works without JavaScript, enhanced with it
- **Accessibility**: WCAG 2.1 AA compliant

## Configuration

```php
'portal' => [
    'enabled' => true,
    'url' => env('HELPDESK_PORTAL_URL', '/support'),
    'authentication' => [
        'method' => 'session', // session, token, oauth
        'providers' => ['email', 'google', 'microsoft'],
    ],
    'features' => [
        'ticket_submission' => true,
        'knowledge_base' => true,
        'live_chat' => false,
        'file_uploads' => true,
        'email_replies' => true,
    ],
    'limits' => [
        'max_file_size' => 10485760, // 10MB
        'allowed_file_types' => ['pdf', 'jpg', 'png', 'doc', 'docx'],
        'rate_limiting' => [
            'tickets_per_day' => 10,
            'comments_per_hour' => 20,
        ],
    ],
]
```

## Security Considerations

1. **Authentication**: Multi-factor authentication support
2. **Authorization**: Role-based access control for different customer tiers
3. **Rate Limiting**: Prevent abuse and spam
4. **Data Privacy**: Ensure customers only see their own data
5. **XSS Protection**: Sanitize all user inputs
6. **CSRF Protection**: Token-based form submissions

## Benefits

- **Reduced Support Load**: 40-60% reduction in direct support requests
- **24/7 Availability**: Customers can get help anytime
- **Improved Satisfaction**: Faster resolution through self-service
- **Cost Reduction**: Lower operational costs per ticket
- **Data Collection**: Better understanding of common issues

## Implementation Phases

### Phase 1: Basic Portal (2-3 weeks)
- User authentication and registration
- Ticket submission and viewing
- Basic dashboard

### Phase 2: Enhanced Features (3-4 weeks)
- Knowledge base integration
- Advanced search
- Email notifications

### Phase 3: Advanced Features (4-6 weeks)
- Live chat integration
- Analytics dashboard
- Mobile app

## Migration Path

```php
php artisan helpdesk:portal:install
php artisan helpdesk:portal:migrate
php artisan helpdesk:portal:publish --assets
```

## Example Usage

```php
// Enable portal for specific users
$user->enablePortalAccess();

// Customize portal for user segment
Portal::forSegment('premium')
    ->enableFeature('priority_support')
    ->setTheme('dark')
    ->allowFileTypes(['zip', 'rar']);

// Track portal usage
$analytics = Portal::analytics()
    ->between(now()->subMonth(), now())
    ->get();
```