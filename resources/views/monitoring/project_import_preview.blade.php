@extends('layouts.app', ['title' => 'Preview Import Project', 'heading' => 'Preview Import Project'])

@section('content')

@php
    $badge = [
        'new'   => ['label' => 'AKAN MASUK', 'class' => 'bg-green-100 text-green-700'],
        'skip'  => ['label' => 'DILEWATI',   'class' => 'bg-amber-100 text-amber-700'],
        'error' => ['label' => 'GAGAL',      'class' => 'bg-red-100 text-red-700'],
    ];
    $rp = function ($n) {
        if ($n === null || $n === '') return '-';
        $n = (int) preg_replace('/[^0-9]/', '', (string) $n);
        if ($n === 0) return '-';
        if ($n >= 1e12) return 'Rp ' . number_format($n / 1e12, 2) . 'T';
        if ($n >= 1e9)  return 'Rp ' . number_format($n / 1e9, 2) . 'M';
        if ($n >= 1e6)  return 'Rp ' . number_format($n / 1e6, 0) . ' Jt';
        return 'Rp ' . number_format($n, 0, ',', '.');
    };
@endphp

{{-- ── Ringkasan ────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-5">
    <div class="flex flex-wrap divide-x divide-slate-200 border border-slate-200 rounded-xl bg-white overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-3">
            <span class="text-2xl font-black text-green-600">{{ number_format($summary['new']) }}</span>
            <span class="text-xs text-slate-500">Akan masuk<br><span class="text-slate-400">project baru</span></span>
        </div>
        <div class="flex items-center gap-3 px-5 py-3">
            <span class="text-2xl font-black text-amber-600">{{ number_format($summary['skip']) }}</span>
            <span class="text-xs text-slate-500">Dilewati<br><span class="text-slate-400">sudah terdaftar</span></span>
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
        <form method="POST" action="{{ route('monitoring.projects.import.cancel') }}">
            @csrf
            <button type="submit" class="btn-soft">Batal</button>
        </form>
        <form method="POST" action="{{ route('monitoring.projects.import.confirm') }}" id="project-confirm-form">
            @csrf
            <button type="submit" class="btn-primary" id="project-confirm-btn" @disabled($summary['new'] === 0)>
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
        <div class="flex items-center gap-1" id="project-preview-filters">
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
                    <th class="px-3 py-2 font-medium">Kontrak</th>
                    <th class="px-3 py-2 font-medium">Nama Project</th>
                    <th class="px-3 py-2 font-medium">Div</th>
                    <th class="px-3 py-2 font-medium">Tipe</th>
                    <th class="px-3 py-2 font-medium">Client</th>
                    <th class="px-3 py-2 font-medium">Area</th>
                    <th class="px-3 py-2 font-medium">Nilai</th>
                    <th class="px-3 py-2 font-medium">Status Project</th>
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
                        <td class="px-3 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap">{{ $item['row']['pjct_contract'] ?: '-' }}</td>
                        <td class="px-3 py-2 max-w-[260px]">
                            <p class="font-semibold text-slate-800 truncate" title="{{ $item['row']['pjct_name'] }}">{{ $item['row']['pjct_name'] ?: '-' }}</p>
                            <p class="text-slate-400 text-[10px] truncate">{{ $item['row']['pjct_misc'] }}</p>
                        </td>
                        <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $item['row']['pjct_div'] ?: '-' }}</td>
                        <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $item['row']['pjct_type'] ?: '-' }}</td>
                        <td class="px-3 py-2 text-slate-600 whitespace-nowrap max-w-[160px] truncate" title="{{ $item['row']['pjct_client'] }}">{{ $item['row']['pjct_client'] ?: '-' }}</td>
                        <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $item['row']['pjct_area'] ?: '-' }}</td>
                        <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $rp($item['row']['pjct_value']) }}</td>
                        <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $item['row']['pjct_status'] ?: '-' }}</td>
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
    const bar = document.getElementById('project-preview-filters');
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

    document.getElementById('project-confirm-form').addEventListener('submit', function () {
        const btn = document.getElementById('project-confirm-btn');
        btn.disabled = true;
        btn.textContent = 'Menyimpan…';
    });
})();
</script>

@endsection
