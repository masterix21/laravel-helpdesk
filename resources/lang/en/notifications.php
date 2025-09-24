<?php

return [
    'dispatcher' => [
        'no_channels' => 'Helpdesk notification dispatcher: no channels registered',
        'channel_failed' => 'Helpdesk notification channel failed',
    ],

    'events' => [
        'ticket_created' => 'Ticket Created',
        'ticket_assigned' => 'Ticket Assigned',
        'ticket_status_changed' => 'Ticket Status Changed',
    ],

    'log' => [
        'message' => '[Helpdesk] Event :event for ticket :ticket',
    ],

    'mail' => [
        'subject' => '[Helpdesk] :event #:ticket',
        'body' => [
            'event_line' => 'Event: :event',
            'ticket_line' => 'Ticket: :subject',
            'id_line' => 'ID: :id',
            'context_line' => 'Context: :context',
            'context_unavailable' => '[context unavailable]',
            'subject_unavailable' => '[subject unavailable]',
        ],
    ],
];
