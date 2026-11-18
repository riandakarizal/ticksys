@extends('layouts.app', ['title' => 'Detail Migrasi Aset', 'heading' => 'Detail Migrasi Aset'])

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <a href="{{ route('project.assets.migration.index') }}" class="btn-soft rounded-xl px-3 py-2 text-xs">← Migrasi Aset</a>
    <span class="text-[11px] text-slate-400">Migrasi #{{ $migration->id }}</span>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-4">
    <div class="grid gap-4 md:grid-cols-[1fr_auto_1fr] items-center">
        @foreach(['Dari' => $migration->fromProject, 'arrow' => null, 'Ke' => $migration->toProject] as $label => $p)
            @if($label === 'arrow')
                <div class="hidden md:flex items-center justify-center text-2xl text-slate-300">→</div>
            @else
                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3 min-w-0">
                    <p class="text-[10px] uppercase tracking-wide text-slate-400">{{ $label }}</p>
                    <p class="font-mono text-[11px] text-slate-500 mt-0.5">{{ $label === 'Dari' ? $migration->from_pjct_id : $migration->to_pjct_id }}
                        @if($p)
                            <span class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold font-sans {{ $p->statusBadgeClass() }}">{{ $p->statusLabel() }}</span>
                        @endif
                    </p>
                    <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $p?->pjct_name ?? 'Project tidak ditemukan' }}</p>
                </div>
            @endif
        @endforeach
    </div>

    <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mt-4 text-xs">
        <div><dt class="text-slate-400">Jumlah aset</dt><dd class="font-bold text-slate-800 text-lg">{{ number_format($migration->asset_count) }}</dd></div>
        <div><dt class="text-slate-400">Waktu</dt><dd class="text-slate-700">{{ $migration->created_at?->format('d M Y H:i') }}</dd></div>
        <div><dt class="text-slate-400">Oleh</dt><dd class="text-slate-700">{{ $migration->user?->user_name ?? $migration->user_id ?? '-' }}</dd></div>
        <div>
            <dt class="text-slate-400">Perubahan data aset</dt>
            <dd class="text-slate-700">
                Status: {{ $migration->stat_after ?? 'tidak diubah' }}<br>
                Pengguna &amp; lokasi: {{ $migration->cleared_location ? 'dikosongkan' : 'tidak diubah' }}
            </dd>
        </div>
    </dl>

    @if($migration->note)
        <div class="mt-4 rounded-xl bg-slate-50 border border-slate-200 px-3 py-2 text-xs text-slate-600 whitespace-pre-line">{{ $migration->note }}</div>
    @endif
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/60 text-xs font-bold text-slate-700">Aset yang dipindah</div>
    <div class="overflow-x-auto max-h-[65vh]">
        <table class="min-w-full text-xs">
            <thead class="text-left text-slate-400 border-b border-slate-100 bg-white sticky top-0">
                <tr>
                    <th class="px-4 py-2 font-medium">ID</th>
                    <th class="px-3 py-2 font-medium">Type</th>
                    <th class="px-3 py-2 font-medium">Brand &amp; Model</th>
                    <th class="px-3 py-2 font-medium">Serial</th>
                    <th class="px-3 py-2 font-medium">Status (sebelum → kini)</th>
                    <th class="px-3 py-2 font-medium">Pengguna / lokasi sebelum</th>
                    <th class="px-3 py-2 font-medium">Posisi kini</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($migration->items as $item)
                    @php
                        $a = $assets[$item->ast_id] ?? null;
                        $before = $item->before ?? [];
                    @endphp
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-4 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap">{{ $item->ast_id }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $a?->ast_type ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <p class="font-semibold text-slate-800">{{ $a?->ast_brand ?? 'Aset sudah tidak ada' }}</p>
                            <p class="text-slate-400 text-[10px]">{{ $a?->ast_brandmodel }}</p>
                        </td>
                        <td class="px-3 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap">{{ $a?->ast_serial ?? '-' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap text-slate-600">{{ $before['ast_stat'] ?? '-' }} → {{ $a?->ast_stat ?? '-' }}</td>
                        <td class="px-3 py-2 text-slate-600 max-w-[260px]">
                            {{ collect([$before['ast_username'] ?? null, $before['ast_userreg'] ?? null, $before['ast_userloc'] ?? null, $before['ast_userlocdet'] ?? null])->filter()->implode(' · ') ?: '-' }}
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            @if($a && $a->ast_pjctid !== $migration->to_pjct_id)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-amber-100 text-amber-700" title="Sudah dipindah lagi setelah migrasi ini">{{ $a->ast_pjctid }}</span>
                            @else
                                <span class="font-mono text-[10px] text-slate-500">{{ $a?->ast_pjctid ?? '-' }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
