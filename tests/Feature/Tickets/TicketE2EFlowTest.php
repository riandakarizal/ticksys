<?php

use App\Models\AstMain;
use App\Models\PjctMain;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Setup bersama ─────────────────────────────────────────────────────────────

beforeEach(function () {
    config(['helpdesk.auto_close_days' => 3]);

    SlaPolicy::factory()->create([
        'name'               => 'Standard',
        'is_default'         => true,
        'response_minutes'   => 60,
        'resolution_minutes' => 480,
    ]);

    $this->project = PjctMain::create([
        'pjct_name'   => 'Seat Management IAS',
        'pjct_client' => 'PT Integrasi Aviasi Solusi',
        'pjct_status' => 'OG',
    ]);

    $this->client  = User::factory()->client()->create(['user_name' => 'Budi Client']);
    $this->teknisi = User::factory()->agent()->create(['user_name' => 'Rizal Teknisi']);

    $this->laptops = collect(range(1, 3))->map(fn ($i) => AstMain::create([
        'ast_type'       => 'Laptop',
        'ast_brand'      => 'Test',
        'ast_brandmodel' => "IAS-00{$i}",
        'ast_serial'     => "SN-IAS-00{$i}",
        'ast_cond'       => 'Good',
        'ast_stat'       => 'Aktif',
    ]));
});

// ── Skenario E2E Utama ────────────────────────────────────────────────────────

test('alur lengkap: client buat tiket per laptop → teknisi respond → client reply → teknisi resolve semua', function () {

    // LANGKAH 1: Client buat tiket (1 per laptop), langsung assign ke teknisi
    foreach ($this->laptops as $laptop) {
        $this->actingAs($this->client)
            ->post(route('tickets.store'), [
                'subject'     => "Masalah pada {$laptop->ast_brandmodel}",
                'description' => 'Laptop tidak bisa booting, perlu pengecekan segera.',
                'priority'    => 'high',
                'pjct_id'     => $this->project->id,
                'ast_id'      => $laptop->id,
                'assigned_to' => $this->teknisi->id,
            ])
            ->assertRedirect();
    }

    $tickets = Ticket::where('pjct_id', $this->project->id)->get();
    expect($tickets)->toHaveCount(3);

    foreach ($tickets as $ticket) {
        expect($ticket->requester_id)->toBe($this->client->id)
            ->and($ticket->assigned_to)->toBe($this->teknisi->id, "Tiket {$ticket->ticket_number} harus ter-assign ke teknisi")
            ->and($ticket->status)->toBe('open');
    }

    // Aset yang dilaporkan harus langsung ditandai bermasalah begitu tiket dibuat.
    foreach ($this->laptops as $laptop) {
        expect($laptop->fresh()->ast_cond)->toBe('Bad');
    }

    // LANGKAH 2: Teknisi reply tiap tiket → status otomatis jadi in_progress
    foreach ($tickets as $ticket) {
        $this->actingAs($this->teknisi)
            ->post(route('tickets.messages.store', $ticket), [
                'body'        => 'Halo, laporan sudah diterima. Saya sedang proses pengecekan perangkat.',
                'is_internal' => false,
            ])
            ->assertRedirect();

        $fresh = $ticket->fresh();
        expect($fresh->status)->toBe('in_progress', "Status harus in_progress setelah teknisi reply pertama kali")
            ->and($fresh->first_responded_at)->not->toBeNull("first_responded_at harus terisi");
    }

    // LANGKAH 3: Client reply di semua tiket (status tetap in_progress)
    foreach ($tickets as $ticket) {
        $this->actingAs($this->client)
            ->post(route('tickets.messages.store', $ticket), [
                'body' => 'Terima kasih sudah ditangani. Ditunggu kabarnya ya.',
            ])
            ->assertRedirect();

        expect($ticket->fresh()->status)->toBe('in_progress', "Status tidak boleh berubah saat client reply pada tiket in_progress");
    }

    // LANGKAH 4: Teknisi resolve semua tiket
    foreach ($tickets as $ticket) {
        $this->actingAs($this->teknisi)
            ->patch(route('tickets.update', $ticket), [
                'status'       => 'resolved',
                'priority'     => $ticket->priority,
                'pjct_id'      => $this->project->id,
                'ast_id'       => $ticket->ast_id,
                'assigned_to'  => $this->teknisi->id,
            ])
            ->assertRedirect();

        $fresh = $ticket->fresh();
        expect($fresh->status)->toBe('resolved', "Tiket {$ticket->ticket_number} harus resolved")
            ->and($fresh->resolved_at)->not->toBeNull("resolved_at harus terisi");
    }

    // LANGKAH 5: Verifikasi akhir keseluruhan
    expect(Ticket::where('pjct_id', $this->project->id)->where('status', 'resolved')->count())
        ->toBe(3, 'Semua 3 tiket harus berstatus resolved');

    expect(TicketMessage::count())
        ->toBe(6, '6 pesan total: 3 dari teknisi + 3 dari client');

    // Begitu tiketnya resolved, kondisi aset dikembalikan ke sebelum dilaporkan.
    foreach ($this->laptops as $laptop) {
        expect($laptop->fresh()->ast_cond)->toBe('Good');
    }
});

// ── Test Edge Cases Pendukung ─────────────────────────────────────────────────

test('client tidak bisa resolve tiket (harus forbidden)', function () {
    $ticket = Ticket::factory()
        ->forRequester($this->client)
        ->create(['pjct_id' => $this->project->id, 'assigned_to' => $this->teknisi->id]);

    $this->actingAs($this->client)
        ->patch(route('tickets.update', $ticket), [
            'status'      => 'resolved',
            'priority'    => 'medium',
            'pjct_id'     => $this->project->id,
            'ast_id'      => $this->laptops->first()->id,
        ])
        ->assertForbidden();
});

test('client reply pada tiket pending membuat tiket reopen ke open', function () {
    $ticket = Ticket::factory()
        ->forRequester($this->client)
        ->create([
            'pjct_id'     => $this->project->id,
            'assigned_to' => $this->teknisi->id,
            'status'      => 'pending',
        ]);

    $this->actingAs($this->client)
        ->post(route('tickets.messages.store', $ticket), [
            'body' => 'Masalahnya muncul lagi, mohon ditindaklanjuti.',
        ])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe('open', 'Client reply pada tiket pending harus reopen ke open');
});

test('client hanya bisa melihat tiket miliknya sendiri', function () {
    $otherClient = User::factory()->client()->create();

    $ownTicket   = Ticket::factory()->forRequester($this->client)->create(['pjct_id' => $this->project->id]);
    $otherTicket = Ticket::factory()->forRequester($otherClient)->create(['pjct_id' => $this->project->id]);

    $this->actingAs($this->client)
        ->get(route('tickets.show', $ownTicket))
        ->assertOk();

    $this->actingAs($this->client)
        ->get(route('tickets.show', $otherTicket))
        ->assertForbidden();
});
