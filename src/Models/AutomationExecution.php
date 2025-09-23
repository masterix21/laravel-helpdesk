<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationExecution extends Model
{
    use HasFactory;

    protected $table = 'helpdesk_automation_executions';

    protected $guarded = [];

    protected $casts = [
        'conditions_snapshot' => AsArrayObject::class,
        'actions_snapshot' => AsArrayObject::class,
        'executed_at' => 'datetime',
        'success' => 'boolean',
        'error_details' => AsArrayObject::class,
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}