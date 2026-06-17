@extends('layouts.app', ['title' => 'Monitoring EQT', 'heading' => 'Monitoring Project Equipment & Technology'])

@section('content')
{{-- ── Page Header Actions ──────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        @if($lastImport)
            <span class="text-xs text-slate-500 bg-slate-100 rounded-xl px-3 py-1.5 inline-flex items-center gap-1">
                Last import: <strong>{{ $lastImport->created_at->format('d/m/Y H:i') }}</strong> by {{ $lastImport->user?->name ?? 'system' }}
            </span>
        @endif
    </div>
    @if(auth()->user()->isAdmin())
    <div class="flex gap-2">
        <a href="{{ route('monitoring.export') }}" class="btn-soft text-xs px-3 py-1.5">⬇ Export Excel</a>
        <a href="{{ route('monitoring.import.show') }}" class="btn-primary text-xs px-3 py-1.5">⬆ Import Excel</a>
    </div>
    @endif
</div>
@php
$rp = function($n) {
    if (!$n || $n == 0) return '-';
    if ($n >= 1e12) return 'Rp ' . number_format($n / 1e12, 2) . 'T';
    if ($n >= 1e9)  return 'Rp ' . number_format($n / 1e9, 2) . 'M';
    if ($n >= 1e6)  return 'Rp ' . number_format($n / 1e6, 0) . ' Jt';
    return 'Rp ' . number_format($n, 0, ',', '.');
};
$isAdmin = auth()->user()->isAdmin();
$EQ_DOCS = ['HOA/SPK','KAK/RKST','RAB/BOQ','JUSTIFIKASI','PO','BAST','BA-SAT'];
$TC_DOCS = ['HOA/SPK','KAK/RKST','RAB','SOP'];
$statuses  = ['ON GOING','PENDING','NO KONTRAK','OUTSTANDING'];
$pjStatuses = ['EXPIRED','SOON','OK','N/A'];
$maintStatuses = ['ON GOING','OUTSTANDING','SELESAI','PENDING','BELUM'];
$lobs = ['Rental','Supply','Supplies','Maintenance','OM'];
@endphp

{{-- ── KPI Cards ─────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
    {{-- 1: Total Projects --}}
    <div class="bg-slate-50 rounded-xl border border-slate-200 shadow-sm p-4">
        <div class="flex items-start justify-between">
            <p class="text-2xl font-black tracking-tight text-slate-800">{{ $kpi['total'] }}</p>
            <svg class="h-5 w-5 text-slate-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 01-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-2.25z" />
            </svg>
        </div>
        <p class="text-xs text-slate-500 mt-1">Total Projects</p>
        <p class="text-[10px] text-slate-400 mt-0.5">EQ {{ $kpi['eq_count'] }} · TC {{ $kpi['tech_count'] }} · HO {{ $kpi['ho_count'] }} · Veh {{ $vehicles->count() }} · Maint {{ $maintenances->count() }}</p>
    </div>

    {{-- 2: On Going --}}
    <div class="bg-green-50 rounded-xl border border-green-200 shadow-sm p-4">
        <div class="flex items-start justify-between">
            <p class="text-2xl font-black tracking-tight text-green-600">{{ $kpi['ongoing'] }}</p>
            <svg class="h-5 w-5 text-green-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <p class="text-xs text-slate-500 mt-1">On Going</p>
        <p class="text-[10px] text-green-500 mt-0.5">Active projects</p>
    </div>

    {{-- 3: Critical Status --}}
    @php $kritis = $kpi['attn'] + $kpi['pending']; @endphp
    <div class="rounded-xl border shadow-sm p-4 {{ $kritis > 0 ? 'bg-red-50 border-red-300' : 'bg-white border-slate-200' }}">
        <div class="flex items-start justify-between">
            <p class="text-2xl font-black tracking-tight {{ $kritis > 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $kritis }}</p>
            <svg class="h-5 w-5 {{ $kritis > 0 ? 'text-red-400' : 'text-slate-300' }} shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
        </div>
        <p class="text-xs text-slate-500 mt-1">Critical Status</p>
        <p class="text-[10px] {{ $kritis > 0 ? 'text-red-400' : 'text-slate-400' }} mt-0.5">Projects with No Contract / Outstanding / Pending</p>
    </div>

    {{-- 4: Incomplete Docs --}}
    <div class="rounded-xl border shadow-sm p-4 {{ $kpi['doc_incomplete'] > 0 ? 'bg-amber-50 border-amber-300' : 'bg-white border-slate-200' }}">
        <div class="flex items-start justify-between">
            <p class="text-2xl font-black tracking-tight {{ $kpi['doc_incomplete'] > 0 ? 'text-amber-600' : 'text-slate-800' }}">{{ $kpi['doc_incomplete'] }}</p>
            <svg class="h-5 w-5 {{ $kpi['doc_incomplete'] > 0 ? 'text-amber-400' : 'text-slate-300' }} shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
        </div>
        <p class="text-xs text-slate-500 mt-1">Incomplete Docs</p>
        <p class="text-[10px] {{ $kpi['doc_incomplete'] > 0 ? 'text-amber-400' : 'text-slate-400' }} mt-0.5">Documents below 100%</p>
    </div>

    {{-- 5: Tax Alert --}}
    <div class="rounded-xl border shadow-sm p-4 {{ $kpi['pajak_alert'] > 0 ? 'bg-rose-50 border-rose-300' : 'bg-white border-slate-200' }}">
        <div class="flex items-start justify-between">
            <p class="text-2xl font-black tracking-tight {{ $kpi['pajak_alert'] > 0 ? 'text-rose-600' : 'text-slate-800' }}">{{ $kpi['pajak_alert'] }}</p>
            <svg class="h-5 w-5 {{ $kpi['pajak_alert'] > 0 ? 'text-rose-400' : 'text-slate-300' }} shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            </svg>
        </div>
        <p class="text-xs text-slate-500 mt-1">Tax Alert</p>
        <p class="text-[10px] {{ $kpi['pajak_alert'] > 0 ? 'text-rose-400' : 'text-slate-400' }} mt-0.5">Expired / Soon</p>
    </div>

    {{-- 6: Maintenance Due --}}
    <div class="rounded-xl border shadow-sm p-4 {{ $kpi['maint_due'] > 0 ? 'bg-orange-50 border-orange-300' : 'bg-white border-slate-200' }}">
        <div class="flex items-start justify-between">
            <p class="text-2xl font-black tracking-tight {{ $kpi['maint_due'] > 0 ? 'text-orange-600' : 'text-slate-800' }}">{{ $kpi['maint_due'] }}</p>
            <svg class="h-5 w-5 {{ $kpi['maint_due'] > 0 ? 'text-orange-400' : 'text-slate-300' }} shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l5.654-4.654m5.624-5.623l2.496-3.03c.317-.384.79-.55 1.264-.518l1.512.233 1.554-1.553.233 1.512c.032.474-.134.947-.518 1.264l-3.03 2.496m-5.624 5.623l-3.03 2.496" />
            </svg>
        </div>
        <p class="text-xs text-slate-500 mt-1">Maintenance Due</p>
        <p class="text-[10px] {{ $kpi['maint_due'] > 0 ? 'text-orange-400' : 'text-slate-400' }} mt-0.5">Due less than 30 days / Outstanding</p>
    </div>
</div>

{{-- ── Filter Bar ───────────────────────────────────────────── --}}
<div class="bg-white border border-slate-200 rounded-xl px-4 py-3 mb-4">
    <form method="GET" action="{{ route('monitoring.index') }}" class="flex flex-wrap gap-2 items-center">
        <select name="year" onchange="this.form.submit()" class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:border-blue-400 focus:outline-none">
            <option value="all" @selected($yearFilter==='all')>All Years</option>
            @foreach([2023,2024,2025,2026] as $y)
                <option value="{{ $y }}" @selected($yearFilter===$y)>{{ $y }}</option>
            @endforeach
        </select>
        <select name="status" onchange="this.form.submit()" class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:border-blue-400 focus:outline-none">
            <option value="all" @selected($statusFilter==='all')>All Status</option>
            @foreach($statuses as $s)
                <option value="{{ $s }}" @selected($statusFilter===$s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="doc" onchange="this.form.submit()" class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:border-blue-400 focus:outline-none">
            <option value="all" @selected($docFilter==='all')>All Docs</option>
            <option value="complete" @selected($docFilter==='complete')>Complete</option>
            <option value="partial" @selected($docFilter==='partial')>Partial</option>
            <option value="none" @selected($docFilter==='none')>None</option>
        </select>
        <input type="text" name="search" value="{{ $search }}" placeholder="Search name / partner / area..."
            class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs bg-white focus:border-blue-400 focus:outline-none min-w-[180px] flex-1">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">Search</button>
        @if($isAdmin)
            @php $archivedUrl = request()->fullUrlWithQuery(['archived' => $showArchived ? '0' : '1']); @endphp
            <a href="{{ $archivedUrl }}" class="text-xs px-2.5 py-1.5 rounded-lg border transition
                {{ $showArchived ? 'bg-amber-50 border-amber-300 text-amber-700 font-semibold' : 'border-slate-200 text-slate-500 hover:border-slate-300' }}">
                {{ $showArchived ? '✓ Show Archived' : 'Show Archived' }}
            </a>
        @endif
        @if($yearFilter!=='all'||$statusFilter!=='all'||$docFilter!=='all'||$search)
            <a href="{{ route('monitoring.index') }}" class="text-xs text-slate-500 hover:text-slate-800 px-2 py-1.5">Reset</a>
        @endif
    </form>
</div>

@if(session('success'))
    <div class="mb-3 rounded-lg bg-green-50 border border-green-200 px-4 py-2.5 text-sm text-green-700">{{ session('success') }}</div>
@endif

{{-- ── Tab Navigation ───────────────────────────────────────── --}}
<div class="flex border-b border-slate-200 overflow-x-auto mb-1" id="mon-tabs">
    <button type="button"
        class="mon-tab whitespace-nowrap px-4 py-2.5 text-sm font-medium -mb-px border-b-2 border-blue-600 text-blue-700 transition"
        data-target="tab-all">
        All
        <span class="ml-1.5 bg-slate-100 text-slate-500 rounded-full px-1.5 py-0.5 text-[10px] font-bold">{{ $kpi['total'] }}</span>
    </button>
    <button type="button"
        class="mon-tab whitespace-nowrap px-4 py-2.5 text-sm font-medium -mb-px border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition"
        data-target="tab-eq">
        Equipment
        <span class="ml-1.5 bg-blue-50 text-blue-500 rounded-full px-1.5 py-0.5 text-[10px] font-bold">{{ $eqProjects->count() }}</span>
    </button>
    <button type="button"
        class="mon-tab whitespace-nowrap px-4 py-2.5 text-sm font-medium -mb-px border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition"
        data-target="tab-tech">
        Technology
        <span class="ml-1.5 bg-violet-50 text-violet-500 rounded-full px-1.5 py-0.5 text-[10px] font-bold">{{ $techProjects->count() }}</span>
    </button>
    <button type="button"
        class="mon-tab whitespace-nowrap px-4 py-2.5 text-sm font-medium -mb-px border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition"
        data-target="tab-ho">
        Hand Over
        <span class="ml-1.5 bg-emerald-50 text-emerald-500 rounded-full px-1.5 py-0.5 text-[10px] font-bold">{{ $handovers->count() }}</span>
    </button>
    <button type="button"
        class="mon-tab whitespace-nowrap px-4 py-2.5 text-sm font-medium -mb-px border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition"
        data-target="tab-maint">
        Maintenance & Tax
        @php $maintTotal = $vehicles->count() + $maintenances->count(); @endphp
        <span class="ml-1.5 bg-orange-50 text-orange-500 rounded-full px-1.5 py-0.5 text-[10px] font-bold">{{ $maintTotal }}</span>
    </button>
    <button type="button"
        class="mon-tab whitespace-nowrap px-4 py-2.5 text-sm font-medium -mb-px border-b-2 border-transparent text-slate-500 hover:text-slate-800 transition"
        data-target="tab-sum">
        Summary
    </button>
    @if($isAdmin)
        <button type="button"
            class="mon-tab whitespace-nowrap px-4 py-2.5 text-sm font-medium -mb-px border-b-2 border-transparent text-slate-500 hover:text-slate-800 ml-auto transition"
            data-target="tab-log">History</button>
    @endif
</div>

{{-- ── Tab Contents ─────────────────────────────────────────── --}}

{{-- ALL --}}
<div id="tab-all" class="mon-content mt-4 space-y-6">
    @include('monitoring._table_eq', ['rows' => $eqProjects, 'rp' => $rp, 'isAdmin' => $isAdmin, 'EQ_DOCS' => $EQ_DOCS])
    @include('monitoring._table_tech', ['rows' => $techProjects, 'rp' => $rp, 'isAdmin' => $isAdmin, 'TC_DOCS' => $TC_DOCS])
    @include('monitoring._table_ho', ['rows' => $handovers, 'isAdmin' => $isAdmin])
</div>

{{-- EQUIPMENT --}}
<div id="tab-eq" class="mon-content hidden mt-4">
    @include('monitoring._table_eq', ['rows' => $eqProjects, 'rp' => $rp, 'isAdmin' => $isAdmin, 'EQ_DOCS' => $EQ_DOCS])
</div>

{{-- TECHNOLOGY --}}
<div id="tab-tech" class="mon-content hidden mt-4">
    @include('monitoring._table_tech', ['rows' => $techProjects, 'rp' => $rp, 'isAdmin' => $isAdmin, 'TC_DOCS' => $TC_DOCS])
</div>

{{-- HAND OVER --}}
<div id="tab-ho" class="mon-content hidden mt-4">
    @include('monitoring._table_ho', ['rows' => $handovers, 'isAdmin' => $isAdmin])
</div>

{{-- MAINTENANCE & TAX --}}
<div id="tab-maint" class="mon-content hidden mt-4 space-y-4">
    {{-- Vehicle Tax Status --}}
    <div class="bg-white border border-slate-200 border-l-4 border-l-orange-400 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <h3 class="text-sm font-bold text-slate-800">Vehicle Tax Status</h3>
                <span class="text-xs text-slate-400">{{ $vehicles->count() }} units</span>
                @php $exp = $vehicles->where('status_pajak','EXPIRED')->count(); $soon = $vehicles->where('status_pajak','SOON')->count(); $ok = $vehicles->where('status_pajak','OK')->count(); @endphp
                @if($exp > 0)<span class="text-[10px] font-semibold text-red-600 bg-red-50 px-1.5 py-0.5 rounded">{{ $exp }} expired</span>@endif
                @if($soon > 0)<span class="text-[10px] font-semibold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded">{{ $soon }} soon</span>@endif
            </div>
            @if($isAdmin)
                <button type="button" class="text-xs text-blue-600 hover:text-blue-800 font-semibold" onclick="openAddModal('vehicle')">+ Add</button>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="text-left text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="pb-2 pr-3">No</th>
                        <th class="pb-2 pr-3 font-semibold">License Plate</th>
                        <th class="pb-2 pr-3">Vehicle Type</th>
                        <th class="pb-2 pr-3">Customer/User</th>
                        <th class="pb-2 pr-3">Vendor</th>
                        <th class="pb-2 pr-3">Contract No.</th>
                        <th class="pb-2 pr-3">PKB Until</th>
                        <th class="pb-2 pr-3">PKB Value</th>
                        <th class="pb-2 pr-3">Status</th>
                        <th class="pb-2 pr-3">Notes</th>
                        @if($isAdmin)<th class="pb-2"></th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($vehicles as $i => $v)
                        <tr class="{{ $v->trashed() ? 'opacity-50 bg-slate-100/60' : ($v->status_pajak==='EXPIRED' ? 'bg-red-50/40' : ($v->status_pajak==='SOON' ? 'bg-yellow-50/30' : '')) }}">
                            <td class="py-1.5 pr-3 text-slate-400">{{ $i+1 }}</td>
                            <td class="py-1.5 pr-3 font-semibold whitespace-nowrap">
                                {{ $v->nopol }}
                                @if($v->trashed())<span class="ml-1 inline-flex items-center rounded px-1 py-0.5 text-[9px] font-medium bg-slate-200 text-slate-500">Archived</span>@endif
                            </td>
                            <td class="py-1.5 pr-3 text-slate-600">{{ $v->jenis ?: '-' }}</td>
                            <td class="py-1.5 pr-3">{{ $v->customer ?: '-' }}</td>
                            <td class="py-1.5 pr-3 text-slate-500">{{ $v->vendor ?: '-' }}</td>
                            <td class="py-1.5 pr-3 text-slate-400 max-w-[160px] truncate" title="{{ $v->no_kontrak }}">{{ $v->no_kontrak ?: '-' }}</td>
                            <td class="py-1.5 pr-3 font-semibold whitespace-nowrap">{{ $v->pkb_date ?: '-' }}</td>
                            <td class="py-1.5 pr-3 whitespace-nowrap">{{ $v->nilai_pkb ? $rp($v->nilai_pkb) : '-' }}</td>
                            <td class="py-1.5 pr-3 whitespace-nowrap"><span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $v->statusPajakBadgeClass() }}">{{ $v->status_pajak }}</span></td>
                            <td class="py-1.5 pr-3 text-slate-500 max-w-[160px] text-[11px]">{{ $v->keterangan ?: '' }}</td>
                            @if($isAdmin)
                                <td class="py-1.5 whitespace-nowrap text-right">
                                    @if($v->trashed())
                                        <form method="POST" action="{{ route('monitoring.restore', ['type'=>'vehicles','id'=>$v->id]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-purple-600 hover:text-purple-800 font-medium">Restore</button>
                                        </form>
                                    @else
                                        <button type="button" onclick="openEditModal('vehicle', {{ $v->id }}, {{ $v->toJson() }})" class="text-xs text-blue-600 hover:text-blue-800 font-medium mr-2">Edit</button>
                                        <form method="POST" action="{{ route('monitoring.destroy', ['type'=>'vehicles','id'=>$v->id]) }}" class="inline" onsubmit="return confirm('Archive this vehicle?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-slate-400 hover:text-amber-700 font-medium">Archive</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="11" class="py-6 text-center text-slate-400">No vehicle data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Equipment Maintenance --}}
    <div class="bg-white border border-slate-200 border-l-4 border-l-slate-400 rounded-xl p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <h3 class="text-sm font-bold text-slate-800">Equipment Maintenance</h3>
                <span class="text-xs text-slate-400">{{ $maintenances->count() }} items</span>
            </div>
            @if($isAdmin)
                <button type="button" class="text-xs text-blue-600 hover:text-blue-800 font-semibold" onclick="openAddModal('maintenance')">+ Add</button>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="text-left text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="pb-2 pr-3">No</th>
                        <th class="pb-2 pr-3">Equipment / Vehicle</th>
                        <th class="pb-2 pr-3">Type</th>
                        <th class="pb-2 pr-3">Area</th>
                        <th class="pb-2 pr-3">Service Partner</th>
                        <th class="pb-2 pr-3">Contract No.</th>
                        <th class="pb-2 pr-3">Last Service</th>
                        <th class="pb-2 pr-3">Next Schedule</th>
                        <th class="pb-2 pr-3">Status</th>
                        <th class="pb-2 pr-3">Notes</th>
                        @if($isAdmin)<th class="pb-2"></th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($maintenances as $i => $m)
                        <tr class="{{ $m->trashed() ? 'opacity-50 bg-slate-100/60' : '' }}">
                            <td class="py-1.5 pr-3 text-slate-400">{{ $i+1 }}</td>
                            <td class="py-1.5 pr-3 font-medium max-w-[200px]">
                                {{ $m->nama_alat }}
                                @if($m->trashed())<span class="ml-1 inline-flex items-center rounded px-1 py-0.5 text-[9px] font-medium bg-slate-200 text-slate-500">Archived</span>@endif
                            </td>
                            <td class="py-1.5 pr-3 text-slate-500">{{ $m->tipe_alat ?: '-' }}</td>
                            <td class="py-1.5 pr-3 whitespace-nowrap">{{ $m->area ?: '-' }}</td>
                            <td class="py-1.5 pr-3">{{ $m->mitra ?: '-' }}</td>
                            <td class="py-1.5 pr-3 text-slate-400 max-w-[160px] truncate" title="{{ $m->no_kontrak }}">{{ $m->no_kontrak ?: '-' }}</td>
                            <td class="py-1.5 pr-3 whitespace-nowrap">{{ $m->last_service ?: '-' }}</td>
                            <td class="py-1.5 pr-3 font-semibold whitespace-nowrap">{{ $m->next_service ?: '-' }}</td>
                            <td class="py-1.5 pr-3 whitespace-nowrap"><span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $m->statusBadgeClass() }}">{{ $m->status_maint }}</span></td>
                            <td class="py-1.5 pr-3 text-slate-500 max-w-[160px] text-[11px]">{{ $m->keterangan ?: '' }}</td>
                            @if($isAdmin)
                                <td class="py-1.5 whitespace-nowrap text-right">
                                    @if($m->trashed())
                                        <form method="POST" action="{{ route('monitoring.restore', ['type'=>'maintenances','id'=>$m->id]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-purple-600 hover:text-purple-800 font-medium">Restore</button>
                                        </form>
                                    @else
                                        <button type="button" onclick="openEditModal('maintenance', {{ $m->id }}, {{ $m->toJson() }})" class="text-xs text-blue-600 hover:text-blue-800 font-medium mr-2">Edit</button>
                                        <form method="POST" action="{{ route('monitoring.destroy', ['type'=>'maintenances','id'=>$m->id]) }}" class="inline" onsubmit="return confirm('Archive this item?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-slate-400 hover:text-amber-700 font-medium">Archive</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="11" class="py-6 text-center text-slate-400">No maintenance data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- SUMMARY --}}
<div id="tab-sum" class="mon-content hidden mt-4">
    @php
        $allProj = $eqProjects->merge($techProjects)->values();
        $nilaiTotal = $allProj->sum('nilai_pekerjaan');
        $mitraTotal = $allProj->sum('nilai_mitra');
        $opexTotal  = $allProj->sum('serapan');
        $issues = $allProj->whereIn('status', ['NO KONTRAK','OUTSTANDING']);
        $expiredVeh = $vehicles->where('status_pajak','EXPIRED');
    @endphp
    @php
        $docFull = $allProj->filter(fn($p)=>($p->docScore()['score']??-1)===1.0)->count();
        $docPart = $allProj->filter(function($p){ $s=$p->docScore()['score']??-1; return $s>0&&$s<1; })->count();
        $docNone = $allProj->filter(fn($p)=>($p->docScore()['score']??-1)===0.0)->count();
        $docNA   = $allProj->filter(fn($p)=>($p->docScore()['score']??-1)===-1)->count();
        $docTotal = max(1, $allProj->count());
        $vTotal  = max(1,$vehicles->count());
    @endphp
    <div class="grid gap-4 md:grid-cols-2">
        {{-- Contract Value — full-width hero bar --}}
        <div class="bg-white border border-slate-200 rounded-xl p-4 md:col-span-2">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Contract Value</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach([
                    ['Contract Value',      $rp($nilaiTotal), 'bg-blue-50 text-blue-700'],
                    ['Partner Value',   $rp($mitraTotal),  'bg-violet-50 text-violet-700'],
                    ['OPEX Absorption', $rp($opexTotal),   'bg-emerald-50 text-emerald-700'],
                    ['Total Projects',  $kpi['total'],     'bg-slate-100 text-slate-700'],
                ] as [$lbl, $val, $cls])
                <div class="rounded-lg {{ $cls }} px-4 py-4 text-center">
                    <p class="text-[10px] font-semibold uppercase tracking-wide opacity-70 mb-1">{{ $lbl }}</p>
                    <p class="text-sm font-black leading-tight">{{ $val }}</p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Project Status --}}
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Project Status (EQ + Tech)</h3>
            <script type="application/json" id="eqt-status-data">{!! json_encode([
                'labels' => ['On Going','Pending','No Contract','Outstanding'],
                'values' => [
                    $allProj->where('status','ON GOING')->count(),
                    $allProj->where('status','PENDING')->count(),
                    $allProj->where('status','NO KONTRAK')->count(),
                    $allProj->where('status','OUTSTANDING')->count(),
                ],
                'colors' => ['#16a34a','#d97706','#dc2626','#9f1239'],
            ]) !!}</script>
            <div class="mx-auto max-w-[180px] mb-4"><canvas id="eqtStatusChart"></canvas></div>
            <table class="w-full text-xs">
                @foreach([['On Going',$allProj->where('status','ON GOING')->count(),'text-green-700'],['Pending',$allProj->where('status','PENDING')->count(),'text-amber-600'],['No Kontrak',$allProj->where('status','NO KONTRAK')->count(),'text-red-600'],['Outstanding',$allProj->where('status','OUTSTANDING')->count(),'text-rose-800']] as [$lbl,$cnt,$clr])
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="py-1.5 text-slate-600">{{ $lbl }}</td>
                        <td class="py-1.5 text-right font-bold {{ $clr }}">{{ $cnt }}</td>
                    </tr>
                @endforeach
            </table>
        </div>

        {{-- Document Completeness + Vehicle Tax Status --}}
        <div class="bg-white border border-slate-200 rounded-xl p-4 self-start">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Document Completeness</h3>
            @foreach([['Complete (100%)',$docFull,'bg-green-500'],['Partial',$docPart,'bg-amber-400'],['None',$docNone,'bg-red-400'],['N/A',$docNA,'bg-slate-300']] as [$lbl,$cnt,$clr])
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-28 text-xs text-slate-500 shrink-0">{{ $lbl }}</div>
                    <div class="flex-1 h-1.5 rounded-full bg-slate-100">
                        <div class="h-1.5 rounded-full {{ $clr }}" style="width:{{ $docTotal ? round($cnt/$docTotal*100) : 0 }}%"></div>
                    </div>
                    <div class="text-xs font-bold text-slate-700 w-6 text-right">{{ $cnt }}</div>
                </div>
            @endforeach

            <div class="border-t border-slate-100 mt-3 pt-3">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Vehicle Tax Status</h3>
                @foreach([['EXPIRED',$vehicles->where('status_pajak','EXPIRED')->count(),'bg-red-500'],['Soon (≤90 days)',$vehicles->where('status_pajak','SOON')->count(),'bg-amber-400'],['OK',$vehicles->where('status_pajak','OK')->count(),'bg-green-500']] as [$lbl,$cnt,$clr])
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-28 text-xs text-slate-500 shrink-0">{{ $lbl }}</div>
                        <div class="flex-1 h-1.5 rounded-full bg-slate-100">
                            <div class="h-1.5 rounded-full {{ $clr }}" style="width:{{ round($cnt/$vTotal*100) }}%"></div>
                        </div>
                        <div class="text-xs font-bold text-slate-700 w-6 text-right">{{ $cnt }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Alert: projects needing attention --}}
    @if($issues->count())
        <div class="mt-4 border border-red-200 rounded-xl overflow-hidden">
            <div class="bg-red-50 px-4 py-2 border-b border-red-200">
                <p class="text-xs font-semibold text-red-700">{{ $issues->count() }} Projects Needing Attention</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-400 border-b border-slate-100 bg-white">
                        <tr><th class="px-4 py-2">Name</th><th class="py-2 pr-3">Area</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Notes</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach($issues as $p)
                            <tr>
                                <td class="px-4 py-1.5 font-medium max-w-[250px]">{{ $p->nama }}</td>
                                <td class="py-1.5 pr-3 whitespace-nowrap">{{ $p->area }}</td>
                                <td class="py-1.5 pr-3 whitespace-nowrap"><span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $p->statusBadgeClass() }}">{{ $p->statusLabel() }}</span></td>
                                <td class="py-1.5 pr-4 text-slate-500">{{ $p->keterangan }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($expiredVeh->count())
        <div class="mt-4 border border-red-200 rounded-xl overflow-hidden">
            <div class="bg-red-50 px-4 py-2 border-b border-red-200">
                <p class="text-xs font-semibold text-red-700">{{ $expiredVeh->count() }} Vehicles with EXPIRED Tax</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-400 border-b border-slate-100 bg-white">
                        <tr><th class="px-4 py-2">License Plate</th><th class="py-2 pr-3">Type</th><th class="py-2 pr-3">Customer</th><th class="py-2 pr-4">PKB Until</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach($expiredVeh as $v)
                            <tr>
                                <td class="px-4 py-1.5 font-bold">{{ $v->nopol }}</td>
                                <td class="py-1.5 pr-3">{{ $v->jenis }}</td>
                                <td class="py-1.5 pr-3">{{ $v->customer }}</td>
                                <td class="py-1.5 pr-4 text-red-600 font-semibold">{{ $v->pkb_date }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- CHANGE HISTORY (Admin only) --}}
@if($isAdmin)
<div id="tab-log" class="mon-content hidden mt-4">
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <h3 class="text-sm font-bold text-slate-800">Change History</h3>
                <span class="text-xs text-slate-400">200 latest entries</span>
            </div>
            <form method="GET" action="{{ route('monitoring.index') }}" class="flex gap-1.5 flex-wrap">
                <input type="hidden" name="archived" value="{{ $showArchived ? '1' : '0' }}">
                <select name="log_type" onchange="this.form.submit()" class="border border-slate-200 rounded-lg px-2.5 py-1 text-xs text-slate-700 bg-white focus:border-blue-400 focus:outline-none">
                    <option value="">All Types</option>
                    <option value="project" @selected(request('log_type')==='project')>Project</option>
                    <option value="handover" @selected(request('log_type')==='handover')>Hand Over</option>
                    <option value="vehicle" @selected(request('log_type')==='vehicle')>Vehicle</option>
                    <option value="maintenance" @selected(request('log_type')==='maintenance')>Maintenance</option>
                </select>
                <select name="log_action" onchange="this.form.submit()" class="border border-slate-200 rounded-lg px-2.5 py-1 text-xs text-slate-700 bg-white focus:border-blue-400 focus:outline-none">
                    <option value="">All Actions</option>
                    <option value="created" @selected(request('log_action')==='created')>Created</option>
                    <option value="updated" @selected(request('log_action')==='updated')>Updated</option>
                    <option value="archived" @selected(request('log_action')==='archived')>Archived</option>
                    <option value="restored" @selected(request('log_action')==='restored')>Restored</option>
                    <option value="imported" @selected(request('log_action')==='imported')>Imported</option>
                </select>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="text-left text-slate-400 border-b border-slate-100 bg-slate-50/60">
                    <tr>
                        <th class="px-4 py-2 font-medium">Time</th>
                        <th class="py-2 pr-3 font-medium">User</th>
                        <th class="py-2 pr-3 font-medium">Type</th>
                        <th class="py-2 pr-3 font-medium">Record</th>
                        <th class="py-2 pr-3 font-medium">Action</th>
                        <th class="py-2 pr-3 font-medium">Source</th>
                        <th class="py-2 pr-4 font-medium">Changes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($changeLogs as $log)
                        <tr class="hover:bg-slate-50/50 align-top">
                            <td class="px-4 py-1.5 whitespace-nowrap text-slate-400">{{ $log->created_at->format('d/m/y H:i') }}</td>
                            <td class="py-1.5 pr-3 whitespace-nowrap">{{ $log->user?->name ?? '—' }}</td>
                            <td class="py-1.5 pr-3 whitespace-nowrap text-slate-500">{{ $log->recordTypeLabel() }}</td>
                            <td class="py-1.5 pr-3 max-w-[200px] font-medium">{{ $log->record_label }}</td>
                            <td class="py-1.5 pr-3 whitespace-nowrap">
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $log->actionBadgeClass() }}">{{ $log->actionLabel() }}</span>
                            </td>
                            <td class="py-1.5 pr-3 whitespace-nowrap text-slate-400">{{ $log->source }}</td>
                            <td class="py-1.5 pr-4 text-[10px] text-slate-500 max-w-[260px]">
                                @if($log->old_values && $log->new_values)
                                    @foreach(array_keys(array_diff_assoc((array)$log->new_values, (array)$log->old_values)) as $field)
                                        <div><span class="font-medium text-slate-700">{{ $field }}:</span>
                                            <span class="line-through text-red-400">{{ $log->old_values[$field] ?? '—' }}</span>
                                            → <span class="text-green-700">{{ $log->new_values[$field] ?? '—' }}</span>
                                        </div>
                                    @endforeach
                                @elseif($log->action === 'created' && $log->new_values)
                                    <span class="text-green-600">New record created</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-slate-400">No change history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════
     MODALS (Admin only)
══════════════════════════════════════════════════════════ --}}
@if($isAdmin)

{{-- Modal: Add / Edit Project (EQ / TECH) --}}
<dialog id="modal-project" class="max-w-2xl w-full">
    <div class="panel m-0 max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400" id="modal-project-mode">Add Project</p>
                <h2 class="mt-1 text-xl font-black" id="modal-project-title">New Project</h2>
            </div>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>
        <form id="form-project" method="POST">
            @csrf
            <span id="form-project-method"></span>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                    <select name="type" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none" id="fp-type">
                        <option value="EQ">Equipment (EQ)</option>
                        <option value="TECH">Technology (TECH)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Year</label>
                    <input type="number" name="tahun" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" value="{{ date('Y') }}" id="fp-tahun">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Job Name</label>
                    <input type="text" name="nama" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fp-nama">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">LOB</label>
                    <select name="lob" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none" id="fp-lob">
                        @foreach($lobs as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select name="status" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none" id="fp-status">
                        @foreach($statuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Client</label>
                    <input type="text" name="pemberi_kerja" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fp-pemberi_kerja">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Area</label>
                    <input type="text" name="area" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fp-area">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Partner</label>
                    <input type="text" name="mitra" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fp-mitra">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Total Project Value (Rp)</label>
                    <input type="number" name="nilai_pekerjaan" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fp-nilai_pekerjaan">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Total Partner Value (Rp)</label>
                    <input type="number" name="nilai_mitra" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fp-nilai_mitra">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">OPEX Absorption (Rp)</label>
                    <input type="number" name="serapan" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fp-serapan">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Contract No.</label>
                    <input type="text" name="no_kontrak" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fp-no_kontrak">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Start Date</label>
                    <input type="text" name="start_date" placeholder="dd/mm/yyyy" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fp-start_date">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">End Date</label>
                    <input type="text" name="end_date" placeholder="dd/mm/yyyy or -" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fp-end_date">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-2">Documents</label>
                    <div id="fp-docs-eq" class="flex flex-wrap gap-3">
                        @foreach($EQ_DOCS as $di => $dl)
                            <label class="flex items-center gap-1.5 text-xs">
                                <input type="checkbox" name="docs[]" value="1" data-idx="{{ $di }}" data-doctype="EQ" class="rounded fp-doc-cb"> {{ $dl }}
                            </label>
                        @endforeach
                    </div>
                    <div id="fp-docs-tech" class="flex flex-wrap gap-3 hidden">
                        @foreach($TC_DOCS as $di => $dl)
                            <label class="flex items-center gap-1.5 text-xs">
                                <input type="checkbox" name="docs[]" value="1" data-idx="{{ $di }}" data-doctype="TECH" class="rounded fp-doc-cb"> {{ $dl }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Checked = available, unchecked = not yet</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea name="keterangan" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fp-keterangan"></textarea>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</dialog>

{{-- Modal: Add / Edit Hand Over --}}
<dialog id="modal-handover" class="max-w-lg w-full">
    <div class="panel m-0">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <h2 class="text-xl font-black" id="modal-ho-title">New Hand Over</h2>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>
        <form id="form-handover" method="POST">
            @csrf
            <span id="form-ho-method"></span>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Year</label><input type="number" name="tahun" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" value="{{ date('Y') }}" id="fh-tahun"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select name="status" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fh-status">
                        <option value="PIPELINE">PIPELINE</option><option value="ON GOING">ON GOING</option><option value="SELESAI">SELESAI</option>
                    </select></div>
                <div class="sm:col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Job Name</label><input type="text" name="nama" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fh-nama"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">LOB</label><input type="text" name="lob" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fh-lob"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Client</label><input type="text" name="pemberi_kerja" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fh-pemberi_kerja"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Area</label><input type="text" name="area" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fh-area"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Partner</label><input type="text" name="mitra" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fh-mitra"></div>
                <div class="sm:col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label><textarea name="keterangan" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fh-keterangan"></textarea></div>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</dialog>

{{-- Modal: Add / Edit Vehicle --}}
<dialog id="modal-vehicle" class="max-w-lg w-full">
    <div class="panel m-0">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <h2 class="text-xl font-black" id="modal-veh-title">New Vehicle</h2>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>
        <form id="form-vehicle" method="POST">
            @csrf
            <span id="form-veh-method"></span>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">License Plate</label><input type="text" name="nopol" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fv-nopol"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Vehicle Type</label><input type="text" name="jenis" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fv-jenis"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Customer/User</label><input type="text" name="customer" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fv-customer"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Vendor</label><input type="text" name="vendor" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fv-vendor"></div>
                <div class="sm:col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Contract No.</label><input type="text" name="no_kontrak" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fv-no_kontrak"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">PKB Valid Until</label><input type="text" name="pkb_date" placeholder="dd/mm/yyyy" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fv-pkb_date"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">PKB Value (Rp)</label><input type="number" name="nilai_pkb" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fv-nilai_pkb"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Tax Status</label>
                    <select name="status_pajak" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fv-status_pajak">
                        @foreach($pjStatuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                    </select></div>
                <div class="sm:col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label><input type="text" name="keterangan" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fv-keterangan"></div>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</dialog>

{{-- Modal: Add / Edit Maintenance --}}
<dialog id="modal-maintenance" class="max-w-lg w-full">
    <div class="panel m-0">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <h2 class="text-xl font-black" id="modal-maint-title">New Equipment/Vehicle</h2>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>
        <form id="form-maintenance" method="POST">
            @csrf
            <span id="form-maint-method"></span>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Equipment / Vehicle Name</label><input type="text" name="nama_alat" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fm-nama_alat"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Equipment Type</label><input type="text" name="tipe_alat" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fm-tipe_alat"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Area</label><input type="text" name="area" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fm-area"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Service Partner</label><input type="text" name="mitra" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fm-mitra"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Contract No.</label><input type="text" name="no_kontrak" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fm-no_kontrak"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Last Service</label><input type="text" name="last_service" placeholder="dd/mm/yyyy" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fm-last_service"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Next Schedule</label><input type="text" name="next_service" placeholder="dd/mm/yyyy or -" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fm-next_service"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Maintenance Status</label>
                    <select name="status_maint" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fm-status_maint">
                        @foreach($maintStatuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                    </select></div>
                <div class="sm:col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label><input type="text" name="keterangan" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="fm-keterangan"></div>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</dialog>

@endif {{-- end isAdmin --}}

@push('scripts')
<script>
// ── Auto-activate tab from query string ───────────────────
(function() {
    const p = new URLSearchParams(location.search);
    if (p.has('log_type') || p.has('log_action')) {
        const btn = document.querySelector('.mon-tab[data-target="tab-log"]');
        if (btn) btn.click();
    }
})();

// ── Tab switching ──────────────────────────────────────────
document.querySelectorAll('.mon-tab').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.mon-tab').forEach(b => {
            b.classList.remove('border-blue-600','text-blue-700');
            b.classList.add('border-transparent','text-slate-500');
        });
        this.classList.add('border-blue-600','text-blue-700');
        this.classList.remove('border-transparent','text-slate-500');

        document.querySelectorAll('.mon-content').forEach(c => c.classList.add('hidden'));
        document.getElementById(this.dataset.target)?.classList.remove('hidden');

        if (this.dataset.target === 'tab-sum') initSumChart();
    });
});

// ── Summary Charts ────────────────────────────────────────
let sumChartInited = false;
function initSumChart() {
    if (sumChartInited) return;

    const el = document.getElementById('eqtStatusChart');
    if (el) {
        const d = JSON.parse(document.getElementById('eqt-status-data').textContent);
        new Chart(el, {
            type: 'doughnut',
            data: { labels: d.labels, datasets: [{ data: d.values, backgroundColor: d.colors, borderWidth: 0 }] },
            options: { cutout: '68%', plugins: { legend: { display: false } } }
        });
    }

    sumChartInited = true;
}

@if($isAdmin)
// ── Modal helpers ─────────────────────────────────────────
const STORE_URLS = {
    project:     '{{ route('monitoring.store', ['type'=>'projects']) }}',
    handover:    '{{ route('monitoring.store', ['type'=>'handovers']) }}',
    vehicle:     '{{ route('monitoring.store', ['type'=>'vehicles']) }}',
    maintenance: '{{ route('monitoring.store', ['type'=>'maintenances']) }}',
};
function editUrl(type, id) {
    const map = { project:'projects', handover:'handovers', vehicle:'vehicles', maintenance:'maintenances' };
    return `/monitoring/${map[type]}/${id}`;
}

function openAddModal(type) {
    const dialogs = { project:'modal-project', handover:'modal-handover', vehicle:'modal-vehicle', maintenance:'modal-maintenance' };
    const d = document.getElementById(dialogs[type]);
    resetModal(type);
    d.showModal();
}

function openEditModal(type, id, data) {
    const dialogs = { project:'modal-project', handover:'modal-handover', vehicle:'modal-vehicle', maintenance:'modal-maintenance' };
    const d = document.getElementById(dialogs[type]);
    resetModal(type);
    fillModal(type, id, data);
    d.showModal();
}

function resetModal(type) {
    const forms = { project:'form-project', handover:'form-handover', vehicle:'form-vehicle', maintenance:'form-maintenance' };
    const form = document.getElementById(forms[type]);
    form.reset();
    const mSpans = { project:'form-project-method', handover:'form-ho-method', vehicle:'form-veh-method', maintenance:'form-maint-method' };
    document.getElementById(mSpans[type]).innerHTML = '';
    form.action = STORE_URLS[type];
    form.method = 'POST';
    if (type === 'project') {
        document.getElementById('modal-project-mode').textContent = 'Add Project';
        document.getElementById('modal-project-title').textContent = 'New Project';
        document.getElementById('fp-docs-eq').classList.remove('hidden');
        document.getElementById('fp-docs-tech').classList.add('hidden');
    }
}

function syncDocPanel() {
    const t = document.getElementById('fp-type').value;
    document.getElementById('fp-docs-eq').classList.toggle('hidden', t !== 'EQ');
    document.getElementById('fp-docs-tech').classList.toggle('hidden', t !== 'TECH');
}
document.getElementById('fp-type')?.addEventListener('change', syncDocPanel);

function fillModal(type, id, data) {
    const url = editUrl(type, id);
    const forms = { project:'form-project', handover:'form-handover', vehicle:'form-vehicle', maintenance:'form-maintenance' };
    const mSpans = { project:'form-project-method', handover:'form-ho-method', vehicle:'form-veh-method', maintenance:'form-maint-method' };
    const form = document.getElementById(forms[type]);
    form.action = url;
    document.getElementById(mSpans[type]).innerHTML = '<input type="hidden" name="_method" value="PUT">';

    if (type === 'project') {
        document.getElementById('modal-project-mode').textContent = 'Edit Project';
        document.getElementById('modal-project-title').textContent = data.nama || 'Edit Project';
        ['type','tahun','nama','lob','status','pemberi_kerja','area','mitra','nilai_pekerjaan','nilai_mitra','serapan','no_kontrak','start_date','end_date','keterangan'].forEach(f => {
            const el = document.getElementById('fp-' + f);
            if (el) el.value = data[f] ?? '';
        });
        syncDocPanel();
        const cbs = document.querySelectorAll(`.fp-doc-cb[data-doctype="${data.type}"]`);
        const docs = data.docs || [];
        cbs.forEach((cb, i) => { cb.checked = docs[i] === 1; });
    } else if (type === 'handover') {
        ['tahun','status','nama','lob','pemberi_kerja','area','mitra','keterangan'].forEach(f => {
            const el = document.getElementById('fh-' + f);
            if (el) el.value = data[f] ?? '';
        });
    } else if (type === 'vehicle') {
        ['nopol','jenis','customer','vendor','no_kontrak','pkb_date','nilai_pkb','status_pajak','keterangan'].forEach(f => {
            const el = document.getElementById('fv-' + f);
            if (el) el.value = data[f] ?? '';
        });
    } else if (type === 'maintenance') {
        ['nama_alat','tipe_alat','area','mitra','no_kontrak','last_service','next_service','status_maint','keterangan'].forEach(f => {
            const el = document.getElementById('fm-' + f);
            if (el) el.value = data[f] ?? '';
        });
    }
}

// Build docs array from checkboxes before submit
document.getElementById('form-project')?.addEventListener('submit', function(e) {
    const type = document.getElementById('fp-type').value;
    const cbs = this.querySelectorAll(`.fp-doc-cb[data-doctype="${type}"]`);
    this.querySelectorAll('input[name="docs[]"]').forEach(el => el.remove());
    cbs.forEach(cb => {
        const h = document.createElement('input');
        h.type = 'hidden'; h.name = 'docs[]'; h.value = cb.checked ? '1' : '0';
        this.appendChild(h);
    });
});
@endif
</script>
@endpush

@endsection
