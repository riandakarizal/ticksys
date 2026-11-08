<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Setup: beberapa tiket dengan requester/assignee berbeda-beda
beforeEach(function () {
    $this->admin      = User::factory()->admin()->create();
    $this->supervisor = User::factory()->supervisor()->create();
    $this->agent      = User::factory()->agent()->create();
    $this->client     = User::factory()->client()->create();

    $this->ownTicket = Ticket::factory()->forRequester($this->client)->create([
        'assigned_to' => $this->agent->id,
    ]);

    $this->otherClient = User::factory()->client()->create();
    $this->otherTicket = Ticket::factory()->forRequester($this->otherClient)->create([
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

test('siteadmin (agent) hanya melihat tiket yang di-assign ke dirinya', function () {
    $response = $this->actingAs($this->agent)->get('/tickets');

    $response->assertOk();
    $response->assertSee($this->ownTicket->ticket_number);
    $response->assertDontSee($this->otherTicket->ticket_number);
});

test('admin (supervisor) melihat semua tiket', function () {
    $response = $this->actingAs($this->supervisor)->get('/tickets');

    $response->assertOk();
    $response->assertSee($this->ownTicket->ticket_number);
    $response->assertSee($this->otherTicket->ticket_number);
});

test('superadmin (admin) melihat semua tiket', function () {
    $response = $this->actingAs($this->admin)->get('/tickets');

    $response->assertOk();
    $response->assertSee($this->ownTicket->ticket_number);
    $response->assertSee($this->otherTicket->ticket_number);
});

test('client tidak melihat tiket milik client lain', function () {
    $response = $this->actingAs($this->client)->get('/tickets');

    $response->assertDontSee($this->otherTicket->ticket_number);
});

test('ticket index metrics count only visible tickets and ignore list filters', function () {
    $requester = User::factory()->client()->create();
    $stranger  = User::factory()->client()->create();

    Ticket::factory()->forRequester($requester)->create(['status' => 'open', 'assigned_to' => null, 'resolution_due_at' => now()->subHour()]);
    Ticket::factory()->forRequester($requester)->create(['status' => 'pending', 'assigned_to' => $this->admin->id, 'resolution_due_at' => now()->addDay()]);
    Ticket::factory()->forRequester($requester)->create(['status' => 'resolved', 'created_at' => now()->subHours(5), 'resolved_at' => now()->subHour()]);
    Ticket::factory()->forRequester($stranger)->create(['status' => 'open']);

    $metrics = $this->actingAs($requester)->get('/tickets?status=closed')->assertOk()->viewData('metrics');

    expect($metrics)->toMatchArray([
        'total' => 3, 'active' => 2, 'pending' => 1, 'unassigned' => 1, 'breached' => 1,
        'resolved_30d' => 1, 'avg_resolve_h' => 4.0,
    ]);
});
