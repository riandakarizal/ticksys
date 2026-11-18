<?php

namespace App\Http\Controllers;

use App\Models\PjctEmp;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Daftar akun mandiri untuk karyawan di data manpower (`pjct_emp`). Kunci pendaftarannya nomor
 * karyawan (NIK = `pjct_emp.emp_id`): tidak ada di pjct_emp → tidak bisa daftar; satu NIK satu akun.
 * Email jadi username login. Akun yang dibuat selalu teknisi (role `user`) — aksesnya otomatis
 * dibatasi ke project penugasan NIK itu (User::assignedProjectIds()). Atasan (`user_parid`) diatur
 * admin lewat IAM Role; sampai itu approval tiketnya jatuh ke superadmin.
 */
class RegisterController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register', [
            'completedTicketCount' => Ticket::query()->whereIn('status', ['solved', 'closed'])->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'nik' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:225'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'nik.required' => 'Isi nomor karyawan (NIK).',
            'email.required' => 'Isi email.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Isi password.',
            'password.confirmed' => 'Ulangi password tidak sama.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.letters' => 'Password harus mengandung huruf.',
            'password.numbers' => 'Password harus mengandung angka.',
        ]);

        $nik = trim((string) $request->input('nik'));
        $email = mb_strtolower(trim((string) $request->input('email')));
        $back = fn (array $errors) => back()->withErrors($errors)->onlyInput('nik', 'email');

        // NIK yang dipakai lebih dari satu baris (penugasan di beberapa project) → data diambil dari baris pertama.
        $employee = PjctEmp::query()->where('emp_id', $nik)->orderBy('id')->first();
        if (! $employee) {
            return $back(['nik' => 'Nomor karyawan tidak terdaftar di data manpower. Hubungi admin project Anda.']);
        }

        if (User::query()->where('user_empid', $nik)->exists()) {
            return $back(['nik' => 'Nomor karyawan ini sudah punya akun. Silakan login, atau minta admin mereset password kalau lupa.']);
        }

        if (User::query()->where('user_email', $email)->exists()) {
            return $back(['email' => 'Email ini sudah dipakai akun lain.']);
        }

        // Tercatat ke system_logs (aksi `create`, user_id kosong karena belum login) lewat LogsSystemActivity.
        $user = User::create([
            'user_empid' => $nik,
            'user_name' => trim((string) $employee->emp_name) ?: $nik,
            'user_email' => $email,
            'user_pass' => Hash::make((string) $request->input('password')),
            'user_level' => trim((string) $employee->emp_level) ?: 'L7',
            'user_role' => 'user',
            'user_unit' => trim((string) $employee->emp_unit),
            'user_div' => '-',
            'user_parid' => '',
            'user_status' => 'active',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', "Akun berhasil dibuat. Selamat datang, {$user->user_name}.");
    }
}
