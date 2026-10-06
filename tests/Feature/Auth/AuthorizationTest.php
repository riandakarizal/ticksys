<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helper ────────────────────────────────────────────────
function makeUser(string $role): User
{
    return User::factory()->{$role}()->create();
}

// ── Routes guarded by role:supervisor,admin ───────────────

test('client tidak bisa akses /reports', function () {
    $this->actingAs(makeUser('client'))->get('/reports')->assertForbidden();
});

test('agent tidak bisa akses /reports', function () {
    $this->actingAs(makeUser('agent'))->get('/reports')->assertForbidden();
});

test('supervisor bisa akses /reports', function () {
    $this->actingAs(makeUser('supervisor'))->get('/reports')->assertOk();
});

test('admin bisa akses /reports', function () {
    $this->actingAs(makeUser('admin'))->get('/reports')->assertOk();
});

// ── /monitoring — semua role login boleh akses (M-02, all ✓) ─────────────

test('client bisa akses /monitoring', function () {
    $this->actingAs(makeUser('client'))->get('/monitoring')->assertOk();
});

test('agent bisa akses /monitoring', function () {
    $this->actingAs(makeUser('agent'))->get('/monitoring')->assertOk();
});

test('supervisor bisa akses /monitoring', function () {
    $this->actingAs(makeUser('supervisor'))->get('/monitoring')->assertOk();
});

test('admin bisa akses /monitoring', function () {
    $this->actingAs(makeUser('admin'))->get('/monitoring')->assertOk();
});

// ── /admin routes — hanya admin ──────────────────────────

test('client tidak bisa akses /admin/users', function () {
    $this->actingAs(makeUser('client'))->get('/admin/users')->assertForbidden();
});

test('agent tidak bisa akses /admin/users', function () {
    $this->actingAs(makeUser('agent'))->get('/admin/users')->assertForbidden();
});

test('supervisor tidak bisa akses /admin/users', function () {
    $this->actingAs(makeUser('supervisor'))->get('/admin/users')->assertForbidden();
});

test('admin bisa akses /admin/users', function () {
    $this->actingAs(makeUser('admin'))->get('/admin/users')->assertOk();
});

test('supervisor tidak bisa POST ke /admin/users', function () {
    $this->actingAs(makeUser('supervisor'))
        ->post('/admin/users', [])
        ->assertForbidden();
});

// ── /monitoring/import — role:superadmin,admin ───────────

test('supervisor bisa akses /monitoring/import', function () {
    $this->actingAs(makeUser('supervisor'))->get('/monitoring/import')->assertOk();
});

test('admin bisa akses /monitoring/import', function () {
    $this->actingAs(makeUser('admin'))->get('/monitoring/import')->assertOk();
});

test('agent tidak bisa akses /monitoring/import', function () {
    $this->actingAs(makeUser('agent'))->get('/monitoring/import')->assertForbidden();
});

test('client tidak bisa akses /monitoring/import', function () {
    $this->actingAs(makeUser('client'))->get('/monitoring/import')->assertForbidden();
});
