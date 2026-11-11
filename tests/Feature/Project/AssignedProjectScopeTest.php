<?php

use App\Models\AstMain;
use App\Models\PjctDoc;
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
 * Teknisi (role user) hanya melihat project tempat NIK-nya (users.user_empid) tercatat di
 * pjct_emp — di Dashboard, Monitoring, dokumen, Asset, Manpower, dan ticketing. Role lain
 * tetap dibatasi per divisi.
 */

function assignEmp(User $user, string $pjctId): PjctEmp
{
    return PjctEmp::create([
        'emp_id' => $user->user_empid, 'emp_name' => $user->user_name, 'emp_level' => 'L7',
        'emp_levname' => 'Teknisi', 'emp_unit' => 'Technology Operation', 'emp_div' => 'CGK',
        'emp_area' => 'Tangerang', 'emp_pjctid' => $pjctId, 'emp_coid' => '',
    ]);
}

beforeEach(function () {
    Storage::fake();
    SlaPolicy::factory()->create(['is_default' => true, 'response_minutes' => 60, 'resolution_minutes' => 480]);

    $tc = ['pjct_client' => 'APINDO', 'pjct_type' => 'RENT', 'pjct_status' => 'OG', 'pjct_div' => 'TC'];
    $this->assigned = PjctMain::create($tc + ['pjct_name' => 'Seat Management Kantor Cabang', 'pjct_contract' => 'PJJ.06.02.03/00/12/2023/0578']);
    $this->other = PjctMain::create($tc + ['pjct_name' => 'CCTV Terminal 3', 'pjct_contract' => 'PJJ.99/OTHER/2025']);

    $this->teknisi = User::factory()->client()->create(['user_unit' => 'Technology Operation & Maintenance', 'user_empid' => '241002002477']);
    $this->rekan = User::factory()->client()->create(['user_unit' => 'Technology Operation & Maintenance', 'user_empid' => '241002002999']);
    $this->adminTc = User::factory()->supervisor()->create(['user_unit' => 'Technology Operation & Maintenance']);

    assignEmp($this->teknisi, $this->assigned->id);
    assignEmp($this->rekan, $this->assigned->id);
    assignEmp($this->rekan, $this->other->id);

    $this->assetAssigned = AstMain::create(['ast_type' => 'Laptop', 'ast_brand' => 'Dell', 'ast_brandmodel' => 'Latitude', 'ast_serial' => 'SN-A', 'ast_cond' => 'Good', 'ast_stat' => 'Aktif', 'ast_pjctid' => $this->assigned->id]);
    $this->assetOther = AstMain::create(['ast_type' => 'Laptop', 'ast_brand' => 'Dell', 'ast_brandmodel' => 'Latitude', 'ast_serial' => 'SN-B', 'ast_cond' => 'Good', 'ast_stat' => 'Aktif', 'ast_pjctid' => $this->other->id]);
});

function ticketPayload(string $pjctId, array $overrides = []): array
{
    return array_merge([
        'pjct_id' => $pjctId, 'issue_type' => 'sistem', 'subject' => 'Aplikasi error',
        'description' => 'Kronologi', 'priority' => 'medium',
        'documents' => [UploadedFile::fake()->create('kronologi.pdf', 20, 'application/pdf')],
    ], $overrides);
}

test('dashboard teknisi berisi aset & issue project penugasannya, Monitoring ditutup', function () {
    $this->actingAs($this->teknisi)->get(route('monitoring.index'))->assertForbidden();

    // Ticket kerusakan → device jadi Bad dan muncul di dashboard.
    $this->actingAs($this->teknisi)->post(route('tickets.store'), ticketPayload($this->assigned->id, [
        'issue_type' => 'kerusakan', 'ast_ids' => [$this->assetAssigned->id],
    ]))->assertSessionHasNoErrors();
    $ticket = Ticket::firstOrFail();

    $dashboard = $this->actingAs($this->teknisi)->get(route('dashboard'))->assertOk()
        ->assertViewIs('dashboard-technician')
        ->assertDontSee('Contract Value');
    expect($dashboard->viewData('projects')->pluck('id')->all())->toBe([$this->assigned->id])
        ->and($dashboard->viewData('assetTotal'))->toBe(1)
        ->and($dashboard->viewData('assetCond')['Bad'])->toBe(1)
        ->and($dashboard->viewData('badAssets')->pluck('id')->all())->toBe([$this->assetAssigned->id])
        ->and($dashboard->viewData('activeTickets')->pluck('id')->all())->toBe([$ticket->id])
        ->and($dashboard->viewData('needsAction'))->toBe(1)
        ->and($dashboard->viewData('ticketCounts')['open'])->toBe(1);

    // Grafik 8 minggu: ticket baru masuk ke minggu ini sebagai "berjalan".
    $weeks = $dashboard->viewData('ticketWeeks');
    expect($weeks)->toHaveCount(8)
        ->and(end($weeks))->toMatchArray(['berjalan' => 1, 'closed' => 0])
        ->and(collect($weeks)->sum(fn ($w) => $w['berjalan'] + $w['closed']))->toBe(1);

    // Role lain tetap dashboard project & Monitoring per divisi.
    $this->actingAs($this->adminTc)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard');
    expect($this->actingAs($this->adminTc)->get(route('monitoring.index'))->viewData('projects')->total())->toBe(2);
});

test('nomor kontrak lama di emp_pjctid (terpotong 20 karakter) tetap dikenali', function () {
    PjctEmp::query()->where('emp_id', $this->teknisi->user_empid)->update(['emp_pjctid' => 'PJJ.06.02.03/00/12/2']);

    expect($this->teknisi->fresh()->assignedProjectIds())->toBe([$this->assigned->id]);
});

test('teknisi tanpa penugasan tidak melihat project apa pun', function () {
    $baru = User::factory()->client()->create(['user_unit' => 'Technology Operation & Maintenance', 'user_empid' => '999']);

    $dashboard = $this->actingAs($baru)->get(route('dashboard'))->assertOk()->assertSee('belum ditugaskan di project mana pun');
    expect($dashboard->viewData('projects'))->toBeEmpty()->and($dashboard->viewData('assetTotal'))->toBe(0);

    $this->actingAs($baru)->get(route('tickets.create'))->assertOk()->assertSee('belum ditugaskan di project mana pun');
    $this->actingAs($baru)->post(route('tickets.store'), ticketPayload($this->assigned->id))->assertSessionHasErrors('pjct_id');
});

test('Asset & Manpower hanya berisi data project penugasan', function () {
    // Teknisi cukup daftar aset — tanpa kartu KPI (Total Asset, Aktif di Project END, ...).
    $assets = $this->actingAs($this->teknisi)->get(route('project.assets'))->assertOk()
        ->assertDontSee('Aktif di Project END')->viewData('assets');
    expect($assets->pluck('id')->all())->toBe([$this->assetAssigned->id]);
    $this->actingAs($this->adminTc)->get(route('project.assets'))->assertOk()->assertSee('Aktif di Project END');

    // Rekan di project yang sama terlihat; penugasan rekan di project lain tidak.
    $employees = $this->actingAs($this->teknisi)->get(route('project.manpower'))->assertOk()->viewData('employees');
    expect($employees->pluck('emp_pjctid')->unique()->values()->all())->toBe([$this->assigned->id])
        ->and($employees->total())->toBe(2);
});

test('dokumen project di luar penugasan ditolak', function () {
    Storage::fake('docfile');
    $make = fn (PjctMain $p) => tap(PjctDoc::create(['doc_pjctid' => $p->id, 'doc_type' => 'Kontrak', 'doc_filename' => 'k.pdf', 'doc_filepath' => $p->id.'/k.pdf']),
        fn () => Storage::disk('docfile')->put($p->id.'/k.pdf', '%PDF-1.4'));

    $this->actingAs($this->teknisi)->get(route('monitoring.docs.show', $make($this->assigned)))->assertOk();
    $this->actingAs($this->teknisi)->get(route('monitoring.docs.show', $make($this->other)))->assertForbidden();
});

test('ticketing: project & device hanya dari penugasan, ticket project lain tidak terlihat', function () {
    // Dropdown & endpoint device
    $options = $this->actingAs($this->teknisi)->get(route('tickets.create'))->assertOk()->viewData('pjctProjects');
    expect($options->pluck('id')->all())->toBe([$this->assigned->id]);
    $this->actingAs($this->teknisi)->getJson(route('tickets.project-assets', ['pjct_id' => $this->assigned->id]))->assertOk()->assertJsonCount(1, 'assets');
    $this->actingAs($this->teknisi)->getJson(route('tickets.project-assets', ['pjct_id' => $this->other->id]))->assertForbidden();

    // Buat ticket: project lain (satu divisi) ditolak di server
    $this->actingAs($this->teknisi)->post(route('tickets.store'), ticketPayload($this->other->id))->assertSessionHasErrors('pjct_id');
    $this->actingAs($this->teknisi)->post(route('tickets.store'), ticketPayload($this->assigned->id))->assertSessionHasNoErrors();

    // Ticket rekan: yang di project bersama terlihat, yang di project lain tidak
    $this->actingAs($this->rekan)->post(route('tickets.store'), ticketPayload($this->assigned->id, ['subject' => 'Rekan - bersama']));
    $this->actingAs($this->rekan)->post(route('tickets.store'), ticketPayload($this->other->id, ['subject' => 'Rekan - lain']));
    $shared = Ticket::where('subject', 'Rekan - bersama')->firstOrFail();
    $foreign = Ticket::where('subject', 'Rekan - lain')->firstOrFail();

    $listed = $this->actingAs($this->teknisi)->get(route('tickets.index'))->viewData('tickets')->pluck('id');
    expect($listed->contains($shared->id))->toBeTrue()->and($listed->contains($foreign->id))->toBeFalse();
    $this->actingAs($this->teknisi)->get(route('tickets.show', $foreign))->assertForbidden();
});

test('ticket buatan sendiri tetap terlihat setelah penugasan dicabut', function () {
    $this->actingAs($this->teknisi)->post(route('tickets.store'), ticketPayload($this->assigned->id))->assertSessionHasNoErrors();
    $ticket = Ticket::firstOrFail();

    PjctEmp::query()->where('emp_id', $this->teknisi->user_empid)->delete();
    $teknisi = $this->teknisi->fresh();

    $this->actingAs($teknisi)->get(route('tickets.show', $ticket))->assertOk();
    expect($this->actingAs($teknisi)->get(route('dashboard'))->viewData('projects'))->toBeEmpty();
});
