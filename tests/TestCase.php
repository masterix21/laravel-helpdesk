<?php

namespace LucaLongo\LaravelHelpdesk\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LucaLongo\LaravelHelpdesk\LaravelHelpdeskServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LucaLongo\\LaravelHelpdesk\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelHelpdeskServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('helpdesk.user_model', \LucaLongo\LaravelHelpdesk\Tests\Fakes\User::class);

        $this->createUsersTable();
        $this->runPackageMigrations();
        $this->createAgentsTable();
    }

    private function runPackageMigrations(): void
    {
        foreach ([
            __DIR__.'/../database/migrations/create_helpdesk_tickets_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_comments_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_attachments_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_subscriptions_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_categories_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_tags_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_categories_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_tags_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_response_templates_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_ratings_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_automation_rules_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_automation_executions_table.php.stub',
        ] as $migrationPath) {
            $migration = include $migrationPath;
            $migration->up();
        }
    }

    private function createUsersTable(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    private function createAgentsTable(): void
    {
        Schema::create('agents', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
}
