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
