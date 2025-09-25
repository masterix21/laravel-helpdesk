# Automation Templates

Ready-to-use automation rule templates for common helpdesk scenarios.

## Customer Service Templates

### Auto-Assign by Category

```php
use LucaLongo\LaravelHelpdesk\Models\AutomationRule;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;

AutomationRule::create([
    'name' => 'Auto-assign Technical Tickets',
    'description' => 'Automatically assign technical support tickets to the tech team',
    'trigger' => 'ticket_created',
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'ticket_type',
                'operator' => 'equals',
                'value' => 'technical_support',
            ],
            [
                'type' => 'assignee',
                'operator' => 'is_null',
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'assign_to_team',
            'config' => [
                'team' => 'technical_support',
                'method' => 'round_robin',
            ],
        ],
        [
            'type' => 'add_tag',
            'config' => [
                'tags' => ['auto-assigned', 'technical'],
            ],
        ],
    ],
    'priority' => 90,
    'is_active' => true,
]);
```

### Weekend Escalation

```php
AutomationRule::create([
    'name' => 'Weekend Priority Escalation',
    'description' => 'Escalate normal tickets to high priority on weekends',
    'trigger' => 'ticket_created',
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'day_of_week',
                'operator' => 'in',
                'value' => ['saturday', 'sunday'],
            ],
            [
                'type' => 'priority',
                'operator' => 'equals',
                'value' => TicketPriority::Normal,
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'change_priority',
            'config' => [
                'priority' => TicketPriority::High,
            ],
        ],
        [
            'type' => 'add_internal_note',
            'config' => [
                'note' => 'Priority escalated due to weekend submission',
            ],
        ],
        [
            'type' => 'notify_team',
            'config' => [
                'team' => 'support_managers',
                'message' => 'Weekend ticket requiring attention',
            ],
        ],
    ],
    'priority' => 100,
    'is_active' => true,
]);
```

### First Response Auto-Reply

```php
AutomationRule::create([
    'name' => 'Auto-acknowledge New Tickets',
    'description' => 'Send automatic acknowledgment for new tickets',
    'trigger' => 'ticket_created',
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'customer_email',
                'operator' => 'is_not_null',
            ],
            [
                'type' => 'channel',
                'operator' => 'not_equals',
                'value' => 'api',
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'send_email',
            'config' => [
                'template' => 'ticket_acknowledgment',
                'to' => '{{customer_email}}',
                'variables' => [
                    'ticket_id' => '{{ticket.id}}',
                    'expected_response' => '{{sla.first_response}}',
                ],
            ],
        ],
        [
            'type' => 'add_comment',
            'config' => [
                'content' => 'Automatic acknowledgment sent to customer',
                'is_public' => false,
                'is_system' => true,
            ],
        ],
    ],
    'priority' => 95,
    'is_active' => true,
]);
```

## SLA Management Templates

### SLA Warning

```php
AutomationRule::create([
    'name' => 'SLA First Response Warning',
    'description' => 'Alert when first response SLA is at risk',
    'trigger' => 'time_based',
    'schedule' => '*/15 * * * *', // Every 15 minutes
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'first_response_at',
                'operator' => 'is_null',
            ],
            [
                'type' => 'first_response_due_at',
                'operator' => 'within_minutes',
                'value' => 30,
            ],
            [
                'type' => 'status',
                'operator' => 'not_in',
                'value' => ['resolved', 'closed'],
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'change_priority',
            'config' => [
                'priority' => TicketPriority::Urgent,
            ],
        ],
        [
            'type' => 'notify_assignee',
            'config' => [
                'channel' => 'email',
                'message' => 'SLA breach imminent - respond within 30 minutes',
            ],
        ],
        [
            'type' => 'add_tag',
            'config' => [
                'tags' => ['sla-warning'],
            ],
        ],
    ],
    'priority' => 100,
    'is_active' => true,
]);
```

### SLA Breach Escalation

```php
AutomationRule::create([
    'name' => 'SLA Breach Escalation',
    'description' => 'Escalate tickets that have breached SLA',
    'trigger' => 'sla_breach',
    'conditions' => [
        'operator' => 'or',
        'rules' => [
            [
                'type' => 'first_response_breached',
                'operator' => 'is_true',
            ],
            [
                'type' => 'resolution_breached',
                'operator' => 'is_true',
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'escalate_to_manager',
            'config' => [
                'find_manager_by' => 'team',
            ],
        ],
        [
            'type' => 'change_priority',
            'config' => [
                'priority' => TicketPriority::Urgent,
            ],
        ],
        [
            'type' => 'send_notification',
            'config' => [
                'to' => ['support-managers@example.com'],
                'subject' => 'SLA Breach Alert: Ticket #{{ticket.id}}',
                'template' => 'sla_breach_notification',
            ],
        ],
        [
            'type' => 'create_incident',
            'config' => [
                'severity' => 'high',
                'title' => 'SLA breach for ticket #{{ticket.id}}',
            ],
        ],
    ],
    'priority' => 100,
    'is_active' => true,
]);
```

## Customer Satisfaction Templates

### Follow-up After Resolution

```php
AutomationRule::create([
    'name' => 'Customer Satisfaction Survey',
    'description' => 'Send satisfaction survey 24 hours after resolution',
    'trigger' => 'ticket_resolved',
    'delay_minutes' => 1440, // 24 hours
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'customer_email',
                'operator' => 'is_not_null',
            ],
            [
                'type' => 'survey_sent',
                'operator' => 'is_false',
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'send_survey',
            'config' => [
                'survey_type' => 'csat',
                'template' => 'satisfaction_survey',
                'track_response' => true,
            ],
        ],
        [
            'type' => 'update_meta',
            'config' => [
                'survey_sent' => true,
                'survey_sent_at' => '{{now}}',
            ],
        ],
    ],
    'priority' => 80,
    'is_active' => true,
]);
```

### Negative Feedback Response

```php
AutomationRule::create([
    'name' => 'Handle Negative Feedback',
    'description' => 'Escalate tickets with negative customer feedback',
    'trigger' => 'survey_response_received',
    'conditions' => [
        'operator' => 'or',
        'rules' => [
            [
                'type' => 'survey_score',
                'operator' => 'less_than',
                'value' => 3,
            ],
            [
                'type' => 'survey_sentiment',
                'operator' => 'equals',
                'value' => 'negative',
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'reopen_ticket',
        ],
        [
            'type' => 'assign_to_role',
            'config' => [
                'role' => 'customer_success_manager',
            ],
        ],
        [
            'type' => 'change_priority',
            'config' => [
                'priority' => TicketPriority::High,
            ],
        ],
        [
            'type' => 'add_tag',
            'config' => [
                'tags' => ['negative-feedback', 'requires-followup'],
            ],
        ],
        [
            'type' => 'notify_management',
            'config' => [
                'message' => 'Customer dissatisfaction detected',
            ],
        ],
    ],
    'priority' => 90,
    'is_active' => true,
]);
```

## Spam and Duplicate Management

### Spam Detection

```php
AutomationRule::create([
    'name' => 'Auto-close Spam Tickets',
    'description' => 'Automatically close tickets identified as spam',
    'trigger' => 'ticket_created',
    'conditions' => [
        'operator' => 'or',
        'rules' => [
            [
                'type' => 'subject_contains',
                'operator' => 'any',
                'value' => ['viagra', 'casino', 'lottery', 'prize winner'],
            ],
            [
                'type' => 'description_matches',
                'operator' => 'regex',
                'value' => '(bit\.ly|tinyurl|click here|winner|congratulations)',
            ],
            [
                'type' => 'attachment_count',
                'operator' => 'greater_than',
                'value' => 10,
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'change_status',
            'config' => [
                'status' => 'closed',
                'reason' => 'Identified as spam',
            ],
        ],
        [
            'type' => 'add_tag',
            'config' => [
                'tags' => ['spam', 'auto-closed'],
            ],
        ],
        [
            'type' => 'block_customer',
            'config' => [
                'duration_days' => 30,
            ],
        ],
    ],
    'priority' => 100,
    'is_active' => true,
]);
```

### Duplicate Ticket Handling

```php
AutomationRule::create([
    'name' => 'Merge Duplicate Tickets',
    'description' => 'Detect and merge duplicate tickets from same customer',
    'trigger' => 'ticket_created',
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'similar_ticket_exists',
                'config' => [
                    'similarity_threshold' => 0.8,
                    'time_window_hours' => 24,
                    'from_same_customer' => true,
                ],
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'merge_with_existing',
            'config' => [
                'keep' => 'oldest',
                'add_note' => 'Merged duplicate ticket #{{duplicate.id}}',
            ],
        ],
        [
            'type' => 'notify_customer',
            'config' => [
                'message' => 'Your request has been merged with existing ticket #{{original.id}}',
            ],
        ],
    ],
    'priority' => 95,
    'is_active' => true,
]);
```

## VIP Customer Templates

### VIP Customer Priority

```php
AutomationRule::create([
    'name' => 'VIP Customer Treatment',
    'description' => 'Special handling for VIP customers',
    'trigger' => 'ticket_created',
    'conditions' => [
        'operator' => 'or',
        'rules' => [
            [
                'type' => 'customer_tag',
                'operator' => 'contains',
                'value' => 'vip',
            ],
            [
                'type' => 'customer_plan',
                'operator' => 'in',
                'value' => ['enterprise', 'premium'],
            ],
            [
                'type' => 'customer_lifetime_value',
                'operator' => 'greater_than',
                'value' => 10000,
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'change_priority',
            'config' => [
                'priority' => TicketPriority::Urgent,
            ],
        ],
        [
            'type' => 'assign_to_team',
            'config' => [
                'team' => 'vip_support',
                'method' => 'least_busy',
            ],
        ],
        [
            'type' => 'set_sla',
            'config' => [
                'first_response_minutes' => 15,
                'resolution_minutes' => 120,
            ],
        ],
        [
            'type' => 'add_tag',
            'config' => [
                'tags' => ['vip', 'priority-customer'],
            ],
        ],
        [
            'type' => 'notify_team',
            'config' => [
                'team' => 'account_managers',
                'channels' => ['slack', 'email'],
            ],
        ],
    ],
    'priority' => 100,
    'is_active' => true,
]);
```

## Maintenance and Cleanup

### Auto-close Inactive Tickets

```php
AutomationRule::create([
    'name' => 'Close Inactive Tickets',
    'description' => 'Close resolved tickets with no activity for 7 days',
    'trigger' => 'time_based',
    'schedule' => '0 2 * * *', // Daily at 2 AM
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'status',
                'operator' => 'equals',
                'value' => 'resolved',
            ],
            [
                'type' => 'last_activity',
                'operator' => 'older_than_days',
                'value' => 7,
            ],
            [
                'type' => 'waiting_for_customer',
                'operator' => 'is_false',
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'change_status',
            'config' => [
                'status' => 'closed',
            ],
        ],
        [
            'type' => 'add_comment',
            'config' => [
                'content' => 'Automatically closed due to inactivity',
                'is_public' => false,
                'is_system' => true,
            ],
        ],
        [
            'type' => 'send_email',
            'config' => [
                'template' => 'ticket_closed_notification',
                'to' => '{{customer_email}}',
            ],
        ],
    ],
    'priority' => 70,
    'is_active' => true,
]);
```

### Archive Old Tickets

```php
AutomationRule::create([
    'name' => 'Archive Old Closed Tickets',
    'description' => 'Move closed tickets older than 90 days to archive',
    'trigger' => 'time_based',
    'schedule' => '0 3 * * 0', // Weekly on Sunday at 3 AM
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'status',
                'operator' => 'equals',
                'value' => 'closed',
            ],
            [
                'type' => 'closed_at',
                'operator' => 'older_than_days',
                'value' => 90,
            ],
            [
                'type' => 'is_archived',
                'operator' => 'is_false',
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'archive_ticket',
            'config' => [
                'compress_attachments' => true,
                'retain_metadata' => true,
            ],
        ],
        [
            'type' => 'update_search_index',
            'config' => [
                'remove_from_active' => true,
            ],
        ],
    ],
    'priority' => 50,
    'is_active' => true,
]);
```

## Complex Workflow Templates

### Multi-tier Escalation

```php
AutomationRule::create([
    'name' => 'Three-tier Escalation Process',
    'description' => 'Escalate through support tiers based on time and complexity',
    'trigger' => 'composite',
    'conditions' => [
        'operator' => 'and',
        'rules' => [
            [
                'type' => 'unresolved_duration',
                'operator' => 'greater_than_hours',
                'value' => 2,
            ],
            [
                'type' => 'escalation_level',
                'operator' => 'less_than',
                'value' => 3,
            ],
        ],
    ],
    'actions' => [
        [
            'type' => 'conditional_action',
            'config' => [
                'conditions' => [
                    [
                        'if' => ['escalation_level', 'equals', 0],
                        'then' => [
                            'type' => 'assign_to_team',
                            'team' => 'tier_2_support',
                        ],
                    ],
                    [
                        'if' => ['escalation_level', 'equals', 1],
                        'then' => [
                            'type' => 'assign_to_team',
                            'team' => 'tier_3_support',
                        ],
                    ],
                    [
                        'if' => ['escalation_level', 'equals', 2],
                        'then' => [
                            'type' => 'assign_to_role',
                            'role' => 'engineering_lead',
                        ],
                    ],
                ],
            ],
        ],
        [
            'type' => 'increment_escalation_level',
        ],
        [
            'type' => 'notify_previous_assignee',
            'config' => [
                'message' => 'Ticket escalated to next tier',
            ],
        ],
    ],
    'priority' => 90,
    'is_active' => true,
]);
```

## Using Templates Programmatically

```php
namespace App\Services;

use LucaLongo\LaravelHelpdesk\Models\AutomationRule;

class AutomationTemplateService
{
    public function applyTemplate(string $templateName, array $customizations = []): AutomationRule
    {
        $template = $this->getTemplate($templateName);

        // Merge customizations
        $config = array_merge($template, $customizations);

        return AutomationRule::create($config);
    }

    public function applyMultipleTemplates(array $templates): void
    {
        foreach ($templates as $template) {
            $this->applyTemplate($template['name'], $template['customizations'] ?? []);
        }
    }

    private function getTemplate(string $name): array
    {
        // Load from configuration or database
        return config("helpdesk.automation_templates.{$name}");
    }
}

// Usage
$templateService = app(AutomationTemplateService::class);

// Apply single template
$rule = $templateService->applyTemplate('vip_customer', [
    'conditions' => [
        'rules' => [
            ['type' => 'customer_tag', 'value' => 'platinum'],
        ],
    ],
]);

// Apply multiple templates
$templateService->applyMultipleTemplates([
    ['name' => 'auto_assign_by_category'],
    ['name' => 'sla_warning'],
    ['name' => 'weekend_escalation', 'customizations' => [
        'is_active' => false, // Disable initially
    ]],
]);
```