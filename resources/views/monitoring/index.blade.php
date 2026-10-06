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

@if($errors->any())
    <div class="mb-3 rounded-lg bg-red-50 border border-red-200 px-4 py-2.5 text-sm text-red-700">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

{{-- ── Bulk Import Project (superadmin only) ─────────────────── --}}
@if(auth()->user()->isSuperAdmin())
<div class="flex flex-wrap items-center justify-between gap-3 mb-4 px-4 py-3 bg-white rounded-xl border border-slate-200 shadow-sm">
    <div>
        <p class="text-sm font-bold text-slate-800">Input Data Project</p>
        <p class="text-[11px] text-slate-400 mt-0.5">Upload Excel untuk menambah project secara massal. Nomor kontrak yang sudah terdaftar akan dilewati.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('monitoring.projects.import.template') }}"
           class="btn-soft rounded-xl px-3 py-2 text-xs gap-1.5">
            <span>⬇</span> Download Template
        </a>
        <button type="button"
                onclick="document.getElementById('modal-project-import').showModal()"
                class="btn-primary rounded-xl px-3 py-2 text-xs gap-1.5">
            <span>⬆</span> Import Excel
        </button>
    </div>
</div>
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
    <div>
        <table class="w-full table-fixed text-xs">
            <thead class="text-left text-slate-400 border-b border-slate-100 bg-slate-50/60">
                <tr>
                    <th class="w-[7%] pl-4 py-1.5 pr-2 font-medium">Kontrak</th>
                    <th class="w-[17%] py-1.5 pr-2 font-medium">Nama Project</th>
                    <th class="w-[5%] py-1.5 pr-2 font-medium">Type</th>
                    <th class="w-[9%] py-1.5 pr-2 font-medium">Client</th>
                    <th class="w-[5%] py-1.5 pr-2 font-medium">Area</th>
                    <th class="w-[7%] py-1.5 pr-2 font-medium">Nilai</th>
                    <th class="w-[5%] py-1.5 pr-2 font-medium">Durasi</th>
                    <th class="w-[9%] py-1.5 pr-2 font-medium">Periode</th>
                    <th class="w-[6%] py-1.5 pr-2 font-medium">Status</th>
                    <th class="w-[5%] py-1.5 pr-2 font-medium">Assets</th>
                    <th class="w-[9%] py-1.5 pr-2 font-medium">Doc</th>
                    <th class="w-[8%] py-1.5 pr-2 font-medium">Notes</th>
                    @if($isAdmin)<th class="w-[8%] py-1.5 pr-4"></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($projects as $i => $p)
                    <tr class="{{ $p->trashed() ? 'opacity-50 bg-slate-50' : 'hover:bg-slate-50/50' }}">
                        <td class="pl-4 py-1.5 pr-2 text-slate-500 break-words font-mono text-[10px]">
                            {{ $p->pjct_contract ?: '-' }}
                        </td>
                        <td class="py-1.5 pr-2 font-medium break-words">
                            {{ $p->pjct_name }}
                            @if($p->trashed())<span class="ml-1 inline-flex items-center rounded px-1 py-0.5 text-[9px] font-medium bg-slate-200 text-slate-500">Archived</span>@endif
                        </td>
                        <td class="py-1.5 pr-2">
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $p->typeBadgeClass() }}">{{ $p->pjct_type }}</span>
                        </td>
                        <td class="py-1.5 pr-2 text-slate-500 break-words">{{ $p->pjct_client ?: '-' }}</td>
                        <td class="py-1.5 pr-2 text-slate-600 font-semibold break-words">{{ $p->pjct_area ?: '-' }}</td>
                        <td class="py-1.5 pr-2 font-semibold break-words">{{ $rp($p->pjct_value) }}</td>
                        <td class="py-1.5 pr-2 text-slate-500 break-words">{{ $p->pjct_totalperiod ? $p->pjct_totalperiod . ' mo' : '-' }}</td>
                        <td class="py-1.5 pr-2 text-slate-500 text-[10px] break-words">
                            {{ $p->pjct_costart ? $p->pjct_costart->format('d/m/y') : '-' }} – {{ $p->pjct_coend_m ? $p->pjct_coend_m->format('d/m/y') : '-' }}
                        </td>
                        <td class="py-1.5 pr-2">
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $p->statusBadgeClass() }}">{{ $p->statusLabel() }}</span>
                        </td>
                        <td class="py-1.5 pr-2 text-center">
                            @if($p->assets_count > 0)
                                <a href="{{ route('project.assets', ['search' => $p->id]) }}"
                                   class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-brand-50 text-brand-700 hover:bg-brand-100 transition">
                                    {{ number_format($p->assets_count) }}
                                </a>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="py-1.5 pr-2">
                            @php $docsByType = $p->docs->groupBy('doc_type')->map->first(); @endphp
                            @if($docsByType->isEmpty())
                                <span class="text-slate-300">—</span>
                            @else
                                <div class="flex flex-wrap gap-1">
                                @foreach([...array_keys(\App\Models\PjctDoc::TYPES), 'SOP'] as $dt)
                                    @continue(!$docsByType->has($dt))
                                    <a href="{{ route('monitoring.docs.show', $docsByType[$dt]->id) }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center rounded px-1.5 py-0.5 text-[9px] font-semibold hover:opacity-75 transition {{ \App\Models\PjctDoc::badgeClassFor($dt) }}">{{ $dt }}</a>
                                @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="py-1.5 pr-2 text-slate-500 text-[10px] break-words">{{ $p->pjct_misc ?: '' }}</td>
                        @if($isAdmin)
                            <td class="py-1.5 pr-4 text-right">
                                @if($p->trashed())
                                    <form method="POST" action="{{ route('monitoring.restore', ['type'=>'projects','id'=>$p->id]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-purple-600 hover:text-purple-800 font-medium">Restore</button>
                                    </form>
                                @elseif($canCreateProject)
                                    <div class="inline-flex items-center gap-1">
                                    <button type="button" title="Edit project" aria-label="Edit project {{ $p->id }}"
                                            onclick="openEdit('{{ $p->id }}', @js([
                                                'pjct_contract' => $p->pjct_contract,
                                                'pjct_name' => $p->pjct_name,
                                                'pjct_value' => $p->pjct_value ?: null,
                                                'pjct_client' => $p->pjct_client,
                                                'pjct_area' => $p->pjct_area,
                                                'pjct_codate' => $p->pjct_codate?->format('Y-m-d'),
                                            ]))"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-amber-500 text-white shadow-sm transition-all duration-200 ease-out hover:bg-amber-600 hover:shadow-md active:scale-95">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 20h9"/>
                                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>
                                        </svg>
                                    </button>
                                    <button type="button" title="Upload dokumen" aria-label="Upload dokumen {{ $p->id }}"
                                            onclick="openDocUpload('{{ $p->id }}', @js($p->pjct_name))"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-green-600 text-white shadow-sm transition-all duration-200 ease-out hover:bg-green-700 hover:shadow-md active:scale-95">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
                                            <path d="M14 3v5h5M12 11v6M9 14h6"/>
                                        </svg>
                                    </button>
                                    </div>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="13" class="px-4 py-10 text-center text-slate-400">No projects found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@if($projects->hasPages())
<div class="sticky bottom-0 z-20 bg-white px-4 py-3 border border-t-0 border-slate-200 rounded-b-xl shadow-[0_-2px_6px_-2px_rgba(0,0,0,0.06)]">
    {{ $projects->links() }}
</div>
@endif

{{-- ══ Modal: Import Project dari Excel ══ --}}
@if(auth()->user()->isSuperAdmin())
<dialog id="modal-project-import" class="max-w-lg w-full">
    <div class="panel m-0 max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <h2 class="text-xl font-black">Import Project</h2>
                <p class="text-xs text-slate-400 mt-0.5">Format .xlsx / .xls &middot; maksimal 10 MB</p>
            </div>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>

        <form method="POST" action="{{ route('monitoring.projects.import.preview') }}" enctype="multipart/form-data" id="project-import-form">
            @csrf

            <div id="project-drop-area"
                 class="border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center transition cursor-pointer hover:border-teal-400 hover:bg-teal-50/30"
                 onclick="document.getElementById('project-file-input').click()">
                <input type="file" name="file" id="project-file-input" accept=".xlsx,.xls" required class="hidden">
                <p class="text-3xl mb-2">📄</p>
                <p class="text-sm font-semibold text-slate-700" id="project-file-name">Klik atau drag file ke sini</p>
                <p class="text-[11px] text-slate-400 mt-1">Isi data mulai baris ke-2 pada sheet <span class="font-mono">Projects</span></p>
            </div>

            <p class="text-[11px] text-slate-400 mt-3">
                Belum punya template?
                <a href="{{ route('monitoring.projects.import.template') }}" class="text-teal-600 font-semibold hover:underline">Download di sini</a>
                — sheet <span class="font-mono">Referensi</span> berisi kode divisi, tipe, status, dan daftar project yang sudah ada.
            </p>

            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Batal</button>
                <button type="submit" class="btn-primary" id="project-import-submit">Lanjut ke Preview</button>
            </div>
        </form>
    </div>
</dialog>

<script>
(function () {
    const input = document.getElementById('project-file-input');
    const drop  = document.getElementById('project-drop-area');
    const label = document.getElementById('project-file-name');
    if (!input || !drop) return;

    input.addEventListener('change', function () {
        label.textContent = this.files[0] ? this.files[0].name : 'Klik atau drag file ke sini';
    });

    ['dragenter', 'dragover'].forEach(function (evt) {
        drop.addEventListener(evt, function (e) {
            e.preventDefault();
            drop.classList.add('border-teal-400', 'bg-teal-50/30');
        });
    });

    ['dragleave', 'drop'].forEach(function (evt) {
        drop.addEventListener(evt, function (e) {
            e.preventDefault();
            drop.classList.remove('border-teal-400', 'bg-teal-50/30');
        });
    });

    drop.addEventListener('drop', function (e) {
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });

    document.getElementById('project-import-form').addEventListener('submit', function () {
        const btn = document.getElementById('project-import-submit');
        btn.disabled = true;
        btn.textContent = 'Memproses…';
    });
})();
</script>
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

{{-- ══ Modal: Edit Project (Equipment & Technology Commercial) ══ --}}
@if($canCreateProject)
<dialog id="modal-edit-project" class="max-w-lg w-full">
    <div class="panel m-0 max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <h2 class="text-xl font-black">Edit Project</h2>
                <p class="text-xs text-slate-400 mt-0.5" id="edit-project-id">&nbsp;</p>
            </div>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>
        <form id="form-edit-project" method="POST">
            @csrf
            @method('PATCH')
            <div class="grid gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nomor Kontrak</label>
                    <input type="text" name="pjct_contract" maxlength="255" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Kontrak *</label>
                    <textarea name="pjct_name" rows="2" required maxlength="255" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Nilai Kontrak (Rp)</label>
                        <input type="number" name="pjct_value" min="0" step="1" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <p class="text-[11px] text-slate-400 mt-1" id="edit-value-preview">&nbsp;</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Tanggal Kontrak</label>
                        <input type="date" name="pjct_codate" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Klien</label>
                        <input type="text" name="pjct_client" maxlength="255" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Area Kontrak</label>
                        <input type="text" name="pjct_area" maxlength="255" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all duration-200 ease-out hover:bg-amber-600 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-95">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</dialog>
@endif

{{-- ══ Modal: Upload Dokumen Project (Equipment & Technology Commercial) ══
     Dua langkah seperti Google Drive: file diunggah dulu (progress bar) → isi nama & jenis → Simpan. --}}
@if($canCreateProject)
<dialog id="modal-doc-upload" class="max-w-lg w-full">
    <div class="panel m-0 max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <h2 class="text-xl font-black">Upload Dokumen</h2>
                <p class="text-xs text-slate-400 mt-0.5" id="doc-project-name">&nbsp;</p>
            </div>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>

        {{-- Langkah 1: pilih / seret file → langsung terunggah --}}
        <input type="file" id="doc-file-input" accept="application/pdf,.pdf" class="hidden">
        <div id="doc-dropzone" role="button" tabindex="0"
             class="flex flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-slate-200 px-4 py-6 text-center cursor-pointer transition hover:border-green-400 hover:bg-green-50/40">
            <svg class="h-8 w-8 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
            </svg>
            <p class="text-sm font-semibold text-slate-700">Klik atau seret file ke sini</p>
            <p class="text-[11px] text-slate-400">Hanya PDF &middot; maks. 5 MB</p>
        </div>

        <div id="doc-file-card" class="hidden rounded-2xl border border-slate-200 px-4 py-3">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 shrink-0 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-[10px] font-black">PDF</div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-800 truncate" id="doc-file-name">&nbsp;</p>
                    <p class="text-[11px] text-slate-400" id="doc-file-status">&nbsp;</p>
                </div>
                <button type="button" id="doc-file-remove" title="Ganti file"
                        class="shrink-0 h-8 w-8 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 hover:bg-red-50 hover:text-red-500 transition">✕</button>
            </div>
            <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                <div id="doc-progress-bar" class="h-full rounded-full bg-green-600 transition-[width] duration-150" style="width: 0%"></div>
            </div>
        </div>
        <div id="doc-file-error" role="alert" class="hidden mt-3">
            <div class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-xs font-medium text-red-700">
                <svg class="h-4 w-4 shrink-0 mt-px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
                </svg>
                <span id="doc-file-error-text"></span>
            </div>
        </div>

        {{-- Langkah 2: detail dokumen → Simpan --}}
        <form id="form-doc-upload" method="POST" class="mt-4">
            @csrf
            <input type="hidden" name="upload_token" id="doc-upload-token">
            <div class="grid gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Dokumen *</label>
                    <input type="text" name="doc_name" id="doc-name" required maxlength="255" placeholder="mis. Kontrak Induk 2026"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Jenis Dokumen *</label>
                    <select name="doc_type" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="" disabled selected hidden>Pilih jenis dokumen</option>
                        @foreach(\App\Models\PjctDoc::TYPES as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" id="doc-submit" disabled
                        class="inline-flex items-center justify-center rounded-2xl bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all duration-200 ease-out hover:bg-green-700 hover:shadow-lg active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-green-600 disabled:hover:shadow-sm">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</dialog>
@endif

@push('scripts')
<script>
@if($canCreateProject)
document.getElementById('modal-project-tc')?.addEventListener('close', function() {
    this.querySelector('form').reset();
});

const EDIT_ROUTE_TEMPLATE = '{{ route('monitoring.details.update', ['project' => '__ID__']) }}';

function editValuePreview() {
    const input = document.querySelector('#form-edit-project [name="pjct_value"]');
    const v = parseInt(input.value, 10);
    document.getElementById('edit-value-preview').textContent = Number.isFinite(v) ? 'Rp ' + v.toLocaleString('id-ID') : ' ';
}

// Form diisi data project yang sedang tampil, supaya user hanya mengubah yang perlu.
function openEdit(id, data) {
    const form = document.getElementById('form-edit-project');
    form.action = EDIT_ROUTE_TEMPLATE.replace('__ID__', id);
    document.getElementById('edit-project-id').textContent = id;
    ['pjct_contract', 'pjct_name', 'pjct_value', 'pjct_client', 'pjct_area', 'pjct_codate'].forEach(function (field) {
        form.elements[field].value = data[field] ?? '';
    });
    editValuePreview();
    document.getElementById('modal-edit-project').showModal();
}

document.querySelector('#form-edit-project [name="pjct_value"]')?.addEventListener('input', editValuePreview);

// ── Upload dokumen: file diunggah dulu (XHR + progress), lalu form disimpan dengan token ──
const DOC_UPLOAD_ROUTE_TEMPLATE = '{{ route('monitoring.docs.upload', ['project' => '__ID__']) }}';
const DOC_STORE_ROUTE_TEMPLATE = '{{ route('monitoring.docs.store', ['project' => '__ID__']) }}';
const DOC_MAX_BYTES = {{ \App\Http\Controllers\PjctDocController::MAX_KB }} * 1024;
const docEl = (id) => document.getElementById(id);
let docProjectId = null;
let docXhr = null;

function docFormatSize(bytes) {
    return bytes >= 1048576 ? (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

function docShowError(message) {
    docEl('doc-file-error-text').textContent = message;
    docEl('doc-file-error').classList.toggle('hidden', !message);
}

// Sama bunyinya dengan PjctDocController::describeProblem() di server.
function docKindOf(ext) {
    const kinds = {
        'file Word': ['doc', 'docx', 'rtf', 'odt'],
        'file Excel': ['xls', 'xlsx', 'csv', 'ods'],
        'file PowerPoint': ['ppt', 'pptx', 'odp'],
        'file gambar': ['jpg', 'jpeg', 'png', 'gif', 'heic', 'webp', 'bmp', 'tif', 'tiff'],
        'file arsip (zip/rar)': ['zip', 'rar', '7z'],
    };
    for (const [kind, exts] of Object.entries(kinds)) if (exts.includes(ext)) return kind;
    return ext ? 'file .' + ext : 'file tanpa ekstensi';
}

function docDescribeProblem(file) {
    const ext = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
    const tooBig = file.size > DOC_MAX_BYTES;
    const size = docFormatSize(file.size);
    if (ext !== 'pdf') {
        return tooBig
            ? '"' + file.name + '" adalah ' + docKindOf(ext) + ', bukan PDF, dan ukurannya ' + size + ' (maks. 5 MB).'
            : '"' + file.name + '" adalah ' + docKindOf(ext) + ', bukan PDF. Simpan/ekspor dulu sebagai PDF, lalu unggah ulang.';
    }
    if (tooBig) return '"' + file.name + '" berukuran ' + size + ', melebihi batas 5 MB. Kompres PDF-nya atau pecah jadi beberapa file.';
    return null;
}

function docResetFile() {
    if (docXhr) { docXhr.abort(); docXhr = null; }
    docEl('doc-file-input').value = '';
    docEl('doc-upload-token').value = '';
    docEl('doc-submit').disabled = true;
    docEl('doc-file-card').classList.add('hidden');
    docEl('doc-dropzone').classList.remove('hidden');
    docEl('doc-progress-bar').style.width = '0%';
    docEl('doc-progress-bar').classList.remove('bg-red-500');
    docShowError('');
}

function docUpload(file) {
    docShowError('');
    if (!file) return;
    const problem = docDescribeProblem(file);
    if (problem) { docEl('doc-file-input').value = ''; docShowError(problem); return; }

    docEl('doc-dropzone').classList.add('hidden');
    docEl('doc-file-card').classList.remove('hidden');
    docEl('doc-file-name').textContent = file.name;
    docEl('doc-file-status').textContent = 'Mengunggah… 0%';
    docEl('doc-progress-bar').style.width = '0%';
    docEl('doc-submit').disabled = true;

    const nameInput = docEl('doc-name');
    if (!nameInput.value.trim()) nameInput.value = file.name.replace(/\.pdf$/i, '');

    const body = new FormData();
    body.append('doc_file', file);

    const xhr = new XMLHttpRequest();
    docXhr = xhr;
    xhr.open('POST', DOC_UPLOAD_ROUTE_TEMPLATE.replace('__ID__', docProjectId));
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('#form-doc-upload input[name="_token"]').value);
    xhr.upload.addEventListener('progress', function (e) {
        if (!e.lengthComputable) return;
        const pct = Math.round(e.loaded / e.total * 100);
        docEl('doc-progress-bar').style.width = pct + '%';
        docEl('doc-file-status').textContent = 'Mengunggah… ' + pct + '%';
    });
    xhr.addEventListener('load', function () {
        docXhr = null;
        let res = {};
        try { res = JSON.parse(xhr.responseText); } catch (e) {}
        if (xhr.status === 200 && res.token) {
            docEl('doc-upload-token').value = res.token;
            docEl('doc-progress-bar').style.width = '100%';
            docEl('doc-file-status').textContent = docFormatSize(res.size) + ' · Terunggah ✓';
            docEl('doc-submit').disabled = false;
        } else {
            const msg = (res.errors && Object.values(res.errors)[0][0]) || res.message || ('Upload gagal (HTTP ' + xhr.status + ').');
            docEl('doc-progress-bar').classList.add('bg-red-500');
            docEl('doc-file-status').textContent = 'Gagal';
            docShowError(msg);
        }
    });
    xhr.addEventListener('error', function () {
        docXhr = null;
        docEl('doc-progress-bar').classList.add('bg-red-500');
        docEl('doc-file-status').textContent = 'Gagal';
        docShowError('Koneksi terputus saat mengunggah. Coba lagi.');
    });
    xhr.send(body);
}

function openDocUpload(id, name) {
    docProjectId = id;
    docEl('doc-project-name').textContent = name + ' (' + id + ')';
    docEl('form-doc-upload').action = DOC_STORE_ROUTE_TEMPLATE.replace('__ID__', id);
    docEl('modal-doc-upload').showModal();
}

if (docEl('modal-doc-upload')) {
    const dropzone = docEl('doc-dropzone');
    dropzone.addEventListener('click', () => docEl('doc-file-input').click());
    dropzone.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); docEl('doc-file-input').click(); } });
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-green-400', 'bg-green-50/40'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('border-green-400', 'bg-green-50/40'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('border-green-400', 'bg-green-50/40');
        docUpload(e.dataTransfer.files[0]);
    });
    docEl('doc-file-input').addEventListener('change', (e) => docUpload(e.target.files[0]));
    docEl('doc-file-remove').addEventListener('click', docResetFile);
    docEl('form-doc-upload').addEventListener('submit', function (e) {
        if (!docEl('doc-upload-token').value) { e.preventDefault(); docShowError('Unggah file PDF dulu sebelum menyimpan.'); return; }
        docEl('doc-submit').disabled = true;
        docEl('doc-submit').textContent = 'Menyimpan…';
    });
    docEl('modal-doc-upload').addEventListener('close', function () {
        docResetFile();
        docEl('form-doc-upload').reset();
        docEl('doc-submit').textContent = 'Simpan';
    });
}

@endif
</script>
@endpush

@endsection
