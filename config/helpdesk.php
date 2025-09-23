<?php

use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;

return [
    'user_model' => 'App\\Models\\User',

    'defaults' => [
        'due_minutes' => 1440,
        'priority' => TicketPriority::default()->value,
    ],

    'types' => [
        TicketType::ProductSupport->value => [
            'label' => TicketType::ProductSupport->label(),
            'default_priority' => TicketPriority::Normal->value,
            'due_minutes' => 720,
        ],
        TicketType::Commercial->value => [
            'label' => TicketType::Commercial->label(),
            'default_priority' => TicketPriority::High->value,
            'due_minutes' => 480,
        ],
    ],

    'sla' => [
        'enabled' => true,
        'rules' => [
            // Priority-based SLA rules (in minutes from ticket creation)
            TicketPriority::Urgent->value => [
                'first_response' => 30, // 30 minutes
                'resolution' => 240, // 4 hours
            ],
            TicketPriority::High->value => [
                'first_response' => 120, // 2 hours
                'resolution' => 480, // 8 hours
            ],
            TicketPriority::Normal->value => [
                'first_response' => 240, // 4 hours
                'resolution' => 1440, // 24 hours
            ],
            TicketPriority::Low->value => [
                'first_response' => 480, // 8 hours
                'resolution' => 2880, // 48 hours
            ],
        ],
        // Override SLA rules for specific ticket types
        'type_overrides' => [
            TicketType::Commercial->value => [
                TicketPriority::High->value => [
                    'first_response' => 60, // 1 hour for commercial high priority
                    'resolution' => 240, // 4 hours
                ],
            ],
        ],
        // Warning thresholds (percentage of SLA time remaining)
        'warning_thresholds' => [
            75, // Warning when 75% of time consumed
            90, // Critical when 90% of time consumed
        ],
    ],

    'notifications' => [
        'ticket_created' => true,
        'ticket_assigned' => true,
        'ticket_status_changed' => true,
        'ticket_commented' => false,
        'sla_warning' => true,
        'sla_breach' => true,
    ],

    'rating' => [
        'enabled' => true,
        'allowed_statuses' => [
            TicketStatus::Resolved,
            TicketStatus::Closed,
        ],
        'rating_period_days' => 30,
        'send_reminder' => true,
        'reminder_after_days' => 2,
        'require_feedback' => false,
        'min_feedback_length' => 10,
    ],

    'automation' => [
        'enabled' => true,
        'triggers' => [
            'ticket_created' => 'When a new ticket is created',
            'ticket_updated' => 'When a ticket is updated',
            'ticket_assigned' => 'When a ticket is assigned',
            'ticket_status_changed' => 'When ticket status changes',
            'comment_added' => 'When a comment is added',
            'time_based' => 'Scheduled time-based checks',
            'sla_approaching' => 'When SLA deadline is approaching',
            'sla_breached' => 'When SLA is breached',
            'manual' => 'Manual trigger',
            'batch' => 'Batch processing',
        ],
        'templates' => [
            'auto_assign_by_category' => [
                'name' => 'Auto-assign by Category',
                'description' => 'Automatically assign tickets based on their category',
                'trigger' => 'ticket_created',
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        [
                            'type' => 'assignee_status',
                            'value' => 'unassigned',
                        ],
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'assign_to_team',
                        'strategy' => 'least_busy',
                    ],
                ],
                'priority' => 100,
                'stop_processing' => false,
            ],
            'escalate_high_priority' => [
                'name' => 'Escalate High Priority Tickets',
                'description' => 'Escalate high priority tickets after timeout',
                'trigger' => 'time_based',
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        [
                            'type' => 'ticket_priority',
                            'operator' => 'greater_or_equal',
                            'value' => 'high',
                        ],
                        [
                            'type' => 'time_since_created',
                            'operator' => 'greater_than',
                            'value' => 120,
                        ],
                        [
                            'type' => 'ticket_status',
                            'operator' => 'equals',
                            'value' => 'open',
                        ],
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'escalate',
                        'level' => 1,
                        'priority' => 'urgent',
                        'notify_manager' => true,
                    ],
                ],
                'priority' => 90,
                'stop_processing' => true,
            ],
            'auto_tag_vip' => [
                'name' => 'Auto-tag VIP Customers',
                'description' => 'Automatically tag and prioritize VIP customer tickets',
                'trigger' => 'ticket_created',
                'conditions' => [
                    'operator' => 'or',
                    'rules' => [
                        [
                            'type' => 'customer_type',
                            'value' => 'vip',
                        ],
                        [
                            'type' => 'custom_field',
                            'field' => 'contract_level',
                            'operator' => 'equals',
                            'value' => 'gold',
                        ],
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'add_tags',
                        'tag_names' => ['vip', 'priority-customer'],
                    ],
                    [
                        'type' => 'change_priority',
                        'priority' => 'high',
                    ],
                    [
                        'type' => 'update_sla',
                        'first_response_minutes' => 30,
                        'resolution_minutes' => 240,
                    ],
                ],
                'priority' => 95,
                'stop_processing' => false,
            ],
            'auto_close_resolved' => [
                'name' => 'Auto-close Resolved Tickets',
                'description' => 'Automatically close resolved tickets after 7 days',
                'trigger' => 'time_based',
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        [
                            'type' => 'ticket_status',
                            'operator' => 'equals',
                            'value' => 'resolved',
                        ],
                        [
                            'type' => 'time_since_last_update',
                            'operator' => 'greater_than',
                            'value' => 10080, // 7 days in minutes
                        ],
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'change_status',
                        'status' => 'closed',
                    ],
                    [
                        'type' => 'add_internal_note',
                        'note' => 'Ticket automatically closed after 7 days of inactivity in resolved status.',
                    ],
                ],
                'priority' => 50,
                'stop_processing' => false,
            ],
            'sla_breach_notification' => [
                'name' => 'SLA Breach Notification',
                'description' => 'Send notifications when SLA is breached',
                'trigger' => 'sla_breached',
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        [
                            'type' => 'sla_status',
                            'value' => 'breached',
                        ],
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'send_notification',
                        'recipients' => ['assignee', 'manager'],
                        'template' => 'sla_breach',
                        'channels' => ['mail', 'slack'],
                    ],
                    [
                        'type' => 'add_tags',
                        'tag_names' => ['sla-breached'],
                    ],
                ],
                'priority' => 100,
                'stop_processing' => false,
            ],
            'route_by_category' => [
                'name' => 'Route by Category',
                'description' => 'Route tickets to specific teams based on category',
                'trigger' => 'ticket_created',
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        [
                            'type' => 'has_category',
                            'value' => null, // Will be set when template is applied
                            'include_descendants' => true,
                        ],
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'assign_to_team',
                        'team_id' => null, // Will be set when template is applied
                        'strategy' => 'round_robin',
                    ],
                ],
                'priority' => 80,
                'stop_processing' => false,
            ],
        ],
    ],
];
