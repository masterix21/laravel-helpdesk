<?php

namespace LucaLongo\LaravelHelpdesk\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeArticleStatus;
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
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        foreach ([
            __DIR__.'/../database/migrations/create_helpdesk_tickets_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_comments_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_attachments_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_subscriptions_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_categories_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_knowledge_sections_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_knowledge_articles_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_knowledge_article_sections_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_knowledge_article_tickets_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_knowledge_article_relations_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_knowledge_suggestions_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_tags_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_categories_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_tags_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_response_templates_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_ratings_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_automation_rules_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_automation_executions_table.php.stub',
            __DIR__.'/../database/migrations/add_ticket_relations_columns.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_relations_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ticket_time_entries_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_ai_analyses_table.php.stub',
            __DIR__.'/../database/migrations/create_helpdesk_voice_notes_table.php.stub',
        ] as $migrationPath) {
            if ($isSqlite && str_contains($migrationPath, 'create_helpdesk_knowledge_articles_table.php.stub')) {
                $this->createKnowledgeArticlesTableForSqlite();

                continue;
            }

            $migration = include $migrationPath;
            $migration->up();
        }
    }

    private function createKnowledgeArticlesTableForSqlite(): void
    {
        if (Schema::hasTable('helpdesk_knowledge_articles')) {
            return;
        }

        Schema::create('helpdesk_knowledge_articles', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('status')->default(KnowledgeArticleStatus::Draft->value);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_faq')->default(false);
            $table->boolean('is_public')->default(true);
            $table->integer('view_count')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->decimal('effectiveness_score', 5, 2)->nullable();
            $table->json('keywords')->nullable();
            $table->json('meta')->nullable();
            $table->nullableMorphs('author');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->index('status');
            $table->index(['is_faq', 'is_public']);
            $table->index('published_at');
        });
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
