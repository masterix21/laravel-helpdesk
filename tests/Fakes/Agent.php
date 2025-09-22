<?php

namespace LucaLongo\LaravelHelpdesk\Tests\Fakes;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $table = 'agents';

    protected $guarded = [];
}
