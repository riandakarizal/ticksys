@extends('layouts.app', ['title' => 'Migrasi Aset', 'heading' => 'Migrasi Aset'])

@section('content')

@php
    $ended = $sources->where('pjct_status', 'END');
    $running = $sources->where('pjct_status', '!=', 'END');
    $optionLabel = fn ($p) => $p->id.' — '.\Illuminate\Support\Str::limit($p->pjct_name, 70).' ('.$p->statusLabel().')';
@endphp

{{-- ── Langkah 1: project asal ─────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-4">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <p class="text-sm font-bold text-slate-800">Pindahkan aset ke project lain</p>
            <p class="text-[11px] text-slate-400 mt-0.5">
                Untuk project yang kontraknya habis / tidak diperpanjang: pilih project asal, centang aset yang dipindah, lalu pilih project tujuan.
                Aset yang masih ada di ticket aktif tidak bisa dipindah sampai ticket-nya selesai.
            </p>
        </div>
        <a href="{{ route('project.assets') }}" class="btn-soft rounded-xl px-3 py-2 text-xs">← Daftar Asset</a>
    </div>

    <form method="GET" action="{{ route('project.assets.migration.index') }}" class="flex flex-wrap items-end gap-2">
        <div class="flex-1 min-w-[260px]">
            <label class="label" for="mig-from">1. Project asal</label>
            <select class="field" name="from" id="mig-from" onchange="this.form.submit()">
                <option value="">— Pilih project yang asetnya akan dipindah —</option>
                @if($ended->isNotEmpty())
                    <optgroup label="Kontrak berakhir (END)">
                        @foreach($ended as $p)
                            <option value="{{ $p->id }}" @selected($from?->id === $p->id)>{{ $optionLabel($p) }} · {{ $p->asset_count }} aset</option>
                        @endforeach
                    </optgroup>
                @endif
                @if($running->isNotEmpty())
                    <optgroup label="Project lain">
                        @foreach($running as $p)
                            <option value="{{ $p->id }}" @selected($from?->id === $p->id)>{{ $optionLabel($p) }} · {{ $p->asset_count }} aset</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
        </div>
        <noscript><button type="submit" class="btn-primary rounded-xl px-3 py-2 text-xs">Tampilkan aset</button></noscript>
    </form>

    @if($sources->isEmpty())
        <p class="mt-3 text-xs text-slate-400">Belum ada project di divisi Anda yang memiliki aset.</p>
    @elseif(request('from') && ! $from)
        <p class="mt-3 text-xs text-red-600">Project {{ request('from') }} tidak ditemukan atau di luar divisi Anda.</p>
    @endif
</div>

@if($from)
@php $blocked = $assets->whereNotNull('ast_last_ticket_id')->count(); @endphp
<form method="POST" action="{{ route('project.assets.migration.store') }}" id="mig-form">
    @csrf
    <input type="hidden" name="from" value="{{ $from->id }}">

    <div class="grid gap-4 lg:grid-cols-[1fr_320px] mb-4">

        {{-- ── Langkah 2: pilih aset ──────────────────────────────────── --}}
        <div class="pt-well min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-3 pb-1">
                <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-700">2. Pilih aset — {{ $from->id }}
                        <span class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $from->statusBadgeClass() }}">{{ $from->statusLabel() }}</span>
                    </p>
                    <p class="text-[10px] text-slate-400 truncate" title="{{ $from->pjct_name }}">{{ $from->pjct_name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="search" id="mig-search" class="field w-48" placeholder="Cari serial / user / lokasi…">
                    <span class="text-[11px] text-slate-500 whitespace-nowrap"><b id="mig-count">0</b> / {{ $assets->count() - $blocked }} dipilih</span>
                </div>
            </div>

            @if($blocked)
                <p class="px-4 py-2 text-[11px] text-amber-700 bg-amber-50 border-b border-amber-100">
                    {{ $blocked }} aset masih ada di ticket aktif dan tidak bisa dipilih.
                </p>
            @endif

            <div class="overflow-x-auto max-h-[60vh]">
                <table class="pt-table min-w-full text-xs">
                    <thead class="text-left text-slate-400 border-b border-slate-100 bg-white sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-2 w-8">
                                <input type="checkbox" id="mig-all" title="Pilih semua yang tampil" class="rounded border-slate-300">
                            </th>
                            <th class="px-3 py-2 font-medium">ID</th>
                            <th class="px-3 py-2 font-medium">Type</th>
                            <th class="px-3 py-2 font-medium">Brand &amp; Model</th>
                            <th class="px-3 py-2 font-medium">Serial</th>
                            <th class="px-3 py-2 font-medium">Pengguna</th>
                            <th class="px-3 py-2 font-medium">Lokasi</th>
                            <th class="px-3 py-2 font-medium">Kondisi</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($assets as $a)
                            @php $locked = $a->ast_last_ticket_id !== null; @endphp
                            <tr class="{{ $locked ? 'bg-slate-50/80 text-slate-400' : 'hover:bg-slate-50/60' }} transition"
                                data-search="{{ strtolower($a->id.' '.$a->ast_type.' '.$a->ast_brand.' '.$a->ast_brandmodel.' '.$a->ast_serial.' '.$a->ast_username.' '.$a->ast_userloc) }}">
                                <td class="px-4 py-2">
                                    <input type="checkbox" name="ast_ids[]" value="{{ $a->id }}" class="mig-cb rounded border-slate-300"
                                           @checked(in_array($a->id, old('ast_ids', []), true)) @disabled($locked)>
                                </td>
                                <td class="px-3 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap">{{ $a->id }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $a->ast_type }}</td>
                                <td class="px-3 py-2">
                                    <p class="font-semibold {{ $locked ? '' : 'text-slate-800' }}">{{ $a->ast_brand }}</p>
                                    <p class="text-slate-400 text-[10px]">{{ $a->ast_brandmodel }}</p>
                                </td>
                                <td class="px-3 py-2 font-mono text-[10px] whitespace-nowrap">{{ $a->ast_serial }}</td>
                                <td class="px-3 py-2 whitespace-nowrap max-w-[160px] truncate" title="{{ $a->ast_username }}">{{ $a->ast_username ?: '-' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $a->ast_userloc ?: '-' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $a->condBadgeClass() }}">{{ $a->ast_cond }}</span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if($locked)
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-amber-100 text-amber-700">Ticket aktif</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $a->statBadgeClass() }}">{{ $a->ast_stat }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-12 text-center text-slate-400">Project ini tidak punya aset.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Langkah 3: tujuan & opsi ───────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 h-fit lg:sticky lg:top-4 space-y-4">
            <p class="text-xs font-bold text-slate-700">3. Project tujuan</p>

            <div>
                <label class="label" for="mig-to">Pindahkan ke</label>
                <select class="field" name="to" id="mig-to" required>
                    <option value="">— Pilih project tujuan —</option>
                    @foreach($targets as $p)
                        <option value="{{ $p->id }}" @selected(old('to') === $p->id)>{{ $optionLabel($p) }}</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-slate-400 mt-1">Hanya project di divisi Anda yang belum berakhir (bukan END).</p>
            </div>

            <div>
                <label class="label" for="mig-stat">Status aset setelah dipindah</label>
                <select class="field" name="stat_after" id="mig-stat">
                    <option value="">Tidak diubah</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected(old('stat_after') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            <label class="flex items-start gap-2 text-xs text-slate-600 cursor-pointer">
                <input type="hidden" name="clear_location" value="0">
                <input type="checkbox" name="clear_location" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('clear_location'))>
                <span>Kosongkan pengguna, region &amp; lokasi
                    <span class="block text-[10px] text-slate-400">Pilih kalau unit ditarik dulu sebelum dipasang di lokasi project baru. Data lama tetap tersimpan di riwayat.</span>
                </span>
            </label>

            <div>
                <label class="label" for="mig-note">Catatan</label>
                <textarea class="field" name="note" id="mig-note" rows="3" maxlength="1000"
                          placeholder="mis. Kontrak berakhir 31 Des 2026, unit dipakai di kontrak baru — BA No. …">{{ old('note') }}</textarea>
            </div>

            <button type="submit" class="btn-primary w-full rounded-xl px-3 py-2.5 text-sm" id="mig-submit" disabled>
                Pindahkan 0 aset
            </button>
        </div>
    </div>
</form>
@endif

{{-- ── Riwayat ────────────────────────────────────────────────────────── --}}
<div class="pt-well">
    <div class="px-4 pt-3 pb-1">
        <span class="text-xs font-bold text-slate-700">Riwayat migrasi</span>
        <span class="text-[11px] text-slate-400 ml-1">20 terakhir</span>
    </div>
    <div class="overflow-x-auto">
        <table class="pt-table min-w-full text-xs">
            <thead class="text-left text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 font-medium">Tanggal</th>
                    <th class="px-3 py-2 font-medium">Dari</th>
                    <th class="px-3 py-2 font-medium">Ke</th>
                    <th class="px-3 py-2 font-medium text-right">Aset</th>
                    <th class="px-3 py-2 font-medium">Oleh</th>
                    <th class="px-3 py-2 font-medium">Catatan</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($history as $m)
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-4 py-2 text-slate-500 whitespace-nowrap">{{ $m->created_at?->format('d M Y H:i') }}</td>
                        <td class="px-3 py-2 max-w-[220px]" title="{{ $projectNames[$m->from_pjct_id] ?? '' }}">
                            <span class="font-mono text-[10px] text-slate-500">{{ $m->from_pjct_id }}</span>
                            <span class="block truncate text-slate-700">{{ $projectNames[$m->from_pjct_id] ?? '-' }}</span>
                        </td>
                        <td class="px-3 py-2 max-w-[220px]" title="{{ $projectNames[$m->to_pjct_id] ?? '' }}">
                            <span class="font-mono text-[10px] text-slate-500">{{ $m->to_pjct_id }}</span>
                            <span class="block truncate text-slate-700">{{ $projectNames[$m->to_pjct_id] ?? '-' }}</span>
                        </td>
                        <td class="px-3 py-2 text-right font-bold text-slate-800">{{ number_format($m->asset_count) }}</td>
                        <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $m->user?->user_name ?? '-' }}</td>
                        <td class="px-3 py-2 text-slate-500 max-w-[240px] truncate" title="{{ $m->note }}">{{ $m->note ?: '-' }}</td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('project.assets.migration.show', $m) }}" class="text-teal-600 font-semibold hover:underline whitespace-nowrap">Detail →</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Belum ada migrasi aset.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($from)
<script>
(function () {
    const form = document.getElementById('mig-form');
    const all = document.getElementById('mig-all');
    const search = document.getElementById('mig-search');
    const count = document.getElementById('mig-count');
    const submit = document.getElementById('mig-submit');
    const target = document.getElementById('mig-to');
    const boxes = Array.from(form.querySelectorAll('.mig-cb'));
    const usable = () => boxes.filter((cb) => !cb.disabled && !cb.closest('tr').classList.contains('hidden'));

    function refresh() {
        const n = boxes.filter((cb) => cb.checked).length;
        count.textContent = n;
        submit.textContent = 'Pindahkan ' + n + ' aset';
        submit.disabled = n === 0;
        const visible = usable();
        all.checked = visible.length > 0 && visible.every((cb) => cb.checked);
        all.indeterminate = !all.checked && visible.some((cb) => cb.checked);
    }

    all.addEventListener('change', function () {
        usable().forEach((cb) => { cb.checked = all.checked; });
        refresh();
    });
    boxes.forEach((cb) => cb.addEventListener('change', refresh));

    search.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        form.querySelectorAll('tbody tr[data-search]').forEach(function (tr) {
            tr.classList.toggle('hidden', q !== '' && !tr.dataset.search.includes(q));
        });
        refresh();
    });

    form.addEventListener('submit', function (e) {
        const n = boxes.filter((cb) => cb.checked).length;
        const to = target.options[target.selectedIndex];
        if (!to || !to.value) return; // biar validasi browser yang menegur
        if (!confirm('Pindahkan ' + n + ' aset dari {{ $from->id }} ke ' + to.value + '?')) {
            e.preventDefault();
            return;
        }
        submit.disabled = true;
        submit.textContent = 'Memindahkan…';
    });

    refresh();
})();
</script>
@endif

@endsection
