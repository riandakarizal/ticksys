<?php

return [
    'mail' => [
        // 'none' mematikan semua email ticket; nilai lain (default) mengirim tipe di bawah.
        // Semua penerima sekarang staff internal (teknisi, atasan, tim divisi) — client tidak punya akun.
        'audience' => env('HELPDESK_MAIL_AUDIENCE', 'all'),
        'delivery' => env('HELPDESK_MAIL_DELIVERY', 'sync'),
        'cc' => array_values(array_filter(array_map(
            static fn (string $email) => trim($email),
            explode(',', (string) env('HELPDESK_MAIL_CC', ''))
        ))),
        'types' => [
            'ticket_solve',      // atasan: ticket menunggu approval
            'ticket_escalate',   // tim divisi: unit dieskalasi ke pusat
            'ticket_return',     // teknisi: unit dikembalikan dari pusat
            'ticket_approve',    // teknisi: penyelesaian disetujui
            'ticket_reject',     // teknisi: penyelesaian ditolak
            'sla_breached',      // tim divisi + atasan: SLA terlewati
        ],
    ],
];
