<?php

use App\Models\PjctAddendum;
use App\Models\PjctDoc;
use App\Models\PjctMain;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Addendum kontrak: dicatat sebagai riwayat di project (pjct_addenda) dan langsung berlaku ke pjct_main
 * (nilai, tanggal akhir, nama client). Lihat = yang boleh membuka Project → Main; catat = superadmin /
 * admin E&T Commercial di divisinya.
 */

beforeEach(function () {
    Storage::fake('docfile');

    $this->project = PjctMain::create([
        'pjct_name' => 'Sewa Laptop Injourney', 'pjct_client' => 'INJOURNEY', 'pjct_type' => 'RENT',
        'pjct_status' => 'OG', 'pjct_div' => 'TC', 'pjct_contract' => 'PJJ.INJ.11/23/02/2023',
        'pjct_value' => 1_000_000_000, 'pjct_costart' => '2023-02-01', 'pjct_coend_m' => '2026-08-01',
    ]);
    $this->projectEq = PjctMain::create([
        'pjct_name' => 'Sewa GSE', 'pjct_client' => 'GAPURA', 'pjct_type' => 'RENT', 'pjct_status' => 'OG', 'pjct_div' => 'EQ',
    ]);

    $this->superadmin = User::factory()->admin()->create();
    $this->commercialTc = User::factory()->supervisor()->create([
        'user_div' => 'Equipment & Technology Commercial', 'user_unit' => 'Technology Commercial',
    ]);
});

function addendumPayload(array $overrides = []): array
{
    return array_merge([
        'number' => 'PJJ.INJ.11/23/02/2023/A.0121',
        'addendum_date' => '2026-07-15',
        'reasons' => ['NILAI', 'WAKTU', 'LINGKUP'],
        'new_value' => 1_200_000_000,
        'new_end' => '2026-10-01',
        'notes' => ['LINGKUP' => 'Tambah 20 unit laptop untuk kantor cabang', 'SPEK' => 'diabaikan karena tidak dipilih'],
        'document' => UploadedFile::fake()->create('addendum-1.pdf', 120, 'application/pdf'),
    ], $overrides);
}

test('addendum dicatat, langsung berlaku ke project, dokumen tersimpan, riwayat tampil', function () {
    $this->actingAs($this->superadmin)
        ->post(route('monitoring.addenda.store', $this->project->id), addendumPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('monitoring.addenda.index', $this->project->id));

    $project = $this->project->fresh();
    expect($project->pjct_value)->toBe(1_200_000_000)
        ->and($project->pjct_coend_m->toDateString())->toBe('2026-10-01')
        ->and($project->pjct_client)->toBe('INJOURNEY');

    $a = PjctAddendum::sole();
    expect($a->seq)->toBe(1)
        ->and($a->reasons)->toBe(['NILAI', 'WAKTU', 'LINGKUP'])
        ->and($a->notes)->toBe(['LINGKUP' => 'Tambah 20 unit laptop untuk kantor cabang'])
        ->and($a->changes)->toBe([
            'pjct_value' => ['before' => 1_000_000_000, 'after' => 1_200_000_000],
            'pjct_coend_m' => ['before' => '2026-08-01', 'after' => '2026-10-01'],
        ])
        ->and($a->user_id)->toBe($this->superadmin->id);

    $doc = PjctDoc::findOrFail($a->doc_id);
    expect($doc->doc_type)->toBe('ADD')
        ->and($doc->doc_pjctid)->toBe($this->project->id)
        ->and($doc->doc_desc)->toBe('Addendum 1 — PJJ.INJ.11/23/02/2023/A.0121');
    Storage::disk('docfile')->assertExists($doc->doc_filepath);

    // Log: addendum dibuat + kolom project yang berubah.
    expect(SystemLog::where('loggable_type', 'PjctAddendum')->where('action', 'create')->count())->toBe(1);
    $update = SystemLog::where('loggable_type', 'PjctMain')->where('action', 'update')->latest('id')->first();
    expect($update->properties['changed'])->toEqualCanonicalizing(['pjct_value', 'pjct_coend_m']);

    // Addendum kedua: nomor urut lanjut, nilai "sebelum" = hasil addendum pertama; client ikut berubah.
    $this->actingAs($this->superadmin)->post(route('monitoring.addenda.store', $this->project->id), addendumPayload([
        'number' => 'A.0121/2', 'reasons' => ['IDENTITAS', 'BAYAR'], 'new_client' => 'INJOURNEY AIRPORTS',
        'notes' => ['IDENTITAS' => 'Perubahan nama badan usaha', 'BAYAR' => 'Termin bulanan jadi triwulan'],
        'document' => UploadedFile::fake()->create('addendum-1.pdf', 80, 'application/pdf'),
    ]))->assertSessionHasNoErrors();

    $second = PjctAddendum::where('seq', 2)->sole();
    expect($second->changes)->toBe(['pjct_client' => ['before' => 'INJOURNEY', 'after' => 'INJOURNEY AIRPORTS']])
        ->and($this->project->fresh()->pjct_client)->toBe('INJOURNEY AIRPORTS')
        ->and(PjctDoc::findOrFail($second->doc_id)->doc_filename)->toBe('addendum-1 (2).pdf');

    $this->actingAs($this->superadmin)->get(route('monitoring.addenda.index', $this->project->id))
        ->assertOk()
        ->assertSeeInOrder(['Addendum 2', 'A.0121/2', 'Addendum 1', 'PJJ.INJ.11/23/02/2023/A.0121'])
        ->assertSee('Tambah 20 unit laptop untuk kantor cabang')
        ->assertSee('Rp 1.200.000.000');

    $this->actingAs($this->superadmin)->get(route('monitoring.index'))
        ->assertOk()->assertSee('2× addendum');
});

test('validasi: alasan wajib, nilai/tanggal baru wajib & harus berubah, keterangan wajib, dokumen PDF', function () {
    $post = fn (array $o) => $this->actingAs($this->superadmin)->post(route('monitoring.addenda.store', $this->project->id), addendumPayload($o));

    $post(['reasons' => []])->assertSessionHasErrors('reasons');
    $post(['reasons' => ['XYZ']])->assertSessionHasErrors('reasons.0');
    $post(['reasons' => ['NILAI'], 'new_value' => null])->assertSessionHasErrors('new_value');
    $post(['reasons' => ['NILAI'], 'new_value' => 1_000_000_000])->assertSessionHasErrors('new_value');
    $post(['reasons' => ['WAKTU'], 'new_end' => null])->assertSessionHasErrors('new_end');
    $post(['reasons' => ['WAKTU'], 'new_end' => '2026-08-01'])->assertSessionHasErrors('new_end');
    $post(['reasons' => ['SPEK'], 'notes' => []])->assertSessionHasErrors('notes.SPEK');
    $post(['reasons' => ['NILAI'], 'notes' => []])->assertSessionDoesntHaveErrors('notes.NILAI');
    $post(['number' => '', 'addendum_date' => ''])->assertSessionHasErrors(['number', 'addendum_date']);
    $post(['document' => UploadedFile::fake()->create('addendum.docx', 50)])->assertSessionHasErrors('document');
    $post(['document' => null])->assertSessionHasErrors('document');

    expect(PjctAddendum::count())->toBe(1) // hanya kasus NILAI tanpa keterangan yang lolos
        ->and(Storage::disk('docfile')->allFiles())->toHaveCount(1);
});

test('lihat: role Project Main di divisinya; catat: superadmin & admin E&T Commercial di divisinya', function () {
    $index = fn (User $u, PjctMain $p) => $this->actingAs($u)->get(route('monitoring.addenda.index', $p->id));
    $store = fn (User $u, PjctMain $p) => $this->actingAs($u)->post(route('monitoring.addenda.store', $p->id), addendumPayload(['reasons' => ['LAINNYA'], 'notes' => ['LAINNYA' => 'Klausul force majeure']]));

    // Hanya-lihat: tanpa form, POST ditolak.
    foreach ([User::factory()->vip()->create(), User::factory()->qhsse()->create(), User::factory()->agent()->create(['user_unit' => 'Technology Operation & Maintenance'])] as $viewer) {
        $index($viewer, $this->project)->assertOk()->assertDontSee('Catat addendum baru');
        $store($viewer, $this->project)->assertForbidden();
    }

    // Admin di luar E&T Commercial: lihat boleh, catat tidak.
    $adminOps = User::factory()->supervisor()->create(['user_div' => 'Equipment & Technology Operation & Maintenance', 'user_unit' => 'Technology Operation & Maintenance']);
    $index($adminOps, $this->project)->assertOk()->assertDontSee('Catat addendum baru');
    $store($adminOps, $this->project)->assertForbidden();

    // Teknisi & Finance tidak membuka Project Main.
    foreach ([User::factory()->client()->create(), User::factory()->create(['user_role' => 'fin'])] as $other) {
        $index($other, $this->project)->assertForbidden();
        $store($other, $this->project)->assertForbidden();
    }

    // Admin Commercial TC: project TC boleh, project EQ (di luar divisinya) tidak.
    $index($this->commercialTc, $this->project)->assertOk()->assertSee('Catat addendum baru');
    $store($this->commercialTc, $this->project)->assertSessionHasNoErrors();
    $index($this->commercialTc, $this->projectEq)->assertForbidden();
    $store($this->commercialTc, $this->projectEq)->assertForbidden();

    expect(PjctAddendum::pluck('pjct_id')->all())->toBe([$this->project->id]);
});
