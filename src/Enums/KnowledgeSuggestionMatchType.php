<?php

namespace LucaLongo\LaravelHelpdesk\Enums;

use LucaLongo\LaravelHelpdesk\Concerns\ProvidesEnumValues;

enum KnowledgeSuggestionMatchType: string
{
    use ProvidesEnumValues;

    case Keyword = 'keyword';
    case Content = 'content';
    case Category = 'category';
    case SimilarTickets = 'similar_tickets';
    case Tags = 'tags';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Keyword => __('Keyword Match'),
            self::Content => __('Content Match'),
            self::Category => __('Category Match'),
            self::SimilarTickets => __('Similar Tickets'),
            self::Tags => __('Tag Match'),
            self::Manual => __('Manual Suggestion'),
        };
    }

    public function weight(): float
    {
        return match ($this) {
            self::Manual => 1.0,
            self::Keyword => 0.9,
            self::SimilarTickets => 0.8,
            self::Content => 0.7,
            self::Tags => 0.6,
            self::Category => 0.5,
        };
    }
}
