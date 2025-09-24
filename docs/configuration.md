# Configuration

This guide covers all configuration options available in the Laravel Helpdesk package.

## Configuration File

After publishing, the configuration file is located at `config/helpdesk.php`. This file controls all aspects of the package behavior.

## Configuration Sections

### User Model

Specify your application's user model:

```php
'user_model' => 'App\\Models\\User',
```

### Default Settings

Configure global defaults:

```php
'defaults' => [
    'due_minutes' => 1440, // 24 hours default due time
    'priority' => 'normal', // Default ticket priority
],
```

### Ticket Types

Define custom ticket types with specific settings:

```php
'types' => [
    'product_support' => [
        'label' => 'Product Support',
        'default_priority' => 'normal',
        'due_minutes' => 720, // 12 hours
        'auto_assign' => true,
        'require_category' => true,
    ],
    'commercial' => [
        'label' => 'Commercial Inquiry',
        'default_priority' => 'high',
        'due_minutes' => 480, // 8 hours
        'auto_assign' => false,
        'require_category' => false,
    ],
    'bug_report' => [
        'label' => 'Bug Report',
        'default_priority' => 'high',
        'due_minutes' => 240, // 4 hours
        'auto_assign' => true,
        'require_category' => true,
    ],
],
```

### SLA Configuration

Configure Service Level Agreement rules:

```php
'sla' => [
    'enabled' => true,

    // Priority-based SLA rules (in minutes)
    'rules' => [
        'urgent' => [
            'first_response' => 30,  // 30 minutes
            'resolution' => 240,      // 4 hours
            'update_frequency' => 60, // Update every hour
        ],
        'high' => [
            'first_response' => 120,  // 2 hours
            'resolution' => 480,      // 8 hours
            'update_frequency' => 120, // Update every 2 hours
        ],
        'normal' => [
            'first_response' => 240,  // 4 hours
            'resolution' => 1440,     // 24 hours
            'update_frequency' => 480, // Update every 8 hours
        ],
        'low' => [
            'first_response' => 480,  // 8 hours
            'resolution' => 2880,     // 48 hours
            'update_frequency' => 1440, // Update daily
        ],
    ],

    // Type-specific overrides
    'type_overrides' => [
        'commercial' => [
            'high' => [
                'first_response' => 60,  // 1 hour for commercial high priority
                'resolution' => 240,     // 4 hours
            ],
        ],
    ],

    // Warning thresholds (percentage)
    'warning_thresholds' => [
        75, // Warning at 75% time consumed
        90, // Critical at 90% time consumed
    ],

    // Business hours
    'business_hours' => [
        'enabled' => false,
        'timezone' => 'America/New_York',
        'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'start' => '09:00',
        'end' => '17:00',
        'holidays' => [], // Array of dates in Y-m-d format
    ],
],
```

### Notification Settings

Configure notification channels and events:

```php
'notifications' => [
    // Event toggles
    'ticket_created' => true,
    'ticket_assigned' => true,
    'ticket_status_changed' => true,
    'ticket_commented' => false,
    'sla_warning' => true,
    'sla_breach' => true,
    'ticket_rated' => true,
    'ticket_merged' => true,

    // Channel configuration
    'channels' => [
        'mail' => [
            'enabled' => true,
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'helpdesk@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Helpdesk'),
            ],
            'templates' => [
                'ticket_created' => 'emails.ticket-created',
                'ticket_assigned' => 'emails.ticket-assigned',
            ],
        ],
        'database' => [
            'enabled' => true,
            'cleanup_days' => 30, // Delete old notifications after 30 days
        ],
        'slack' => [
            'enabled' => false,
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
            'channel' => '#support',
            'username' => 'Helpdesk Bot',
        ],
        'log' => [
            'enabled' => false,
            'level' => 'info',
            'channel' => 'helpdesk',
        ],
    ],

    // Notification recipients
    'recipients' => [
        'managers' => ['admin@example.com'],
        'escalation' => ['manager@example.com', 'supervisor@example.com'],
    ],
],
```

### Rating System

Configure customer satisfaction tracking:

```php
'rating' => [
    'enabled' => true,

    // Which statuses allow rating
    'allowed_statuses' => ['resolved', 'closed'],

    // Rating validity period
    'rating_period_days' => 30,

    // Reminder settings
    'send_reminder' => true,
    'reminder_after_days' => 2,
    'max_reminders' => 3,

    // Feedback requirements
    'require_feedback' => false,
    'min_feedback_length' => 10,
    'max_feedback_length' => 1000,

    // Rating scale
    'scale' => [
        'min' => 1,
        'max' => 5,
        'show_labels' => true,
        'labels' => [
            1 => 'Very Unsatisfied',
            2 => 'Unsatisfied',
            3 => 'Neutral',
            4 => 'Satisfied',
            5 => 'Very Satisfied',
        ],
    ],

    // Metrics calculation
    'metrics' => [
        'calculate_nps' => true, // Net Promoter Score
        'calculate_csat' => true, // Customer Satisfaction Score
        'calculate_ces' => false, // Customer Effort Score
    ],
],
```

### Time Tracking

Configure time tracking features:

```php
'time_tracking' => [
    'enabled' => true,

    // Billing settings
    'billable_by_default' => true,
    'default_hourly_rate' => 150.00,
    'currency' => 'USD',

    // Time entry settings
    'allow_manual_entries' => true,
    'allow_overlapping_entries' => false,
    'allow_future_entries' => false,

    // Auto-stop conditions
    'auto_stop_on_status_change' => ['resolved', 'closed'],
    'auto_stop_on_assignment_change' => true,
    'auto_stop_after_hours' => 8, // Stop after 8 hours of continuous work

    // Rounding rules
    'minimum_duration_minutes' => 1,
    'round_to_nearest_minutes' => 15, // Round to 15-minute increments
    'rounding_method' => 'up', // 'up', 'down', 'nearest'

    // Reporting
    'require_description' => true,
    'min_description_length' => 10,
    'track_activities' => true, // Log what was done during time entry
],
```

### Automation Configuration

Configure automation rules and triggers:

```php
'automation' => [
    'enabled' => true,

    // Available triggers
    'triggers' => [
        'ticket_created' => 'When a new ticket is created',
        'ticket_updated' => 'When a ticket is updated',
        'ticket_assigned' => 'When a ticket is assigned',
        'ticket_status_changed' => 'When ticket status changes',
        'comment_added' => 'When a comment is added',
        'time_based' => 'Scheduled time-based checks',
        'sla_approaching' => 'When SLA deadline is approaching',
        'sla_breached' => 'When SLA is breached',
        'manual' => 'Manual trigger',
        'batch' => 'Batch processing',
        'customer_replied' => 'When customer replies',
        'agent_replied' => 'When agent replies',
        'ticket_rated' => 'When ticket is rated',
        'ticket_escalated' => 'When ticket is escalated',
    ],

    // Processing settings
    'processing' => [
        'max_rules_per_ticket' => 10, // Prevent infinite loops
        'max_execution_time' => 30, // Seconds
        'batch_size' => 100, // For batch processing
        'retry_attempts' => 3,
        'retry_delay' => 60, // Seconds between retries
    ],

    // Built-in templates (can be disabled)
    'templates' => [
        'auto_assign_by_category' => true,
        'escalate_high_priority' => true,
        'auto_tag_vip' => true,
        'auto_close_resolved' => true,
        'sla_breach_notification' => true,
        'route_by_category' => true,
    ],
],
```

### Knowledge Base

Configure knowledge base settings:

```php
'knowledge_base' => [
    'enabled' => true,

    // Article settings
    'articles' => [
        'require_approval' => true,
        'versioning' => true,
        'max_versions' => 10,
        'allow_comments' => true,
        'allow_ratings' => true,
        'track_views' => true,
    ],

    // AI suggestions
    'suggestions' => [
        'enabled' => true,
        'max_suggestions' => 5,
        'min_relevance_score' => 0.7,
        'use_categories' => true,
        'use_tags' => true,
        'use_content' => true,
    ],

    // FAQ generation
    'faq' => [
        'auto_generate' => true,
        'min_rating' => 4.0,
        'min_views' => 100,
        'max_age_days' => 90,
    ],

    // Search settings
    'search' => [
        'engine' => 'database', // 'database', 'elasticsearch', 'algolia'
        'min_query_length' => 3,
        'fuzzy_matching' => true,
        'boost_recent' => true,
        'boost_popular' => true,
    ],
],
```

### Storage Settings

Configure file storage for attachments:

```php
'storage' => [
    'disk' => 'local', // Storage disk for attachments
    'path' => 'helpdesk/attachments',
    'max_file_size' => 10240, // KB (10MB)
    'allowed_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc',
        'docx', 'xls', 'xlsx', 'txt', 'zip', 'csv'
    ],
    'image_processing' => [
        'create_thumbnails' => true,
        'thumbnail_size' => [150, 150],
        'optimize' => true,
        'max_dimensions' => [2000, 2000],
    ],
],
```

### Security Settings

Configure security options:

```php
'security' => [
    // Data sanitization
    'sanitize_html' => true,
    'allowed_html_tags' => ['p', 'br', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li'],

    // Access control
    'require_authentication' => true,
    'guest_ticket_creation' => false,
    'verify_email' => true,

    // Rate limiting
    'rate_limiting' => [
        'enabled' => true,
        'tickets_per_hour' => 10,
        'comments_per_hour' => 50,
        'api_calls_per_minute' => 60,
    ],

    // Data retention
    'data_retention' => [
        'closed_tickets_days' => 365, // Keep closed tickets for 1 year
        'attachments_days' => 180, // Keep attachments for 6 months
        'logs_days' => 90, // Keep logs for 3 months
    ],
],
```

## Environment Variables

You can override configuration values using environment variables:

```env
# Core settings
HELPDESK_USER_MODEL="App\\Models\\User"

# SLA settings
HELPDESK_SLA_ENABLED=true
HELPDESK_SLA_BUSINESS_HOURS=false

# Notifications
HELPDESK_MAIL_ENABLED=true
HELPDESK_SLACK_ENABLED=false
HELPDESK_SLACK_WEBHOOK_URL="https://hooks.slack.com/..."

# Time tracking
HELPDESK_TIME_TRACKING_ENABLED=true
HELPDESK_DEFAULT_HOURLY_RATE=150.00

# Automation
HELPDESK_AUTOMATION_ENABLED=true
HELPDESK_AUTOMATION_MAX_RULES=10

# Knowledge base
HELPDESK_KB_ENABLED=true
HELPDESK_KB_AI_SUGGESTIONS=true

# Storage
HELPDESK_STORAGE_DISK=s3
HELPDESK_MAX_FILE_SIZE=20480

# Security
HELPDESK_RATE_LIMITING=true
HELPDESK_GUEST_TICKETS=false
```

## Dynamic Configuration

You can modify configuration at runtime:

```php
use LucaLongo\LaravelHelpdesk\Support\HelpdeskConfig;

// Change configuration dynamically
config(['helpdesk.sla.enabled' => false]);

// Or use the helper class
HelpdeskConfig::disableSla();
HelpdeskConfig::setDefaultPriority('high');
HelpdeskConfig::enableAutomation();
```

## Configuration Validation

The package validates configuration on boot. Invalid configurations will throw exceptions with helpful messages:

```php
// In a service provider
use LucaLongo\LaravelHelpdesk\Support\ConfigValidator;

public function boot(): void
{
    ConfigValidator::validate(config('helpdesk'));
}
```

## Next Steps

- [Quick Start Guide](quick-start.md) - Start using the configured package
- [Ticket Management](features/tickets.md) - Learn about ticket operations
- [Automation Setup](features/automation.md) - Configure automation rules