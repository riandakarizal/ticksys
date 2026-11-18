<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Ganti password sendiri — semua role yang login. Password orang lain tetap lewat Admin → IAM Role
 * (superadmin). Hash disimpan di `users.user_pass`; log `update` User (kolom `user_pass`, tanpa nilai)
 * ditulis otomatis oleh `LogsSystemActivity`.
 */
class AccountPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.password', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.required' => 'Isi password saat ini.',
            'password.required' => 'Isi password baru.',
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
            'password.different' => 'Password baru harus berbeda dengan password saat ini.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.letters' => 'Password baru harus mengandung huruf.',
            'password.numbers' => 'Password baru harus mengandung angka.',
        ]);

        if (! Hash::check($request->input('current_password'), $user->user_pass ?? '')) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        $user->forceFill(['user_pass' => Hash::make($request->input('password'))])->save();

        // Sesi di perangkat lain ikut keluar; sesi ini tetap login dengan ID baru.
        $request->session()->regenerate();
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return redirect()->route('account.password.edit')
            ->with('success', 'Password berhasil diganti. Perangkat lain yang memakai akun ini perlu login ulang.');
    }
}
