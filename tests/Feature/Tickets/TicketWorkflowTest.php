<?php

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\AstMain;
use App\Models\PjctEmp;
use App\Models\PjctMain;
use App\Models\SlaPolicy;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Flow ticket teknisi:
 *   open ──solve──► solved ──approve──► closed        (solved ──reject──► open)
 *   open ──escalate (BA ke pusat)──► escalated ──return (BA dari pusat)──► open
 * Teknisi = role user, approver = atasan langsung (user_parid), tim pusat = admin divisi project.
 */

beforeEach(function () {
    Storage::fake();
    SlaPolicy::factory()->create(['is_default' => true, 'response_minutes' => 60, 'resolution_minutes' => 480]);

    $this->atasan = User::factory()->supervisor()->create(['user_unit' => '-', 'user_parid' => '-']);
    $this->teknisi = User::factory()->client()->create(['user_unit' => 'Technology Operation & Maintenance', 'user_parid' => $this->atasan->id]);
    $this->teknisiEq = User::factory()->client()->create(['user_unit' => 'Equipment Operation & Maintenance', 'user_parid' => '-']);
    $this->timPusat = User::factory()->supervisor()->create(['user_unit' => 'Technology Operation & Maintenance', 'user_parid' => '-']);
    $this->adminEq = User::factory()->supervisor()->create(['user_unit' => 'Equipment Operation & Maintenance', 'user_parid' => '-']);
    $this->superadmin = User::factory()->admin()->create();
    $this->vip = User::factory()->vip()->create();

    $this->project = PjctMain::create(['pjct_name' => 'Seat Management API HO', 'pjct_client' => 'APINDO', 'pjct_type' => 'RENT', 'pjct_status' => 'OG', 'pjct_div' => 'TC']);
    $this->projectEq = PjctMain::create(['pjct_name' => 'Sewa GSE', 'pjct_client' => 'GAPURA', 'pjct_type' => 'RENT', 'pjct_status' => 'OG', 'pjct_div' => 'EQ']);

    // Teknisi hanya melihat project penugasannya di pjct_emp (dicocokkan lewat NIK).
    PjctEmp::create(['emp_id' => $this->teknisi->user_empid, 'emp_name' => $this->teknisi->user_name, 'emp_level' => 'L7', 'emp_levname' => 'Teknisi', 'emp_unit' => 'Technology Operation', 'emp_div' => 'HO', 'emp_area' => 'Jakarta', 'emp_pjctid' => $this->project->id, 'emp_coid' => '']);
    PjctEmp::create(['emp_id' => $this->teknisiEq->user_empid, 'emp_name' => $this->teknisiEq->user_name, 'emp_level' => 'L7', 'emp_levname' => 'Teknisi', 'emp_unit' => 'Regional 2', 'emp_div' => 'CGK', 'emp_area' => 'Tangerang', 'emp_pjctid' => $this->projectEq->id, 'emp_coid' => '']);

    $makeAsset = fn (PjctMain $project, string $serial, string $cond) => AstMain::create([
        'ast_type' => 'Laptop', 'ast_brand' => 'Dell', 'ast_brandmodel' => 'Latitude', 'ast_serial' => $serial,
        'ast_cond' => $cond, 'ast_stat' => 'Aktif', 'ast_pjctid' => $project->id,
    ]);
    $this->laptop = $makeAsset($this->project, 'SN-1', 'Good');
    $this->laptop2 = $makeAsset($this->project, 'SN-2', 'Fair');
    $this->laptopEq = $makeAsset($this->projectEq, 'SN-3', 'Good');
});

function doc(string $name = 'dokumen.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 50, 'application/pdf');
}

function createTicket($test, User $user, array $overrides = [])
{
    return $test->actingAs($user)->post(route('tickets.store'), array_merge([
        'pjct_id' => $test->project->id,
        'issue_type' => 'kerusakan',
        'ast_ids' => [$test->laptop->id],
        'subject' => 'Laptop check-in mati',
        'description' => 'Tersiram air, rencana cek board.',
        'priority' => 'high',
        'documents' => [doc('kronologi.pdf')],
    ], $overrides));
}

function transition($test, User $user, Ticket $ticket, string $action, array $data = [])
{
    return $test->actingAs($user)->post(route('tickets.transition', [$ticket, $action]), $data);
}

// ── Alur utama ────────────────────────────────────────────────────────────────

test('alur minor: teknisi buat → solved → atasan approve → closed, kondisi device dipulihkan', function () {
    createTicket($this, $this->teknisi)->assertRedirect();

    $ticket = Ticket::firstOrFail();
    expect($ticket->status)->toBe('open')
        ->and($ticket->created_by)->toBe($this->teknisi->id)
        ->and($ticket->requester_name)->toBe('APINDO')
        ->and($ticket->assets->pluck('id')->all())->toBe([$this->laptop->id])
        ->and($this->laptop->fresh()->ast_cond)->toBe('Bad')
        ->and($ticket->attachments()->where('doc_type', 'kronologi')->count())->toBe(1);

    // Atasan diberi tahu ada ticket baru dari bawahannya.
    expect(AppNotification::where('user_id', $this->atasan->id)->where('type', 'ticket_created')->exists())->toBeTrue();

    transition($this, $this->teknisi, $ticket, 'solve', ['note' => 'Adaptor diganti, laptop normal.'])->assertRedirect();
    $ticket->refresh();
    expect($ticket->status)->toBe('solved')
        ->and($ticket->resolved_at)->not->toBeNull()
        ->and($this->laptop->fresh()->ast_cond)->toBe('Bad'); // tetap Bad sampai di-approve

    transition($this, $this->atasan, $ticket, 'approve')->assertRedirect();
    $ticket->refresh();
    expect($ticket->status)->toBe('closed')
        ->and($ticket->approved_by)->toBe($this->atasan->id)
        ->and($ticket->closed_at)->not->toBeNull()
        ->and($this->laptop->fresh()->ast_cond)->toBe('Good')
        ->and($this->laptop->fresh()->ast_last_ticket_id)->toBeNull();
});

test('alur eskalasi: BA wajib di kedua arah, tim pusat mengembalikan ke open, atasan menolak lalu approve', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();

    // Eskalasi tanpa BA ditolak.
    transition($this, $this->teknisi, $ticket, 'escalate', ['note' => 'Board rusak'])->assertSessionHasErrors('documents');
    expect($ticket->fresh()->status)->toBe('open');

    transition($this, $this->teknisi, $ticket, 'escalate', ['note' => 'Board rusak', 'documents' => [doc('ba-ke-pusat.pdf')]])->assertRedirect();
    expect($ticket->fresh()->status)->toBe('escalated');
    expect(AppNotification::where('user_id', $this->timPusat->id)->where('type', 'ticket_escalate')->exists())->toBeTrue();

    // Hanya admin divisi project (TC) yang bisa mengembalikan.
    transition($this, $this->teknisi, $ticket, 'return', ['note' => 'x', 'documents' => [doc()]])->assertForbidden();
    transition($this, $this->adminEq, $ticket, 'return', ['note' => 'x', 'documents' => [doc()]])->assertForbidden();
    transition($this, $this->timPusat, $ticket, 'return', ['note' => 'Unit diganti'])->assertSessionHasErrors('documents');

    transition($this, $this->timPusat, $ticket, 'return', ['note' => 'Unit diganti', 'documents' => [doc('ba-dari-pusat.pdf')]])->assertRedirect();
    expect($ticket->fresh()->status)->toBe('open')
        ->and($this->laptop->fresh()->ast_cond)->toBe('Bad');

    transition($this, $this->teknisi, $ticket, 'solve', ['note' => 'Unit pengganti terpasang'])->assertRedirect();
    transition($this, $this->atasan, $ticket, 'reject', [])->assertSessionHasErrors('note');
    transition($this, $this->atasan, $ticket, 'reject', ['note' => 'BA belum ditandatangani client'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe('open')->and($ticket->fresh()->resolved_at)->toBeNull();

    transition($this, $this->teknisi, $ticket, 'solve', ['note' => 'BA lengkap'])->assertRedirect();
    transition($this, $this->atasan, $ticket, 'approve')->assertRedirect();
    expect($ticket->fresh()->status)->toBe('closed');

    expect($ticket->attachments()->pluck('doc_type')->sort()->values()->all())
        ->toBe(['ba_eskalasi', 'ba_pengembalian', 'kronologi']);
    expect(ActivityLog::where('ticket_id', $ticket->id)->pluck('action')->all())
        ->toContain('ticket_escalate', 'ticket_return', 'ticket_reject', 'ticket_approve');
});

test('aksi di luar status asalnya ditolak — kembali ke halaman tiket dengan penjelasan, bukan 403', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();

    // belum solved / belum escalated
    transition($this, $this->atasan, $ticket, 'approve')
        ->assertRedirect(route('tickets.show', $ticket))->assertSessionHasErrors('ticket');
    transition($this, $this->timPusat, $ticket, 'return', ['note' => 'x', 'documents' => [doc()]])
        ->assertRedirect(route('tickets.show', $ticket))->assertSessionHasErrors('ticket');
    expect($ticket->fresh()->status)->toBe('open');
});

test('klik ganda "Tandai Selesai": request kedua tidak diproses ulang dan tidak berujung 403', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();

    transition($this, $this->teknisi, $ticket, 'solve', ['note' => 'Adaptor diganti.'])->assertSessionHasNoErrors();
    transition($this, $this->teknisi, $ticket, 'solve', ['note' => 'Adaptor diganti.'])
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertSessionHasErrors(['ticket' => 'Aksi tidak diproses: ticket sudah berstatus Solved. Kemungkinan tombol terkirim dua kali atau sudah diproses dari tempat lain.']);

    expect($ticket->fresh()->status)->toBe('solved')
        ->and($ticket->activityLogs()->where('action', 'ticket_solve')->count())->toBe(1);

    // Halaman tiket memuat pengaman kirim-sekali di form aksi.
    $this->actingAs($this->teknisi)->get(route('tickets.show', $ticket))->assertOk()->assertSee('data-submit-once', false);
});

test('approve hanya oleh atasan langsung teknisi pembuat, superadmin sebagai cadangan', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();
    transition($this, $this->teknisi, $ticket, 'solve', ['note' => 'ok']);

    transition($this, $this->teknisi, $ticket, 'approve')->assertForbidden();
    transition($this, $this->timPusat, $ticket, 'approve')->assertForbidden();
    transition($this, $this->superadmin, $ticket, 'approve')->assertRedirect();
    expect($ticket->fresh()->status)->toBe('closed');
});

test('halaman detail tampil di setiap status dengan tombol aksi yang sesuai', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();
    $show = fn (User $user) => $this->actingAs($user)->get(route('tickets.show', $ticket))->assertOk();

    $show($this->teknisi)->assertSee('Tandai Selesai')->assertSee('Eskalasi ke Pusat');
    $show($this->atasan)->assertDontSee('Tandai Selesai');

    transition($this, $this->teknisi, $ticket, 'escalate', ['note' => 'x', 'documents' => [doc('ba-ke-pusat.pdf')]]);
    $show($this->timPusat)->assertSee('Kembalikan ke Teknisi')->assertSee('ba-ke-pusat.pdf');
    $show($this->teknisi)->assertDontSee('Kembalikan ke Teknisi');

    transition($this, $this->timPusat, $ticket, 'return', ['note' => 'x', 'documents' => [doc()]]);
    transition($this, $this->teknisi, $ticket, 'solve', ['note' => 'ok']);
    $show($this->atasan)->assertSee('Approve &amp; Close', false)->assertSee('Tolak');
    $show($this->teknisi)->assertSee('Menunggu approval');

    transition($this, $this->atasan, $ticket, 'approve');
    $show($this->vip)->assertSee('disetujui')->assertDontSee('Ubah Detail');
});

// ── Aturan form ───────────────────────────────────────────────────────────────

test('device wajib untuk kerusakan/accident, opsional untuk masalah sistem', function () {
    createTicket($this, $this->teknisi, ['ast_ids' => []])->assertSessionHasErrors('ast_ids');
    createTicket($this, $this->teknisi, ['issue_type' => 'accident', 'ast_ids' => []])->assertSessionHasErrors('ast_ids');

    createTicket($this, $this->teknisi, ['issue_type' => 'sistem', 'ast_ids' => []])->assertRedirect()->assertSessionHasNoErrors();
    expect(Ticket::where('issue_type', 'sistem')->first()->assets)->toHaveCount(0);
});

test('device harus milik project, project harus di divisi teknisi, dokumen kronologi wajib', function () {
    createTicket($this, $this->teknisi, ['ast_ids' => [$this->laptopEq->id]])->assertSessionHasErrors('ast_ids.0');
    createTicket($this, $this->teknisi, ['pjct_id' => $this->projectEq->id, 'ast_ids' => [$this->laptopEq->id]])->assertSessionHasErrors('pjct_id');
    createTicket($this, $this->teknisi, ['documents' => []])->assertSessionHasErrors('documents');

    expect(Ticket::count())->toBe(0);
});

test('hanya teknisi (role user) dan superadmin yang bisa membuat ticket', function () {
    foreach ([$this->atasan, $this->vip] as $user) {
        $this->actingAs($user)->get(route('tickets.create'))->assertForbidden();
        createTicket($this, $user)->assertForbidden();
    }

    $this->actingAs($this->teknisi)->get(route('tickets.create'))->assertOk();
    createTicket($this, $this->superadmin)->assertRedirect();
});

// ── Kondisi device (regresi) ──────────────────────────────────────────────────

test('dua ticket aktif pada device yang sama: kondisi asli tetap dipulihkan', function () {
    createTicket($this, $this->teknisi);
    createTicket($this, $this->teknisi);
    [$first, $second] = Ticket::orderBy('id')->get();

    foreach ([$first, $second] as $ticket) {
        transition($this, $this->teknisi, $ticket, 'solve', ['note' => 'ok']);
        transition($this, $this->atasan, $ticket, 'approve');
        $expected = $ticket->is($first) ? 'Bad' : 'Good'; // masih ditahan ticket kedua → tetap Bad
        expect($this->laptop->fresh()->ast_cond)->toBe($expected);
    }
});

test('mengganti device di ticket memulihkan device lama dan menandai device baru', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();

    $this->actingAs($this->teknisi)->patch(route('tickets.update', $ticket), [
        'priority' => 'high', 'issue_type' => 'kerusakan', 'ast_ids' => [$this->laptop2->id],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($this->laptop->fresh()->ast_cond)->toBe('Good')
        ->and($this->laptop2->fresh()->ast_cond)->toBe('Bad');

    transition($this, $this->teknisi, $ticket, 'solve', ['note' => 'ok']);
    transition($this, $this->atasan, $ticket, 'approve');
    expect($this->laptop2->fresh()->ast_cond)->toBe('Fair'); // kondisi aslinya, bukan milik device lama
});

// ── Visibilitas & akses ───────────────────────────────────────────────────────

test('visibilitas per divisi: teknisi & admin divisi lain tidak melihat ticket', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();

    foreach ([$this->teknisi, $this->atasan, $this->timPusat, $this->superadmin, $this->vip] as $user) {
        $this->actingAs($user)->get(route('tickets.show', $ticket))->assertOk();
        $this->actingAs($user)->get(route('tickets.index'))->assertSee($ticket->ticket_number);
    }

    foreach ([$this->teknisiEq, $this->adminEq] as $user) {
        $this->actingAs($user)->get(route('tickets.show', $ticket))->assertForbidden();
        $this->actingAs($user)->get(route('tickets.index'))->assertDontSee($ticket->ticket_number);
    }
});

test('filter "perlu tindakan saya" sesuai peran', function () {
    createTicket($this, $this->teknisi, ['subject' => 'Ticket solved']);
    createTicket($this, $this->teknisi, ['subject' => 'Ticket escalated', 'ast_ids' => [$this->laptop2->id]]);
    [$solved, $escalated] = Ticket::orderBy('id')->get();
    transition($this, $this->teknisi, $solved, 'solve', ['note' => 'ok']);
    transition($this, $this->teknisi, $escalated, 'escalate', ['note' => 'x', 'documents' => [doc()]]);

    // Cek data daftar, bukan HTML: nomor ticket lain juga muncul di dropdown notifikasi layout.
    $listed = fn (User $user) => $this->actingAs($user)->get(route('tickets.index', ['needs_action' => 1]))
        ->assertOk()->viewData('tickets')->pluck('id')->all();

    // Atasan = admin yang divisinya (lewat bawahan) mencakup TC → juga termasuk tim divisi untuk eskalasi.
    expect($listed($this->atasan))->toEqualCanonicalizing([$solved->id, $escalated->id])
        ->and($listed($this->timPusat))->toBe([$escalated->id])
        ->and($listed($this->adminEq))->toBe([])
        ->and($listed($this->teknisi))->toBe([]); // tidak ada lagi yang open
});

test('visibilitas daftar ticket tidak bocor lewat HTML daftar', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();

    expect($this->actingAs($this->adminEq)->get(route('tickets.index'))->viewData('tickets')->total())->toBe(0)
        ->and($this->actingAs($this->teknisi)->get(route('tickets.index'))->viewData('tickets')->pluck('id')->all())->toBe([$ticket->id]);
});

test('lampiran bisa diunduh oleh yang berhak, ditolak untuk divisi lain', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();
    $attachment = TicketAttachment::firstOrFail();

    $this->actingAs($this->atasan)->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertOk();
    $this->actingAs($this->adminEq)->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertForbidden();
});

test('komentar: vip hanya-lihat, ticket closed tidak bisa dikomentari', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();

    $this->actingAs($this->vip)->post(route('tickets.messages.store', $ticket), ['body' => 'cek'])->assertForbidden();
    $this->actingAs($this->timPusat)->post(route('tickets.messages.store', $ticket), ['body' => 'Siap bantu'])->assertRedirect();
    expect($ticket->messages()->count())->toBe(1)->and($ticket->fresh()->status)->toBe('open'); // komentar tidak mengubah status

    transition($this, $this->teknisi, $ticket, 'solve', ['note' => 'ok']);
    transition($this, $this->atasan, $ticket, 'approve');
    $this->actingAs($this->teknisi)->post(route('tickets.messages.store', $ticket), ['body' => 'lagi'])->assertForbidden();
});

test('endpoint device project dibatasi divisi', function () {
    $this->actingAs($this->teknisi)->getJson(route('tickets.project-assets', ['pjct_id' => $this->project->id]))
        ->assertOk()
        ->assertJsonCount(2, 'assets')
        ->assertJsonPath('assets.0.id', $this->laptop->id);

    $this->actingAs($this->teknisi)->getJson(route('tickets.project-assets', ['pjct_id' => $this->projectEq->id]))->assertForbidden();
    $this->actingAs($this->vip)->getJson(route('tickets.project-assets', ['pjct_id' => $this->project->id]))->assertForbidden();
});

// ── Otomasi ───────────────────────────────────────────────────────────────────

test('notifikasi SLA terlewati dikirim sekali, ticket tidak di-auto-close', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();
    $ticket->update(['resolution_due_at' => now()->subHour()]);

    $this->artisan('app:run-helpdesk-automation')->assertSuccessful();
    $this->artisan('app:run-helpdesk-automation')->assertSuccessful();

    expect(AppNotification::where('type', 'sla_breached')->where('user_id', $this->timPusat->id)->count())->toBe(1)
        ->and(AppNotification::where('type', 'sla_breached')->where('user_id', $this->atasan->id)->count())->toBe(1)
        ->and($ticket->fresh()->status)->toBe('open');
});

test('superadmin menghapus ticket permanen: lampiran & file, komentar, notifikasi, log ikut terhapus; device dipulihkan', function () {
    createTicket($this, $this->teknisi);
    $ticket = Ticket::firstOrFail();
    $this->actingAs($this->timPusat)->post(route('tickets.messages.store', $ticket), [
        'body' => 'Foto kerusakan', 'attachments' => [UploadedFile::fake()->image('foto.jpg')],
    ])->assertRedirect();

    $paths = TicketAttachment::where('ticket_id', $ticket->id)->pluck('path');
    expect($paths)->toHaveCount(2)
        ->and($this->laptop->fresh()->only(['ast_cond', 'ast_last_ticket_id']))->toBe(['ast_cond' => 'Bad', 'ast_last_ticket_id' => $ticket->id])
        ->and(AppNotification::where('ticket_id', $ticket->id)->exists())->toBeTrue()
        ->and(ActivityLog::where('ticket_id', $ticket->id)->exists())->toBeTrue();
    $paths->each(fn ($p) => Storage::assertExists($p));

    // Tombol hapus hanya untuk superadmin (URL-nya sama dengan halaman tiket, jadi dicek dari teksnya).
    $this->actingAs($this->superadmin)->get(route('tickets.show', $ticket))->assertOk()->assertSee('Hapus ticket…');
    $this->actingAs($this->teknisi)->get(route('tickets.show', $ticket))->assertOk()->assertDontSee('Hapus ticket');

    // Konfirmasi salah → tidak terhapus.
    $this->actingAs($this->superadmin)->from(route('tickets.show', $ticket))
        ->delete(route('tickets.destroy', $ticket), ['confirm_number' => 'salah'])
        ->assertSessionHasErrors('confirm_number');
    expect(Ticket::whereKey($ticket->id)->exists())->toBeTrue();

    $this->actingAs($this->superadmin)
        ->delete(route('tickets.destroy', $ticket), ['confirm_number' => $ticket->ticket_number])
        ->assertRedirect(route('tickets.index'))
        ->assertSessionHas('success', "Ticket {$ticket->ticket_number} dihapus beserta 2 lampiran, kondisi 1 device dipulihkan.");

    expect(Ticket::whereKey($ticket->id)->exists())->toBeFalse()
        ->and(TicketAttachment::where('ticket_id', $ticket->id)->count())->toBe(0)
        ->and(DB::table('ticket_messages')->where('ticket_id', $ticket->id)->count())->toBe(0)
        ->and(DB::table('ticket_assets')->where('ticket_id', $ticket->id)->count())->toBe(0)
        ->and(AppNotification::where('ticket_id', $ticket->id)->count())->toBe(0)
        ->and(ActivityLog::where('ticket_id', $ticket->id)->count())->toBe(0)
        ->and($this->laptop->fresh()->only(['ast_cond', 'ast_last_ticket_id']))->toBe(['ast_cond' => 'Good', 'ast_last_ticket_id' => null]);
    $paths->each(fn ($p) => Storage::assertMissing($p));

    $log = SystemLog::where('loggable_type', 'Ticket')->where('action', 'delete')->sole();
    expect($log->user_id)->toBe($this->superadmin->id)
        ->and($log->properties['ticket_number'])->toBe($ticket->ticket_number)
        ->and($log->properties['attachments'])->toBe(2)
        ->and($log->properties['released_assets'])->toBe([$this->laptop->id]);
});

test('hapus ticket: selain superadmin ditolak; device yang masih ditahan ticket aktif lain tetap Bad', function () {
    createTicket($this, $this->teknisi);
    createTicket($this, $this->teknisi, ['subject' => 'Laptop sama, laporan kedua']);
    [$first, $second] = Ticket::orderBy('id')->get()->all();

    foreach ([$this->teknisi, $this->atasan, $this->timPusat, $this->vip] as $user) {
        $this->actingAs($user)->delete(route('tickets.destroy', $first), ['confirm_number' => $first->ticket_number])->assertForbidden();
    }

    $this->actingAs($this->superadmin)->delete(route('tickets.destroy', $first), ['confirm_number' => $first->ticket_number])->assertRedirect(route('tickets.index'));

    // Laptop masih ditahan ticket kedua → tetap Bad, penunjuknya pindah ke ticket kedua.
    expect($this->laptop->fresh()->only(['ast_cond', 'ast_last_ticket_id']))->toBe(['ast_cond' => 'Bad', 'ast_last_ticket_id' => $second->id])
        ->and(Ticket::pluck('id')->all())->toBe([$second->id]);
});
