<?php

use App\Models\Team;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Setup: buat tenant + team, lalu user-user yang terkait
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->team   = Team::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->admin      = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
    $this->supervisor = User::factory()->supervisor()->create(['tenant_id' => $this->tenant->id]);
    $this->agent      = User::factory()->agent()->create(['tenant_id' => $this->tenant->id]);
    $this->client     = User::factory()->client()->create(['tenant_id' => $this->tenant->id]);

    // Supervisor dan agent bergabung ke team
    $this->team->members()->attach([$this->supervisor->id, $this->agent->id, $this->client->id]);

    // Buat beberapa tiket di team yang sama
    $this->ownTicket = Ticket::factory()->forTeam($this->team)->forRequester($this->client)->create([
        'assigned_to' => $this->agent->id,
    ]);

    $this->otherClient = User::factory()->client()->create(['tenant_id' => $this->tenant->id]);
    $this->team->members()->attach($this->otherClient->id);

    $this->otherTicket = Ticket::factory()->forTeam($this->team)->forRequester($this->otherClient)->create([
        'assigned_to' => null,
    ]);
});

test('GET /tickets mengembalikan 200 untuk user yang sudah login', function () {
    $this->actingAs($this->client)->get('/tickets')->assertOk();
});

test('client hanya melihat tiket miliknya sendiri (requester)', function () {
    $response = $this->actingAs($this->client)->get('/tickets');

    $response->assertOk();
    $response->assertSee($this->ownTicket->ticket_number);
    $response->assertDontSee($this->otherTicket->ticket_number);
});

test('agent hanya melihat tiket yang di-assign ke dirinya', function () {
    $response = $this->actingAs($this->agent)->get('/tickets');

    $response->assertOk();
    $response->assertSee($this->ownTicket->ticket_number);
    $response->assertDontSee($this->otherTicket->ticket_number);
});

test('supervisor melihat semua tiket di teamnya', function () {
    $response = $this->actingAs($this->supervisor)->get('/tickets');

    $response->assertOk();
    $response->assertSee($this->ownTicket->ticket_number);
    $response->assertSee($this->otherTicket->ticket_number);
});

test('admin melihat semua tiket', function () {
    $response = $this->actingAs($this->admin)->get('/tickets');

    $response->assertOk();
    $response->assertSee($this->ownTicket->ticket_number);
    $response->assertSee($this->otherTicket->ticket_number);
});

test('client tidak melihat tiket dari tenant lain', function () {
    $otherTenant = Tenant::factory()->create();
    $otherTeam   = Team::factory()->create(['tenant_id' => $otherTenant->id]);
    $outsider    = User::factory()->client()->create(['tenant_id' => $otherTenant->id]);
    $foreignTicket = Ticket::factory()->forTeam($otherTeam)->forRequester($outsider)->create();

    $response = $this->actingAs($this->client)->get('/tickets');

    $response->assertDontSee($foreignTicket->ticket_number);
});
