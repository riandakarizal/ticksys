@extends('layouts.app', ['title' => 'Project Manpower', 'heading' => 'Project Manpower'])

@section('content')

{{-- ── KPI Cards ────────────────────────────────────────────────────── --}}
<div class="flex gap-3 mb-5">
    <div class="flex-1 bg-slate-50 rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-2xl font-black text-slate-800">{{ number_format($kpi['total']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Total Manpower</p>
        <p class="text-[10px] text-slate-400 mt-0.5">Semua unit</p>
    </div>
    <div class="flex-1 bg-blue-50 rounded-xl border border-blue-200 shadow-sm p-4">
        <p class="text-2xl font-black text-blue-600">{{ number_format($kpi['tcop']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Technology Operation</p>
        <p class="text-[10px] text-blue-400 mt-0.5">TC & OM</p>
    </div>
    <div class="flex-1 bg-violet-50 rounded-xl border border-violet-200 shadow-sm p-4">
        <p class="text-2xl font-black text-violet-600">{{ number_format($kpi['reg2']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Regional 2</p>
        <p class="text-[10px] text-violet-400 mt-0.5">Unit regional</p>
    </div>
    <div class="flex-1 bg-teal-50 rounded-xl border border-teal-200 shadow-sm p-4">
        <p class="text-2xl font-black text-teal-600">{{ number_format($kpi['teknisi']) }}</p>
        <p class="text-xs text-slate-500 mt-1">Teknisi</p>
        <p class="text-[10px] text-teal-400 mt-0.5">Field engineer</p>
    </div>
</div>

{{-- ── Bulk Import (superadmin only) ───────────────────────────────── --}}
@if(auth()->user()->isSuperAdmin())
<div class="flex flex-wrap items-center justify-between gap-3 mb-4 px-4 py-3 bg-white rounded-xl border border-slate-200 shadow-sm">
    <div>
        <p class="text-sm font-bold text-slate-800">Input Data Manpower</p>
        <p class="text-[11px] text-slate-400 mt-0.5">Upload Excel untuk menambah manpower secara massal. NIK yang sudah terdaftar di project yang sama akan dilewati.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('project.manpower.import.template') }}"
           class="btn-soft rounded-xl px-3 py-2 text-xs gap-1.5">
            <span>⬇</span> Download Template
        </a>
        <button type="button"
                onclick="document.getElementById('modal-manpower-import').showModal()"
                class="btn-primary rounded-xl px-3 py-2 text-xs gap-1.5">
            <span>⬆</span> Import Excel
        </button>
    </div>
</div>
@endif

{{-- ── Filter & Table ──────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

    {{-- Filter Bar --}}
    <div class="px-4 py-3 border-b border-slate-100">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[180px]">
                <label class="label">Search</label>
                <input class="field" type="text" name="search" value="{{ $search }}"
                    placeholder="Nama / NIK / site / area / PKWT...">
            </div>
            <div class="w-44 shrink-0">
                <label class="label">Unit</label>
                <select class="field" name="unit">
                    <option value="">All Unit</option>
                    @foreach($filterUnits as $u)
                        <option value="{{ $u }}" @selected($unit===$u)>{{ $u }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40 shrink-0">
                <label class="label">Jabatan</label>
                <select class="field" name="jabatan">
                    <option value="">All Jabatan</option>
                    @foreach($filterJabatans as $j)
                        <option value="{{ $j }}" @selected($jabatan===$j)>{{ $j }}</option>
                    @endforeach
                </select>
            </div>
            <div class="shrink-0 flex items-end gap-2 pb-0.5">
                <button type="submit" class="btn-primary rounded-xl px-3 py-2 text-xs">Search</button>
                <a href="{{ route('project.manpower') }}" class="btn-soft rounded-xl px-3 py-2 text-xs">Reset</a>
            </div>
        </form>
    </div>

    {{-- Table Header --}}
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
        <span class="text-xs text-slate-500">
            {{ number_format($employees->total()) }} karyawan
            @if($employees->total() !== $kpi['total'])
                <span class="text-slate-400">(filtered dari {{ number_format($kpi['total']) }})</span>
            @endif
        </span>
        <span class="text-xs text-slate-400">Halaman {{ $employees->currentPage() }} / {{ $employees->lastPage() }}</span>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="text-left text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 font-medium">#</th>
                    <th class="px-3 py-2 font-medium">Nama</th>
                    <th class="px-3 py-2 font-medium">NIK</th>
                    <th class="px-3 py-2 font-medium">Jabatan</th>
                    <th class="px-3 py-2 font-medium">Unit</th>
                    <th class="px-3 py-2 font-medium">Site / Area</th>
                    <th class="px-3 py-2 font-medium">Region</th>
                    <th class="px-3 py-2 font-medium">No. PKWT</th>
                    <th class="px-3 py-2 font-medium">Periode PKWT</th>
                    <th class="px-3 py-2 font-medium">Kontak</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($employees as $i => $e)
                <tr class="hover:bg-slate-50/60 transition">
                    <td class="px-4 py-2.5 text-slate-400">{{ ($employees->currentPage()-1)*$employees->perPage()+$i+1 }}</td>
                    <td class="px-3 py-2.5">
                        <p class="font-semibold text-slate-800">{{ $e->emp_name }}</p>
                        <p class="text-slate-400 text-[10px]">{{ $e->id }}</p>
                    </td>
                    <td class="px-3 py-2.5 font-mono text-[10px] text-slate-500 whitespace-nowrap">{{ $e->emp_id ?: '-' }}</td>
                    <td class="px-3 py-2.5 whitespace-nowrap">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold
                            {{ $e->emp_levname === 'Koordinator Teknisi' ? 'bg-brand-50 text-brand-700' :
                               ($e->emp_levname === 'Admin' ? 'bg-violet-100 text-violet-700' : 'bg-slate-100 text-slate-600') }}">
                            {{ $e->emp_levname ?: '-' }}
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-slate-600 whitespace-nowrap">{{ $e->emp_unit ?: '-' }}</td>
                    <td class="px-3 py-2.5 text-slate-600 whitespace-nowrap">{{ $e->emp_div ?: '-' }}</td>
                    <td class="px-3 py-2.5 text-slate-500 whitespace-nowrap">{{ $e->emp_area ?: '-' }}</td>
                    <td class="px-3 py-2.5 text-slate-500 max-w-[180px] truncate text-[10px]" title="{{ $e->emp_coid }}">{{ $e->emp_coid ?: '-' }}</td>
                    <td class="px-3 py-2.5 whitespace-nowrap text-[10px]">
                        @if($e->emp_costart && $e->emp_coend)
                            @php $coend = \Carbon\Carbon::parse($e->emp_coend); @endphp
                            <span class="text-slate-500">{{ \Carbon\Carbon::parse($e->emp_costart)->format('d/m/y') }} - {{ $coend->format('d/m/y') }}</span>
                            @if($coend->isPast())
                                <span class="ml-1 inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 text-[9px] font-semibold text-rose-700">Expired</span>
                            @elseif($coend->diffInDays(now()) <= 30)
                                <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold text-amber-700">Segera berakhir</span>
                            @endif
                        @else
                            <span class="text-slate-300">-</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-slate-500 whitespace-nowrap font-mono text-[10px]">{{ $e->emp_contact ?: '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-12 text-center text-slate-400">Tidak ada data manpower.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($employees->hasPages())
    <div class="px-4 py-3 border-t border-slate-100">
        {{ $employees->links() }}
    </div>
    @endif

</div>

{{-- ══ Modal: Import Manpower dari Excel ══ --}}
@if(auth()->user()->isSuperAdmin())
<dialog id="modal-manpower-import" class="max-w-lg w-full">
    <div class="panel m-0 max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <h2 class="text-xl font-black">Import Manpower</h2>
                <p class="text-xs text-slate-400 mt-0.5">Format .xlsx / .xls &middot; maksimal 5 MB</p>
            </div>
            <button type="button" class="h-9 w-9 rounded-2xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-500 hover:bg-slate-200" onclick="this.closest('dialog').close()">✕</button>
        </div>

        <form method="POST" action="{{ route('project.manpower.import.preview') }}" enctype="multipart/form-data" id="manpower-import-form">
            @csrf

            <div id="manpower-drop-area"
                 class="border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center transition cursor-pointer hover:border-teal-400 hover:bg-teal-50/30"
                 onclick="document.getElementById('manpower-file-input').click()">
                <input type="file" name="file" id="manpower-file-input" accept=".xlsx,.xls" required class="hidden">
                <p class="text-3xl mb-2">📄</p>
                <p class="text-sm font-semibold text-slate-700" id="manpower-file-name">Klik atau drag file ke sini</p>
                <p class="text-[11px] text-slate-400 mt-1">Isi data mulai baris ke-2 pada sheet <span class="font-mono">Manpower</span></p>
            </div>

            <p class="text-[11px] text-slate-400 mt-3">
                Belum punya template?
                <a href="{{ route('project.manpower.import.template') }}" class="text-teal-600 font-semibold hover:underline">Download di sini</a>
                — sheet <span class="font-mono">Referensi</span> berisi daftar Project ID yang valid dan contoh pengisian.
            </p>

            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="btn-soft" onclick="this.closest('dialog').close()">Batal</button>
                <button type="submit" class="btn-primary" id="manpower-import-submit">Lanjut ke Preview</button>
            </div>
        </form>
    </div>
</dialog>

<script>
(function () {
    const input = document.getElementById('manpower-file-input');
    const drop  = document.getElementById('manpower-drop-area');
    const label = document.getElementById('manpower-file-name');
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

    document.getElementById('manpower-import-form').addEventListener('submit', function () {
        const btn = document.getElementById('manpower-import-submit');
        btn.disabled = true;
        btn.textContent = 'Memproses…';
    });
})();
</script>
@endif

@endsection
