<?php

namespace LucaLongo\LaravelHelpdesk\Commands;

use Illuminate\Console\Command;

class LaravelHelpdeskCommand extends Command
{
    public $signature = 'laravel-helpdesk';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
