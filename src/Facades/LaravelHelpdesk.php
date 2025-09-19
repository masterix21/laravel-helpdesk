<?php

namespace LucaLongo\LaravelHelpdesk\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LucaLongo\LaravelHelpdesk\LaravelHelpdesk
 */
class LaravelHelpdesk extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LucaLongo\LaravelHelpdesk\LaravelHelpdesk::class;
    }
}
