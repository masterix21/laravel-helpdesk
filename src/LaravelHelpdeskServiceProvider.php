<?php

namespace LucaLongo\LaravelHelpdesk;

use LucaLongo\LaravelHelpdesk\Services\TicketService;
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
            ->hasMigration('create_helpdesk_ticket_tags_table');
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(TicketService::class, static fn () => new TicketService);
        $this->app->singleton(LaravelHelpdesk::class, static fn ($app) => new LaravelHelpdesk($app->make(TicketService::class)));
    }
}
