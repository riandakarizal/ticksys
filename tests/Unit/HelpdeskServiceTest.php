<?php

use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\Helpdesk;

// Needs TestCase so Eloquent has a database connection for attribute casting
uses(\Tests\TestCase::class);

// ── parseTags ─────────────────────────────────────────────

test('parseTags mengubah string menjadi array yang di-lowercase dan trim', function () {
    $helpdesk = new Helpdesk();

    expect($helpdesk->parseTags('tag1, TAG2 , tag3'))->toBe(['tag1', 'tag2', 'tag3']);
});

test('parseTags menghapus duplikat', function () {
    $helpdesk = new Helpdesk();

    expect($helpdesk->parseTags('bug, BUG, bug'))->toBe(['bug']);
});

test('parseTags menghapus entri kosong', function () {
    $helpdesk = new Helpdesk();

    expect($helpdesk->parseTags('tag1,,, tag2'))->toBe(['tag1', 'tag2']);
});

test('parseTags mengembalikan array kosong untuk null', function () {
    $helpdesk = new Helpdesk();

    expect($helpdesk->parseTags(null))->toBe([]);
});

test('parseTags mengembalikan array kosong untuk string kosong', function () {
    $helpdesk = new Helpdesk();

    expect($helpdesk->parseTags(''))->toBe([]);
});

// ── applySlaDeadlines ─────────────────────────────────────

test('applySlaDeadlines mengisi response_due_at dan resolution_due_at berdasarkan menit', function () {
    $helpdesk = new Helpdesk();
    $ticket = new Ticket();
    $sla = new SlaPolicy(['response_minutes' => 60, 'resolution_minutes' => 240]);

    $before = now();
    $helpdesk->applySlaDeadlines($ticket, $sla);
    $after = now();

    expect($ticket->response_due_at)->not->toBeNull();
    expect($ticket->resolution_due_at)->not->toBeNull();

    // response_due_at harus antara before+60min dan after+60min
    expect($ticket->response_due_at->timestamp)
        ->toBeGreaterThanOrEqual($before->copy()->addMinutes(60)->timestamp)
        ->toBeLessThanOrEqual($after->copy()->addMinutes(60)->timestamp);

    // resolution_due_at harus antara before+240min dan after+240min
    expect($ticket->resolution_due_at->timestamp)
        ->toBeGreaterThanOrEqual($before->copy()->addMinutes(240)->timestamp)
        ->toBeLessThanOrEqual($after->copy()->addMinutes(240)->timestamp);
});

test('applySlaDeadlines tidak mengisi deadline jika policy null', function () {
    $helpdesk = new Helpdesk();
    $ticket = new Ticket();
    // Prevent relationship DB query — tell Eloquent slaPolicy is already resolved as null
    $ticket->setRelation('slaPolicy', null);

    $helpdesk->applySlaDeadlines($ticket, null);

    expect($ticket->response_due_at)->toBeNull();
    expect($ticket->resolution_due_at)->toBeNull();
});
