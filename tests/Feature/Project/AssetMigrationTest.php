<?php

use App\Models\AstMain;
use App\Models\AstMigration;
use App\Models\PjctMain;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Project → Migrasi Aset: kontrak project habis → aset dipindah ke project baru. Pengelolanya sama
 * dengan import aset (superadmin + admin divisi E&T O&M, non-superadmin hanya project divisinya),
 * aset di ticket aktif tidak ikut, dan setiap batch tercatat di ast_migrations + items + system_logs.
 */

function migAsset(PjctMain $project, string $serial, array $extra = []): AstMain
{
    return AstMain::create($extra + [
        'ast_type' => 'Laptop', 'ast_brand' => 'Dell', 'ast_brandmodel' => 'Latitude 3440',
        'ast_serial' => $serial, 'ast_cond' => 'Good', 'ast_stat' => 'Aktif', 'ast_pjctid' => $project->id,
        'ast_username' => 'Budi', 'ast_userreg' => 'Jawa', 'ast_userloc' => 'CGK', 'ast_userlocdet' => 'Lt 2',
    ]);
}

beforeEach(function () {
    $tc = ['pjct_client' => 'APINDO', 'pjct_type' => 'RENT', 'pjct_div' => 'TC'];
    $this->old = PjctMain::create($tc + ['pjct_name' => 'Sewa Komputer 2024', 'pjct_status' => 'END']);
    $this->new = PjctMain::create($tc + ['pjct_name' => 'Sewa Komputer 2026', 'pjct_status' => 'OG']);
    $this->ended = PjctMain::create($tc + ['pjct_name' => 'Seat Management lama', 'pjct_status' => 'END']);
    $this->eq = PjctMain::create(['pjct_client' => 'GAPURA', 'pjct_type' => 'RENT', 'pjct_div' => 'EQ', 'pjct_name' => 'Sewa GSE', 'pjct_status' => 'OG']);

    $this->a1 = migAsset($this->old, 'SN-1');
    $this->a2 = migAsset($this->old, 'SN-2', ['ast_stat' => 'Back Up']);
    $this->busy = migAsset($this->old, 'SN-3', ['ast_cond' => 'Bad', 'ast_last_ticket_id' => 999]);
    $this->gse = migAsset($this->eq, 'GSE-1', ['ast_type' => 'Tractor']);

    $this->superadmin = User::factory()->admin()->create();
    $this->adminEt = User::factory()->supervisor()->create([
        'user_unit' => 'Technology Operation & Maintenance',
        'user_div' => 'Equipment & Technology Operation & Maintenance',
    ]);
});

function postMigration($test, User $user, array $data)
{
    return $test->actingAs($user)->post(route('project.assets.migration.store'), $data);
}

test('superadmin memindah aset terpilih dari project END ke project baru, riwayat & log tercatat', function () {
    $this->actingAs($this->superadmin)->get(route('project.assets.migration.index', ['from' => $this->old->id]))
        ->assertOk()
        ->assertSee($this->a1->ast_serial)
        ->assertSee('Ticket aktif');

    $response = postMigration($this, $this->superadmin, [
        'from' => $this->old->id, 'to' => $this->new->id,
        'ast_ids' => [$this->a1->id, $this->a2->id], 'note' => 'Kontrak 2024 berakhir',
    ]);

    $migration = AstMigration::firstOrFail();
    $response->assertSessionHasNoErrors()->assertRedirect(route('project.assets.migration.show', $migration));

    expect($this->a1->fresh()->ast_pjctid)->toBe($this->new->id)
        ->and($this->a2->fresh()->ast_pjctid)->toBe($this->new->id)
        ->and($this->a2->fresh()->ast_stat)->toBe('Back Up')          // status tidak diubah
        ->and($this->a1->fresh()->ast_userloc)->toBe('CGK')           // lokasi tidak dikosongkan
        ->and($this->busy->fresh()->ast_pjctid)->toBe($this->old->id) // tidak dipilih → tetap
        ->and($migration->only(['from_pjct_id', 'to_pjct_id', 'user_id', 'asset_count', 'stat_after', 'cleared_location', 'note']))
        ->toBe([
            'from_pjct_id' => $this->old->id, 'to_pjct_id' => $this->new->id, 'user_id' => $this->superadmin->id,
            'asset_count' => 2, 'stat_after' => null, 'cleared_location' => false, 'note' => 'Kontrak 2024 berakhir',
        ])
        ->and($migration->items->pluck('ast_id')->sort()->values()->all())->toBe(collect([$this->a1->id, $this->a2->id])->sort()->values()->all());

    $log = SystemLog::where('action', 'migrate')->sole();
    expect($log->loggable_type)->toBe('AstMigration')
        ->and($log->loggable_id)->toBe((string) $migration->id)
        ->and($log->properties['ids'])->toHaveCount(2);

    // Massal, tanpa log `update` per aset.
    expect(SystemLog::where('loggable_type', 'AstMain')->where('action', 'update')->count())->toBe(0);

    $this->actingAs($this->superadmin)->get(route('project.assets.migration.show', $migration))
        ->assertOk()->assertSee('SN-1')->assertSee('Kontrak 2024 berakhir');
    $this->actingAs($this->superadmin)->get(route('project.assets.migration.index'))
        ->assertOk()->assertSee(route('project.assets.migration.show', $migration));
});

test('opsi status baru & kosongkan lokasi diterapkan, nilai lama tersimpan di riwayat', function () {
    postMigration($this, $this->superadmin, [
        'from' => $this->old->id, 'to' => $this->new->id, 'ast_ids' => [$this->a1->id],
        'stat_after' => 'Back Up', 'clear_location' => '1',
    ])->assertSessionHasNoErrors();

    $a1 = $this->a1->fresh();
    expect($a1->only(['ast_pjctid', 'ast_stat', 'ast_username', 'ast_userreg', 'ast_userloc', 'ast_userlocdet']))->toBe([
        'ast_pjctid' => $this->new->id, 'ast_stat' => 'Back Up',
        'ast_username' => '', 'ast_userreg' => '', 'ast_userloc' => '', 'ast_userlocdet' => '',
    ]);

    $item = AstMigration::sole()->items->sole();
    expect($item->before)->toBe([
        'ast_stat' => 'Aktif', 'ast_username' => 'Budi', 'ast_userreg' => 'Jawa', 'ast_userloc' => 'CGK', 'ast_userlocdet' => 'Lt 2',
    ]);
});

test('aset di ticket aktif menggagalkan seluruh batch', function () {
    postMigration($this, $this->superadmin, [
        'from' => $this->old->id, 'to' => $this->new->id, 'ast_ids' => [$this->a1->id, $this->busy->id],
    ])->assertSessionHasErrors('ast_ids');

    expect($this->a1->fresh()->ast_pjctid)->toBe($this->old->id)
        ->and($this->busy->fresh()->ast_pjctid)->toBe($this->old->id)
        ->and(AstMigration::count())->toBe(0)
        ->and(SystemLog::where('action', 'migrate')->count())->toBe(0);
});

test('tujuan harus berbeda, belum END, dan aset harus milik project asal', function () {
    postMigration($this, $this->superadmin, ['from' => $this->old->id, 'to' => $this->old->id, 'ast_ids' => [$this->a1->id]])
        ->assertSessionHasErrors('to');
    postMigration($this, $this->superadmin, ['from' => $this->old->id, 'to' => $this->ended->id, 'ast_ids' => [$this->a1->id]])
        ->assertSessionHasErrors('to');
    postMigration($this, $this->superadmin, ['from' => $this->old->id, 'to' => $this->new->id, 'ast_ids' => [$this->gse->id]])
        ->assertSessionHasErrors('ast_ids');
    postMigration($this, $this->superadmin, ['from' => $this->old->id, 'to' => $this->new->id, 'ast_ids' => []])
        ->assertSessionHasErrors('ast_ids');
    postMigration($this, $this->superadmin, ['from' => $this->old->id, 'to' => $this->new->id, 'ast_ids' => [$this->a1->id], 'stat_after' => 'Rusak'])
        ->assertSessionHasErrors('stat_after');

    // Pilihan tujuan di halaman tidak memuat project END maupun project asal.
    $targets = $this->actingAs($this->superadmin)
        ->get(route('project.assets.migration.index', ['from' => $this->old->id]))
        ->viewData('targets')->pluck('id')->all();
    expect($targets)->toContain($this->new->id, $this->eq->id)
        ->not->toContain($this->old->id, $this->ended->id);

    expect(AstMigration::count())->toBe(0);
});

test('hanya superadmin & admin divisi E&T O&M; selain itu 403', function () {
    $others = [
        User::factory()->supervisor()->create(['user_div' => 'Commercial', 'user_unit' => 'Technology Operation & Maintenance']),
        User::factory()->agent()->create(),
        User::factory()->client()->create(),
        User::factory()->vip()->create(),
        User::factory()->qhsse()->create(),
        User::factory()->create(['user_role' => 'fin']),
    ];

    foreach ($others as $user) {
        $this->actingAs($user)->get(route('project.assets.migration.index'))->assertForbidden();
        postMigration($this, $user, ['from' => $this->old->id, 'to' => $this->new->id, 'ast_ids' => [$this->a1->id]])->assertForbidden();
        $this->actingAs($user)->get(route('project.assets'))->assertOk()->assertDontSee(route('project.assets.migration.index'));
    }

    $this->actingAs($this->adminEt)->get(route('project.assets'))->assertOk()->assertSee(route('project.assets.migration.index'));
    expect($this->a1->fresh()->ast_pjctid)->toBe($this->old->id);
});

test('admin E&T O&M dibatasi ke project divisinya', function () {
    $sources = $this->actingAs($this->adminEt)->get(route('project.assets.migration.index'))
        ->assertOk()->viewData('sources')->pluck('id')->all();
    expect($sources)->toBe([$this->old->id]); // project EQ (punya aset) tidak muncul

    $targets = $this->actingAs($this->adminEt)->get(route('project.assets.migration.index', ['from' => $this->old->id]))
        ->viewData('targets')->pluck('id')->all();
    expect($targets)->toBe([$this->new->id]);

    // Tujuan atau asal di luar divisi → 403, tidak ada yang berubah.
    postMigration($this, $this->adminEt, ['from' => $this->old->id, 'to' => $this->eq->id, 'ast_ids' => [$this->a1->id]])->assertForbidden();
    postMigration($this, $this->adminEt, ['from' => $this->eq->id, 'to' => $this->new->id, 'ast_ids' => [$this->gse->id]])->assertForbidden();
    expect(AstMigration::count())->toBe(0);

    // Riwayat migrasi divisi lain tidak terlihat.
    $eqNew = PjctMain::create(['pjct_client' => 'GAPURA', 'pjct_type' => 'RENT', 'pjct_div' => 'EQ', 'pjct_name' => 'Sewa GSE 2026', 'pjct_status' => 'OG']);
    postMigration($this, $this->superadmin, ['from' => $this->eq->id, 'to' => $eqNew->id, 'ast_ids' => [$this->gse->id]])->assertSessionHasNoErrors();
    $eqMigration = AstMigration::sole();

    $this->actingAs($this->adminEt)->get(route('project.assets.migration.show', $eqMigration))->assertForbidden();
    expect($this->actingAs($this->adminEt)->get(route('project.assets.migration.index'))->viewData('history'))->toHaveCount(0);

    postMigration($this, $this->adminEt, ['from' => $this->old->id, 'to' => $this->new->id, 'ast_ids' => [$this->a1->id]])->assertSessionHasNoErrors();
    expect($this->actingAs($this->adminEt)->get(route('project.assets.migration.index'))->viewData('history'))->toHaveCount(1);
});
