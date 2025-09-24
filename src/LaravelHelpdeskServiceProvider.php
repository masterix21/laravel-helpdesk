<?php

namespace LucaLongo\LaravelHelpdesk;

use Illuminate\Support\Facades\Event;
use LucaLongo\LaravelHelpdesk\Console\Commands\GenerateMetricsSnapshotCommand;
use LucaLongo\LaravelHelpdesk\Events\TicketAssigned;
use LucaLongo\LaravelHelpdesk\Events\TicketCreated;
use LucaLongo\LaravelHelpdesk\Events\TicketStatusChanged;
use LucaLongo\LaravelHelpdesk\Notifications\Channels\LoggingNotificationChannel;
use LucaLongo\LaravelHelpdesk\Notifications\Channels\MailNotificationChannel;
use LucaLongo\LaravelHelpdesk\Notifications\NotificationDispatcher;
use LucaLongo\LaravelHelpdesk\Services\Automation\ActionExecutor;
use LucaLongo\LaravelHelpdesk\Services\Automation\ConditionEvaluator;
use LucaLongo\LaravelHelpdesk\Services\AutomationService;
use LucaLongo\LaravelHelpdesk\Services\BulkActionService;
use LucaLongo\LaravelHelpdesk\Services\CommentService;
use LucaLongo\LaravelHelpdesk\Services\ResponseTemplateService;
use LucaLongo\LaravelHelpdesk\Services\SlaService;
use LucaLongo\LaravelHelpdesk\Services\SubscriptionService;
use LucaLongo\LaravelHelpdesk\Services\TicketService;
use LucaLongo\LaravelHelpdesk\Services\TimeTrackingService;
use LucaLongo\LaravelHelpdesk\Services\WorkflowService;
use LucaLongo\LaravelHelpdesk\AI\AIService;
use LucaLongo\LaravelHelpdesk\AI\AIProviderSelector;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelHelpdeskServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-helpdesk')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigration('create_helpdesk_tickets_table')
            ->hasMigration('create_helpdesk_ticket_comments_table')
            ->hasMigration('create_helpdesk_ticket_attachments_table')
            ->hasMigration('create_helpdesk_ticket_subscriptions_table')
            ->hasMigration('create_helpdesk_categories_table')
            ->hasMigration('create_helpdesk_tags_table')
            ->hasMigration('create_helpdesk_ticket_categories_table')
            ->hasMigration('create_helpdesk_ticket_tags_table')
            ->hasMigration('create_helpdesk_response_templates_table')
            ->hasMigration('create_helpdesk_ticket_ratings_table')
            ->hasMigration('create_helpdesk_automation_rules_table')
            ->hasMigration('create_helpdesk_automation_executions_table')
            ->hasMigration('add_ticket_relations_columns')
            ->hasMigration('create_helpdesk_ticket_relations_table')
            ->hasMigration('create_helpdesk_ticket_time_entries_table')
            ->hasMigration('create_helpdesk_ai_analyses_table')
            ->hasCommand(GenerateMetricsSnapshotCommand::class);
    }

    public function registeringPackage(): void
    {
        // Register core services
        $this->app->singleton(SlaService::class);
        $this->app->singleton(CommentService::class);
        $this->app->singleton(SubscriptionService::class);
        $this->app->singleton(TimeTrackingService::class);

        // Register AI services
        $this->app->singleton(AIProviderSelector::class);
        $this->app->singleton(AIService::class, function ($app) {
            return new AIService(
                $app->make(AIProviderSelector::class)
            );
        });

        // Register services with proper dependency injection
        $this->app->singleton(TicketService::class, function ($app) {
            return new TicketService(
                $app->make(SlaService::class),
                $app->make(SubscriptionService::class),
                config('helpdesk.ai.enabled') ? $app->make(AIService::class) : null
            );
        });

        $this->app->singleton(ResponseTemplateService::class);
        $this->app->singleton(AutomationService::class);
        $this->app->singleton(BulkActionService::class);
        $this->app->singleton(WorkflowService::class);
        $this->app->singleton(NotificationDispatcher::class);

        $this->app->singleton('helpdesk.automation.evaluator', ConditionEvaluator::class);
        $this->app->singleton('helpdesk.automation.executor', ActionExecutor::class);

        $this->app->singleton(LaravelHelpdesk::class, function ($app) {
            return new LaravelHelpdesk(
                $app->make(TicketService::class),
                $app->make(CommentService::class),
                $app->make(SubscriptionService::class)
            );
        });
    }

    public function packageBooted(): void
    {
        $this->registerNotificationChannels();

        Event::listen(TicketCreated::class, NotificationDispatcher::class.'@onTicketCreated');
        Event::listen(TicketAssigned::class, NotificationDispatcher::class.'@onTicketAssigned');
        Event::listen(TicketStatusChanged::class, NotificationDispatcher::class.'@onTicketStatusChanged');
    }

    protected function registerNotificationChannels(): void
    {
        $channels = config('helpdesk.notifications.channels', []);

        if ((bool) ($channels['log']['enabled'] ?? false)) {
            $this->app->singleton(LoggingNotificationChannel::class);
            $this->app->tag(LoggingNotificationChannel::class, 'helpdesk.notification_channels');
        }

        if ((bool) ($channels['mail']['enabled'] ?? false)) {
            $this->app->singleton(MailNotificationChannel::class);
            $this->app->tag(MailNotificationChannel::class, 'helpdesk.notification_channels');
        }
    }
}
