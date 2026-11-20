@extends('layouts.app', ['title' => 'IAM Role — Admin', 'heading' => 'Admin · IAM Role'])

{{-- Admin → IAM Role dalam bahasa Papan Teknisi (DESIGN.md): judul besar, chip role sebagai filter,
     daftar user sebagai baris putih di dalam well abu. --}}
@section('content')
@php
    $ico = fn (string $d, string $cls = 'h-5 w-5') => '<svg class="'.$cls.'" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.$d.'"/></svg>';
    $path = [
        'plus'   => 'M12 4.5v15m7.5-7.5h-15',
        'search' => 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
        'close'  => 'M6 18 18 6M6 6l12 12',
    ];

    $total    = $users->count();

    $roleOrder = ['superadmin', 'admin', 'siteadmin', 'user', 'vip', 'fin', 'qhsse'];
    $roleCount = $users->countBy('user_role');
    $roleTone  = [
        'superadmin' => 'bg-[#eef0fd] text-[#3b45a3]',
        'admin'      => 'bg-[#e7f1f6] text-[#245b73]',
        'siteadmin'  => 'bg-[#e3f1ee] text-[#1f6b55]',
        'user'       => 'bg-[#fde2ea] text-[#9b2f52]',
    ];
    $initials = fn (string $name) => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $me = auth()->id();
@endphp

<div class="-m-4 sm:-m-6 min-h-full bg-white p-4 sm:p-6 lg:p-8">

    {{-- ── Judul + aksi ───────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <h2 class="text-4xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-5xl">{{ $pageTitle }}</h2>
        </div>
        <button type="button" data-open-dialog="user-create-dialog"
                class="inline-flex shrink-0 items-center gap-2 self-start rounded-full bg-linear-to-r from-[#fde2ea] to-[#e3e9fb] px-5 py-3 text-sm font-medium text-slate-800 transition hover:brightness-95">
            {!! $ico($path['plus']) !!} Tambah user
        </button>
    </div>

    {{-- ── Chip role (filter daftar) ──────────────────────────────────────── --}}
    <nav class="-mx-1 mt-7 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Filter user per role" data-role-chips>
        <button type="button" data-role="" aria-pressed="true" class="role-chip inline-flex shrink-0 items-center gap-2 rounded-full px-6 py-3 text-sm transition">
            Semua <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-900">{{ $total }}</span>
        </button>
        @foreach($roleOrder as $role)
            @continue(! $roleCount->has($role))
            <button type="button" data-role="{{ $role }}" aria-pressed="false" class="role-chip inline-flex shrink-0 items-center gap-2 rounded-full px-6 py-3 text-sm transition">
                {{ $users->firstWhere('user_role', $role)->roleLabel() }} <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-900">{{ $roleCount[$role] }}</span>
            </button>
        @endforeach
    </nav>

    {{-- ── Daftar user ────────────────────────────────────────────────────── --}}
    <section class="mt-5 rounded-[28px] bg-[#f5f5f6] p-2">
        <div class="flex flex-col gap-3 px-4 pb-3 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-xl font-semibold text-slate-900">Daftar user <span class="ml-1 text-base font-normal text-slate-500" data-user-count>{{ $total }}</span></h3>
            <label class="relative block sm:w-80">
                <span class="sr-only">Cari user</span>
                <span class="pointer-events-none absolute inset-y-0 left-4 grid place-items-center text-slate-400">{!! $ico($path['search'], 'h-4 w-4') !!}</span>
                <input type="search" data-user-search placeholder="Cari nama, NIK, email, unit…" class="w-full rounded-full border border-transparent bg-white py-2.5 pl-10 pr-4 text-sm outline-none transition placeholder:text-slate-400 focus:border-[#18191d]">
            </label>
        </div>

        {{-- Header kolom (desktop) --}}
        <div class="hidden grid-cols-[minmax(0,1.5fr)_minmax(0,1.3fr)_7.5rem_minmax(0,1.6fr)_5.5rem_10rem] gap-4 px-5 pb-2 text-xs font-medium text-slate-500 lg:grid">
            <span>Nama</span><span>Email</span><span>Role</span><span>Divisi / unit</span><span>Status</span><span class="text-right">Aksi</span>
        </div>

        <ul class="space-y-2" data-user-list>
            @foreach($users as $user)
                <li data-user-row data-role="{{ $user->user_role }}"
                    data-search="{{ mb_strtolower($user->user_name.' '.$user->user_empid.' '.$user->user_email.' '.$user->user_div.' '.$user->user_unit.' '.$user->roleLabel()) }}"
                    class="grid gap-3 rounded-3xl bg-white px-5 py-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1.3fr)_7.5rem_minmax(0,1.6fr)_5.5rem_10rem] lg:items-center lg:gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-xs font-semibold {{ $roleTone[$user->user_role] ?? 'bg-slate-100 text-slate-600' }}" aria-hidden="true">{{ $initials($user->user_name) }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-900" title="{{ $user->user_name }}">{{ $user->user_name }}</p>
                            <p class="text-xs text-slate-500">{{ $user->user_level ?: '-' }} · NIK {{ $user->user_empid ?: '-' }}@if($user->id === $me) · <span class="font-medium text-slate-700">akun Anda</span>@endif</p>
                        </div>
                    </div>
                    <p class="truncate text-sm text-slate-700" title="{{ $user->user_email }}">{{ $user->user_email ?: '-' }}</p>
                    <span><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $roleTone[$user->user_role] ?? 'bg-slate-100 text-slate-600' }}">{{ $user->roleLabel() }}</span></span>
                    <div class="min-w-0 text-sm">
                        <p class="truncate text-slate-700" title="{{ $user->user_div }}">{{ $user->user_div ?: '-' }}</p>
                        <p class="truncate text-xs text-slate-500" title="{{ $user->user_unit }}">{{ $user->user_unit ?: '-' }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-sm {{ $user->user_status === 'active' ? 'text-emerald-700' : 'text-slate-500' }}">
                        <span class="h-2 w-2 rounded-full {{ $user->user_status === 'active' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                        {{ $user->user_status === 'active' ? 'Aktif' : 'Nonaktif' }}
                    </span>
                    <div class="flex gap-2 lg:justify-end">
                        <button type="button" data-open-dialog="user-edit-{{ $user->id }}" class="rounded-full bg-slate-100 px-4 py-2 text-sm font-medium text-slate-800 transition hover:bg-slate-200" aria-label="Edit {{ $user->user_name }}">Edit</button>
                        @unless($user->id === $me)
                            <button type="button" data-open-dialog="user-delete-{{ $user->id }}" class="rounded-full px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50" aria-label="Hapus {{ $user->user_name }}">Hapus</button>
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>
        <p data-user-empty hidden class="mt-2 rounded-3xl bg-white py-10 text-center text-sm text-slate-500">Tidak ada user yang cocok dengan filter.</p>
    </section>
</div>

{{-- ── Dialog tambah ─────────────────────────────────────────────────────── --}}
<dialog id="user-create-dialog" class="max-w-3xl rounded-[28px]">
    <form method="POST" action="{{ route('admin.users.store') }}" data-ajax-form class="flex max-h-[calc(100vh-2rem)] flex-col">
        @csrf
        <div class="flex items-start justify-between gap-3 px-7 pb-2 pt-7">
            <div>
                <h3 class="text-2xl font-semibold text-slate-900">Tambah user</h3>
                <p class="mt-1 text-sm text-slate-500">Akun langsung bisa login dengan email dan password ini.</p>
            </div>
            <button type="button" data-close-dialog aria-label="Tutup" class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200">{!! $ico($path['close']) !!}</button>
        </div>
        <div class="overflow-y-auto px-7 py-5">
            @include('admin._user_fields', ['u' => null])
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-7 py-5">
            <button type="button" data-close-dialog class="rounded-full bg-slate-100 px-5 py-3 text-sm font-medium text-slate-800 transition hover:bg-slate-200">Batal</button>
            <button type="submit" class="rounded-full bg-[#18191d] px-6 py-3 text-sm font-medium text-white transition hover:bg-black">Simpan user</button>
        </div>
    </form>
</dialog>

{{-- ── Dialog edit & hapus ───────────────────────────────────────────────── --}}
@foreach($users as $user)
    <dialog id="user-edit-{{ $user->id }}" class="max-w-3xl rounded-[28px]">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" data-ajax-form class="flex max-h-[calc(100vh-2rem)] flex-col">
            @csrf
            @method('PATCH')
            <div class="flex items-start justify-between gap-3 px-7 pb-2 pt-7">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full text-sm font-semibold {{ $roleTone[$user->user_role] ?? 'bg-slate-100 text-slate-600' }}" aria-hidden="true">{{ $initials($user->user_name) }}</span>
                    <div class="min-w-0">
                        <h3 class="truncate text-2xl font-semibold text-slate-900">{{ $user->user_name }}</h3>
                        <p class="text-sm text-slate-500">{{ $user->roleLabel() }} · {{ $user->user_email }}</p>
                    </div>
                </div>
                <button type="button" data-close-dialog aria-label="Tutup" class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200">{!! $ico($path['close']) !!}</button>
            </div>
            <div class="overflow-y-auto px-7 py-5">
                @include('admin._user_fields', ['u' => $user])
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-7 py-5">
                <button type="button" data-close-dialog class="rounded-full bg-slate-100 px-5 py-3 text-sm font-medium text-slate-800 transition hover:bg-slate-200">Batal</button>
                <button type="submit" class="rounded-full bg-[#18191d] px-6 py-3 text-sm font-medium text-white transition hover:bg-black">Simpan perubahan</button>
            </div>
        </form>
    </dialog>

    @unless($user->id === $me)
        <dialog id="user-delete-{{ $user->id }}" class="max-w-md rounded-[28px]">
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-ajax-form class="p-7">
                @csrf
                @method('DELETE')
                <h3 class="text-2xl font-semibold text-slate-900">Hapus {{ $user->user_name }}?</h3>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    Akun <span class="font-medium text-slate-900">{{ $user->user_email }}</span> dihapus dari daftar user dan semua penugasan project.
                    Riwayat ticket tetap disimpan. Tindakan ini tidak bisa dibatalkan.
                </p>
                <div class="mt-7 flex justify-end gap-2">
                    <button type="button" data-close-dialog class="rounded-full bg-slate-100 px-5 py-3 text-sm font-medium text-slate-800 transition hover:bg-slate-200">Batal</button>
                    <button type="submit" class="rounded-full bg-rose-600 px-6 py-3 text-sm font-medium text-white transition hover:bg-rose-700">Hapus user</button>
                </div>
            </form>
        </dialog>
    @endunless
@endforeach
@endsection

@push('scripts')
<script>
// Filter daftar user: chip role + kotak cari, di browser (daftar user kecil, semua sudah dimuat).
(function () {
    const rows   = [...document.querySelectorAll('[data-user-row]')];
    const chips  = [...document.querySelectorAll('[data-role-chips] [data-role]')];
    const search = document.querySelector('[data-user-search]');
    const count  = document.querySelector('[data-user-count]');
    const empty  = document.querySelector('[data-user-empty]');
    let role = '';

    function apply() {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        rows.forEach(function (row) {
            const ok = (!role || row.dataset.role === role) && (!q || row.dataset.search.includes(q));
            row.hidden = !ok;
            if (ok) shown++;
        });
        count.textContent = shown;
        empty.hidden = shown > 0;
        chips.forEach(function (chip) { chip.setAttribute('aria-pressed', String(chip.dataset.role === role)); });
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () { role = chip.dataset.role; apply(); });
    });
    search.addEventListener('input', apply);
    apply();
})();
</script>
@endpush
