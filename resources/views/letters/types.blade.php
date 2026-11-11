@extends('layouts.app', ['title' => 'Jenis Surat', 'heading' => 'Surat — Jenis Surat'])

@php
    $oldDialog = old('_dialog');
@endphp

@section('content')

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <p class="max-w-3xl text-xs text-slate-500">
        Format nomor: <span class="font-mono font-semibold text-slate-700">IASS/{unit}/{kode}/{bulan romawi}/{tahun}/{urut}</span>.
        Urutan dihitung per <b>kode</b> dan mulai dari 001 tiap tahun — jenis surat yang kodenya sama berbagi urutan,
        jadi nomornya tidak bisa dobel. Mengubah kode hanya berlaku untuk nomor berikutnya.
    </p>
    <button type="button" class="btn-primary rounded-xl" data-open-type>+ Tambah Jenis Surat</button>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full text-xs">
        <thead class="border-b border-slate-100 text-left text-slate-400">
            <tr>
                <th class="px-4 py-2 font-medium">Jenis Surat</th>
                <th class="px-3 py-2 font-medium">Kode</th>
                <th class="px-3 py-2 font-medium">Unit</th>
                <th class="px-3 py-2 font-medium">Contoh nomor berikutnya ({{ $year }})</th>
                <th class="px-3 py-2 text-right font-medium">Jumlah nomor</th>
                <th class="px-3 py-2 font-medium">Status</th>
                <th class="px-3 py-2 text-right font-medium">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($types as $t)
            @php
                $next = ($lastSeq[$t->code] ?? 0) + 1;
                $shared = ($sharedCodes[$t->code] ?? collect())->reject(fn ($n) => $n === $t->name);
            @endphp
            <tr class="{{ $t->is_active ? 'hover:bg-slate-50/60' : 'bg-slate-50 text-slate-400' }}">
                <td class="px-4 py-2 font-semibold {{ $t->is_active ? 'text-slate-800' : '' }}">
                    {{ $t->name }}
                    @if($shared->isNotEmpty())
                        <span class="mt-0.5 block text-[10px] font-normal text-slate-400">Berbagi urutan dengan: {{ $shared->implode(', ') }}</span>
                    @endif
                </td>
                <td class="px-3 py-2 font-mono font-bold">{{ $t->code }}</td>
                <td class="px-3 py-2 font-mono">{{ $t->unit_code }}</td>
                <td class="px-3 py-2 font-mono text-slate-500">{{ \App\Models\Letter::formatNumber($t->unit_code, $t->code, (int) now()->month, $year, $next) }}</td>
                <td class="px-3 py-2 text-right">
                    <a href="{{ route('letters.index', ['type' => $t->id]) }}" class="font-semibold text-teal-700 hover:underline">{{ number_format($t->letters_count) }}</a>
                </td>
                <td class="px-3 py-2">
                    @if($t->is_active)
                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Aktif</span>
                    @else
                        <span class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-500">Nonaktif</span>
                    @endif
                </td>
                <td class="px-3 py-2 text-right">
                    <button type="button" class="font-semibold text-slate-600 hover:text-slate-900" data-open-type
                        data-type="{{ json_encode(['id' => $t->id, 'name' => $t->name, 'code' => $t->code, 'unit_code' => $t->unit_code, 'is_active' => $t->is_active]) }}">Edit</button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<dialog id="letter-type-dialog" class="max-w-lg">
    <form method="POST" class="m-0" data-type-form
          action="{{ $oldDialog === 'type-edit' ? route('letters.types.update', old('_type')) : route('letters.types.store') }}"
          data-store-action="{{ route('letters.types.store') }}"
          data-update-template="{{ route('letters.types.update', '__ID__') }}">
        @csrf
        <input type="hidden" name="_method" value="{{ $oldDialog === 'type-edit' ? 'PATCH' : 'POST' }}">
        <input type="hidden" name="_dialog" value="{{ $oldDialog === 'type-edit' ? 'type-edit' : 'type-create' }}">
        <input type="hidden" name="_type" value="{{ old('_type') }}">
        <div class="border-b border-slate-200 px-6 py-4">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Surat</p>
            <h2 class="mt-1 text-xl font-black text-slate-900" data-type-title>{{ $oldDialog === 'type-edit' ? 'Edit Jenis Surat' : 'Tambah Jenis Surat' }}</h2>
        </div>
        <div class="space-y-3 px-6 py-4">
            <div>
                <label class="label">Nama Jenis Surat <span class="text-rose-500">*</span></label>
                <input class="field" name="name" maxlength="100" required value="{{ old('name') }}" placeholder="mis. BA Serah Terima (BAST)">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Kode <span class="text-rose-500">*</span></label>
                    <input class="field font-mono uppercase" name="code" maxlength="20" required value="{{ old('code') }}" placeholder="mis. BAST">
                </div>
                <div>
                    <label class="label">Unit <span class="text-rose-500">*</span></label>
                    <input class="field font-mono uppercase" name="unit_code" maxlength="10" required value="{{ old('unit_code', 'DEO') }}">
                </div>
            </div>
            <label class="hidden items-center gap-2 text-sm text-slate-600" data-type-active>
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                Aktif (muncul di pilihan saat membuat nomor)
            </label>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
            <button class="btn-soft" type="button" data-close-dialog>Batal</button>
            <button class="btn-primary" type="submit">Simpan</button>
        </div>
    </form>
</dialog>

@endsection

@push('scripts')
<script>
(function () {
    const dialog = document.getElementById('letter-type-dialog');
    const form = dialog.querySelector('[data-type-form]');
    const active = form.querySelector('[data-type-active]');

    function open(data) {
        const editing = !!data;
        form.action = editing ? form.dataset.updateTemplate.replace('__ID__', data.id) : form.dataset.storeAction;
        form.querySelector('[name=_method]').value = editing ? 'PATCH' : 'POST';
        form.querySelector('[name=_dialog]').value = editing ? 'type-edit' : 'type-create';
        form.querySelector('[name=_type]').value = editing ? data.id : '';
        form.querySelector('[data-type-title]').textContent = editing ? 'Edit Jenis Surat' : 'Tambah Jenis Surat';
        form.querySelector('[name=name]').value = editing ? data.name : '';
        form.querySelector('[name=code]').value = editing ? data.code : '';
        form.querySelector('[name=unit_code]').value = editing ? data.unit_code : 'DEO';
        form.querySelector('[name=is_active]').checked = editing ? !!data.is_active : true;
        active.classList.toggle('hidden', !editing);
        active.classList.toggle('flex', editing);
        dialog.showModal();
    }

    document.querySelectorAll('[data-open-type]').forEach((btn) => {
        btn.addEventListener('click', () => open(btn.dataset.type ? JSON.parse(btn.dataset.type) : null));
    });

    @if($oldDialog === 'type-edit' || $oldDialog === 'type-create')
        active.classList.toggle('hidden', @json($oldDialog !== 'type-edit'));
        active.classList.toggle('flex', @json($oldDialog === 'type-edit'));
        dialog.showModal();
    @endif
})();
</script>
@endpush
