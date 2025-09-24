<?php

namespace LucaLongo\LaravelHelpdesk\AI;

class AIHelper
{
    public static function canAnalyzeSentiment(): bool
    {
        return self::hasCapability('analyze_sentiment');
    }

    public static function canSuggestResponse(): bool
    {
        return self::hasCapability('suggest_response');
    }

    public static function canCategorize(): bool
    {
        return self::hasCapability('auto_categorize');
    }

    public static function canFindSimilar(): bool
    {
        return self::hasCapability('find_similar');
    }

    public static function isEnabled(): bool
    {
        return config('helpdesk.ai.enabled', false);
    }

    public static function availableProviders(): array
    {
        if (! self::isEnabled()) {
            return [];
        }

        return collect(config('helpdesk.ai.providers', []))
            ->filter(fn($config) => ($config['enabled'] ?? false) && $config['api_key'])
            ->keys()
            ->all();
    }

    public static function activeCapabilities(): array
    {
        $selector = app(AIProviderSelector::class);
        $provider = $selector->selectProvider();

        if (! $provider) {
            return [];
        }

        return array_keys(
            array_filter(
                config("helpdesk.ai.providers.{$provider}.capabilities", [])
            )
        );
    }

    private static function hasCapability(string $capability): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        $selector = app(AIProviderSelector::class);
        $provider = $selector->selectProvider($capability);

        return $provider !== null;
    }
}