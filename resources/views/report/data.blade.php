@extends('layouts.app', ['title' => 'Report: Data', 'heading' => 'Report — Data Asset'])

@php
    /** Filter lanjutan dibuka otomatis kalau salah satunya sedang dipakai. */
    $advancedKeys = ['brand', 'reg', 'year', 'project', 'delv_from', 'delv_to', 'purc_from', 'purc_to'];
    $advancedOpen = collect($advancedKeys)->contains(fn ($k) => ($filters[$k] ?? '') !== '');
    $activeCount = collect($filters)->except(['sort', 'dir'])->filter(fn ($v) => $v !== '')->count();

    /** Link header tabel: pertahankan semua filter, tukar kolom/arah sorting. */
    $sortUrl = function (string $column) use ($filters) {
        $dir = ($filters['sort'] === $column && $filters['dir'] === 'asc') ? 'desc' : 'asc';

        return route('report.data', array_merge(request()->query(), ['sort' => $column, 'dir' => $dir, 'page' => null]));
    };

    $sortMark = fn (string $column) => $filters['sort'] === $column
        ? ($filters['dir'] === 'asc' ? '▲' : '▼')
        : '';
@endphp

@section('content')

{{-- ── Ringkasan hasil tarikan ──────────────────────────────────────── --}}
<div class="flex gap-3 mb-5">
    <div class="flex-1 bg-slate-50 rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-2xl font-black text-slate-800">{{ number_format($summary['filtered']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Baris Ditarik</p>
        <p class="text-[10px] text-slate-400 mt-0.5">dari {{ number_format($summary['total']) }} total asset</p>
    </div>
    <div class="flex-1 bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-2xl font-black text-slate-800">{{ number_format($summary['types']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Jenis Aset</p>
        <p class="text-[10px] text-slate-400 mt-0.5">unik pada hasil filter</p>
    </div>
    <div class="flex-1 bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-2xl font-black text-slate-800">{{ number_format($summary['locations']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Lokasi</p>
        <p class="text-[10px] text-slate-400 mt-0.5">unik pada hasil filter</p>
    </div>
    <div class="flex-1 bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-2xl font-black text-slate-800">{{ number_format($summary['projects']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Project Terkait</p>
        <p class="text-[10px] text-slate-400 mt-0.5">unik pada hasil filter</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

    {{-- ── Filter ──────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('report.data') }}" class="px-4 py-3 border-b border-slate-100">
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[200px]">
                <label class="label">Search</label>
                <input class="field" type="text" name="search" value="{{ $filters['search'] }}"
                    placeholder="ID / type / brand / model / serial / user / lokasi / project / doc / catatan...">
            </div>
            <div class="w-36 shrink-0">
                <label class="label">Type</label>
                <select class="field" name="type">
                    <option value="">All Types</option>
                    @foreach($options['types'] as $t)
                        <option value="{{ $t }}" @selected($filters['type'] === (string) $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36 shrink-0">
                <label class="label">Status</label>
                <select class="field" name="stat">
                    <option value="">All Status</option>
                    @foreach($options['stats'] as $s)
                        <option value="{{ $s }}" @selected($filters['stat'] === (string) $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-32 shrink-0">
                <label class="label">Kondisi</label>
                <select class="field" name="cond">
                    <option value="">All</option>
                    @foreach($options['conds'] as $c)
                        <option value="{{ $c }}" @selected($filters['cond'] === (string) $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40 shrink-0">
                <label class="label">Lokasi</label>
                <select class="field" name="loc">
                    <option value="">All Lokasi</option>
                    @foreach($options['locs'] as $l)
                        <option value="{{ $l }}" @selected($filters['loc'] === (string) $l)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-24 shrink-0">
                <label class="label">Per Hal.</label>
                <select class="field" name="per_page">
                    @foreach($perPageOptions as $pp)
                        <option value="{{ $pp }}" @selected($perPage === $pp)>{{ $pp }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Filter lanjutan --}}
        <details class="mt-3" @if($advancedOpen) open @endif>
            <summary class="cursor-pointer text-xs font-semibold text-slate-500 hover:text-slate-700 select-none">
                Filter lanjutan
                <span class="text-slate-400 font-normal">— brand, region, tahun, project, rentang tanggal</span>
            </summary>

            <div class="flex flex-wrap items-end gap-2 mt-3">
                <div class="w-40 shrink-0">
                    <label class="label">Brand</label>
                    <select class="field" name="brand">
                        <option value="">All Brand</option>
                        @foreach($options['brands'] as $b)
                            <option value="{{ $b }}" @selected($filters['brand'] === (string) $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-36 shrink-0">
                    <label class="label">Region</label>
                    <select class="field" name="reg">
                        <option value="">All Region</option>
                        @foreach($options['regs'] as $r)
                            <option value="{{ $r }}" @selected($filters['reg'] === (string) $r)>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-28 shrink-0">
                    <label class="label">Tahun</label>
                    <select class="field" name="year">
                        <option value="">All</option>
                        @foreach($options['years'] as $y)
                            <option value="{{ $y }}" @selected($filters['year'] === (string) $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[220px]">
                    <label class="label">Project</label>
                    <select class="field" name="project">
                        <option value="">All Project</option>
                        <option value="__none" @selected($filters['project'] === '__none')>— Tanpa project —</option>
                        @foreach($projectNames as $pid => $pname)
                            <option value="{{ $pid }}" @selected($filters['project'] === (string) $pid)>{{ $pid }} — {{ $pname }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-2 mt-2">
                <div class="w-40 shrink-0">
                    <label class="label">Tgl Kirim dari</label>
                    <input class="field" type="date" name="delv_from" value="{{ $filters['delv_from'] }}">
                </div>
                <div class="w-40 shrink-0">
                    <label class="label">Tgl Kirim s/d</label>
                    <input class="field" type="date" name="delv_to" value="{{ $filters['delv_to'] }}">
                </div>
                <div class="w-40 shrink-0">
                    <label class="label">Tgl Beli dari</label>
                    <input class="field" type="date" name="purc_from" value="{{ $filters['purc_from'] }}">
                </div>
                <div class="w-40 shrink-0">
                    <label class="label">Tgl Beli s/d</label>
                    <input class="field" type="date" name="purc_to" value="{{ $filters['purc_to'] }}">
                </div>
            </div>
        </details>

        <div class="flex items-center gap-2 mt-3">
            <button type="submit" class="btn-primary rounded-xl px-3 py-2 text-xs">Tarik Data</button>
            <a href="{{ route('report.data') }}" class="btn-soft rounded-xl px-3 py-2 text-xs">Reset</a>
            <a href="{{ route('report.data.export', request()->query()) }}"
               class="btn-soft rounded-xl px-3 py-2 text-xs gap-1.5"
               title="Unduh laporan .xlsx — sheet Data Asset + Ringkasan, mengikuti filter di atas">
                <span>⬇</span> Export Excel
            </a>
            @if($activeCount)
                <span class="text-[11px] text-slate-400">{{ $activeCount }} filter aktif</span>
            @endif
        </div>
    </form>

    {{-- ── Info baris ──────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
        <span class="text-xs text-slate-500">
            {{ number_format($assets->total()) }} baris
            @if($assets->total() !== $summary['total'])
                <span class="text-slate-400">(filtered dari {{ number_format($summary['total']) }})</span>
            @endif
        </span>
        <span class="text-xs text-slate-400">Halaman {{ $assets->currentPage() }} / {{ max($assets->lastPage(), 1) }}</span>
    </div>

    {{-- ── Tabel: semua kolom ast_main ─────────────────────────────── --}}
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="text-left text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 font-medium">#</th>
                    @foreach([
                        'id' => 'ID',
                        'ast_type' => 'Type',
                        'ast_brand' => 'Brand',
                        'ast_brandmodel' => 'Model',
                        'ast_prodyear' => 'Tahun',
                        'ast_serial' => 'Serial',
                        'ast_vendid' => 'Vendor',
                        'ast_username' => 'User',
                        'ast_userreg' => 'Region',
                        'ast_userloc' => 'Lokasi',
                        'ast_userlocdet' => 'Detail Lokasi',
                        'ast_cond' => 'Kondisi',
                        'ast_delvdate' => 'Tgl Kirim',
                        'ast_purcdate' => 'Tgl Beli',
                        'ast_stat' => 'Status',
                        'ast_pjctid' => 'Project',
                        'ast_docid' => 'Doc ID',
                    ] as $column => $heading)
                        <th class="px-3 py-2 font-medium whitespace-nowrap">
                            <a href="{{ $sortUrl($column) }}" class="hover:text-slate-600">
                                {{ $heading }}
                                <span class="text-[9px] text-teal-500">{{ $sortMark($column) }}</span>
                            </a>
                        </th>
                    @endforeach
                    <th class="px-3 py-2 font-medium">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($assets as $i => $a)
                <tr class="hover:bg-slate-50/60 transition">
                    <td class="px-4 py-2 text-slate-400">{{ ($assets->currentPage()-1)*$assets->perPage()+$i+1 }}</td>
                    <td class="px-3 py-2 font-mono text-[10px] text-slate-700 whitespace-nowrap">{{ $a->id }}</td>
                    <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $a->ast_type ?: '-' }}</td>
                    <td class="px-3 py-2 font-semibold text-slate-800 whitespace-nowrap">{{ $a->ast_brand ?: '-' }}</td>
                    <td class="px-3 py-2 text-slate-500">{{ $a->ast_brandmodel ?: '-' }}</td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ $a->ast_prodyear ?: '-' }}</td>
                    <td class="px-3 py-2 font-mono text-[10px] text-slate-500 whitespace-nowrap">{{ $a->ast_serial ?: '-' }}</td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ $a->ast_vendid ?: '-' }}</td>
                    <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $a->ast_username ?: '-' }}</td>
                    <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $a->ast_userreg ?: '-' }}</td>
                    <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $a->ast_userloc ?: '-' }}</td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ $a->ast_userlocdet ?: '-' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $a->condBadgeClass() }}">
                            {{ $a->ast_cond ?: '-' }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ $a->ast_delvdate?->format('d M Y') ?: '-' }}</td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ $a->ast_purcdate?->format('d M Y') ?: '-' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $a->statBadgeClass() }}">
                            {{ $a->ast_stat ?: '-' }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-[10px] text-slate-500 max-w-[200px] truncate"
                        title="{{ $a->ast_pjctid ? ($projectNames[$a->ast_pjctid] ?? $a->ast_pjctid) : '' }}">
                        {{ $a->ast_pjctid ? ($projectNames[$a->ast_pjctid] ?? $a->ast_pjctid) : '-' }}
                    </td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ $a->ast_docid ?: '-' }}</td>
                    <td class="px-3 py-2 text-[10px] text-slate-400 max-w-[220px] truncate" title="{{ $a->ast_misc }}">
                        {{ $a->ast_misc ?: '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="19" class="px-4 py-12 text-center text-slate-400">Tidak ada data asset yang cocok dengan filter.</td>
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
