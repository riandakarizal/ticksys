<?php

use App\Models\Device;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Setup bersama ─────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'IAS', 'auto_close_days' => 3]);

    SlaPolicy::factory()->create([
        'company_id'          => $this->company->id,
        'name'               => 'Standard',
        'is_default'         => true,
        'response_minutes'   => 60,
        'resolution_minutes' => 480,
    ]);

    $this->project = Team::factory()->create([
        'company_id' => $this->company->id,
        'name'      => 'Seat Management IAS',
    ]);

    $this->client  = User::factory()->client()->create(['company_id' => $this->company->id, 'name' => 'Budi Client']);
    $this->teknisi = User::factory()->agent()->create(['company_id' => $this->company->id, 'name' => 'Rizal Teknisi']);

    $this->project->members()->attach([$this->client->id, $this->teknisi->id]);

    $this->laptops = collect(range(1, 3))->map(fn ($i) => Device::create([
        'company_id'   => $this->company->id,
        'team_id'     => $this->project->id,
        'name'        => "Laptop IAS-00{$i}",
        'device_type' => 'laptop',
        'is_active'   => true,
    ]));
});

// ── Skenario E2E Utama ────────────────────────────────────────────────────────

test('alur lengkap: client buat tiket per laptop → auto-assign teknisi → teknisi respond → client reply → teknisi resolve semua', function () {

    // LANGKAH 1 & 2: Client buat tiket (1 per laptop) + verifikasi auto-assign
    foreach ($this->laptops as $laptop) {
        $this->actingAs($this->client)
            ->post(route('tickets.store'), [
                'subject'     => "Masalah pada {$laptop->name}",
                'description' => 'Laptop tidak bisa booting, perlu pengecekan segera.',
                'priority'    => 'high',
                'team_id'     => $this->project->id,
                'device_id'   => $laptop->id,
            ])
            ->assertRedirect();
    }

    $tickets = Ticket::where('team_id', $this->project->id)->get();
    expect($tickets)->toHaveCount(3);

    foreach ($tickets as $ticket) {
        expect($ticket->requester_id)->toBe($this->client->id)
            ->and($ticket->assigned_to)->toBe($this->teknisi->id, "Tiket {$ticket->ticket_number} harus ter-auto-assign ke teknisi")
            ->and($ticket->status)->toBe('open');
    }

    // LANGKAH 3: Teknisi reply tiap tiket → status otomatis jadi in_progress
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

    // LANGKAH 4: Client reply di semua tiket (status tetap in_progress)
    foreach ($tickets as $ticket) {
        $this->actingAs($this->client)
            ->post(route('tickets.messages.store', $ticket), [
                'body' => 'Terima kasih sudah ditangani. Ditunggu kabarnya ya.',
            ])
            ->assertRedirect();

        expect($ticket->fresh()->status)->toBe('in_progress', "Status tidak boleh berubah saat client reply pada tiket in_progress");
    }

    // LANGKAH 5: Teknisi resolve semua tiket
    foreach ($tickets as $ticket) {
        $this->actingAs($this->teknisi)
            ->patch(route('tickets.update', $ticket), [
                'status'       => 'resolved',
                'priority'     => $ticket->priority,
                'team_id'      => $this->project->id,
                'device_id'    => $ticket->device_id,
                'requester_id' => $this->client->id,
                'assigned_to'  => $this->teknisi->id,
            ])
            ->assertRedirect();

        $fresh = $ticket->fresh();
        expect($fresh->status)->toBe('resolved', "Tiket {$ticket->ticket_number} harus resolved")
            ->and($fresh->resolved_at)->not->toBeNull("resolved_at harus terisi");
    }

    // LANGKAH 6: Verifikasi akhir keseluruhan
    expect(Ticket::where('team_id', $this->project->id)->where('status', 'resolved')->count())
        ->toBe(3, 'Semua 3 tiket harus berstatus resolved');

    expect(TicketMessage::count())
        ->toBe(6, '6 pesan total: 3 dari teknisi + 3 dari client');
});

// ── Test Edge Cases Pendukung ─────────────────────────────────────────────────

test('client tidak bisa resolve tiket (harus forbidden)', function () {
    $ticket = Ticket::factory()
        ->forTeam($this->project)
        ->forRequester($this->client)
        ->create(['assigned_to' => $this->teknisi->id]);

    $this->actingAs($this->client)
        ->patch(route('tickets.update', $ticket), [
            'status'       => 'resolved',
            'priority'     => 'medium',
            'team_id'      => $this->project->id,
            'device_id'    => $this->laptops->first()->id,
            'requester_id' => $this->client->id,
        ])
        ->assertForbidden();
});

test('client reply pada tiket pending membuat tiket reopen ke open', function () {
    $ticket = Ticket::factory()
        ->forTeam($this->project)
        ->forRequester($this->client)
        ->create([
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

test('teknisi tidak bisa mengakses tiket dari company lain', function () {
    $otherCompany = Company::factory()->create();
    $otherTeam   = Team::factory()->create(['company_id' => $otherCompany->id]);
    $otherClient = User::factory()->client()->create(['company_id' => $otherCompany->id]);
    $otherTicket = Ticket::factory()->forTeam($otherTeam)->forRequester($otherClient)->create();

    $this->actingAs($this->teknisi)
        ->get(route('tickets.show', $otherTicket))
        ->assertForbidden();
});

test('client hanya bisa melihat tiket miliknya sendiri di project yang sama', function () {
    $otherClient = User::factory()->client()->create(['company_id' => $this->company->id]);
    $this->project->members()->attach($otherClient->id);

    $ownTicket   = Ticket::factory()->forTeam($this->project)->forRequester($this->client)->create();
    $otherTicket = Ticket::factory()->forTeam($this->project)->forRequester($otherClient)->create();

    $this->actingAs($this->client)
        ->get(route('tickets.show', $ownTicket))
        ->assertOk();

    $this->actingAs($this->client)
        ->get(route('tickets.show', $otherTicket))
        ->assertForbidden();
});
