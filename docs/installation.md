# Installation

This guide will walk you through installing and setting up the Laravel Helpdesk package.

## Requirements

Before installing, ensure your system meets these requirements:

- **PHP** 8.2 or higher
- **Laravel** 12.0 or higher
- **Database** MySQL 5.7+ / PostgreSQL 9.6+ / SQLite 3.8.8+ / SQL Server 2017+
- **Composer** 2.0 or higher

## Installation Steps

### 1. Install via Composer

```bash
composer require lucalongo/laravel-helpdesk
```

### 2. Publish Configuration

Publish the package configuration file:

```bash
php artisan vendor:publish --tag=helpdesk-config
```

This will create a `config/helpdesk.php` file where you can customize the package settings.

### 3. Run Migrations

Publish and run the database migrations:

```bash
php artisan vendor:publish --tag=helpdesk-migrations
php artisan migrate
```

This will create the following tables:
- `helpdesk_tickets` - Main ticket storage
- `helpdesk_ticket_comments` - Ticket comments
- `helpdesk_ticket_attachments` - File attachments
- `helpdesk_ticket_subscriptions` - Notification subscriptions
- `helpdesk_categories` - Ticket categories (hierarchical)
- `helpdesk_tags` - Ticket tags
- `helpdesk_response_templates` - Predefined responses
- `helpdesk_knowledge_articles` - Knowledge base articles
- `helpdesk_knowledge_sections` - Knowledge base organization
- `helpdesk_ticket_ratings` - Customer satisfaction ratings
- `helpdesk_ticket_time_entries` - Time tracking entries
- `helpdesk_automation_rules` - Automation configuration
- `helpdesk_automation_executions` - Automation history

### 4. Publish Assets (Optional)

If you want to customize views or translations:

```bash
# Publish views
php artisan vendor:publish --tag=helpdesk-views

# Publish translations
php artisan vendor:publish --tag=helpdesk-translations
```

### 5. Configure User Model

Add the `HasHelpdesk` trait to your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use LucaLongo\LaravelHelpdesk\Traits\HasHelpdesk;

class User extends Authenticatable
{
    use HasHelpdesk;

    // Your existing code...
}
```

### 6. Configure Service Provider (Optional)

If you need to customize bindings or add observers, you can modify your `AppServiceProvider`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use App\Observers\TicketObserver;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Add custom observer
        Ticket::observe(TicketObserver::class);

        // Override configuration at runtime
        config(['helpdesk.sla.enabled' => true]);
    }
}
```

## Environment Configuration

Add these optional environment variables to your `.env` file:

```env
# SLA Configuration
HELPDESK_SLA_ENABLED=true
HELPDESK_SLA_WARNING_THRESHOLD=75

# Notification Channels
HELPDESK_NOTIFICATION_MAIL_ENABLED=true
HELPDESK_NOTIFICATION_LOG_ENABLED=false

# Time Tracking
HELPDESK_TIME_TRACKING_ENABLED=true
HELPDESK_TIME_TRACKING_BILLABLE_DEFAULT=true

# Automation
HELPDESK_AUTOMATION_ENABLED=true

# Rating System
HELPDESK_RATING_ENABLED=true
HELPDESK_RATING_PERIOD_DAYS=30
```

## Database Seeding (Optional)

Create sample data for testing:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LucaLongo\LaravelHelpdesk\Models\Category;
use LucaLongo\LaravelHelpdesk\Models\Tag;
use LucaLongo\LaravelHelpdesk\Models\ResponseTemplate;

class HelpdeskSeeder extends Seeder
{
    public function run(): void
    {
        // Create categories
        $technical = Category::create([
            'name' => 'Technical Support',
            'description' => 'Technical issues and bugs',
        ]);

        Category::create([
            'name' => 'Software',
            'parent_id' => $technical->id,
        ]);

        // Create tags
        Tag::create(['name' => 'urgent', 'color' => '#FF0000']);
        Tag::create(['name' => 'bug', 'color' => '#FFA500']);
        Tag::create(['name' => 'feature-request', 'color' => '#00FF00']);

        // Create response templates
        ResponseTemplate::create([
            'name' => 'Welcome',
            'subject' => 'Thank you for contacting support',
            'content' => 'We have received your request and will respond within 24 hours.',
        ]);
    }
}
```

Run the seeder:

```bash
php artisan db:seed --class=HelpdeskSeeder
```

## Verification

Verify the installation:

```bash
# Check if migrations ran successfully
php artisan migrate:status

# Test ticket creation
php artisan tinker
>>> $ticket = \LucaLongo\LaravelHelpdesk\Facades\LaravelHelpdesk::open([
...     'type' => 'product_support',
...     'subject' => 'Test Ticket',
...     'description' => 'Testing the installation',
... ]);
>>> $ticket->ulid;
```

## Queue Configuration

For optimal performance, configure Laravel queues for:
- Email notifications
- Automation rule processing
- SLA monitoring
- Bulk operations

```bash
# Run queue workers
php artisan queue:work --queue=helpdesk,default
```

## Scheduled Tasks

Add the helpdesk scheduler to your console kernel:

```php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use LucaLongo\LaravelHelpdesk\Console\Commands\GenerateMetricsSnapshotCommand;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Generate daily metrics
        $schedule->command(GenerateMetricsSnapshotCommand::class)
            ->daily()
            ->at('00:00');

        // Process automation rules
        $schedule->command('helpdesk:process-automation')
            ->everyFiveMinutes();

        // Check SLA compliance
        $schedule->command('helpdesk:check-sla')
            ->everyMinute();
    }
}
```

## Next Steps

After installation, proceed to:
- [Configuration Guide](configuration.md) - Customize the package settings
- [Quick Start Guide](quick-start.md) - Create your first ticket
- [API Documentation](api/services.md) - Integrate with your application

## Troubleshooting

### Common Issues

**Migration fails with foreign key constraint**
- Ensure your users table exists before running helpdesk migrations
- Check that your database supports foreign keys

**Class not found errors**
- Run `composer dump-autoload`
- Clear Laravel caches: `php artisan cache:clear`

**Configuration not loading**
- Clear config cache: `php artisan config:clear`
- Republish configuration: `php artisan vendor:publish --tag=helpdesk-config --force`

For more help, visit our [GitHub Issues](https://github.com/yourusername/laravel-helpdesk/issues).