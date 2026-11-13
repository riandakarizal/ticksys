<?php

use App\Models\AstMain;
use App\Models\PjctEmp;
use App\Models\PjctMain;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Role `qhsse` (unit Quality, Health, Safety, Security & Environment) — daftar-putih hanya-lihat:
 * Dashboard, Project → Main, Project → Asset, Tickets. Semua divisi, tanpa aksi tulis apa pun.
 */

beforeEach(function () {
    Storage::fake();
    SlaPolicy::factory()->create(['is_default' => true, 'response_minutes' => 60, 'resolution_minutes' => 480]);

    $this->qhsse = User::factory()->qhsse()->create(['user_parid' => '-']);

    $base = ['pjct_client' => 'APINDO', 'pjct_type' => 'RENT', 'pjct_status' => 'OG'];
    $this->projectTc = PjctMain::create($base + ['pjct_name' => 'Seat Management HO', 'pjct_div' => 'TC']);
    $this->projectEq = PjctMain::create($base + ['pjct_name' => 'Sewa GSE Bandara', 'pjct_div' => 'EQ']);

    // Teknisi yang atasannya user QHSSE — memastikan QHSSE tetap tidak bisa approve.
    $this->teknisi = User::factory()->client()->create(['user_unit' => 'Technology Operation & Maintenance', 'user_parid' => $this->qhsse->id]);
    PjctEmp::create([
        'emp_id' => $this->teknisi->user_empid, 'emp_name' => $this->teknisi->user_name, 'emp_level' => 'L7',
        'emp_levname' => 'Teknisi', 'emp_unit' => 'Technology Operation', 'emp_div' => 'HO',
        'emp_area' => 'Jakarta', 'emp_pjctid' => $this->projectTc->id, 'emp_coid' => '',
    ]);

    $this->laptop = AstMain::create([
        'ast_type' => 'Laptop', 'ast_brand' => 'Dell', 'ast_brandmodel' => 'Latitude', 'ast_serial' => 'SN-QH1',
        'ast_cond' => 'Good', 'ast_stat' => 'Aktif', 'ast_pjctid' => $this->projectTc->id,
    ]);

    $this->actingAs($this->teknisi)->post(route('tickets.store'), [
        'pjct_id' => $this->projectTc->id,
        'issue_type' => 'kerusakan',
        'ast_ids' => [$this->laptop->id],
        'subject' => 'Laptop check-in mati',
        'description' => 'Tersiram air.',
        'priority' => 'high',
        'documents' => [UploadedFile::fake()->create('kronologi.pdf', 50, 'application/pdf')],
    ])->assertRedirect();

    $this->ticket = Ticket::query()->latest('id')->firstOrFail();
});

test('qhsse melihat semua divisi', function () {
    expect($this->qhsse->allowedDivCodes())->toBeNull()
        ->and($this->qhsse->canSeeProject($this->projectEq))->toBeTrue()
        ->and($this->qhsse->roleLabel())->toBe('QHSSE');
});

test('qhsse bisa membuka Dashboard, Project → Main, Project → Asset, dan Tickets', function () {
    $this->actingAs($this->qhsse);

    $this->get('/dashboard')->assertOk();
    $this->get('/monitoring')->assertOk()->assertSee('Seat Management HO')->assertSee('Sewa GSE Bandara');
    $this->get('/project/assets')->assertOk();
    $this->get('/tickets')->assertOk()->assertSee($this->ticket->ticket_number);
    $this->get(route('tickets.show', $this->ticket))->assertOk();
});

test('qhsse tidak melihat tombol tulis', function () {
    $this->actingAs($this->qhsse);

    $this->get('/tickets')->assertDontSee(route('tickets.create'));
    // tickets.update memakai URL yang sama dengan halaman detail (beda method) — dicek lewat policy di bawah.
    $this->get(route('tickets.show', $this->ticket))
        ->assertDontSee(route('tickets.messages.store', $this->ticket));
    $this->get('/monitoring')->assertDontSee(route('monitoring.store', 'projects'));
});

test('qhsse tidak bisa membuka menu di luar daftar-putih', function (string $url) {
    $this->actingAs($this->qhsse)->get($url)->assertForbidden();
})->with([
    '/project/manpower',
    '/report/issues',
    '/report/data',
    '/report/projects',
    '/reports',
    '/vendor',
    '/letters',
    '/admin/users',
    '/monitoring/import',
    '/tickets/create',
]);

test('qhsse tidak bisa menulis ke ticket', function () {
    $this->actingAs($this->qhsse);

    $this->post(route('tickets.messages.store', $this->ticket), ['body' => 'cek'])->assertForbidden();
    $this->patch(route('tickets.update', $this->ticket), ['subject' => 'ubah'])->assertForbidden();
    $this->post(route('tickets.transition', [$this->ticket, 'solve']))->assertForbidden();
    $this->post(route('tickets.store'), [])->assertForbidden();

    expect($this->qhsse->can('comment', $this->ticket))->toBeFalse()
        ->and($this->qhsse->can('update', $this->ticket))->toBeFalse();
});

test('qhsse tidak bisa approve walau menjadi atasan teknisi pembuat ticket', function () {
    $this->ticket->update(['status' => 'solved']);

    expect($this->qhsse->can('approve', $this->ticket->fresh()))->toBeFalse()
        ->and($this->qhsse->can('reject', $this->ticket->fresh()))->toBeFalse();
});

test('qhsse tidak bisa mengubah project', function () {
    $this->actingAs($this->qhsse);

    $this->post(route('monitoring.store', 'projects'), ['pjct_name' => 'X'])->assertForbidden();
    $this->put(route('monitoring.update', ['projects', $this->projectTc->id]), ['pjct_name' => 'X'])->assertForbidden();
    $this->patch(route('monitoring.details.update', $this->projectTc), ['pjct_name' => 'X'])->assertForbidden();
    $this->delete(route('monitoring.destroy', ['projects', $this->projectTc->id]))->assertForbidden();
});

test('superadmin bisa membuat akun qhsse dengan unit QHSSE', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.users.store'), [
        'user_empid' => 'QH001',
        'user_name' => 'Staff QHSSE',
        'user_email' => 'qhsse@example.test',
        'user_pass' => 'Prism@123',
        'user_level' => 'L5',
        'user_role' => 'qhsse',
        'user_unit' => 'Quality, Health, Safety, Security & Environment',
        'user_div' => '-',
        'user_status' => 'active',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('user_email', 'qhsse@example.test')->value('user_role'))->toBe('qhsse');
});
