@extends('layouts.app', ['title' => 'Project Assets', 'heading' => 'Project Assets'])

@section('content')

{{-- ── KPI Cards ────────────────────────────────────────────────────── --}}
<div class="flex gap-3 mb-5">
    <div class="flex-1 bg-slate-50 rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-2xl font-black text-slate-800">{{ number_format($kpi['total']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Asset</p>
        <p class="text-[10px] text-slate-400 mt-0.5">Semua status</p>
    </div>
    <div class="flex-1 bg-green-50 rounded-xl border border-green-200 shadow-sm p-4">
        <p class="text-2xl font-black text-green-600">{{ number_format($kpi['aktif']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Aktif</p>
        <p class="text-[10px] text-green-500 mt-0.5">In deployment</p>
    </div>
    <div class="flex-1 bg-sky-50 rounded-xl border border-sky-200 shadow-sm p-4">
        <p class="text-2xl font-black text-sky-600">{{ number_format($kpi['sewa']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Sewa / SewaBeli</p>
        <p class="text-[10px] text-sky-400 mt-0.5">Aktif-Sewa & SewaBeli</p>
    </div>
    <div class="flex-1 bg-amber-50 rounded-xl border border-amber-200 shadow-sm p-4">
        <p class="text-2xl font-black text-amber-600">{{ number_format($kpi['backup']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Back Up</p>
        <p class="text-[10px] text-amber-400 mt-0.5">Standby unit</p>
    </div>
    <div class="flex-1 bg-red-50 rounded-xl border border-red-200 shadow-sm p-4">
        <p class="text-2xl font-black text-red-500">{{ number_format($kpi['nonaktif']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Non-Aktif</p>
        <p class="text-[10px] text-red-400 mt-0.5">Perlu perhatian</p>
    </div>
    <div class="flex-1 rounded-xl border shadow-sm p-4 {{ $kpi['aktif_end'] > 0 ? 'bg-orange-50 border-orange-300' : 'bg-white border-slate-200' }}">
        <p class="text-2xl font-black {{ $kpi['aktif_end'] > 0 ? 'text-orange-600' : 'text-slate-800' }}">{{ number_format($kpi['aktif_end']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Aktif di Project END</p>
        <p class="text-[10px] {{ $kpi['aktif_end'] > 0 ? 'text-orange-400' : 'text-slate-400' }} mt-0.5">{{ $kpi['aktif_end'] > 0 ? 'Perlu ditarik / relokasi' : 'Semua clear' }}</p>
    </div>
</div>


{{-- ── Filter & Table ──────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

    {{-- Filter Bar --}}
    <div class="px-4 py-3 border-b border-slate-100">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[180px]">
                <label class="label">Search</label>
                <input class="field" type="text" name="search" value="{{ $search }}"
                    placeholder="ID / model / serial / user / contract...">
            </div>
            <div class="w-36 shrink-0">
                <label class="label">Type</label>
                <select class="field" name="type">
                    <option value="">All Types</option>
                    @foreach($filterTypes as $t)
                        <option value="{{ $t }}" @selected($type===$t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36 shrink-0">
                <label class="label">Status</label>
                <select class="field" name="stat">
                    <option value="">All Status</option>
                    @foreach($filterStats as $s)
                        <option value="{{ $s }}" @selected($stat===$s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-32 shrink-0">
                <label class="label">Kondisi</label>
                <select class="field" name="cond">
                    <option value="">All</option>
                    @foreach($filterConds as $c)
                        <option value="{{ $c }}" @selected($cond===$c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40 shrink-0">
                <label class="label">Lokasi</label>
                <select class="field" name="loc">
                    <option value="">All Lokasi</option>
                    @foreach($filterLocs as $l)
                        <option value="{{ $l }}" @selected($loc===$l)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="shrink-0 flex items-end gap-2 pb-0.5">
                <button type="submit" class="btn-primary rounded-xl px-3 py-2 text-xs">Search</button>
                <a href="{{ route('project.assets') }}" class="btn-soft rounded-xl px-3 py-2 text-xs">Reset</a>
            </div>
        </form>
    </div>

    {{-- Table Header --}}
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
        <span class="text-xs text-slate-500">
            {{ number_format($assets->total()) }} asset
            @if($assets->total() !== $kpi['total'])
                <span class="text-slate-400">(filtered dari {{ number_format($kpi['total']) }})</span>
            @endif
        </span>
        <span class="text-xs text-slate-400">Halaman {{ $assets->currentPage() }} / {{ $assets->lastPage() }}</span>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="text-left text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 font-medium">#</th>
                    <th class="px-3 py-2 font-medium">Type</th>
                    <th class="px-3 py-2 font-medium">Brand & Model</th>
                    <th class="px-3 py-2 font-medium">Year</th>
                    <th class="px-3 py-2 font-medium">Serial</th>
                    <th class="px-3 py-2 font-medium">Lokasi</th>
                    <th class="px-3 py-2 font-medium">Kondisi</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium">Project Name</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($assets as $i => $a)
                <tr class="hover:bg-slate-50/60 transition">
                    <td class="px-4 py-2 text-slate-400">{{ ($assets->currentPage()-1)*$assets->perPage()+$i+1 }}</td>
                    <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $a->ast_type }}</td>
                    <td class="px-3 py-2">
                        <p class="font-semibold text-slate-800">{{ $a->ast_brand }}</p>
                        <p class="text-slate-400 text-[10px]">{{ $a->ast_brandmodel }}</p>
                    </td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ $a->ast_prodyear ?? '-' }}</td>
                    <td class="px-3 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap">{{ $a->ast_serial }}</td>
                    <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $a->ast_userloc ?: '-' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $a->condBadgeClass() }}">
                            {{ $a->ast_cond }}
                        </span>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $a->statBadgeClass() }}">
                            {{ $a->ast_stat }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-[10px] text-slate-500 max-w-[200px]" title="{{ $projectNames[$a->ast_pjctid] ?? $a->ast_pjctid }}">
                        {{ $a->ast_pjctid ? ($projectNames[$a->ast_pjctid] ?? $a->ast_pjctid) : '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center text-slate-400">Tidak ada data asset.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($assets->hasPages())
    <div class="px-4 py-3 border-t border-slate-100">
        {{ $assets->links() }}
    </div>
    @endif

</div>

@endsection
