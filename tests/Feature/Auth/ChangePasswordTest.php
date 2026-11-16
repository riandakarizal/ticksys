<?php

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Ganti password sendiri (sidebar → ikon kunci): semua role, wajib password saat ini,
 * password baru min 8 karakter berisi huruf + angka, berbeda dari yang lama.
 */

function changePassword($test, User $user, array $data)
{
    return $test->actingAs($user)
        ->from(route('account.password.edit'))
        ->put(route('account.password.update'), $data);
}

test('semua role bisa membuka halaman ganti password, tamu diarahkan ke login', function () {
    $users = [
        User::factory()->admin()->create(),
        User::factory()->supervisor()->create(),
        User::factory()->agent()->create(),
        User::factory()->client()->create(),
        User::factory()->vip()->create(),
        User::factory()->qhsse()->create(),
        User::factory()->create(['user_role' => 'fin']),
    ];

    foreach ($users as $user) {
        $this->actingAs($user)->get(route('account.password.edit'))
            ->assertOk()->assertSee($user->user_email);
        $this->actingAs($user)->get(route('dashboard'))->assertSee(route('account.password.edit'));
    }

    auth()->logout();
    $this->get(route('account.password.edit'))->assertRedirect(route('login'));
});

test('password berhasil diganti: login dengan password baru, password lama ditolak, log tanpa hash', function () {
    $user = User::factory()->client()->create(['user_email' => 'teknisi@ias.id']);

    changePassword($this, $user, [
        'current_password' => 'password',
        'password' => 'Rahasia2026',
        'password_confirmation' => 'Rahasia2026',
    ])->assertSessionHasNoErrors()->assertRedirect(route('account.password.edit'));

    expect(Hash::check('Rahasia2026', $user->fresh()->user_pass))->toBeTrue();

    $log = SystemLog::where('loggable_type', 'User')->where('loggable_id', $user->id)->where('action', 'update')->sole();
    expect($log->properties)->toBe(['changed' => ['user_pass']])
        ->and($log->user_id)->toBe($user->id);

    auth()->logout();
    $this->post(route('login.store'), ['email' => 'teknisi@ias.id', 'password' => 'password'])->assertSessionHasErrors('email');
    $this->post(route('login.store'), ['email' => 'teknisi@ias.id', 'password' => 'Rahasia2026'])->assertRedirect(route('dashboard'));
});

test('password saat ini salah, konfirmasi beda, atau password baru lemah/sama ditolak', function () {
    $user = User::factory()->supervisor()->create();
    $original = $user->user_pass;

    $cases = [
        'current_password' => ['current_password' => 'salah123', 'password' => 'Rahasia2026', 'password_confirmation' => 'Rahasia2026'],
        'password' => ['current_password' => 'password', 'password' => 'Rahasia2026', 'password_confirmation' => 'Rahasia2027'],
    ];
    foreach ($cases as $field => $data) {
        changePassword($this, $user, $data)->assertSessionHasErrors($field)->assertRedirect(route('account.password.edit'));
    }

    foreach (['Rah4', 'rahasiasekali', '1234567890', 'password'] as $weak) {
        changePassword($this, $user, ['current_password' => 'password', 'password' => $weak, 'password_confirmation' => $weak])
            ->assertSessionHasErrors('password');
    }

    expect($user->fresh()->user_pass)->toBe($original);
});

test('sesi akun ini di perangkat lain dihapus, sesi akun lain tidak tersentuh', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->supervisor()->create();
    $other = User::factory()->supervisor()->create();
    $row = fn (string $id, string $userId) => ['id' => $id, 'user_id' => $userId, 'ip_address' => '10.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()];
    DB::table('sessions')->insert([$row('laptop-lain', $user->id), $row('hp', $user->id), $row('punya-orang', $other->id)]);

    changePassword($this, $user, [
        'current_password' => 'password',
        'password' => 'Rahasia2026',
        'password_confirmation' => 'Rahasia2026',
    ])->assertSessionHasNoErrors();

    expect(DB::table('sessions')->whereIn('id', ['laptop-lain', 'hp'])->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'punya-orang')->exists())->toBeTrue();

    // Sesi yang dipakai untuk mengganti password tetap login.
    $this->get(route('account.password.edit'))->assertOk();
});
