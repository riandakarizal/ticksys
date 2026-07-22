@extends('layouts.app', ['title' => 'Monitoring EQT', 'heading' => 'Monitoring Project Equipment & Technology'])

@section('content')
@php
$rp = function($n) {
    if (!$n || $n == 0) return '-';
    if ($n >= 1e12) return 'Rp ' . number_format($n / 1e12, 2) . 'T';
    if ($n >= 1e9)  return 'Rp ' . number_format($n / 1e9, 2) . 'M';
    if ($n >= 1e6)  return 'Rp ' . number_format($n / 1e6, 0) . ' Jt';
    return 'Rp ' . number_format($n, 0, ',', '.');
};
@endphp

{{-- ── KPI Cards ─────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
    <div class="bg-slate-50 rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-2xl font-black text-slate-800">{{ $kpi['total'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Projects</p>
        <p class="text-[10px] text-slate-400 mt-0.5">RENT {{ $kpi['rent'] }} · SUPPLY {{ $kpi['supply'] }} · JASA {{ $kpi['jasa'] }}</p>
    </div>
    <div class="bg-green-50 rounded-xl border border-green-200 shadow-sm p-4">
        <p class="text-2xl font-black text-green-600">{{ $kpi['og'] }}</p>
        <p class="text-xs text-slate-500 mt-1">On Going</p>
        <p class="text-[10px] text-green-500 mt-0.5">Active contracts</p>
    </div>
    <div class="rounded-xl border shadow-sm p-4 {{ $kpi['dly'] > 0 ? 'bg-amber-50 border-amber-300' : 'bg-white border-slate-200' }}">
        <p class="text-2xl font-black {{ $kpi['dly'] > 0 ? 'text-amber-600' : 'text-slate-800' }}">{{ $kpi['dly'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Delay</p>
        <p class="text-[10px] {{ $kpi['dly'] > 0 ? 'text-amber-400' : 'text-slate-400' }} mt-0.5">Needs attention</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-2xl font-black text-slate-600">{{ $rp($kpi['nilai']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Contract Value</p>
        <p class="text-[10px] text-slate-400 mt-0.5">UPC {{ $kpi['upc'] }} · HVR {{ $kpi['hvr'] }} · END {{ $kpi['end'] }}</p>
    </div>
</div>

{{-- ── Filter Bar ───────────────────────────────────────────── --}}
<div class="bg-white border border-slate-200 rounded-xl px-4 py-3 mb-4">
    <form method="GET" action="{{ route('monitoring.index') }}" class="flex flex-wrap gap-2 items-center">
        <select name="year" onchange="this.form.submit()" class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:border-blue-400 focus:outline-none">
            <option value="all" @selected($yearFilter==='all')>All Years</option>
            @foreach([2021,2022,2023,2024,2025,2026] as $y)
                <option value="{{ $y }}" @selected($yearFilter==$y)>{{ $y }}</option>
            @endforeach
        </select>
        <select name="status" onchange="this.form.submit()" class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:border-blue-400 focus:outline-none">
            <option value="all" @selected($statusFilter==='all')>All Status</option>
            <option value="UPC" @selected($statusFilter==='UPC')>Upcoming</option>
            <option value="OG"  @selected($statusFilter==='OG')>On Going</option>
            <option value="HVR" @selected($statusFilter==='HVR')>Hand Over</option>
            <option value="DLY" @selected($statusFilter==='DLY')>Delay</option>
            <option value="END" @selected($statusFilter==='END')>Ended</option>
        </select>
        <select name="type" onchange="this.form.submit()" class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:border-blue-400 focus:outline-none">
            <option value="all"    @selected($typeFilter==='all')>All Types</option>
            <option value="RENT"   @selected($typeFilter==='RENT')>Rental</option>
            <option value="SUPPLY" @selected($typeFilter==='SUPPLY')>Supply</option>
            <option value="JASA"   @selected($typeFilter==='JASA')>Jasa</option>
        </select>
        <select name="unit" onchange="this.form.submit()" class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:border-blue-400 focus:outline-none">
            <option value="all" @selected($unitFilter==='all')>All Units</option>
            <option value="TC"  @selected($unitFilter==='TC')>Technology</option>
            <option value="EQ"  @selected($unitFilter==='EQ')>Equipment</option>
        </select>
        <input type="text" name="search" value="{{ $search }}" placeholder="Search name / client / area / contract..."
            class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs bg-white focus:border-blue-400 focus:outline-none min-w-[200px] flex-1">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">Search</button>
        @if($isAdmin)
            @php $archivedUrl = request()->fullUrlWithQuery(['archived' => $showArchived ? '0' : '1']); @endphp
            <a href="{{ $archivedUrl }}" class="text-xs px-2.5 py-1.5 rounded-lg border transition {{ $showArchived ? 'bg-amber-50 border-amber-300 text-amber-700 font-semibold' : 'border-slate-200 text-slate-500 hover:border-slate-300' }}">
                {{ $showArchived ? '✓ Archived' : 'Show Archived' }}
            </a>
        @endif
        @if($yearFilter!=='all'||$statusFilter!=='all'||$typeFilter!=='all'||$unitFilter!=='all'||$search)
            <a href="{{ route('monitoring.index') }}" class="text-xs text-slate-500 hover:text-slate-800 px-2 py-1.5">Reset</a>
        @endif
    </form>
</div>

@if(session('success'))
    <div class="mb-3 rounded-lg bg-green-50 border border-green-200 px-4 py-2.5 text-sm text-green-700">{{ session('success') }}</div>
@endif

{{-- ── Projects Table ────────────────────────────────────────── --}}
<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
        <div class="flex items-center gap-3">
            <h3 class="text-sm font-bold text-slate-800">Project List</h3>
            <span class="text-xs text-slate-400">{{ $projects->total() }} project{{ $projects->total() !== 1 ? 's' : '' }}</span>
        </div>
        @if($canCreateProject)
            <button type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-all duration-200 ease-out hover:bg-blue-700 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-95"
                    onclick="document.getElementById('modal-project-tc').showModal()">
                +Project
            </button>
        @endif
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="text-left text-slate-400 border-b border-slate-100 bg-slate-50/60">
                <tr>
                    <th class="px-4 py-2 font-medium w-8">#</th>
                    <th class="py-2 pr-3 font-medium">Contract No.</th>
                    <th class="py-2 pr-3 font-medium">Project Name</th>
                    <th class="py-2 pr-3 font-medium">Type</th>
                    <th class="py-2 pr-3 font-medium">Client</th>
                    <th class="py-2 pr-3 font-medium">Area</th>
                    <th class="py-2 pr-3 font-medium">Contract Value</th>
                    <th class="py-2 pr-3 font-medium">Period</th>
                    <th class="py-2 pr-3 font-medium">Start</th>
                    <th class="py-2 pr-3 font-medium">End</th>
                    <th class="py-2 pr-3 font-medium">Status</th>
                    <th class="py-2 pr-3 font-medium">Assets</th>
                    <th class="py-2 pr-3 font-medium">Doc</th>
                    <th class="py-2 pr-3 font-medium">Notes</th>
                    @if($isAdmin)<th class="py-2 pr-4"></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($projects as $i => $p)
                    <tr class="{{ $p->trashed() ? 'opacity-50 bg-slate-50' : 'hover:bg-slate-50/50' }}">
                        <td class="px-4 py-2 text-slate-400">{{ ($projects->currentPage()-1)*$projects->perPage()+$i+1 }}</td>
                        <td class="py-2 pr-3 text-slate-500 max-w-[160px] truncate font-mono text-[10px]" title="{{ $p->pjct_contract }}">
                            {{ $p->pjct_contract ?: '-' }}
                        </td>
                        <td class="py-2 pr-3 font-medium max-w-[260px] leading-snug">
                            {{ $p->pjct_name }}
                            @if($p->trashed())<span class="ml-1 inline-flex items-center rounded px-1 py-0.5 text-[9px] font-medium bg-slate-200 text-slate-500">Archived</span>@endif
                        </td>
                        <td class="py-2 pr-3 whitespace-nowrap">
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $p->typeBadgeClass() }}">{{ $p->pjct_type }}</span>
                        </td>
                        <td class="py-2 pr-3 text-slate-500 max-w-[140px] truncate" title="{{ $p->pjct_client }}">{{ $p->pjct_client ?: '-' }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap text-slate-600 font-semibold">{{ $p->pjct_area ?: '-' }}</td>
                        <td class="py-2 pr-3 font-semibold whitespace-nowrap">{{ $rp($p->pjct_value) }}</td>
                        <td class="py-2 pr-3 text-slate-500 whitespace-nowrap">{{ $p->pjct_totalperiod ? $p->pjct_totalperiod . ' mo' : '-' }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap text-slate-500">{{ $p->pjct_costart ? $p->pjct_costart->format('d/m/Y') : '-' }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap text-slate-500">{{ $p->pjct_coend_m ? $p->pjct_coend_m->format('d/m/Y') : '-' }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap">
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $p->statusBadgeClass() }}">{{ $p->statusLabel() }}</span>
                        </td>
                        <td class="py-2 pr-3 whitespace-nowrap text-center">
                            @if($p->assets_count > 0)
                                <a href="{{ route('project.assets', ['search' => $p->id]) }}"
                                   class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-brand-50 text-brand-700 hover:bg-brand-100 transition">
                                    {{ number_format($p->assets_count) }}
                                </a>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="py-2 pr-3 whitespace-nowrap">
                            @php $docsByType = $p->docs->groupBy('doc_type')->map->first(); @endphp
                            @if($docsByType->isEmpty())
                                <span class="text-slate-300">—</span>
                            @else
                                @foreach(['KONTRAK','RKST','RAB','BAST','SOP'] as $dt)
                                    @continue(!$docsByType->has($dt))
                                    <a href="{{ route('monitoring.docs.show', $docsByType[$dt]->id) }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center rounded px-1.5 py-0.5 text-[9px] font-semibold mr-1 hover:opacity-75 transition {{ \App\Models\PjctDoc::badgeClassFor($dt) }}">{{ $dt }}</a>
                                @endforeach
                            @endif
                        </td>
                        <td class="py-2 pr-3 text-slate-500 max-w-[160px] text-[10px]">{{ $p->pjct_misc ?: '' }}</td>
                        @if($isAdmin)
                            <td class="py-2 pr-4 whitespace-nowrap text-right">
                                @if($p->trashed())
                                    <form method="POST" action="{{ route('monitoring.restore', ['type'=>'projects','id'=>$p->id]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-purple-600 hover:text-purple-800 font-medium">Restore</button>
                                    </form>
                                @else
                                    <button type="button" onclick="openEdit({{ $p->id }}, {{ $p->toJson() }})" class="text-xs text-blue-600 hover:text-blue-800 font-medium mr-2">Edit</button>
                                    <form method="POST" action="{{ route('monitoring.destroy', ['type'=>'projects','id'=>$p->id]) }}" class="inline" onsubmit="return confirm('Archive this project?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-slate-400 hover:text-amber-700 font-medium">Archive</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="14" class="px-4 py-10 text-center text-slate-400">No projects found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($projects->hasPages())
    <div class="px-4 py-3 border-t border-slate-100">
        {{ $projects->links() }}
    </div>
    @endif
</div>

{{-- ══ Modal: Add / Edit Project (Admin only) ══ --}}
@if($isAdmin)
<dialog id="modal-project" class="max-w-2xl w-full">
    <div class="panel m-0 max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <h2 class="text-xl font-black" id="modal-title">New Project</h2>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>
        <form id="form-project" method="POST" action="{{ route('monitoring.store', ['type'=>'projects']) }}">
            @csrf
            <span id="form-method"></span>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Project Name *</label>
                    <input type="text" name="pjct_name" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_name">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Type *</label>
                    <select name="pjct_type" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_type">
                        <option value="RENT">Rental (RENT)</option>
                        <option value="SUPPLY">Supply (SUPPLY)</option>
                        <option value="JASA">Jasa (JASA)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Status *</label>
                    <select name="pjct_status" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_status">
                        <option value="UPC">Upcoming</option>
                        <option value="OG">On Going</option>
                        <option value="HVR">Hand Over</option>
                        <option value="DLY">Delay</option>
                        <option value="END">Ended</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Contract No.</label>
                    <input type="text" name="pjct_contract" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_contract">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Contract Date</label>
                    <input type="date" name="pjct_codate" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_codate">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Division</label>
                    <input type="text" name="pjct_div" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_div">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Client</label>
                    <input type="text" name="pjct_client" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_client">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Area</label>
                    <input type="text" name="pjct_area" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_area">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Contract Value (Rp)</label>
                    <input type="number" name="pjct_value" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_value">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Period (months)</label>
                    <input type="number" name="pjct_totalperiod" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_totalperiod">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Start Date</label>
                    <input type="date" name="pjct_costart" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_costart">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">End Date</label>
                    <input type="date" name="pjct_coend_m" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_coend_m">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea name="pjct_misc" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" id="f-pjct_misc"></textarea>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</dialog>
@endif

{{-- ══ Modal: Project Baru — Equipment & Technology Commercial (quick create, status fixed) ══ --}}
@if($canCreateProject)
<dialog id="modal-project-tc" class="max-w-lg w-full">
    <div class="panel m-0 max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <h2 class="text-xl font-black">Project Baru</h2>
                <p class="text-xs text-slate-400 mt-0.5">Equipment &amp; Technology Commercial &middot; Status awal: Upcoming</p>
            </div>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>
        <form method="POST" action="{{ route('monitoring.store', ['type'=>'projects']) }}">
            @csrf
            <input type="hidden" name="pjct_status" value="UPC">
            <div class="grid gap-3">
                @if(auth()->user()->isSuperAdmin())
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Divisi *</label>
                        <select name="pjct_div" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="TC">Technology Commercial (TC)</option>
                            <option value="EQ">Equipment Commercial (EQ)</option>
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Project *</label>
                    <input type="text" name="pjct_name" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Jenis Project *</label>
                    <select name="pjct_type" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="RENT">Rental (RENT)</option>
                        <option value="SUPPLY">Supply (SUPPLY)</option>
                        <option value="JASA">Jasa (JASA)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Area Pekerjaan</label>
                    <input type="text" name="pjct_area" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan</label>
                    <textarea name="pjct_misc" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all duration-200 ease-out hover:bg-blue-700 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-95">
                    Create Project
                </button>
            </div>
        </form>
    </div>
</dialog>
@endif

@push('scripts')
<script>
@if($isAdmin)
const STORE_URL = '{{ route('monitoring.store', ['type'=>'projects']) }}';

function openEdit(id, data) {
    const form = document.getElementById('form-project');
    document.getElementById('modal-title').textContent = 'Edit Project';
    form.action = `/monitoring/projects/${id}`;
    document.getElementById('form-method').innerHTML = '<input type="hidden" name="_method" value="PUT">';

    ['pjct_name','pjct_type','pjct_status','pjct_contract','pjct_div',
     'pjct_client','pjct_area','pjct_value','pjct_totalperiod','pjct_misc'].forEach(f => {
        const el = document.getElementById('f-' + f);
        if (el) el.value = data[f] ?? '';
    });
    ['pjct_codate','pjct_costart','pjct_coend_m'].forEach(f => {
        const el = document.getElementById('f-' + f);
        if (el) el.value = data[f] ? data[f].substring(0, 10) : '';
    });

    document.getElementById('modal-project').showModal();
}

document.getElementById('modal-project')?.addEventListener('close', function() {
    const form = document.getElementById('form-project');
    form.reset();
    form.action = STORE_URL;
    document.getElementById('form-method').innerHTML = '';
    document.getElementById('modal-title').textContent = 'New Project';
});

document.getElementById('modal-project-tc')?.addEventListener('close', function() {
    this.querySelector('form').reset();
});
@endif
</script>
@endpush

@endsection
