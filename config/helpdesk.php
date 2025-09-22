<?php

use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;

return [
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
];
