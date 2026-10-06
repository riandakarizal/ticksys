<?php

return [
    // PRISM single-tenant: berapa hari ticket resolved dibiarkan sebelum auto-close.
    'auto_close_days' => (int) env('HELPDESK_AUTO_CLOSE_DAYS', 5),

    'mail' => [
        'audience' => env('HELPDESK_MAIL_AUDIENCE', 'client_only'),
        'delivery' => env('HELPDESK_MAIL_DELIVERY', 'sync'),
        'cc' => array_values(array_filter(array_map(
            static fn (string $email) => trim($email),
            explode(',', (string) env('HELPDESK_MAIL_CC', ''))
        ))),
        'types' => [
            'ticket_created',
            'ticket_status_changed',
            'ticket_reply',
            'ticket_auto_close_warning',
            'ticket_assigned',
            'sla_escalated',
        ],
        'staff_types' => [
            'ticket_assigned',   // agent dapat email saat diassign
            'ticket_reply',      // agent dapat email saat client reply
            'sla_escalated',     // supervisor dapat email saat SLA breach
        ],
    ],
];
