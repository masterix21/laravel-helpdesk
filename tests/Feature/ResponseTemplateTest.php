<?php

use LucaLongo\LaravelHelpdesk\Database\Factories\ResponseTemplateFactory;
use LucaLongo\LaravelHelpdesk\Database\Factories\TicketFactory;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Models\ResponseTemplate;
use LucaLongo\LaravelHelpdesk\Services\ResponseTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ResponseTemplateService();
});

it('can create a response template', function () {
    $template = ResponseTemplateFactory::new()->create([
        'name' => 'Test Template',
        'slug' => 'test-template',
        'content' => 'Hello {customer_name}',
        'variables' => ['customer_name'],
    ]);

    expect($template)->toBeInstanceOf(ResponseTemplate::class)
        ->and($template->name)->toBe('Test Template')
        ->and($template->slug)->toBe('test-template')
        ->and($template->is_active)->toBeTrue();
});

it('can render a template with variables', function () {
    $template = ResponseTemplateFactory::new()->create([
        'content' => 'Hello {customer_name}, your ticket #{ticket_number} is ready.',
        'variables' => ['customer_name', 'ticket_number'],
    ]);

    $rendered = $template->render([
        'customer_name' => 'John Doe',
        'ticket_number' => 'TKT-001',
    ]);

    expect($rendered)->toBe('Hello John Doe, your ticket #TKT-001 is ready.');
});

it('filters templates by ticket type', function () {
    $supportTemplate = ResponseTemplateFactory::new()
        ->forType(TicketType::ProductSupport)
        ->create(['name' => 'Support Template']);

    $commercialTemplate = ResponseTemplateFactory::new()
        ->forType(TicketType::Commercial)
        ->create(['name' => 'Commercial Template']);

    $generalTemplate = ResponseTemplateFactory::new()
        ->create(['name' => 'General Template', 'ticket_type' => null]);

    $supportTicket = TicketFactory::new()->create(['type' => TicketType::ProductSupport]);
    $templates = $this->service->getTemplatesForTicket($supportTicket);

    expect($templates)->toHaveCount(2)
        ->and($templates->pluck('name')->toArray())->toContain('Support Template', 'General Template')
        ->and($templates->pluck('name')->toArray())->not->toContain('Commercial Template');
});

it('only returns active templates', function () {
    ResponseTemplateFactory::new()->active()->create(['name' => 'Active']);
    ResponseTemplateFactory::new()->inactive()->create(['name' => 'Inactive']);

    $templates = ResponseTemplate::active()->get();

    expect($templates)->toHaveCount(1)
        ->and($templates->first()->name)->toBe('Active');
});

it('can get template by slug', function () {
    ResponseTemplateFactory::new()->create([
        'name' => 'Welcome Template',
        'slug' => 'welcome',
    ]);

    $template = $this->service->getTemplateBySlug('welcome');

    expect($template)->not->toBeNull()
        ->and($template->name)->toBe('Welcome Template');
});

it('returns null for non-existent slug', function () {
    $template = $this->service->getTemplateBySlug('non-existent');

    expect($template)->toBeNull();
});

it('renders template with ticket variables', function () {
    $ticket = TicketFactory::new()->create([
        'ticket_number' => 'TKT-123',
        'subject' => 'Test Issue',
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
    ]);

    $template = ResponseTemplateFactory::new()->create([
        'content' => 'Hi {customer_name}, ticket #{ticket_number} "{ticket_subject}" has been received.',
    ]);

    $rendered = $this->service->renderTemplate($template, $ticket);

    expect($rendered)->toBe('Hi Jane Doe, ticket #TKT-123 "Test Issue" has been received.');
});

it('can apply template by slug', function () {
    $ticket = TicketFactory::new()->create([
        'ticket_number' => 'TKT-456',
        'customer_name' => 'Bob Smith',
    ]);

    ResponseTemplateFactory::new()->create([
        'slug' => 'test-slug',
        'content' => 'Hello {customer_name}, regarding ticket #{ticket_number}.',
    ]);

    $result = $this->service->applyTemplate('test-slug', $ticket);

    expect($result)->toBe('Hello Bob Smith, regarding ticket #TKT-456.');
});

it('can create default templates', function () {
    expect(ResponseTemplate::count())->toBe(0);

    $this->service->createDefaultTemplates();

    expect(ResponseTemplate::count())->toBeGreaterThan(0)
        ->and(ResponseTemplate::where('slug', 'welcome')->exists())->toBeTrue()
        ->and(ResponseTemplate::where('slug', 'resolved')->exists())->toBeTrue()
        ->and(ResponseTemplate::where('slug', 'awaiting-response')->exists())->toBeTrue();
});

it('does not duplicate default templates', function () {
    $this->service->createDefaultTemplates();
    $count = ResponseTemplate::count();

    $this->service->createDefaultTemplates();

    expect(ResponseTemplate::count())->toBe($count);
});

it('merges additional variables when rendering', function () {
    $ticket = TicketFactory::new()->create([
        'customer_name' => 'Alice',
    ]);

    $template = ResponseTemplateFactory::new()->create([
        'content' => 'Hi {customer_name}, {custom_message}',
    ]);

    $rendered = $this->service->renderTemplate($template, $ticket, [
        'custom_message' => 'this is a custom message',
    ]);

    expect($rendered)->toBe('Hi Alice, this is a custom message');
});