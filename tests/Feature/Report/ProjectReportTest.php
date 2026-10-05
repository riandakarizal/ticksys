<?php

use App\Models\PjctMain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // PjctMain::created membuat folder dokumen di disk `docfile`.
    Storage::fake('docfile');

    PjctMain::create([
        'pjct_name' => 'Sewa Trolley T3', 'pjct_div' => 'EQ', 'pjct_status' => 'OG', 'pjct_type' => 'RENT',
        'pjct_contract' => 'KTR/001', 'pjct_value' => 1500000000,
        'pjct_costart' => '2026-01-01', 'pjct_coend_m' => '2026-12-31',
    ]);
    PjctMain::create([
        'pjct_name' => 'Pengadaan Nurse Tender', 'pjct_div' => 'EQ', 'pjct_status' => 'UPC', 'pjct_type' => 'SUPPLY',
        'pjct_value' => 0,
    ]);
    PjctMain::create([
        'pjct_name' => 'Seat Management Reg 5', 'pjct_div' => 'TC', 'pjct_status' => 'OG', 'pjct_type' => 'RENT',
        'pjct_contract' => 'KTR/002', 'pjct_value' => 900000000,
        'pjct_costart' => '2026-02-01', 'pjct_coend_m' => '2027-01-31',
    ]);
});

test('superadmin bisa membuka Report Projects dan melihat semua divisi', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/report/projects')
        ->assertOk()
        ->assertSee('Sewa Trolley T3')
        ->assertSee('Seat Management Reg 5');
});

test('finance dan user biasa tidak bisa membuka Report Projects', function (string $role) {
    $this->actingAs(User::factory()->create(['user_role' => $role]))
        ->get('/report/projects')
        ->assertForbidden();
})->with(['fin', 'user']);

test('data dibatasi divisi user seperti halaman Monitoring', function () {
    $user = User::factory()->agent()->create(['user_unit' => 'Equipment Operation & Maintenance']);

    $this->actingAs($user)
        ->get('/report/projects')
        ->assertOk()
        ->assertSee('Sewa Trolley T3')
        ->assertDontSee('Seat Management Reg 5');
});

test('filter kelengkapan menampilkan project yang nilainya kosong', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/report/projects?gap=value')
        ->assertOk()
        ->assertSee('Pengadaan Nurse Tender')
        ->assertDontSee('Sewa Trolley T3');
});

test('export menghasilkan file xlsx', function () {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->get('/report/projects/export?div=EQ');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
    expect($response->headers->get('content-disposition'))->toContain('laporan-data-project-');
});
