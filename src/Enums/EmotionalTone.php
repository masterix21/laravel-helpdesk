<?php

namespace LucaLongo\LaravelHelpdesk\Enums;

use LucaLongo\LaravelHelpdesk\Concerns\ProvidesEnumValues;

enum EmotionalTone: string
{
    use ProvidesEnumValues;

    case NEUTRAL = 'neutral';
    case HAPPY = 'happy';
    case FRUSTRATED = 'frustrated';
    case ANGRY = 'angry';
    case SAD = 'sad';
    case ANXIOUS = 'anxious';
    case CONFUSED = 'confused';
    case EXCITED = 'excited';
    case DISAPPOINTED = 'disappointed';
    case GRATEFUL = 'grateful';
    case PROFESSIONAL = 'professional';
    case URGENT = 'urgent';
    case CONCERNED = 'concerned';
    case SATISFIED = 'satisfied';

    public function label(): string
    {
        return match ($this) {
            self::NEUTRAL => __('Neutral'),
            self::HAPPY => __('Happy'),
            self::FRUSTRATED => __('Frustrated'),
            self::ANGRY => __('Angry'),
            self::SAD => __('Sad'),
            self::ANXIOUS => __('Anxious'),
            self::CONFUSED => __('Confused'),
            self::EXCITED => __('Excited'),
            self::DISAPPOINTED => __('Disappointed'),
            self::GRATEFUL => __('Grateful'),
            self::PROFESSIONAL => __('Professional'),
            self::URGENT => __('Urgent'),
            self::CONCERNED => __('Concerned'),
            self::SATISFIED => __('Satisfied'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NEUTRAL => 'gray',
            self::HAPPY, self::EXCITED, self::GRATEFUL, self::SATISFIED => 'green',
            self::FRUSTRATED, self::DISAPPOINTED, self::CONCERNED => 'yellow',
            self::ANGRY, self::URGENT => 'red',
            self::SAD, self::ANXIOUS => 'blue',
            self::CONFUSED => 'orange',
            self::PROFESSIONAL => 'indigo',
        };
    }

    public function isPositive(): bool
    {
        return in_array($this, [
            self::HAPPY,
            self::EXCITED,
            self::GRATEFUL,
            self::SATISFIED,
        ]);
    }

    public function isNegative(): bool
    {
        return in_array($this, [
            self::FRUSTRATED,
            self::ANGRY,
            self::SAD,
            self::ANXIOUS,
            self::DISAPPOINTED,
        ]);
    }

    public function requiresAttention(): bool
    {
        return in_array($this, [
            self::ANGRY,
            self::URGENT,
            self::FRUSTRATED,
            self::ANXIOUS,
        ]);
    }
}
