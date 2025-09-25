<?php

namespace LucaLongo\LaravelHelpdesk\AI;

use Illuminate\Support\Facades\Cache;

class AIProviderSelector
{
    public function selectProvider(?string $requiredCapability = null): ?string
    {
        if (! config('helpdesk.ai.enabled')) {
            return null;
        }

        $availableProviders = $this->getAvailableProviders($requiredCapability);

        if (empty($availableProviders)) {
            return null;
        }

        $currentIndex = Cache::get('helpdesk.ai_provider_index', 0);
        $provider = $availableProviders[$currentIndex % count($availableProviders)];

        Cache::put('helpdesk.ai_provider_index', $currentIndex + 1);

        return $provider;
    }

    private function getAvailableProviders(?string $capability): array
    {
        return collect(config('helpdesk.ai.providers', []))
            ->filter(function ($config) use ($capability) {
                if (! ($config['enabled'] ?? false) || ! $config['api_key']) {
                    return false;
                }

                if ($capability && ! ($config['capabilities'][$capability] ?? false)) {
                    return false;
                }

                return true;
            })
            ->keys()
            ->values()
            ->all();
    }
}
