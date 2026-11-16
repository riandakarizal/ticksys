@extends('layouts.app', ['title' => 'Ganti Password', 'heading' => 'Ganti Password'])

@section('content')

<div class="max-w-xl">
    <div class="flex items-center gap-3 mb-4 px-4 py-3 bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="h-10 w-10 rounded-full bg-brand-600 flex items-center justify-center text-white text-sm font-black uppercase shrink-0">
            {{ substr($user->user_name, 0, 1) }}
        </div>
        <div class="min-w-0">
            <p class="text-sm font-bold text-slate-800 truncate">{{ $user->user_name }}</p>
            <p class="text-[11px] text-slate-400 truncate">{{ $user->user_email }} · {{ $user->roleLabel() }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('account.password.update') }}" id="pw-form"
          class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
        @csrf
        @method('PUT')

        @foreach([
            ['current_password', 'Password saat ini', 'current-password'],
            ['password', 'Password baru', 'new-password'],
            ['password_confirmation', 'Ulangi password baru', 'new-password'],
        ] as [$name, $label, $autocomplete])
            <div>
                <label class="label" for="pw-{{ $name }}">{{ $label }}</label>
                <div class="relative">
                    <input class="field pr-16 @error($name) border-red-400 @enderror" type="password"
                           name="{{ $name }}" id="pw-{{ $name }}" autocomplete="{{ $autocomplete }}" required
                           @if($name === 'current_password') autofocus @endif>
                    <button type="button" data-pw-toggle="pw-{{ $name }}"
                            class="absolute inset-y-0 right-0 px-3 text-[11px] font-semibold text-slate-400 hover:text-slate-600">Lihat</button>
                </div>
                @error($name)
                    <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <ul class="text-[11px] text-slate-500 space-y-0.5" id="pw-rules">
            <li data-rule="len">• Minimal 8 karakter</li>
            <li data-rule="letter">• Mengandung huruf</li>
            <li data-rule="number">• Mengandung angka</li>
            <li data-rule="match">• Sama dengan kolom "Ulangi password baru"</li>
        </ul>

        <p class="text-[11px] text-slate-400">Setelah diganti, perangkat lain yang sedang login dengan akun ini akan keluar otomatis.</p>

        <div class="flex justify-end gap-2 pt-1">
            <a href="{{ route('dashboard') }}" class="btn-soft">Batal</a>
            <button type="submit" class="btn-primary" id="pw-submit">Simpan password</button>
        </div>
    </form>
</div>

<script>
(function () {
    document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.dataset.pwToggle);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Sembunyikan' : 'Lihat';
        });
    });

    const pw = document.getElementById('pw-password');
    const confirm = document.getElementById('pw-password_confirmation');
    const rules = {
        len: (v) => v.length >= 8,
        letter: (v) => /\p{L}/u.test(v),
        number: (v) => /\d/.test(v),
        match: (v) => v !== '' && v === confirm.value,
    };

    function refresh() {
        Object.entries(rules).forEach(function ([key, test]) {
            const li = document.querySelector('#pw-rules [data-rule="' + key + '"]');
            const ok = test(pw.value);
            li.classList.toggle('text-green-600', ok);
            li.classList.toggle('text-slate-500', !ok);
        });
    }

    pw.addEventListener('input', refresh);
    confirm.addEventListener('input', refresh);

    document.getElementById('pw-form').addEventListener('submit', function () {
        const btn = document.getElementById('pw-submit');
        btn.disabled = true;
        btn.textContent = 'Menyimpan…';
    });
})();
</script>

@endsection
