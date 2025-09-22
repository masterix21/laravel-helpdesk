<?php

namespace LucaLongo\LaravelHelpdesk\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;

class ResponseTemplate extends Model
{
    protected $table = 'helpdesk_response_templates';

    protected $fillable = [
        'name',
        'slug',
        'content',
        'ticket_type',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'ticket_type' => TicketType::class,
        'variables' => AsArrayObject::class,
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, ?TicketType $type)
    {
        if (! $type) {
            return $query->whereNull('ticket_type');
        }

        return $query->where(function ($query) use ($type) {
            $query->where('ticket_type', $type)
                ->orWhereNull('ticket_type');
        });
    }

    public function render(array $variables = []): string
    {
        $content = $this->content;

        foreach ($variables as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
        }

        return $content;
    }

    public function getAvailableVariables(): array
    {
        return $this->variables ?? [];
    }
}
