<?php

namespace LucaLongo\LaravelHelpdesk\Enums;

use LucaLongo\LaravelHelpdesk\Concerns\ProvidesEnumValues;

enum KnowledgeArticleRelationType: string
{
    use ProvidesEnumValues;

    case Related = 'related';
    case Prerequisite = 'prerequisite';
    case NextStep = 'next_step';
    case Alternative = 'alternative';

    public function label(): string
    {
        return match ($this) {
            self::Related => __('Related'),
            self::Prerequisite => __('Prerequisite'),
            self::NextStep => __('Next Step'),
            self::Alternative => __('Alternative'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Related => __('Related articles on similar topics'),
            self::Prerequisite => __('Should be read before this article'),
            self::NextStep => __('Recommended next article to read'),
            self::Alternative => __('Alternative solution or approach'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Related => 'link',
            self::Prerequisite => 'arrow-left',
            self::NextStep => 'arrow-right',
            self::Alternative => 'refresh',
        };
    }
}