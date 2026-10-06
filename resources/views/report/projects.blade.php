@extends('layouts.app', ['title' => 'Report: Projects', 'heading' => 'Report — Data Project'])

@php
    /** Filter lanjutan dibuka otomatis kalau salah satunya sedang dipakai. */
    $advancedKeys = ['client', 'area', 'year', 'start_from', 'start_to', 'end_from', 'end_to', 'archived'];
    $advancedOpen = collect($advancedKeys)->contains(fn ($k) => ($filters[$k] ?? '') !== '');
    $activeCount = collect($filters)->except(['sort', 'dir'])->filter(fn ($v) => $v !== '')->count();

    /** Link header tabel: pertahankan semua filter, tukar kolom/arah sorting. */
    $sortUrl = function (string $column) use ($filters) {
        $dir = ($filters['sort'] === $column && $filters['dir'] === 'asc') ? 'desc' : 'asc';

        return route('report.projects', array_merge(request()->query(), ['sort' => $column, 'dir' => $dir, 'page' => null]));
    };

    $sortMark = fn (string $column) => $filters['sort'] === $column
        ? ($filters['dir'] === 'asc' ? '▲' : '▼')
        : '';

    $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');

    /** Penanda sel kosong — nilai/tanggal/kontrak yang perlu dikonfirmasi. */
    $empty = '<span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold bg-amber-100 text-amber-700">kosong</span>';
@endphp

@section('content')

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

    {{-- ── Filter ──────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('report.projects') }}" class="px-4 py-3 border-b border-slate-100">
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[200px]">
                <label class="label">Search</label>
                <input class="field" type="text" name="search" value="{{ $filters['search'] }}"
                    placeholder="ID / no kontrak / nama / client / area / catatan...">
            </div>
            <div class="w-32 shrink-0">
                <label class="label">Divisi</label>
                <select class="field" name="div">
                    <option value="">All Divisi</option>
                    @foreach($options['divs'] as $d)
                        <option value="{{ $d }}" @selected($filters['div'] === (string) $d)>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36 shrink-0">
                <label class="label">Status</label>
                <select class="field" name="stat">
                    <option value="">All Status</option>
                    @foreach($options['stats'] as $code => $label)
                        <option value="{{ $code }}" @selected($filters['stat'] === (string) $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-28 shrink-0">
                <label class="label">Type</label>
                <select class="field" name="type">
                    <option value="">All</option>
                    @foreach($options['types'] as $t)
                        <option value="{{ $t }}" @selected($filters['type'] === (string) $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-48 shrink-0">
                <label class="label">Kelengkapan</label>
                <select class="field" name="gap">
                    <option value="">Semua</option>
                    @foreach($options['gaps'] as $key => $label)
                        <option value="{{ $key }}" @selected($filters['gap'] === $key)>{{ $label }}</option>
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
                <span class="text-slate-400 font-normal">— client, area, tahun, rentang tanggal, arsip</span>
            </summary>

            <div class="flex flex-wrap items-end gap-2 mt-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="label">Client</label>
                    <select class="field" name="client">
                        <option value="">All Client</option>
                        @foreach($options['clients'] as $c)
                            <option value="{{ $c }}" @selected($filters['client'] === (string) $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-36 shrink-0">
                    <label class="label">Area</label>
                    <select class="field" name="area">
                        <option value="">All Area</option>
                        @foreach($options['areas'] as $a)
                            <option value="{{ $a }}" @selected($filters['area'] === (string) $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-28 shrink-0">
                    <label class="label">Tahun Kontrak</label>
                    <select class="field" name="year">
                        <option value="">All</option>
                        @foreach($options['years'] as $y)
                            <option value="{{ $y }}" @selected($filters['year'] === (string) $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-48 shrink-0">
                    <label class="label">Arsip</label>
                    <select class="field" name="archived">
                        <option value="">Tanpa yang diarsipkan</option>
                        <option value="with" @selected($filters['archived'] === 'with')>Termasuk yang diarsipkan</option>
                        <option value="only" @selected($filters['archived'] === 'only')>Hanya yang diarsipkan</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-2 mt-2">
                <div class="w-40 shrink-0">
                    <label class="label">Mulai dari</label>
                    <input class="field" type="date" name="start_from" value="{{ $filters['start_from'] }}">
                </div>
                <div class="w-40 shrink-0">
                    <label class="label">Mulai s/d</label>
                    <input class="field" type="date" name="start_to" value="{{ $filters['start_to'] }}">
                </div>
                <div class="w-40 shrink-0">
                    <label class="label">Selesai dari</label>
                    <input class="field" type="date" name="end_from" value="{{ $filters['end_from'] }}">
                </div>
                <div class="w-40 shrink-0">
                    <label class="label">Selesai s/d</label>
                    <input class="field" type="date" name="end_to" value="{{ $filters['end_to'] }}">
                </div>
            </div>
        </details>

        <div class="flex items-center gap-2 mt-3">
            <button type="submit" class="btn-primary rounded-xl px-3 py-2 text-xs">Tarik Data</button>
            <a href="{{ route('report.projects') }}" class="btn-soft rounded-xl px-3 py-2 text-xs">Reset</a>
            <a href="{{ route('report.projects.export', request()->query()) }}"
               class="btn-soft rounded-xl px-3 py-2 text-xs gap-1.5"
               title="Unduh laporan .xlsx — sheet Data Project + Ringkasan, mengikuti filter di atas">
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
            {{ number_format($projects->total()) }} project
            @if($projects->total() !== $summary['total'])
                <span class="text-slate-400">(filtered dari {{ number_format($summary['total']) }})</span>
            @endif
        </span>
        <span class="text-xs text-slate-400">Halaman {{ $projects->currentPage() }} / {{ max($projects->lastPage(), 1) }}</span>
    </div>

    {{-- ── Tabel: semua kolom pjct_main ────────────────────────────── --}}
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="text-left text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 font-medium">#</th>
                    @foreach([
                        'id' => 'ID',
                        'pjct_contract' => 'No Kontrak',
                        'pjct_codate' => 'Tgl Kontrak',
                        'pjct_div' => 'Divisi',
                        'pjct_name' => 'Nama Project',
                        'pjct_type' => 'Type',
                        'pjct_client' => 'Client',
                        'pjct_area' => 'Area',
                        'pjct_value' => 'Nilai Kontrak',
                        'pjct_costart' => 'Mulai',
                        'pjct_totalperiod' => 'Durasi',
                        'pjct_coend_m' => 'Selesai',
                        'pjct_status' => 'Status',
                    ] as $column => $heading)
                        <th class="px-3 py-2 font-medium whitespace-nowrap">
                            <a href="{{ $sortUrl($column) }}" class="hover:text-slate-600">
                                {{ $heading }}
                                <span class="text-[9px] text-teal-500">{{ $sortMark($column) }}</span>
                            </a>
                        </th>
                    @endforeach
                    <th class="px-3 py-2 font-medium">Asset</th>
                    <th class="px-3 py-2 font-medium">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($projects as $i => $p)
                <tr class="{{ $p->trashed() ? 'opacity-50 bg-slate-50' : 'hover:bg-slate-50/60' }} transition">
                    <td class="px-4 py-2 text-slate-400">{{ ($projects->currentPage()-1)*$projects->perPage()+$i+1 }}</td>
                    <td class="px-3 py-2 font-mono text-[10px] text-slate-700 whitespace-nowrap">{{ $p->id }}</td>
                    <td class="px-3 py-2 font-mono text-[10px] text-slate-500 max-w-[180px] break-words">{!! $p->pjct_contract ? e($p->pjct_contract) : $empty !!}</td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ $p->pjct_codate?->format('d M Y') ?: '-' }}</td>
                    <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $p->pjct_div }}</td>
                    <td class="px-3 py-2 font-semibold text-slate-800 min-w-[240px]">
                        {{ $p->pjct_name }}
                        @if($p->trashed())<span class="ml-1 inline-flex items-center rounded px-1 py-0.5 text-[9px] font-medium bg-slate-200 text-slate-500">Archived</span>@endif
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        @if($p->pjct_type)
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $p->typeBadgeClass() }}">{{ $p->pjct_type }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-3 py-2 text-slate-500 max-w-[180px] truncate" title="{{ $p->pjct_client }}">{{ $p->pjct_client ?: '-' }}</td>
                    <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $p->pjct_area ?: '-' }}</td>
                    <td class="px-3 py-2 font-semibold text-slate-800 whitespace-nowrap text-right">{!! $p->pjct_value ? e($rp($p->pjct_value)) : $empty !!}</td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{!! $p->pjct_costart ? e($p->pjct_costart->format('d M Y')) : $empty !!}</td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ $p->pjct_totalperiod ? $p->pjct_totalperiod.' bln' : '-' }}</td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{!! $p->pjct_coend_m ? e($p->pjct_coend_m->format('d M Y')) : $empty !!}</td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $p->statusBadgeClass() }}">
                            {{ $p->statusLabel() }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-center text-slate-500">{{ $p->assets_count ?: '-' }}</td>
                    <td class="px-3 py-2 text-[10px] text-slate-400 max-w-[260px] truncate" title="{{ $p->pjct_misc }}">
                        {{ $p->pjct_misc ?: '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="16" class="px-4 py-12 text-center text-slate-400">Tidak ada data project yang cocok dengan filter.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($projects->hasPages())
    <div class="px-4 py-3 border-t border-slate-100">
        {{ $projects->links() }}
    </div>
    @endif

</div>

@endsection
