<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── /monitoring — semua role login boleh akses (M-02, all ✓) ─────────────

test('supervisor bisa akses /monitoring', function () {
    $this->actingAs(User::factory()->supervisor()->create())
        ->get('/monitoring')
        ->assertOk();
});

test('admin bisa akses /monitoring', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/monitoring')
        ->assertOk();
});

test('agent bisa akses /monitoring', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get('/monitoring')
        ->assertOk();
});

test('client bisa akses /monitoring', function () {
    $this->actingAs(User::factory()->client()->create())
        ->get('/monitoring')
        ->assertOk();
});

// ── /monitoring/import — role:superadmin,admin ───────────

test('admin bisa akses /monitoring/import', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/monitoring/import')
        ->assertOk();
});

test('supervisor bisa akses /monitoring/import', function () {
    $this->actingAs(User::factory()->supervisor()->create())
        ->get('/monitoring/import')
        ->assertOk();
});

test('agent tidak bisa akses /monitoring/import', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get('/monitoring/import')
        ->assertForbidden();
});

test('client tidak bisa akses /monitoring/import', function () {
    $this->actingAs(User::factory()->client()->create())
        ->get('/monitoring/import')
        ->assertForbidden();
});

// ── template download — role:superadmin,admin ────────────

test('admin bisa download template /monitoring/import/template', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/monitoring/import/template')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('admin bisa download template per-type', function (string $type) {
    $this->actingAs(User::factory()->admin()->create())
        ->get("/monitoring/import/template/{$type}")
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
})->with(['project_eq', 'project_tech', 'handover', 'vehicle', 'maintenance']);

test('supervisor bisa download template', function () {
    $this->actingAs(User::factory()->supervisor()->create())
        ->get('/monitoring/import/template')
        ->assertOk();
});

test('agent tidak bisa download template', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get('/monitoring/import/template')
        ->assertForbidden();
});

// ── /monitoring/export — role:superadmin,admin ───────────

test('admin bisa GET /monitoring/export', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/monitoring/export')
        ->assertOk();
});

test('supervisor bisa GET /monitoring/export', function () {
    $this->actingAs(User::factory()->supervisor()->create())
        ->get('/monitoring/export')
        ->assertOk();
});

test('agent tidak bisa GET /monitoring/export', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get('/monitoring/export')
        ->assertForbidden();
});
