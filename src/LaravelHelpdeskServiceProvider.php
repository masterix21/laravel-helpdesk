<?php

namespace LucaLongo\LaravelHelpdesk;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use LucaLongo\LaravelHelpdesk\Commands\LaravelHelpdeskCommand;

class LaravelHelpdeskServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-helpdesk')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_helpdesk_table')
            ->hasCommand(LaravelHelpdeskCommand::class);
    }
}
