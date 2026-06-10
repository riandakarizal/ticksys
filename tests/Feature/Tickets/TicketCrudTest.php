<?php

use App\Models\Device;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->team   = Team::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->client = User::factory()->client()->create(['tenant_id' => $this->tenant->id]);
    $this->other  = User::factory()->client()->create(['tenant_id' => $this->tenant->id]);
    $this->admin  = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);

    $this->team->members()->attach([$this->client->id, $this->other->id]);

    $this->device = Device::create([
        'tenant_id' => $this->tenant->id,
        'team_id'   => $this->team->id,
        'name'      => 'Test Device',
        'is_active' => true,
    ]);

    // Tiket milik client
    $this->ownTicket = Ticket::factory()->forTeam($this->team)->forRequester($this->client)->create();

    // Tiket milik user lain
    $this->otherTicket = Ticket::factory()->forTeam($this->team)->forRequester($this->other)->create();
});

// ── GET /tickets/create ───────────────────────────────────

test('authenticated user bisa GET /tickets/create', function () {
    $this->actingAs($this->client)->get('/tickets/create')->assertOk();
});

test('unauthenticated redirect dari /tickets/create', function () {
    $this->get('/tickets/create')->assertRedirect('/login');
});

// ── POST /tickets — validasi ──────────────────────────────

test('POST /tickets dengan body kosong mengembalikan validation error', function () {
    $this->actingAs($this->client)
        ->post('/tickets', [])
        ->assertSessionHasErrors(['subject', 'description', 'priority', 'team_id', 'device_id']);
});

test('POST /tickets berhasil membuat tiket baru', function () {
    $this->actingAs($this->client)
        ->post('/tickets', [
            'subject'     => 'Test ticket subject',
            'description' => 'Deskripsi masalah yang lengkap',
            'priority'    => 'medium',
            'team_id'     => $this->team->id,
            'device_id'   => $this->device->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tickets', [
        'tenant_id'   => $this->tenant->id,
        'subject'     => 'Test ticket subject',
        'requester_id' => $this->client->id,
    ]);
});

// ── GET /tickets/{ticket} — akses per role ────────────────

test('client bisa GET tiket miliknya sendiri', function () {
    $this->actingAs($this->client)
        ->get("/tickets/{$this->ownTicket->id}")
        ->assertOk();
});

test('client tidak bisa GET tiket milik user lain', function () {
    $this->actingAs($this->client)
        ->get("/tickets/{$this->otherTicket->id}")
        ->assertForbidden();
});

test('admin bisa GET semua tiket', function () {
    $this->actingAs($this->admin)
        ->get("/tickets/{$this->ownTicket->id}")
        ->assertOk();

    $this->actingAs($this->admin)
        ->get("/tickets/{$this->otherTicket->id}")
        ->assertOk();
});
