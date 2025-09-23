<?php

namespace LucaLongo\LaravelHelpdesk;

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
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelHelpdeskServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-helpdesk')
            ->hasConfigFile()
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
            ->hasMigration('create_helpdesk_ticket_time_entries_table');
    }

    public function registeringPackage(): void
    {
        // Register core services
        $this->app->singleton(SlaService::class);
        $this->app->singleton(CommentService::class);
        $this->app->singleton(SubscriptionService::class);
        $this->app->singleton(TimeTrackingService::class);

        // Register services with proper dependency injection
        $this->app->singleton(TicketService::class, function ($app) {
            return new TicketService(
                $app->make(SlaService::class),
                $app->make(SubscriptionService::class)
            );
        });

        $this->app->singleton(ResponseTemplateService::class);
        $this->app->singleton(AutomationService::class);
        $this->app->singleton(BulkActionService::class);
        $this->app->singleton(WorkflowService::class);

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
}
