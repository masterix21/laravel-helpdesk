<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_automation_rules';

    protected $guarded = [];

    protected $casts = [
        'conditions' => AsArrayObject::class,
        'actions' => AsArrayObject::class,
        'is_active' => 'boolean',
        'stop_processing' => 'boolean',
        'last_executed_at' => 'datetime',
    ];

    public function executions(): HasMany
    {
        return $this->hasMany(AutomationExecution::class);
    }

    #[Scope]
    public function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    public function byTrigger(Builder $query, string $trigger): void
    {
        $query->where('trigger', $trigger);
    }

    #[Scope]
    public function ordered(Builder $query): void
    {
        $query->orderBy('priority', 'desc')->orderBy('id');
    }

    public function evaluate(Ticket $ticket): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $evaluator = app('helpdesk.automation.evaluator');

        return $evaluator->evaluate($this->conditions, $ticket);
    }

    public function execute(Ticket $ticket): bool
    {
        if (! $this->evaluate($ticket)) {
            return false;
        }

        $executor = app('helpdesk.automation.executor');
        $result = $executor->execute($this->actions, $ticket);

        if ($result) {
            $this->update(['last_executed_at' => now()]);

            AutomationExecution::create([
                'automation_rule_id' => $this->id,
                'ticket_id' => $ticket->id,
                'executed_at' => now(),
                'conditions_snapshot' => $this->conditions,
                'actions_snapshot' => $this->actions,
                'success' => true,
            ]);
        }

        return $result;
    }
}