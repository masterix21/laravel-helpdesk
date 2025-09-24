# Response Templates

The Laravel Helpdesk package includes a flexible response template system that allows support agents to quickly respond to common inquiries using pre-defined templates with dynamic variable substitution.

## Overview

The `ResponseTemplateService` provides functionality to:
- Create and manage reusable response templates
- Support dynamic variable substitution in templates
- Filter templates by ticket type for relevance
- Render templates with ticket and agent context
- Create default templates for common scenarios

## Service Methods

### Get Templates for Ticket

Retrieve templates applicable to a specific ticket based on its type.

```php
use LucaLongo\LaravelHelpdesk\Services\ResponseTemplateService;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;

$service = app(ResponseTemplateService::class);

$templates = $service->getTemplatesForTicket($ticket);
```

**Returns:** `Collection` of `ResponseTemplate` models ordered by name

### Get Template by Slug

Retrieve a specific template using its unique slug identifier.

```php
$template = $service->getTemplateBySlug('welcome');
```

**Returns:** `ResponseTemplate|null`

### Render Template

Render a template with dynamic variables for a specific ticket.

```php
$renderedContent = $service->renderTemplate(
    template: $template,
    ticket: $ticket,
    additionalVariables: ['custom_message' => 'Thanks for your patience']
);
```

**Parameters:**
- `$template` (ResponseTemplate) - The template to render
- `$ticket` (Ticket) - Ticket context for variables
- `$additionalVariables` (array) - Extra variables to include

**Returns:** `string` - Rendered template content with variables replaced

### Apply Template by Slug

Render a template directly by slug without retrieving the model first.

```php
$renderedContent = $service->applyTemplate(
    slug: 'resolved',
    ticket: $ticket,
    additionalVariables: ['resolution_details' => 'Password reset completed']
);
```

**Returns:** `string|null` - Rendered content, or null if template not found

### Create Default Templates

Create a set of default templates for common support scenarios.

```php
$service->createDefaultTemplates();
```

This method creates templates for:
- Welcome messages for new tickets
- Ticket resolution notifications
- Requests for additional information
- Product support responses
- Commercial inquiry responses

## ResponseTemplate Model

The template model stores reusable response content with variable support.

### Model Properties

```php
class ResponseTemplate extends Model
{
    protected $fillable = [
        'name',          // Display name of the template
        'slug',          // Unique identifier for the template
        'content',       // Template content with variable placeholders
        'ticket_type',   // Optional ticket type filter
        'variables',     // Array of available variables
        'is_active',     // Whether template is active
    ];

    protected $casts = [
        'ticket_type' => TicketType::class,
        'variables' => AsArrayObject::class,
        'is_active' => 'boolean',
    ];
}
```

### Model Methods

```php
// Render template with variables
$content = $template->render(['customer_name' => 'John Doe']);

// Get available variables
$variables = $template->getAvailableVariables();
```

### Scopes

```php
// Get only active templates
ResponseTemplate::active()->get();

// Get templates for specific ticket type
ResponseTemplate::forType(TicketType::ProductSupport)->get();
```

## Built-in Variables

The service provides several built-in variables that are automatically available:

### Ticket Variables

```php
[
    'ticket_number' => 'HD-2023-001',
    'ticket_subject' => 'Login Issue',
    'ticket_type' => 'Product Support',
    'ticket_status' => 'Open',
    'ticket_priority' => 'High',
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
]
```

### Agent Variables

```php
[
    'agent_name' => 'Jane Smith',
    'agent_email' => 'jane@company.com',
    'company_name' => 'Your Company',
]
```

## Default Templates

The service includes several default templates:

### Welcome Template

```
Hello {customer_name},

Thank you for contacting our support team. Your ticket #{ticket_number} has been created and we will respond to you shortly.

Best regards,
{agent_name}
```

### Ticket Resolved Template

```
Hi {customer_name},

Your ticket #{ticket_number} has been resolved. If you have any further questions, please don't hesitate to contact us.

Best regards,
{agent_name}
```

### Awaiting Response Template

```
Hi {customer_name},

We need additional information to proceed with your ticket #{ticket_number}.

{message}

Please respond at your earliest convenience.

Best regards,
{agent_name}
```

## Usage Examples

### Basic Template Usage

```php
$service = app(ResponseTemplateService::class);

// Get available templates for a ticket
$templates = $service->getTemplatesForTicket($ticket);

// Apply a specific template
$welcomeMessage = $service->applyTemplate('welcome', $ticket);

// Send the rendered template as a response
$ticket->comments()->create([
    'body' => $welcomeMessage,
    'author_type' => User::class,
    'author_id' => auth()->id(),
    'is_internal' => false,
]);
```

### Creating Custom Templates

```php
ResponseTemplate::create([
    'name' => 'Password Reset Instructions',
    'slug' => 'password-reset',
    'content' => "Hi {customer_name},\n\nTo reset your password, please follow these steps:\n\n1. Go to our login page\n2. Click 'Forgot Password'\n3. Enter your email: {customer_email}\n4. Check your email for reset instructions\n\nIf you need further assistance, please reply to this ticket.\n\nBest regards,\n{agent_name}",
    'ticket_type' => TicketType::ProductSupport,
    'variables' => ['customer_name', 'customer_email', 'agent_name'],
    'is_active' => true,
]);
```

### Template with Custom Variables

```php
$template = ResponseTemplate::where('slug', 'custom-resolution')->first();

$renderedContent = $service->renderTemplate($template, $ticket, [
    'resolution_steps' => "1. Updated database\n2. Cleared cache\n3. Restarted service",
    'estimated_downtime' => '5 minutes',
    'next_maintenance' => 'Sunday at 2 AM EST'
]);
```

### Agent Interface Integration

```php
class TicketController extends Controller
{
    public function getTemplates(Ticket $ticket)
    {
        $templates = $this->responseTemplateService
            ->getTemplatesForTicket($ticket);

        return response()->json(
            $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'variables' => $template->getAvailableVariables(),
                ];
            })
        );
    }

    public function previewTemplate(Request $request, Ticket $ticket)
    {
        $template = ResponseTemplate::findOrFail($request->template_id);

        $preview = $this->responseTemplateService->renderTemplate(
            $template,
            $ticket,
            $request->input('variables', [])
        );

        return response()->json(['preview' => $preview]);
    }
}
```

### Template Management Interface

```php
class TemplateController extends Controller
{
    public function index()
    {
        $templates = ResponseTemplate::active()
            ->orderBy('name')
            ->get();

        return view('admin.templates.index', compact('templates'));
    }

    public function create()
    {
        $ticketTypes = TicketType::cases();
        $commonVariables = [
            'ticket_number', 'ticket_subject', 'ticket_status',
            'customer_name', 'customer_email', 'agent_name'
        ];

        return view('admin.templates.create', compact('ticketTypes', 'commonVariables'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:response_templates,slug',
            'content' => 'required|string',
            'ticket_type' => 'nullable|string',
            'variables' => 'array',
        ]);

        ResponseTemplate::create($request->all());

        return redirect()->route('admin.templates.index')
            ->with('success', 'Template created successfully');
    }
}
```

### Template Categories

Organize templates by purpose or department:

```php
// Create templates for different departments
$templates = [
    'billing' => [
        'name' => 'Billing Inquiry Response',
        'slug' => 'billing-inquiry',
        'content' => "Hi {customer_name},\n\nRegarding your billing inquiry for ticket #{ticket_number}...",
    ],
    'technical' => [
        'name' => 'Technical Issue Escalation',
        'slug' => 'tech-escalation',
        'content' => "Hi {customer_name},\n\nWe've escalated your technical issue #{ticket_number} to our engineering team...",
    ],
    'sales' => [
        'name' => 'Sales Follow-up',
        'slug' => 'sales-followup',
        'content' => "Dear {customer_name},\n\nThank you for your interest in our products...",
    ]
];

foreach ($templates as $category => $template) {
    ResponseTemplate::create(array_merge($template, [
        'variables' => ['customer_name', 'ticket_number', 'agent_name'],
        'metadata' => ['category' => $category]
    ]));
}
```

### Variable Validation

Ensure templates have required variables:

```php
class TemplateValidator
{
    public function validateTemplate(ResponseTemplate $template, array $requiredVariables): array
    {
        $content = $template->content;
        $missingVariables = [];

        foreach ($requiredVariables as $variable) {
            if (!str_contains($content, "{{$variable}}")) {
                $missingVariables[] = $variable;
            }
        }

        return $missingVariables;
    }

    public function getTemplateVariables(string $content): array
    {
        preg_match_all('/\{([^}]+)\}/', $content, $matches);
        return array_unique($matches[1]);
    }
}
```

### Multi-language Templates

Support for multiple languages:

```php
class MultiLanguageTemplateService extends ResponseTemplateService
{
    public function getTemplatesForTicket(Ticket $ticket): Collection
    {
        $locale = $ticket->customer_locale ?? app()->getLocale();

        return ResponseTemplate::active()
            ->where(function ($query) use ($ticket, $locale) {
                $query->forType($ticket->type)
                    ->where('locale', $locale)
                    ->orWhereNull('locale'); // Fallback to default language
            })
            ->orderBy('name')
            ->get();
    }
}

// Create multi-language templates
ResponseTemplate::create([
    'name' => 'Welcome (English)',
    'slug' => 'welcome-en',
    'locale' => 'en',
    'content' => 'Thank you for contacting us...',
]);

ResponseTemplate::create([
    'name' => 'Welcome (Spanish)',
    'slug' => 'welcome-es',
    'locale' => 'es',
    'content' => 'Gracias por contactarnos...',
]);
```

### Template Analytics

Track template usage and effectiveness:

```php
class TemplateAnalytics
{
    public function trackTemplateUsage(ResponseTemplate $template, Ticket $ticket)
    {
        // Log template usage
        TemplateUsage::create([
            'template_id' => $template->id,
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'used_at' => now(),
        ]);
    }

    public function getPopularTemplates(int $days = 30): Collection
    {
        return ResponseTemplate::withCount(['usages' => function ($query) use ($days) {
            $query->where('used_at', '>=', now()->subDays($days));
        }])
        ->orderByDesc('usages_count')
        ->limit(10)
        ->get();
    }

    public function getTemplateEffectiveness(ResponseTemplate $template): array
    {
        $tickets = Ticket::whereHas('comments', function ($query) use ($template) {
            $query->where('body', 'like', '%' . substr($template->content, 0, 50) . '%');
        })->get();

        $resolved = $tickets->where('status', TicketStatus::Resolved)->count();
        $total = $tickets->count();

        return [
            'total_uses' => $total,
            'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 2) : 0,
            'avg_response_time' => $tickets->avg('first_response_time_minutes'),
        ];
    }
}
```

### Template Automation

Automatically apply templates based on conditions:

```php
class AutoTemplateService
{
    public function applyAutoTemplate(Ticket $ticket, string $trigger): ?string
    {
        $rules = [
            'ticket_created' => [
                'template' => 'welcome',
                'conditions' => ['status' => TicketStatus::Open->value]
            ],
            'ticket_resolved' => [
                'template' => 'resolved',
                'conditions' => ['status' => TicketStatus::Resolved->value]
            ],
            'awaiting_response' => [
                'template' => 'awaiting-response',
                'conditions' => ['status' => 'pending'] // Note: 'pending' is not a standard TicketStatus enum value
            ]
        ];

        if (!isset($rules[$trigger])) {
            return null;
        }

        $rule = $rules[$trigger];

        // Check conditions
        foreach ($rule['conditions'] as $field => $value) {
            if ($ticket->$field !== $value) {
                return null;
            }
        }

        return $this->responseTemplateService->applyTemplate($rule['template'], $ticket);
    }
}

// Usage in event listeners
class ApplyAutoTemplate
{
    public function handle(TicketStatusChanged $event): void
    {
        $autoResponse = app(AutoTemplateService::class)
            ->applyAutoTemplate($event->ticket, 'ticket_resolved');

        if ($autoResponse && config('helpdesk.auto_responses.enabled')) {
            $event->ticket->comments()->create([
                'body' => $autoResponse,
                'author_type' => null, // System generated
                'author_id' => null,
                'is_internal' => false,
                'is_system' => true,
            ]);
        }
    }
}
```

## Best Practices

1. **Clear Naming**: Use descriptive names and slugs for templates
2. **Variable Documentation**: Document available variables for each template
3. **Type Filtering**: Use ticket type filtering to show relevant templates
4. **Version Control**: Keep track of template changes and versions
5. **Testing**: Test templates with various ticket scenarios
6. **User Training**: Train agents on available templates and variables
7. **Regular Review**: Periodically review and update template content
8. **Performance**: Cache frequently used templates for better performance

## Integration Examples

### With Rich Text Editor

```javascript
// JavaScript for template integration in WYSIWYG editor
class TemplateIntegration {
    constructor(editorInstance) {
        this.editor = editorInstance;
        this.loadTemplates();
    }

    async loadTemplates() {
        const response = await fetch(`/tickets/${ticketId}/templates`);
        const templates = await response.json();
        this.renderTemplateSelector(templates);
    }

    renderTemplateSelector(templates) {
        const selector = document.createElement('select');
        selector.innerHTML = '<option value="">Select Template...</option>';

        templates.forEach(template => {
            const option = document.createElement('option');
            option.value = template.slug;
            option.textContent = template.name;
            selector.appendChild(option);
        });

        selector.onchange = (e) => this.insertTemplate(e.target.value);
        this.editor.toolbar.appendChild(selector);
    }

    async insertTemplate(slug) {
        if (!slug) return;

        const response = await fetch('/templates/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                template_slug: slug,
                ticket_id: ticketId,
                variables: {}
            })
        });

        const { preview } = await response.json();
        this.editor.insertContent(preview);
    }
}
```

## Database Schema

The response template system uses the `helpdesk_response_templates` table:

```php
Schema::create('helpdesk_response_templates', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('content');
    $table->string('ticket_type')->nullable();
    $table->json('variables')->nullable();
    $table->boolean('is_active')->default(true);
    $table->string('locale', 5)->nullable(); // For multi-language support
    $table->json('metadata')->nullable(); // For categories, tags, etc.
    $table->timestamps();

    $table->index(['is_active', 'ticket_type']);
    $table->index('slug');
});
```