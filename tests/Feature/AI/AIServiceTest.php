<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use LucaLongo\LaravelHelpdesk\AI\AIProviderSelector;
use LucaLongo\LaravelHelpdesk\AI\AIService;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

beforeEach(function () {
    Config::set('helpdesk.ai.enabled', true);
    Config::set('helpdesk.ai.providers', [
        'openai' => [
            'enabled' => true,
            'api_key' => 'test-key',
            'model' => 'gpt-4o-mini',
            'capabilities' => [
                'analyze_sentiment' => true,
                'suggest_response' => true,
                'auto_categorize' => true,
                'find_similar' => true,
            ],
        ],
        'claude' => [
            'enabled' => true,
            'api_key' => 'test-key',
            'model' => 'claude-3-haiku',
            'capabilities' => [
                'analyze_sentiment' => true,
                'suggest_response' => true,
                'auto_categorize' => true,
                'find_similar' => true,
            ],
        ],
    ]);
});

it('analyzes a ticket with AI when enabled', function () {
    $this->markTestSkipped('Requires Prism API mock implementation');
});

it('returns null when AI is disabled', function () {
    Config::set('helpdesk.ai.enabled', false);

    $ticket = Ticket::factory()->create();
    $service = app(AIService::class);
    $analysis = $service->analyze($ticket);

    expect($analysis)->toBeNull();
});

it('rotates providers using round robin', function () {
    Cache::forget('helpdesk.ai_provider_index');

    $selector = new AIProviderSelector;

    $provider1 = $selector->selectProvider();
    expect($provider1)->toBe('openai');

    $provider2 = $selector->selectProvider();
    expect($provider2)->toBe('claude');

    $provider3 = $selector->selectProvider();
    expect($provider3)->toBe('openai'); // Back to first
});

it('filters providers by capability', function () {
    Config::set('helpdesk.ai.providers.claude.capabilities.find_similar', false);

    $selector = new AIProviderSelector;

    // Claude doesn't support find_similar
    $providers = [];
    for ($i = 0; $i < 5; $i++) {
        $provider = $selector->selectProvider('find_similar');
        if ($provider) {
            $providers[] = $provider;
        }
    }

    expect($providers)->each->toBe('openai');
});

it('generates response suggestions', function () {
    $this->markTestSkipped('Requires Prism API mock implementation');
});

it('finds similar tickets based on keywords', function () {
    $this->markTestSkipped('Requires Prism API mock implementation');
});

it('handles AI provider errors gracefully', function () {
    $this->markTestSkipped('Requires Prism API mock implementation');
});

it('ticket can access AI methods directly', function () {
    $this->markTestSkipped('Requires Prism API mock implementation');
});
