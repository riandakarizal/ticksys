@extends('layouts.app', ['title' => 'Addendum '.$project->id, 'heading' => 'Addendum Kontrak'])

@section('content')

@use('App\Models\PjctAddendum')
@php
    $rp =fn ($v) => $v === null || $v === '' ? '-' : 'Rp '.number_format((int) $v, 0, ',', '.');
    $tgl = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M Y') : '-';
    $fmt = fn (string $field, $v) => match ($field) {
        'pjct_value' => $rp($v),
        'pjct_coend_m' => $tgl($v),
        default => $v === null || $v === '' ? '-' : $v,
    };
    $oldReasons = old('reasons', []);
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <a href="{{ route('monitoring.index', ['search' => $project->pjct_contract ?: $project->pjct_name]) }}" class="btn-soft rounded-xl px-3 py-2 text-xs">← Project Main</a>
</div>

{{-- ── Ringkasan kontrak (nilai terkini, sudah termasuk addendum) ─────────── --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-mono text-[11px] text-slate-500">{{ $project->id }}
                <span class="ml-1 inline-flex items-center rounded px-1.5 py-0.5 font-sans text-[10px] font-semibold {{ $project->statusBadgeClass() }}">{{ $project->statusLabel() }}</span>
            </p>
            <p class="text-base font-bold text-slate-800 mt-0.5">{{ $project->pjct_name }}</p>
        </div>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $addenda->isEmpty() ? 'bg-slate-100 text-slate-500' : 'bg-orange-100 text-orange-700' }}">
            {{ $addenda->count() }} addendum
        </span>
    </div>
    <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mt-3 text-xs">
        <div><dt class="text-slate-400">No. kontrak</dt><dd class="font-mono text-[11px] text-slate-700 break-words">{{ $project->pjct_contract ?: '-' }}</dd></div>
        <div><dt class="text-slate-400">Client</dt><dd class="text-slate-700">{{ $project->pjct_client ?: '-' }}</dd></div>
        <div><dt class="text-slate-400">Nilai kontrak (terkini)</dt><dd class="font-bold text-slate-800">{{ $rp($project->pjct_value) }}</dd></div>
        <div><dt class="text-slate-400">Periode (terkini)</dt><dd class="text-slate-700">{{ $tgl($project->pjct_costart) }} – {{ $tgl($project->pjct_coend_m) }}</dd></div>
    </dl>

    @if($legacyAddenda->isNotEmpty())
        <p class="mt-3 rounded-lg bg-amber-50 border border-amber-100 px-3 py-2 text-[11px] text-amber-800">
            Ada {{ $legacyAddenda->count() }} addendum format lama yang tercatat sebagai project terpisah:
            {{ $legacyAddenda->map(fn ($a) => $a->id.' (s/d '.$tgl($a->pjct_coend_m).')')->implode(', ') }}.
        </p>
    @endif
    @if($legacyParent)
        <p class="mt-3 rounded-lg bg-amber-50 border border-amber-100 px-3 py-2 text-[11px] text-amber-800">
            Project ini tercatat (format lama) sebagai addendum dari
            <a href="{{ route('monitoring.addenda.index', $legacyParent->id) }}" class="font-semibold underline">{{ $legacyParent->id }} — {{ $legacyParent->pjct_name }}</a>.
        </p>
    @endif
</div>

<div class="grid gap-4 {{ $canManage ? 'lg:grid-cols-[1fr_380px]' : '' }} items-start">

    {{-- ── Riwayat ────────────────────────────────────────────────────────── --}}
    <div class="space-y-3 min-w-0">
        @forelse($addenda as $a)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-800">
                            <span class="inline-flex items-center rounded-full bg-orange-100 text-orange-700 px-2 py-0.5 text-[11px] font-bold mr-1">Addendum {{ $a->seq }}</span>
                            <span class="font-mono text-xs text-slate-600 break-all">{{ $a->number }}</span>
                        </p>
                        <p class="text-[11px] text-slate-400 mt-1">Tanggal addendum {{ $tgl($a->addendum_date) }}</p>
                    </div>
                    @if($a->doc)
                        <a href="{{ route('monitoring.docs.show', $a->doc_id) }}" target="_blank" rel="noopener"
                           class="btn-soft rounded-xl px-3 py-1.5 text-xs">📄 Dokumen</a>
                    @endif
                </div>

                <div class="flex flex-wrap gap-1 mt-2">
                    @foreach($a->reasons as $code)
                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold bg-slate-100 text-slate-600">{{ PjctAddendum::reasonLabel($code) }}</span>
                    @endforeach
                </div>

                @if($a->changes)
                    <div class="mt-3 rounded-lg border border-slate-100 divide-y divide-slate-100 text-xs">
                        @foreach($a->changes as $field => $c)
                            <div class="flex flex-wrap items-center gap-x-2 px-3 py-1.5">
                                <span class="text-slate-400 w-28 shrink-0">{{ PjctAddendum::FIELD_LABELS[$field] ?? $field }}</span>
                                <span class="text-slate-500 line-through">{{ $fmt($field, $c['before']) }}</span>
                                <span class="text-slate-300">→</span>
                                <span class="font-semibold text-slate-800">{{ $fmt($field, $c['after']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($a->notes)
                    <dl class="mt-3 space-y-2 text-xs">
                        @foreach($a->reasons as $code)
                            @continue(empty($a->notes[$code]))
                            <div>
                                <dt class="font-semibold text-slate-600">{{ PjctAddendum::reasonLabel($code) }}</dt>
                                <dd class="text-slate-600 whitespace-pre-line">{{ $a->notes[$code] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                <p class="text-[10px] text-slate-400 mt-3">Dicatat {{ $a->user?->user_name ?? $a->user_id ?? '-' }} · {{ $a->created_at?->format('d M Y H:i') }}</p>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-10 text-center text-sm text-slate-400">
                Belum ada addendum untuk project ini.
            </div>
        @endforelse
    </div>

    {{-- ── Form catat addendum ─────────────────────────────────────────── --}}
    @if($canManage)
    <form method="POST" action="{{ route('monitoring.addenda.store', $project->id) }}" enctype="multipart/form-data" id="add-form"
          class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 space-y-4 lg:sticky lg:top-20">
        @csrf
        <div>
            <p class="text-sm font-bold text-slate-800">Catat addendum baru</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Langsung berlaku: nilai, tanggal akhir, dan nama client project ikut diperbarui. Nilai lama tetap tersimpan di riwayat.</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
                <label class="label" for="add-number">Nomor addendum</label>
                <input class="field" type="text" name="number" id="add-number" value="{{ old('number') }}" maxlength="255" required>
            </div>
            <div class="col-span-2 sm:col-span-1">
                <label class="label" for="add-date">Tanggal addendum</label>
                <input class="field" type="date" name="addendum_date" id="add-date" value="{{ old('addendum_date') }}" required>
            </div>
        </div>

        <fieldset>
            <legend class="label">Alasan addendum <span class="font-normal text-slate-400">(boleh lebih dari satu)</span></legend>
            <div class="space-y-2 mt-1">
                @foreach(PjctAddendum::REASONS as $code => [$label, $hint])
                    @php $checked = in_array($code, $oldReasons, true); @endphp
                    <div class="rounded-lg border border-slate-200 px-3 py-2" data-reason="{{ $code }}">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="reasons[]" value="{{ $code }}" class="mt-0.5 rounded border-slate-300" @checked($checked)>
                            <span class="text-xs">
                                <span class="font-semibold text-slate-700">{{ $label }}</span>
                                <span class="block text-[10px] text-slate-400">{{ $hint }}</span>
                            </span>
                        </label>

                        <div class="mt-2 space-y-2 {{ $checked ? '' : 'hidden' }}" data-reason-detail>
                            @if($code === 'NILAI')
                                <div>
                                    <label class="label" for="add-new-value">Nilai kontrak baru (Rp)</label>
                                    <input class="field" type="number" min="0" step="1" name="new_value" id="add-new-value" value="{{ old('new_value') }}" data-required>
                                    <p class="text-[10px] text-slate-400 mt-1">Sekarang {{ $rp($project->pjct_value) }} · <span id="add-new-value-fmt"></span></p>
                                </div>
                            @elseif($code === 'WAKTU')
                                <div>
                                    <label class="label" for="add-new-end">Tanggal akhir kontrak baru</label>
                                    <input class="field" type="date" name="new_end" id="add-new-end" value="{{ old('new_end') }}" data-required>
                                    <p class="text-[10px] text-slate-400 mt-1">Sekarang {{ $tgl($project->pjct_coend_m) }}</p>
                                </div>
                            @elseif($code === 'IDENTITAS')
                                <div>
                                    <label class="label" for="add-new-client">Nama client baru</label>
                                    <input class="field" type="text" name="new_client" id="add-new-client" value="{{ old('new_client') }}" maxlength="255"
                                           placeholder="Kosongkan bila nama tidak berubah">
                                    <p class="text-[10px] text-slate-400 mt-1">Sekarang {{ $project->pjct_client ?: '-' }}</p>
                                </div>
                            @endif
                            <textarea class="field" name="notes[{{ $code }}]" rows="2" maxlength="2000"
                                      placeholder="{{ in_array($code, PjctAddendum::NOTE_OPTIONAL, true) ? 'Keterangan (opsional)' : 'Keterangan perubahan' }}"
                                      @unless(in_array($code, PjctAddendum::NOTE_OPTIONAL, true)) data-required @endunless>{{ old('notes.'.$code) }}</textarea>
                        </div>
                    </div>
                @endforeach
            </div>
        </fieldset>

        <div>
            <label class="label" for="add-document">Dokumen addendum (PDF, maks. 5 MB)</label>
            <input class="field" type="file" name="document" id="add-document" accept="application/pdf,.pdf" required>
            @if($errors->any())
                <p class="text-[10px] text-amber-600 mt-1">Pilih ulang file PDF-nya — browser tidak menyimpan pilihan file setelah form ditolak.</p>
            @endif
        </div>

        <button type="submit" class="btn-primary w-full rounded-xl px-3 py-2.5 text-sm" id="add-submit">Simpan addendum</button>
    </form>
    @endif
</div>

@if($canManage)
<script>
(function () {
    const form = document.getElementById('add-form');

    function sync(box) {
        const detail = box.querySelector('[data-reason-detail]');
        const on = box.querySelector('input[type="checkbox"]').checked;
        detail.classList.toggle('hidden', !on);
        detail.querySelectorAll('input, textarea').forEach(function (el) {
            el.disabled = !on;
            el.required = on && el.hasAttribute('data-required');
        });
    }

    form.querySelectorAll('[data-reason]').forEach(function (box) {
        box.querySelector('input[type="checkbox"]').addEventListener('change', function () { sync(box); });
        sync(box);
    });

    const value = document.getElementById('add-new-value');
    const valueFmt = document.getElementById('add-new-value-fmt');
    function fmt() {
        valueFmt.textContent = value.value === '' ? '' : 'Baru Rp ' + Number(value.value).toLocaleString('id-ID');
    }
    value.addEventListener('input', fmt);
    fmt();

    form.addEventListener('submit', function (e) {
        if (!form.querySelector('input[name="reasons[]"]:checked')) {
            e.preventDefault();
            alert('Pilih minimal satu alasan addendum.');
            return;
        }
        if (!confirm('Simpan addendum ini? Perubahan langsung diterapkan ke data project dan tidak bisa dihapus.')) {
            e.preventDefault();
            return;
        }
        const btn = document.getElementById('add-submit');
        btn.disabled = true;
        btn.textContent = 'Menyimpan…';
    });
})();
</script>
@endif

@endsection
