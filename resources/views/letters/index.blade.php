@extends('layouts.app', ['title' => 'Nomor Surat', 'heading' => 'Surat — Nomor Surat'])

@php
    $romanMonths = \App\Models\Letter::ROMAN_MONTHS;

    $activeCount = collect($filters)->except(['sort', 'dir'])->filter(fn ($v) => $v !== '')->count();

    $sortUrl = function (string $column) use ($filters) {
        $dir = ($filters['sort'] === $column && $filters['dir'] === 'desc') ? 'asc' : 'desc';

        return route('letters.index', array_merge(request()->query(), ['sort' => $column, 'dir' => $dir, 'page' => null]));
    };
    $sortMark = fn (string $column) => $filters['sort'] === $column ? ($filters['dir'] === 'asc' ? '▲' : '▼') : '';

    $activeTypes = $types->where('is_active', true);
    // Dihitung di sini karena @json memecah argumennya per koma.
    $typeMap = $activeTypes->mapWithKeys(fn ($t) => [$t->id => ['code' => $t->code, 'unit' => $t->unit_code]]);
    $romanList = array_values($romanMonths);
    $oldDialog = old('_dialog');
    $isOld = fn (string $dialog) => $oldDialog === $dialog;
@endphp

@section('content')

{{-- ── Hasil buat nomor ─────────────────────────────────────────────── --}}
@if($created->isNotEmpty())
<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">
            {{ $created->count() === 1 ? 'Nomor surat berhasil dibuat' : $created->count().' nomor surat berhasil dibuat' }}
        </p>
        @if($created->count() > 1)
            <button type="button" class="rounded-lg border border-emerald-300 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                data-copy="{{ $created->pluck('number')->implode("\n") }}">Salin semua</button>
        @endif
    </div>
    <div class="mt-2 flex flex-wrap gap-2">
        @foreach($created as $c)
            <button type="button" data-copy="{{ $c->number }}" title="Klik untuk menyalin"
                class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-1.5 font-mono text-sm font-bold text-slate-900 hover:border-emerald-400">
                {{ $c->number }}
                <span class="font-sans text-[10px] font-semibold text-emerald-600" data-copy-label>Salin</span>
            </button>
        @endforeach
    </div>
    <p class="mt-2 text-xs text-emerald-800">{{ $created->first()->type?->name }} · {{ $created->first()->subject }}</p>
</div>
@endif

@unless($hasImported)
<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
    <span class="font-bold">Register lama dari Excel belum diimpor.</span>
    Nomor yang dibuat sekarang bisa bentrok dengan nomor yang sudah terpakai di Excel —
    jalankan <code class="rounded bg-amber-100 px-1">php artisan letters:import</code> lebih dulu.
</div>
@endunless

{{-- ── Ringkasan + tombol ───────────────────────────────────────────── --}}
<div class="mb-4 flex flex-wrap items-stretch gap-3">
    @foreach([
        ['Nomor aktif '.$stats['year'], $stats['year_total'], 'text-slate-900', null],
        ['Bulan ini', $stats['month_total'], 'text-slate-900', null],
        ['Dibatalkan', $stats['cancelled'], 'text-slate-500', route('letters.index', ['status' => 'cancelled'])],
        ['Data impor bertanda', $stats['flagged'], 'text-amber-600', route('letters.index', ['flagged' => 1])],
    ] as [$label, $value, $color, $link])
        <a @if($link) href="{{ $link }}" @endif class="min-w-[140px] rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm {{ $link ? 'hover:border-slate-300' : '' }}">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</p>
            <p class="mt-1 text-2xl font-black {{ $color }}">{{ number_format($value) }}</p>
        </a>
    @endforeach

    <div class="ml-auto flex items-center gap-2">
        <button type="button" class="btn-primary rounded-xl" data-open-letter="create">+ Buat Nomor Surat</button>
    </div>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    {{-- ── Filter ─────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('letters.index') }}" class="border-b border-slate-100 px-4 py-3">
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

        <div class="flex flex-wrap items-end gap-2">
            <div class="min-w-[220px] flex-1">
                <label class="label">Cari</label>
                <input class="field" type="text" name="search" value="{{ $filters['search'] }}" placeholder="Nomor / perihal / tujuan / penandatangan / PIC / ID project...">
            </div>
            <div class="w-56 shrink-0">
                <label class="label">Jenis Surat</label>
                <select class="field" name="type">
                    <option value="">Semua jenis</option>
                    @foreach($types as $t)
                        <option value="{{ $t->id }}" @selected($filters['type'] === (string) $t->id)>{{ $t->name }} ({{ $t->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="w-24 shrink-0">
                <label class="label">Tahun</label>
                <select class="field" name="year">
                    <option value="">Semua</option>
                    @foreach($years as $y)
                        <option value="{{ $y }}" @selected($filters['year'] === (string) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-24 shrink-0">
                <label class="label">Bulan</label>
                <select class="field" name="month">
                    <option value="">Semua</option>
                    @foreach($romanMonths as $num => $roman)
                        <option value="{{ $num }}" @selected($filters['month'] === (string) $num)>{{ $roman }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-28 shrink-0">
                <label class="label">Status</label>
                <select class="field" name="status">
                    <option value="">Semua</option>
                    <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                    <option value="cancelled" @selected($filters['status'] === 'cancelled')>Batal</option>
                </select>
            </div>
            <div class="w-32 shrink-0">
                <label class="label">Sumber</label>
                <select class="field" name="source">
                    <option value="">Semua</option>
                    <option value="app" @selected($filters['source'] === 'app')>PRISM</option>
                    <option value="import" @selected($filters['source'] === 'import')>Impor Excel</option>
                </select>
            </div>
            <div class="w-20 shrink-0">
                <label class="label">Per Hal.</label>
                <select class="field" name="per_page">
                    @foreach($perPageOptions as $pp)
                        <option value="{{ $pp }}" @selected($perPage === $pp)>{{ $pp }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary rounded-xl px-3 py-2 text-xs">Cari</button>
            <a href="{{ route('letters.index') }}" class="btn-soft rounded-xl px-3 py-2 text-xs">Reset</a>
            <a href="{{ route('letters.export', request()->query()) }}" class="btn-soft gap-1.5 rounded-xl px-3 py-2 text-xs"
               title="Unduh .xlsx — sheet Nomor Surat + Ringkasan, mengikuti filter di atas"><span>⬇</span> Export Excel</a>
            <label class="ml-1 inline-flex items-center gap-1.5 text-xs text-slate-500">
                <input type="checkbox" name="flagged" value="1" @checked($filters['flagged'] !== '') onchange="this.form.submit()">
                Hanya data impor bertanda
            </label>
            @if($activeCount)
                <span class="text-[11px] text-slate-400">{{ $activeCount }} filter aktif</span>
            @endif
        </div>
    </form>

    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-4 py-2.5">
        <span class="text-xs text-slate-500">{{ number_format($letters->total()) }} nomor surat</span>
        <span class="text-xs text-slate-400">Halaman {{ $letters->currentPage() }} / {{ max($letters->lastPage(), 1) }}</span>
    </div>

    {{-- ── Tabel ──────────────────────────────────────────────────────── --}}
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="border-b border-slate-100 text-left text-slate-400">
                <tr>
                    <th class="whitespace-nowrap px-4 py-2 font-medium">
                        <a href="{{ $sortUrl('number') }}" class="hover:text-slate-600">Nomor Surat <span class="text-[9px] text-teal-500">{{ $sortMark('number') }}</span></a>
                    </th>
                    <th class="whitespace-nowrap px-3 py-2 font-medium">
                        <a href="{{ $sortUrl('date') }}" class="hover:text-slate-600">Tanggal <span class="text-[9px] text-teal-500">{{ $sortMark('date') }}</span></a>
                    </th>
                    <th class="px-3 py-2 font-medium">Jenis</th>
                    <th class="px-3 py-2 font-medium">Perihal</th>
                    <th class="px-3 py-2 font-medium">Tujuan</th>
                    <th class="px-3 py-2 font-medium">Penandatangan</th>
                    <th class="px-3 py-2 font-medium">PIC</th>
                    <th class="px-3 py-2 font-medium">File</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($letters as $l)
                <tr class="{{ $l->isCancelled() ? 'bg-slate-50 text-slate-400' : 'hover:bg-slate-50/60' }}">
                    <td class="whitespace-nowrap px-4 py-2">
                        <button type="button" data-copy="{{ $l->number }}" title="Klik untuk menyalin"
                            class="font-mono text-[11px] font-bold {{ $l->isCancelled() ? 'text-slate-400 line-through' : 'text-slate-800 hover:text-teal-700' }}">{{ $l->number }}</button>
                        <div class="mt-0.5 flex gap-1">
                            @if($l->isImported())
                                <span class="rounded bg-slate-100 px-1 py-0.5 text-[9px] font-semibold text-slate-500">Impor Excel</span>
                            @endif
                            @if($l->import_note)
                                <span class="cursor-help rounded bg-amber-100 px-1 py-0.5 text-[9px] font-semibold text-amber-700" title="{{ $l->import_note }}">⚠ catatan impor</span>
                            @endif
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ $l->letter_date?->format('d M Y') ?? '-' }}</td>
                    <td class="min-w-[140px] max-w-[190px] px-3 py-2 text-slate-600">{{ $l->type?->name }}</td>
                    <td class="min-w-[220px] max-w-[340px] px-3 py-2 {{ $l->isCancelled() ? '' : 'text-slate-800' }}">
                        <span class="font-semibold">{{ $l->subject ?? '-' }}</span>
                        @if($l->project || $l->pjct_id)
                            <span class="mt-0.5 block text-[10px] text-teal-700">{{ $l->project ? $l->project->id.' — '.$l->project->pjct_name : $l->pjct_id }}</span>
                        @endif
                        @if($l->isCancelled())
                            <span class="mt-0.5 block text-[10px] text-rose-500">Batal: {{ $l->cancel_reason }}</span>
                        @endif
                    </td>
                    <td class="max-w-[180px] px-3 py-2 text-slate-500">{{ $l->recipient ?? '-' }}</td>
                    <td class="max-w-[180px] px-3 py-2 text-slate-500">{{ $l->signer ?? '-' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ $l->pic ?? '-' }}</td>
                    <td class="px-3 py-2">
                        @if($l->file_path)
                            <a href="{{ route('letters.file', $l) }}" target="_blank" class="font-semibold text-teal-700 hover:underline" title="{{ $l->file_name }}">Lihat</a>
                        @else
                            <span class="text-slate-300">-</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                        @if($l->isCancelled())
                            <span class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-500">Batal</span>
                        @else
                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Aktif</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right">
                        @unless($l->isCancelled())
                            <button type="button" class="font-semibold text-slate-600 hover:text-slate-900" data-open-letter="edit" data-id="{{ $l->id }}">Edit</button>
                            <span class="text-slate-300">·</span>
                            <button type="button" class="font-semibold text-teal-700 hover:text-teal-900" data-open-letter="sub" data-id="{{ $l->id }}" title="Buat nomor turunan (.1, .2, ...)">Turunan</button>
                            <span class="text-slate-300">·</span>
                            <button type="button" class="font-semibold text-rose-600 hover:text-rose-800" data-open-letter="cancel" data-id="{{ $l->id }}">Batalkan</button>
                        @endunless
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-12 text-center text-slate-400">Belum ada nomor surat yang cocok dengan filter.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($letters->hasPages())
    <div class="border-t border-slate-100 px-4 py-3">{{ $letters->links() }}</div>
    @endif
</div>

{{-- Saran isian (dipakai semua dialog) --}}
<datalist id="letter-recipients">@foreach($suggest['recipient'] as $v)<option value="{{ $v }}">@endforeach</datalist>
<datalist id="letter-signers">@foreach($suggest['signer'] as $v)<option value="{{ $v }}">@endforeach</datalist>
<datalist id="letter-pics">@foreach($suggest['pic'] as $v)<option value="{{ $v }}">@endforeach</datalist>

{{-- ══ Dialog: Buat nomor ═══════════════════════════════════════════════ --}}
<dialog id="letter-create-dialog" class="max-w-2xl">
    <form method="POST" action="{{ route('letters.store') }}" enctype="multipart/form-data" class="m-0 flex max-h-[calc(100vh-2rem)] flex-col" data-letter-form="create">
        @csrf
        <input type="hidden" name="_dialog" value="create">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-6 py-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Surat</p>
                <h2 class="mt-1 text-xl font-black text-slate-900">Buat Nomor Surat</h2>
            </div>
            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-slate-500 hover:bg-slate-200" data-close-dialog>✕</button>
        </div>
        <div class="space-y-3 overflow-y-auto px-6 py-4">
            <div class="grid gap-3 sm:grid-cols-[1fr_170px_110px]">
                <div>
                    <label class="label">Jenis Surat <span class="text-rose-500">*</span></label>
                    <select class="field" name="letter_type_id" required data-preview-input>
                        <option value="">— Pilih jenis surat —</option>
                        @foreach($activeTypes as $t)
                            <option value="{{ $t->id }}" @selected($isOld('create') && (string) old('letter_type_id') === (string) $t->id)>{{ $t->name }} ({{ $t->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Tanggal Surat <span class="text-rose-500">*</span></label>
                    <input class="field" type="date" name="letter_date" required data-preview-input
                        value="{{ $isOld('create') ? old('letter_date') : now()->toDateString() }}">
                </div>
                <div>
                    <label class="label">Jumlah</label>
                    <input class="field" type="number" name="quantity" min="1" max="{{ \App\Support\LetterNumberService::MAX_BATCH }}" required data-preview-input
                        value="{{ $isOld('create') ? old('quantity', 1) : 1 }}">
                </div>
            </div>
            <div class="rounded-xl border border-teal-100 bg-teal-50/60 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-teal-700">Pratinjau nomor</p>
                <p class="mt-1 font-mono text-base font-black text-slate-900" data-preview>—</p>
                <p class="mt-0.5 text-[11px] text-slate-500">Perkiraan. Nomor pasti diambil saat disimpan — kalau ada yang menyimpan lebih dulu, nomornya bergeser.</p>
            </div>
            @include('letters._detail_fields', ['dialog' => 'create', 'projects' => $projects, 'defaultPic' => auth()->user()->user_name])
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
            <button class="btn-soft" type="button" data-close-dialog>Batal</button>
            <button class="btn-primary" type="submit">Ambil Nomor</button>
        </div>
    </form>
</dialog>

{{-- ══ Dialog: Nomor turunan ═══════════════════════════════════════════ --}}
<dialog id="letter-sub-dialog" class="max-w-2xl">
    <form method="POST" action="{{ $isOld('sub') ? route('letters.sub', old('_letter')) : '#' }}" enctype="multipart/form-data" class="m-0 flex max-h-[calc(100vh-2rem)] flex-col" data-letter-form="sub"
          data-action-template="{{ route('letters.sub', '__ID__') }}">
        @csrf
        <input type="hidden" name="_dialog" value="sub">
        <input type="hidden" name="_letter" value="{{ $isOld('sub') ? old('_letter') : '' }}">
        <input type="hidden" name="_number" value="{{ $isOld('sub') ? old('_number') : '' }}">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-6 py-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Nomor turunan dari</p>
                <h2 class="mt-1 font-mono text-lg font-black text-slate-900" data-show="number">{{ $isOld('sub') ? old('_number') : '' }}</h2>
            </div>
            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-slate-500 hover:bg-slate-200" data-close-dialog>✕</button>
        </div>
        <div class="space-y-3 overflow-y-auto px-6 py-4">
            <div class="grid gap-3 sm:grid-cols-[170px_110px_1fr]">
                <div>
                    <label class="label">Tanggal Surat <span class="text-rose-500">*</span></label>
                    <input class="field" type="date" name="letter_date" required data-preview-input value="{{ $isOld('sub') ? old('letter_date') : '' }}">
                </div>
                <div>
                    <label class="label">Jumlah</label>
                    <input class="field" type="number" name="quantity" min="1" max="{{ \App\Support\LetterNumberService::MAX_BATCH }}" required data-preview-input
                        value="{{ $isOld('sub') ? old('quantity', 1) : 1 }}">
                </div>
                <div class="rounded-xl border border-teal-100 bg-teal-50/60 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-teal-700">Pratinjau</p>
                    <p class="mt-0.5 font-mono text-sm font-black text-slate-900" data-preview>—</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-500">Bulan romawi mengikuti tanggal surat turunan; tahunnya harus sama dengan nomor induk.</p>
            @include('letters._detail_fields', ['dialog' => 'sub', 'projects' => $projects, 'defaultPic' => auth()->user()->user_name])
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
            <button class="btn-soft" type="button" data-close-dialog>Batal</button>
            <button class="btn-primary" type="submit">Ambil Nomor Turunan</button>
        </div>
    </form>
</dialog>

{{-- ══ Dialog: Edit data surat ═════════════════════════════════════════ --}}
<dialog id="letter-edit-dialog" class="max-w-2xl">
    <form method="POST" action="{{ $isOld('edit') ? route('letters.update', old('_letter')) : '#' }}" enctype="multipart/form-data" class="m-0 flex max-h-[calc(100vh-2rem)] flex-col" data-letter-form="edit"
          data-action-template="{{ route('letters.update', '__ID__') }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_dialog" value="edit">
        <input type="hidden" name="_letter" value="{{ $isOld('edit') ? old('_letter') : '' }}">
        <input type="hidden" name="_number" value="{{ $isOld('edit') ? old('_number') : '' }}">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-6 py-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Edit data surat</p>
                <h2 class="mt-1 font-mono text-lg font-black text-slate-900" data-show="number">{{ $isOld('edit') ? old('_number') : '' }}</h2>
                <p class="mt-1 text-xs text-slate-500"><span data-show="type"></span> · <span data-show="date"></span></p>
            </div>
            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-slate-500 hover:bg-slate-200" data-close-dialog>✕</button>
        </div>
        <div class="space-y-3 overflow-y-auto px-6 py-4">
            <p class="rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-500">Jenis, tanggal, dan nomor tidak bisa diubah. Kalau salah, batalkan nomor ini lalu buat nomor baru.</p>
            @include('letters._detail_fields', ['dialog' => 'edit', 'projects' => $projects])
            <label class="hidden items-center gap-2 text-xs text-slate-600" data-remove-file>
                <input type="checkbox" name="remove_file" value="1">
                Hapus file yang sekarang (<span data-show="file_name"></span>)
            </label>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
            <button class="btn-soft" type="button" data-close-dialog>Batal</button>
            <button class="btn-primary" type="submit">Simpan</button>
        </div>
    </form>
</dialog>

{{-- ══ Dialog: Batalkan nomor ══════════════════════════════════════════ --}}
<dialog id="letter-cancel-dialog" class="max-w-lg">
    <form method="POST" action="{{ $isOld('cancel') ? route('letters.cancel', old('_letter')) : '#' }}" class="m-0" data-letter-form="cancel"
          data-action-template="{{ route('letters.cancel', '__ID__') }}">
        @csrf
        <input type="hidden" name="_dialog" value="cancel">
        <input type="hidden" name="_letter" value="{{ $isOld('cancel') ? old('_letter') : '' }}">
        <input type="hidden" name="_number" value="{{ $isOld('cancel') ? old('_number') : '' }}">
        <div class="border-b border-slate-200 px-6 py-4">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Batalkan nomor</p>
            <h2 class="mt-1 font-mono text-lg font-black text-slate-900" data-show="number">{{ $isOld('cancel') ? old('_number') : '' }}</h2>
        </div>
        <div class="space-y-3 px-6 py-4">
            <p class="text-sm text-slate-600">Nomor tidak dihapus — tetap tercatat sebagai <b>Batal</b> beserta alasannya, dan tidak akan dipakai lagi.</p>
            <div>
                <label class="label">Alasan <span class="text-rose-500">*</span></label>
                <input class="field" name="cancel_reason" maxlength="255" required value="{{ $isOld('cancel') ? old('cancel_reason') : '' }}" placeholder="mis. Salah pilih jenis surat">
            </div>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
            <button class="btn-soft" type="button" data-close-dialog>Kembali</button>
            <button class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700" type="submit">Batalkan Nomor</button>
        </div>
    </form>
</dialog>

@endsection

@push('scripts')
<script>
(function () {
    const types = @json($typeMap);
    const nextSeq = @json($nextSeq);
    const rows = @json($rows);
    const roman = @json($romanList);
    const today = @json(now()->toDateString());
    const oldDialog = @json($oldDialog);
    const pad = (n) => String(n).padStart(3, '0');

    const dialogs = {
        create: document.getElementById('letter-create-dialog'),
        sub: document.getElementById('letter-sub-dialog'),
        edit: document.getElementById('letter-edit-dialog'),
        cancel: document.getElementById('letter-cancel-dialog'),
    };
    const formOf = (name) => dialogs[name].querySelector('form');
    let subParent = null;

    // ── Pratinjau nomor ─────────────────────────────────────────────
    function updatePreview(name) {
        const form = formOf(name);
        const out = form.querySelector('[data-preview]');
        const date = form.querySelector('[name=letter_date]').value;
        const qty = Math.max(1, parseInt(form.querySelector('[name=quantity]').value || '1', 10));
        toggleFileForQuantity(form, qty);

        if (!date) { out.textContent = 'Isi tanggal surat'; return; }
        const [y, m] = date.split('-').map(Number);

        if (name === 'create') {
            const t = types[form.querySelector('[name=letter_type_id]').value];
            if (!t) { out.textContent = 'Pilih jenis surat'; return; }
            const first = (nextSeq[y] && nextSeq[y][t.code]) || 1;
            const prefix = `IASS/${t.unit}/${t.code}/${roman[m - 1]}/${y}/`;
            out.textContent = qty > 1 ? `${prefix}${pad(first)} s/d ${pad(first + qty - 1)}` : prefix + pad(first);
            return;
        }

        const p = subParent;
        if (!p) { out.textContent = '—'; return; }
        if (y !== p.year) { out.textContent = `Tahun harus ${p.year}`; return; }
        const prefix = `IASS/${p.unit}/${p.code}/${roman[m - 1]}/${p.year}/${p.seq}.`;
        out.textContent = qty > 1 ? `${prefix}${p.next_sub} s/d .${p.next_sub + qty - 1}` : prefix + p.next_sub;
    }

    function toggleFileForQuantity(form, qty) {
        const input = form.querySelector('[data-field=file]');
        const hint = form.querySelector('[data-file-multi-hint]');
        if (!input) return;
        input.disabled = qty > 1;
        if (qty > 1) input.value = '';
        hint?.classList.toggle('hidden', qty <= 1);
    }

    ['create', 'sub'].forEach((name) => {
        formOf(name).querySelectorAll('[data-preview-input]').forEach((el) => {
            el.addEventListener('input', () => updatePreview(name));
            el.addEventListener('change', () => updatePreview(name));
        });
    });

    // ── Isi dialog dari data baris ──────────────────────────────────
    function fillDetails(form, data, withDefaults = false) {
        ['subject', 'recipient', 'signer', 'pjct_id'].forEach((key) => {
            const el = form.querySelector(`[data-field=${key}]`);
            if (!el) return;
            const value = data[key] ?? '';
            // Project di luar divisi user tetap ditampilkan supaya tidak terhapus saat disimpan.
            if (el.tagName === 'SELECT' && value && !el.querySelector(`option[value="${CSS.escape(value)}"]`)) {
                el.add(new Option(data.project || value, value));
            }
            el.value = value;
        });
        if (!withDefaults) {
            form.querySelector('[data-field=pic]').value = data.pic ?? '';
        }
        const file = form.querySelector('[data-field=file]');
        if (file) file.value = '';
    }

    function setTarget(form, id, data) {
        form.action = form.dataset.actionTemplate.replace('__ID__', id);
        form.querySelector('[name=_letter]').value = id;
        form.querySelector('[name=_number]').value = data.number;
        form.querySelectorAll('[data-show]').forEach((el) => { el.textContent = data[el.dataset.show] ?? '-'; });
    }

    function open(name, id) {
        const form = formOf(name);
        const data = id ? rows[id] : null;

        if (name === 'create') {
            updatePreview('create');
        } else if (name === 'sub') {
            subParent = data;
            setTarget(form, id, data);
            fillDetails(form, data, true);
            form.querySelector('[name=quantity]').value = 1;
            form.querySelector('[name=letter_date]').value = today.startsWith(String(data.year)) ? today : (data.date || `${data.year}-12-31`);
            updatePreview('sub');
        } else if (name === 'edit') {
            setTarget(form, id, data);
            fillDetails(form, data);
            const remove = form.querySelector('[data-remove-file]');
            remove.classList.toggle('hidden', !data.file_name);
            remove.classList.toggle('flex', !!data.file_name);
            remove.querySelector('input').checked = false;
        } else if (name === 'cancel') {
            setTarget(form, id, data);
            form.querySelector('[name=cancel_reason]').value = '';
        }

        dialogs[name].showModal();
    }

    document.querySelectorAll('[data-open-letter]').forEach((btn) => {
        btn.addEventListener('click', () => open(btn.dataset.openLetter, btn.dataset.id));
    });

    // Validasi gagal → buka lagi dialog yang tadi dengan isian sebelumnya.
    if (oldDialog && dialogs[oldDialog]) {
        if (oldDialog === 'sub') {
            const id = formOf('sub').querySelector('[name=_letter]').value;
            subParent = rows[id] || null;
        }
        if (oldDialog === 'create' || oldDialog === 'sub') updatePreview(oldDialog);
        dialogs[oldDialog].showModal();
    }

    // ── Salin nomor ─────────────────────────────────────────────────
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(btn.dataset.copy);
            } catch {
                return;
            }
            const label = btn.querySelector('[data-copy-label]');
            const target = label || btn;
            const original = target.textContent;
            target.textContent = 'Tersalin ✓';
            setTimeout(() => { target.textContent = original; }, 1400);
        });
    });
})();
</script>
@endpush
