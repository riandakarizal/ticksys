<?php

use App\Models\PjctEmp;
use App\Models\PjctMain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Daftar akun mandiri: hanya NIK yang ada di pjct_emp, satu NIK satu akun, email = username login.
 * Akun baru = teknisi (role user) yang otomatis dibatasi ke project penugasan NIK-nya.
 */

beforeEach(function () {
    $this->project = PjctMain::create([
        'pjct_name' => 'Sewa Komputer Kantor Cabang', 'pjct_client' => 'API', 'pjct_type' => 'RENT', 'pjct_status' => 'OG', 'pjct_div' => 'TC',
    ]);
    PjctEmp::create([
        'emp_id' => '241002002477', 'emp_name' => 'Adityo Nugroho', 'emp_level' => 'L7', 'emp_levname' => 'Teknisi',
        'emp_unit' => 'Technology Operation', 'emp_div' => 'Gedung 600 CGK', 'emp_area' => 'Tangerang',
        'emp_pjctid' => $this->project->id, 'emp_coid' => 'IASS/HR/PKWT/X/2025/00/1118',
    ]);
});

function register($test, array $overrides = [])
{
    return $test->from(route('register'))->post(route('register.store'), array_merge([
        'nik' => '241002002477',
        'email' => 'Adityo.Nugroho@IAS.id',
        'password' => 'Teknisi2026',
        'password_confirmation' => 'Teknisi2026',
    ], $overrides));
}

test('NIK terdaftar di pjct_emp → akun teknisi dibuat, langsung login, bisa login lagi pakai email', function () {
    $this->get(route('login'))->assertOk()->assertSee(route('register'));
    $this->get(route('register'))->assertOk()->assertSee('Nomor karyawan (NIK)');

    register($this)->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

    $user = User::where('user_empid', '241002002477')->sole();
    expect($user->only(['user_name', 'user_email', 'user_role', 'user_level', 'user_unit', 'user_status']))->toBe([
        'user_name' => 'Adityo Nugroho', 'user_email' => 'adityo.nugroho@ias.id', 'user_role' => 'user',
        'user_level' => 'L7', 'user_unit' => 'Technology Operation', 'user_status' => 'active',
    ])
        ->and(Hash::check('Teknisi2026', $user->user_pass))->toBeTrue()
        ->and($user->assignedProjectIds())->toBe([$this->project->id]);
    $this->assertAuthenticatedAs($user);

    $this->get(route('dashboard'))->assertOk()->assertViewIs('dashboard-technician');

    auth()->logout();
    $this->post(route('login.store'), ['email' => 'adityo.nugroho@ias.id', 'password' => 'Teknisi2026'])
        ->assertRedirect(route('dashboard'));
});

test('NIK tidak ada di pjct_emp, NIK sudah punya akun, atau email sudah dipakai → ditolak', function () {
    register($this, ['nik' => '999999999999'])->assertSessionHasErrors('nik')->assertRedirect(route('register'));
    expect(User::count())->toBe(0);
    $this->assertGuest();

    User::factory()->create(['user_email' => 'dipakai@ias.id']);
    register($this, ['email' => 'DIPAKAI@ias.id'])->assertSessionHasErrors('email');

    User::factory()->client()->create(['user_empid' => '241002002477']);
    register($this)->assertSessionHasErrors('nik');

    expect(User::where('user_empid', '241002002477')->count())->toBe(1);
});

test('password lemah / tidak sama dan input kosong ditolak', function () {
    register($this, ['password' => 'pendek1', 'password_confirmation' => 'pendek1'])->assertSessionHasErrors('password');
    register($this, ['password' => 'tanpaangka', 'password_confirmation' => 'tanpaangka'])->assertSessionHasErrors('password');
    register($this, ['password_confirmation' => 'Beda2026'])->assertSessionHasErrors('password');
    register($this, ['nik' => '', 'email' => 'bukan-email'])->assertSessionHasErrors(['nik', 'email']);

    expect(User::count())->toBe(0);
});

test('yang sudah login diarahkan ke dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('register'))->assertRedirect(route('dashboard'));
});
