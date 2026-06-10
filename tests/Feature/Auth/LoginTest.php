<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('login');
});

test('halaman login tampil tanpa auth', function () {
    $this->get('/login')->assertOk();
});

test('redirect ke login saat akses halaman yang butuh auth', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/tickets')->assertRedirect('/login');
});

test('login berhasil dengan kredensial yang benar', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('login gagal dengan password salah', function () {
    $user = User::factory()->create(['password' => bcrypt('correct')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login gagal dengan email yang tidak terdaftar', function () {
    $this->post('/login', [
        'email' => 'tidakada@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login throttle setelah 5 percobaan gagal', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);
    }

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong',
    ])->assertStatus(429);
});

test('logout mengakhiri sesi dan redirect ke login', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
