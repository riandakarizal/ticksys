<?php

use App\Models\AstMain;
use App\Models\PjctMain;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->client = User::factory()->client()->create();
    $this->other  = User::factory()->client()->create();
    $this->admin  = User::factory()->admin()->create();

    $this->project = PjctMain::create([
        'pjct_name'   => 'Test Project',
        'pjct_client' => 'PT Test Client',
        'pjct_status' => 'OG',
    ]);

    $this->asset = AstMain::create([
        'ast_type'   => 'Laptop',
        'ast_brand'  => 'Test',
        'ast_brandmodel' => 'Test Model',
        'ast_serial' => 'SN-TEST-0001',
        'ast_cond'   => 'Good',
        'ast_stat'   => 'Aktif',
    ]);

    // Tiket milik client
    $this->ownTicket = Ticket::factory()->forRequester($this->client)->create(['pjct_id' => $this->project->id]);

    // Tiket milik user lain
    $this->otherTicket = Ticket::factory()->forRequester($this->other)->create(['pjct_id' => $this->project->id]);
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
        ->assertSessionHasErrors(['subject', 'description', 'priority', 'pjct_id']);
});

test('POST /tickets berhasil membuat tiket baru', function () {
    $this->actingAs($this->client)
        ->post('/tickets', [
            'subject'     => 'Test ticket subject',
            'description' => 'Deskripsi masalah yang lengkap',
            'priority'    => 'medium',
            'pjct_id'     => $this->project->id,
            'ast_id'      => $this->asset->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tickets', [
        'subject'     => 'Test ticket subject',
        'requester_id' => $this->client->id,
        'pjct_id'     => $this->project->id,
        'ast_id'      => $this->asset->id,
    ]);
});

test('POST /tickets oleh staff atas nama client mengambil requester_name dari project', function () {
    $this->actingAs($this->admin)
        ->post('/tickets', [
            'subject'     => 'Staff-created ticket',
            'description' => 'Dibuatkan oleh admin atas nama client.',
            'priority'    => 'medium',
            'pjct_id'     => $this->project->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tickets', [
        'subject'         => 'Staff-created ticket',
        'requester_id'    => null,
        'requester_name'  => 'PT Test Client',
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
