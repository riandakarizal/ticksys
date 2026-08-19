@extends('layouts.app', ['title' => 'Preview Import Asset', 'heading' => 'Preview Import Asset'])

@section('content')

@php
    $badge = [
        'new'   => ['label' => 'AKAN MASUK', 'class' => 'bg-green-100 text-green-700'],
        'skip'  => ['label' => 'DILEWATI',   'class' => 'bg-amber-100 text-amber-700'],
        'error' => ['label' => 'GAGAL',      'class' => 'bg-red-100 text-red-700'],
    ];
@endphp

{{-- ── Ringkasan ────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-5">
    <div class="flex flex-wrap divide-x divide-slate-200 border border-slate-200 rounded-xl bg-white overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-3">
            <span class="text-2xl font-black text-green-600">{{ number_format($summary['new']) }}</span>
            <span class="text-xs text-slate-500">Akan masuk<br><span class="text-slate-400">data baru</span></span>
        </div>
        <div class="flex items-center gap-3 px-5 py-3">
            <span class="text-2xl font-black text-amber-600">{{ number_format($summary['skip']) }}</span>
            <span class="text-xs text-slate-500">Dilewati<br><span class="text-slate-400">serial duplikat</span></span>
        </div>
        <div class="flex items-center gap-3 px-5 py-3">
            <span class="text-2xl font-black text-red-500">{{ number_format($summary['error']) }}</span>
            <span class="text-xs text-slate-500">Gagal<br><span class="text-slate-400">data tidak valid</span></span>
        </div>
        <div class="flex items-center gap-3 px-5 py-3 bg-slate-50/60">
            <span class="text-2xl font-black text-slate-700">{{ number_format($summary['total']) }}</span>
            <span class="text-xs text-slate-500">Total baris<br><span class="text-slate-400 truncate max-w-[160px] inline-block align-bottom" title="{{ $filename }}">{{ $filename }}</span></span>
        </div>
    </div>

    <div class="flex items-center gap-2">
        <form method="POST" action="{{ route('project.assets.import.cancel') }}">
            @csrf
            <button type="submit" class="btn-soft">Batal</button>
        </form>
        <form method="POST" action="{{ route('project.assets.import.confirm') }}" id="asset-confirm-form">
            @csrf
            <button type="submit" class="btn-primary" id="asset-confirm-btn" @disabled($summary['new'] === 0)>
                Konfirmasi Import ({{ number_format($summary['new']) }})
            </button>
        </form>
    </div>
</div>

@if($summary['new'] === 0)
    <div class="mb-5 rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-600 text-center">
        Tidak ada data baru untuk dimasukkan. Semua baris duplikat atau tidak valid.
    </div>
@endif

{{-- ── Detail per baris ─────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
        <span class="text-xs text-slate-500">Detail per baris</span>
        <div class="flex items-center gap-1" id="asset-preview-filters">
            @foreach(['all' => 'Semua', 'new' => 'Akan masuk', 'skip' => 'Dilewati', 'error' => 'Gagal'] as $key => $label)
                <button type="button" data-filter="{{ $key }}"
                        class="rounded-lg px-2.5 py-1 text-[11px] font-semibold transition {{ $key === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="overflow-x-auto max-h-[60vh]">
        <table class="min-w-full text-xs">
            <thead class="text-left text-slate-400 border-b border-slate-100 bg-white sticky top-0">
                <tr>
                    <th class="px-4 py-2 font-medium">Baris</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium">Project</th>
                    <th class="px-3 py-2 font-medium">Type</th>
                    <th class="px-3 py-2 font-medium">Brand &amp; Model</th>
                    <th class="px-3 py-2 font-medium">Serial</th>
                    <th class="px-3 py-2 font-medium">Lokasi</th>
                    <th class="px-3 py-2 font-medium">Status Aset</th>
                    <th class="px-3 py-2 font-medium">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($classified as $item)
                    @php $b = $badge[$item['status']]; @endphp
                    <tr class="hover:bg-slate-50/60 transition" data-status="{{ $item['status'] }}">
                        <td class="px-4 py-2 text-slate-400">{{ $item['line'] }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $b['class'] }}">{{ $b['label'] }}</span>
                        </td>
                        <td class="px-3 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap">{{ $item['row']['ast_pjctid'] ?: '-' }}</td>
                        <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $item['row']['ast_type'] ?: '-' }}</td>
                        <td class="px-3 py-2">
                            <p class="font-semibold text-slate-800">{{ $item['row']['ast_brand'] ?: '-' }}</p>
                            <p class="text-slate-400 text-[10px]">{{ $item['row']['ast_brandmodel'] }}</p>
                        </td>
                        <td class="px-3 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap">{{ $item['row']['ast_serial'] ?: '-' }}</td>
                        <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $item['row']['ast_userloc'] ?: '-' }}</td>
                        <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $item['row']['ast_stat'] ?: '-' }}</td>
                        <td class="px-3 py-2 text-[10px] {{ $item['status'] === 'error' ? 'text-red-600' : 'text-slate-400' }} max-w-[280px]">
                            {{ $item['message'] ?: '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const bar = document.getElementById('asset-preview-filters');
    const rows = document.querySelectorAll('tbody tr[data-status]');

    bar.addEventListener('click', function (e) {
        const btn = e.target.closest('button[data-filter]');
        if (!btn) return;

        bar.querySelectorAll('button').forEach(function (b) {
            b.className = b === btn
                ? 'rounded-lg px-2.5 py-1 text-[11px] font-semibold transition bg-slate-800 text-white'
                : 'rounded-lg px-2.5 py-1 text-[11px] font-semibold transition bg-slate-100 text-slate-500 hover:bg-slate-200';
        });

        const want = btn.dataset.filter;
        rows.forEach(function (row) {
            row.classList.toggle('hidden', want !== 'all' && row.dataset.status !== want);
        });
    });

    document.getElementById('asset-confirm-form').addEventListener('submit', function () {
        const btn = document.getElementById('asset-confirm-btn');
        btn.disabled = true;
        btn.textContent = 'Menyimpan…';
    });
})();
</script>

@endsection
