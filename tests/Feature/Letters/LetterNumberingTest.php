<?php

use App\Models\Letter;
use App\Models\LetterCounter;
use App\Models\LetterType;
use App\Models\PjctMain;
use App\Models\User;
use App\Support\LetterImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

// Tahun berjalan menentukan counter & tanda "tahun salah ketik" di impor.
beforeEach(fn () => $this->travelTo('2026-11-11 10:00:00'));

function letterType(string $name): LetterType
{
    return LetterType::query()->where('name', $name)->firstOrFail();
}

function letterAdmin(): User
{
    return User::factory()->supervisor()->create(['user_name' => 'Anisa']);
}

function makeLetter(User $user, string $type, string $date, array $extra = [])
{
    return test()->actingAs($user)->post('/letters', [
        'letter_type_id' => letterType($type)->id,
        'letter_date' => $date,
        'quantity' => 1,
        'subject' => 'Permohonan izin masuk teknisi',
        'recipient' => 'PT Angkasa Pura Indonesia',
        'signer' => 'E & T OPS GH',
        'pic' => 'Anisa',
        ...$extra,
    ]);
}

test('superadmin dan admin bisa membuka menu surat', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/letters')->assertOk()->assertSee('Buat Nomor Surat');
    $this->actingAs($user)->get('/letters/types')->assertOk()->assertSee('Surat Keluar');
})->with(['admin', 'supervisor']);

test('role selain superadmin dan admin tidak bisa membuka menu surat', function (string $role) {
    $user = User::factory()->create(['user_role' => $role]);

    $this->actingAs($user)->get('/letters')->assertForbidden();
    $this->actingAs($user)->get('/letters/types')->assertForbidden();
    makeLetter($user, 'Surat Keluar', '2026-11-11')->assertForbidden();
})->with(['siteadmin', 'vip', 'fin', 'user']);

test('nomor dibuat berurutan dengan format IASS/unit/kode/romawi/tahun/urut', function () {
    $admin = letterAdmin();

    makeLetter($admin, 'Surat Keluar', '2026-11-11')->assertRedirect('/letters')->assertSessionHas('created_letters');
    makeLetter($admin, 'Surat Keluar', '2026-11-12')->assertRedirect('/letters');

    expect(Letter::query()->orderBy('id')->pluck('number')->all())
        ->toBe(['IASS/DEO/SKR/XI/2026/001', 'IASS/DEO/SKR/XI/2026/002']);

    $letter = Letter::query()->first();
    expect($letter->created_by)->toBe($admin->id)
        ->and($letter->source)->toBe('app')
        ->and($letter->letter_date->toDateString())->toBe('2026-11-11');
});

test('urutan reset tiap tahun dan terpisah per kode', function () {
    $admin = letterAdmin();

    makeLetter($admin, 'Surat Keluar', '2025-12-30');
    makeLetter($admin, 'Surat Keluar', '2026-01-02');
    makeLetter($admin, 'Nota Dinas', '2026-01-02');

    expect(Letter::query()->orderBy('id')->pluck('number')->all())->toBe([
        'IASS/DEO/SKR/XII/2025/001',
        'IASS/DEO/SKR/I/2026/001',
        'IASS/DEO/NODIN/I/2026/001',
    ]);
});

test('jenis surat yang kodenya sama berbagi urutan', function () {
    $admin = letterAdmin();

    makeLetter($admin, 'BA Pemeriksaan Pelaksanaan Pekerjaan (BAPPP)', '2026-10-01');
    makeLetter($admin, 'BA Utilisasi', '2026-10-01');
    makeLetter($admin, 'BA Training & Familiarization', '2026-10-02');

    expect(Letter::query()->orderBy('id')->pluck('number')->all())->toBe([
        'IASS/DEO/BAPPP/X/2026/001',
        'IASS/DEO/BAPPP/X/2026/002',
        'IASS/DEO/BAPPP/X/2026/003',
    ]);
});

test('bisa membuat beberapa nomor sekaligus', function () {
    makeLetter(letterAdmin(), 'Laporan Hasil Pekerjaan', '2026-02-27', ['quantity' => 3])->assertSessionHasNoErrors();

    expect(Letter::query()->orderBy('id')->pluck('number')->all())->toBe([
        'IASS/DEO/LHP/II/2026/001',
        'IASS/DEO/LHP/II/2026/002',
        'IASS/DEO/LHP/II/2026/003',
    ]);
});

test('urutan yang sudah terpakai (mis. data impor) dilompati', function () {
    $type = letterType('Surat Keluar');
    Letter::create([
        'letter_type_id' => $type->id, 'number' => 'IASS/DEO/SKR/XI/2026/002', 'unit_code' => 'DEO', 'code' => 'SKR',
        'year' => 2026, 'month' => 11, 'seq' => 2, 'subject' => 'Data lama', 'source' => 'import',
    ]);

    $admin = letterAdmin();
    makeLetter($admin, 'Surat Keluar', '2026-11-11');
    makeLetter($admin, 'Surat Keluar', '2026-11-11');

    expect(Letter::query()->where('source', 'app')->orderBy('id')->pluck('number')->all())
        ->toBe(['IASS/DEO/SKR/XI/2026/001', 'IASS/DEO/SKR/XI/2026/003']);
    expect(LetterCounter::query()->where('code', 'SKR')->where('year', 2026)->value('last_seq'))->toBe(3);
});

test('nomor turunan memakai urutan induk dan bulan dari tanggalnya sendiri', function () {
    $admin = letterAdmin();
    makeLetter($admin, 'BA Pemeriksaan / Penyelesaian Pekerjaan (BAPP)', '2026-02-03');
    $parent = Letter::query()->firstOrFail();

    $payload = ['letter_date' => '2026-03-03', 'quantity' => 1, 'subject' => 'Addendum I periode Februari'];
    $this->actingAs($admin)->post("/letters/{$parent->id}/sub", $payload)->assertRedirect('/letters');
    $this->actingAs($admin)->post("/letters/{$parent->id}/sub", ['quantity' => 2] + $payload)->assertRedirect('/letters');

    expect(Letter::query()->orderBy('id')->pluck('number')->all())->toBe([
        'IASS/DEO/BAPP/II/2026/001',
        'IASS/DEO/BAPP/III/2026/001.1',
        'IASS/DEO/BAPP/III/2026/001.2',
        'IASS/DEO/BAPP/III/2026/001.3',
    ]);

    // Nomor utama berikutnya tetap 002 — turunan tidak memakan urutan.
    makeLetter($admin, 'BA Pemeriksaan / Penyelesaian Pekerjaan (BAPP)', '2026-03-10');
    expect(Letter::query()->latest('id')->value('number'))->toBe('IASS/DEO/BAPP/III/2026/002');
});

test('nomor turunan harus di tahun yang sama dengan induknya', function () {
    $admin = letterAdmin();
    makeLetter($admin, 'Surat Keluar', '2025-12-20');
    $parent = Letter::query()->firstOrFail();

    $this->actingAs($admin)
        ->post("/letters/{$parent->id}/sub", ['letter_date' => '2026-01-05', 'quantity' => 1, 'subject' => 'Lanjutan'])
        ->assertSessionHasErrors('letter_date');

    expect(Letter::query()->count())->toBe(1);
});

test('edit hanya mengubah data surat, nomornya tetap', function () {
    Storage::fake('docfile');
    $project = PjctMain::create(['pjct_name' => 'Sewa Trolley T3', 'pjct_div' => 'EQ', 'pjct_status' => 'OG', 'pjct_type' => 'RENT']);
    $admin = letterAdmin();
    makeLetter($admin, 'Surat Keluar', '2026-11-11');
    $letter = Letter::query()->firstOrFail();

    $this->actingAs($admin)->patch("/letters/{$letter->id}", [
        'subject' => 'Perihal yang benar',
        'recipient' => 'Gapura',
        'pjct_id' => $project->id,
        // Field di luar daftar edit diabaikan.
        'number' => 'IASS/DEO/SKR/XI/2026/999',
        'letter_date' => '2026-01-01',
    ])->assertSessionHasNoErrors();

    $letter->refresh();
    expect($letter->number)->toBe('IASS/DEO/SKR/XI/2026/001')
        ->and($letter->letter_date->toDateString())->toBe('2026-11-11')
        ->and($letter->subject)->toBe('Perihal yang benar')
        ->and($letter->pjct_id)->toBe($project->id);
});

test('pembatalan butuh alasan dan nomor batal tidak bisa diedit atau diberi turunan', function () {
    $admin = letterAdmin();
    makeLetter($admin, 'Surat Keluar', '2026-11-11');
    $letter = Letter::query()->firstOrFail();

    $this->actingAs($admin)->post("/letters/{$letter->id}/cancel", [])->assertSessionHasErrors('cancel_reason');
    $this->actingAs($admin)->post("/letters/{$letter->id}/cancel", ['cancel_reason' => 'Salah jenis surat'])->assertSessionHasNoErrors();

    $letter->refresh();
    expect($letter->status)->toBe(Letter::STATUS_CANCELLED)
        ->and($letter->cancelled_by)->toBe($admin->id);

    $this->actingAs($admin)->patch("/letters/{$letter->id}", ['subject' => 'x'])->assertSessionHasErrors('letter');
    $this->actingAs($admin)->post("/letters/{$letter->id}/sub", ['letter_date' => '2026-11-11', 'quantity' => 1, 'subject' => 'x'])
        ->assertSessionHasErrors('letter');

    // Nomor batal tidak dipakai ulang.
    makeLetter($admin, 'Surat Keluar', '2026-11-12');
    expect(Letter::query()->latest('id')->value('number'))->toBe('IASS/DEO/SKR/XI/2026/002');
});

test('file surat bisa diunggah, dibuka, dan diganti', function () {
    Storage::fake('local');
    $admin = letterAdmin();

    makeLetter($admin, 'Surat Keluar', '2026-11-11', ['file' => UploadedFile::fake()->create('surat.pdf', 200, 'application/pdf')])
        ->assertSessionHasNoErrors();
    $letter = Letter::query()->firstOrFail();
    expect($letter->file_name)->toBe('surat.pdf');
    Storage::disk('local')->assertExists($letter->file_path);

    $this->actingAs($admin)->get("/letters/{$letter->id}/file")->assertOk();

    $old = $letter->file_path;
    $this->actingAs($admin)->patch("/letters/{$letter->id}", [
        'subject' => $letter->subject,
        'file' => UploadedFile::fake()->create('surat-ttd.pdf', 100, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $letter->refresh();
    expect($letter->file_name)->toBe('surat-ttd.pdf');
    Storage::disk('local')->assertMissing($old);
});

test('file ditolak kalau membuat lebih dari satu nomor', function () {
    Storage::fake('local');

    makeLetter(letterAdmin(), 'Surat Keluar', '2026-11-11', [
        'quantity' => 2,
        'file' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('file');

    expect(Letter::query()->count())->toBe(0);
});

test('export menghasilkan file xlsx', function () {
    $admin = letterAdmin();
    makeLetter($admin, 'Surat Keluar', '2026-11-11');

    $response = $this->actingAs($admin)->get('/letters/export?year=2026');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
    expect($response->headers->get('content-disposition'))->toContain('register-nomor-surat-');
});

test('admin bisa menambah jenis surat baru dengan kode huruf besar', function () {
    $admin = letterAdmin();

    $this->actingAs($admin)->post('/letters/types', ['name' => 'Surat Kuasa', 'code' => 'sk', 'unit_code' => 'deo'])
        ->assertSessionHasNoErrors();

    $type = letterType('Surat Kuasa');
    expect($type->code)->toBe('SK')->and($type->unit_code)->toBe('DEO');

    $this->actingAs($admin)->post('/letters/types', ['name' => 'Kode Salah', 'code' => 'S K!', 'unit_code' => 'DEO'])
        ->assertSessionHasErrors('code');
});

test('impor Excel membaca register, menandai kejanggalan, dan melanjutkan urutan', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('SKR');
    $sheet->fromArray([
        ['NO', 'TANGGAL', 'BULAN (ROMAWI)', 'TAHUN', 'TIPE SURAT', 'NOMOR SURAT', 'PERIHAL', 'PENANDATANGAN', 'TUJUAN SURAT', 'PIC INTERNAL'],
        ['001', 16, 'I', 2026, 'SURAT KELUAR', 'IASS/DEO/SKR/I/2026/001', 'Surat Pernyataan', 'E & T OPS GH', 'OTBAN', 'TRIO'],
        ['002', '27 Februari 2026', 'II', 2026, 'SURAT KELUAR', 'IASS/DEO/SKR/II/2026/002', 'Penyampaian Risalah', 'E & T OPS GH', 'APINDO', 'agung'],
        ['002', 28, 'II', 2026, 'SURAT KELUAR', 'IASS/DEO/SKR/II/2026/002', 'Penyampaian Risalah', 'E & T OPS GH', 'APINDO', 'agung'],
        ['002,1', 28, 'II', 2026, 'SURAT KELUAR', 'IASS/DEO/SKR/II/2026/002,1', 'Lampiran Risalah', 'E & T OPS GH', 'APINDO', 'Agung'],
        ['005', 4, 'VIII', 2027, 'SURAT KELUAR', 'IASS/DEO/SKR/VIII/2027/133', 'Salah ketik tahun', 'E & T OPS GH', 'API', 'Rahayu'],
        ['006', null, null, null, null, 'IASS/DEO/SKR///', null, null, null, null],
    ], null, 'A2');

    $bapp = $spreadsheet->createSheet();
    $bapp->setTitle('BAPP');
    $bapp->fromArray([
        ['NO', 'TANGGAL', 'BULAN (ROMAWI)', 'TAHUN', 'TIPE SURAT', 'NOMOR SURAT', 'PERIHAL', 'PENANDATANGAN', 'TUJUAN SURAT', 'PIC INTERNAL'],
        ['004', 3, 'II', 2026, 'BA PEMERIKSAAN PEKERJAAN', 'IASS/DEO/BAPP/II/2026/4', 'Sewa trolley', 'GH', 'PT X', 'Anisa'],
        ['004', 3, 'II', 2026, 'BA PEMERIKSAAN PEKERJAAN', 'IASS/DEO/BAPP/II/2026/004', 'Sewa trolley periode lain', 'GH', 'PT X', 'Anisa'],
    ], null, 'A2');

    $path = tempnam(sys_get_temp_dir(), 'surat').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $importer = app(LetterImportService::class);
    $result = $importer->parse($path);

    // Baris salinan (nomor + perihal sama) dilewati; baris kosong tanpa data diabaikan.
    expect(collect($result['skipped'])->pluck('reason')->implode('|'))->toContain('Salinan dari sheet SKR');
    expect(collect($result['rows'])->pluck('number')->all())->toBe([
        'IASS/DEO/SKR/I/2026/001',
        'IASS/DEO/SKR/II/2026/002',
        'IASS/DEO/SKR/II/2026/002.1',
        'IASS/DEO/SKR/VIII/2027/133',
        'IASS/DEO/BAPP/II/2026/004',
        'IASS/DEO/BAPP/II/2026/004',
    ]);

    $rows = collect($result['rows'])->keyBy(fn ($r) => $r['sheet'].$r['row']);
    expect($rows['SKR3']['letter_date'])->toBe('2026-01-16')
        ->and($rows['SKR4']['letter_date'])->toBe('2026-02-27')
        ->and($rows['SKR4']['pic'])->toBe('Agung')
        ->and(implode(' ', $rows['SKR6']['notes']))->toContain('Nomor asli di Excel')
        ->and(implode(' ', $rows['SKR7']['notes']))->toContain('melebihi tahun berjalan')
        ->and($rows['SKR7']['letter_date'])->toBeNull()
        ->and(implode(' ', $rows['BAPP3']['notes']))->toContain('Nomor ganda');

    $importer->execute($result['rows'], null);

    expect(Letter::query()->where('source', 'import')->count())->toBe(6);
    expect(LetterCounter::query()->where('code', 'SKR')->where('year', 2026)->value('last_seq'))->toBe(2);
    // Tahun salah ketik (2027) tidak menaikkan counter tahun depan.
    expect(LetterCounter::query()->where('code', 'SKR')->where('year', 2027)->exists())->toBeFalse();

    makeLetter(letterAdmin(), 'Surat Keluar', '2026-11-11');
    expect(Letter::query()->where('source', 'app')->value('number'))->toBe('IASS/DEO/SKR/XI/2026/003');

    @unlink($path);
});
