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
                    <th class="w-[10%] py-1.5 pr-2 font-medium">Notes</th>
                    @if($isAdmin)<th class="w-[6%] py-1.5 pr-4"></th>@endif
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
                                @foreach(['KONTRAK','RKST','RAB','BAST','SOP','BOQ'] as $dt)
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
                                    <details class="relative inline-block text-left" data-row-menu>
                                        <summary class="cursor-pointer list-none inline-flex items-center gap-1 rounded-lg bg-green-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm transition-all duration-200 ease-out hover:bg-green-700 hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 active:scale-95">
                                            INPUT
                                            <span class="text-[10px]">▾</span>
                                        </summary>
                                        <div class="absolute right-0 top-[calc(100%+0.25rem)] z-20 w-36 rounded-xl border border-slate-200 bg-white py-1 shadow-xl shadow-slate-200/80 text-left">
                                            <button type="button" onclick="this.closest('details').removeAttribute('open'); openBoq('{{ $p->id }}', {{ $p->toJson() }})"
                                                    class="block w-full px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-slate-50">BoQ</button>
                                            <button type="button" onclick="this.closest('details').removeAttribute('open'); openKak('{{ $p->id }}', {{ $p->toJson() }})"
                                                    class="block w-full px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-slate-50">KAK/RKST</button>
                                        </div>
                                    </details>
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

{{-- ══ Modal: Upload KAK/RKST (Equipment & Technology Commercial) ══ --}}
@if($canCreateProject)
<dialog id="modal-kak" class="max-w-lg w-full">
    <div class="panel m-0 max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <h2 class="text-xl font-black">Upload KAK/RKST</h2>
                <p class="text-xs text-slate-400 mt-0.5" id="kak-project-name">&nbsp;</p>
            </div>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>
        <form id="form-kak" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="doc_type" value="RKST">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Dokumen KAK/RKST *</label>
                <input type="file" name="doc_file" required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all duration-200 ease-out hover:bg-blue-700 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-95">
                    Upload
                </button>
            </div>
        </form>
    </div>
</dialog>
@endif

{{-- ══ Modal: Input BoQ (Equipment & Technology Commercial) ══ --}}
@if($canCreateProject)
<dialog id="modal-boq" class="max-w-4xl w-full">
    <div class="panel m-0 max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <h2 class="text-xl font-black">Input BoQ</h2>
                <p class="text-xs text-slate-400 mt-0.5" id="boq-project-name">&nbsp;</p>
            </div>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>
        <form id="form-boq" method="POST" enctype="multipart/form-data">
            @csrf
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-semibold text-slate-600">Komponen BoQ *</label>
                    <button type="button" id="boq-add-row"
                            class="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm transition-all duration-200 ease-out hover:bg-rose-700 hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 active:scale-95">
                        + Komponen
                    </button>
                </div>
                <div id="boq-components" class="grid gap-2"></div>
            </div>
            <div class="mt-4">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Upload Dokumen BoQ</label>
                <input type="file" name="boq_file" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all duration-200 ease-out hover:bg-rose-700 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-95">
                    Simpan BoQ
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

let boqRowIndex = 0;
const BOQ_ROUTE_TEMPLATE = '{{ route('monitoring.boq.store', ['project' => '__ID__']) }}';

function boqRowTemplate(i) {
    return `<div class="flex flex-nowrap items-center gap-2" data-boq-row>
        <input type="text" name="components[${i}][bdg_name]" placeholder="Komponen" required class="flex-[2] min-w-0 rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <select name="components[${i}][bdg_type]" required class="flex-[2] min-w-0 rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="" disabled selected hidden>Jenis</option>
            <option value="PENGADAAN">Pengadaan</option>
            <option value="PEKERJAAN">Pekerjaan</option>
            <option value="JASA">Jasa</option>
        </select>
        <input type="number" name="components[${i}][bdg_value]" placeholder="Jumlah" min="0" required class="flex-1 min-w-0 rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <input type="text" name="components[${i}][bdg_type2]" placeholder="Unit" required class="flex-1 min-w-0 rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <button type="button" class="shrink-0 h-9 w-9 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 hover:bg-red-50 hover:text-red-500 transition" onclick="this.closest('[data-boq-row]').remove()">✕</button>
    </div>`;
}

function boqAddRow() {
    document.getElementById('boq-components').insertAdjacentHTML('beforeend', boqRowTemplate(boqRowIndex));
    boqRowIndex++;
}

document.getElementById('boq-add-row')?.addEventListener('click', boqAddRow);

function openBoq(id, data) {
    document.getElementById('boq-project-name').textContent = data.pjct_name + ' (' + id + ')';
    document.getElementById('form-boq').action = BOQ_ROUTE_TEMPLATE.replace('__ID__', id);
    document.getElementById('boq-components').innerHTML = '';
    boqRowIndex = 0;
    boqAddRow();
    document.getElementById('modal-boq').showModal();
}

document.getElementById('modal-boq')?.addEventListener('close', function() {
    document.getElementById('form-boq').reset();
    document.getElementById('boq-components').innerHTML = '';
    boqRowIndex = 0;
});

const KAK_ROUTE_TEMPLATE = '{{ route('monitoring.docs.store', ['project' => '__ID__']) }}';

function openKak(id, data) {
    document.getElementById('kak-project-name').textContent = data.pjct_name + ' (' + id + ')';
    document.getElementById('form-kak').action = KAK_ROUTE_TEMPLATE.replace('__ID__', id);
    document.getElementById('modal-kak').showModal();
}

document.getElementById('modal-kak')?.addEventListener('close', function() {
    document.getElementById('form-kak').reset();
});

// Row action dropdowns: only one open at a time, close when clicking outside
document.querySelectorAll('details[data-row-menu]').forEach(function(menu) {
    menu.addEventListener('toggle', function() {
        if (menu.open) {
            document.querySelectorAll('details[data-row-menu][open]').forEach(function(other) {
                if (other !== menu) other.removeAttribute('open');
            });
        }
    });
});

document.addEventListener('click', function(e) {
    document.querySelectorAll('details[data-row-menu][open]').forEach(function(menu) {
        if (!menu.contains(e.target)) menu.removeAttribute('open');
    });
});
@endif
</script>
@endpush

@endsection
