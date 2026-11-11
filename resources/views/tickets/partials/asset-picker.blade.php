{{--
    Pemilih device project (ast_main) — dimuat via AJAX dari tickets.project-assets.
    Props: $projectId (project tetap) atau $projectSelect (selector <select> project di form yang sama),
           $selected (array id device terpilih), $issueType (jenis masalah awal).
    Label wajib/opsional mengikuti input [name=issue_type] di form yang sama (lihat app.js initAssetPickers).
--}}
@php
    $requiresDevice = in_array($issueType ?? 'kerusakan', \App\Models\Ticket::ISSUE_TYPES_REQUIRING_DEVICE, true);
@endphp
<div data-asset-picker
     data-endpoint="{{ route('tickets.project-assets') }}"
     data-project-id="{{ $projectId ?? '' }}"
     @if(!empty($projectSelect)) data-project-select="{{ $projectSelect }}" @endif
     data-selected='@json(array_values($selected ?? []))'
     data-requiring-types='@json(\App\Models\Ticket::ISSUE_TYPES_REQUIRING_DEVICE)'>
    <div class="flex items-center justify-between gap-3">
        <span class="label mb-0">
            Device Terkait
            <span data-asset-required class="text-rose-500 {{ $requiresDevice ? '' : 'hidden' }}">*</span>
            <span data-asset-optional class="text-xs font-normal text-slate-400 {{ $requiresDevice ? 'hidden' : '' }}">(opsional untuk masalah sistem)</span>
        </span>
        <span class="text-xs font-semibold text-slate-500" data-asset-count>{{ count($selected ?? []) }} dipilih</span>
    </div>
    <input type="search" class="field mt-2" placeholder="Cari ID, tipe, brand, serial, atau lokasi…" data-asset-search>
    <div class="choice-panel mt-2" data-asset-list>
        <p class="px-2 py-6 text-center text-sm text-slate-500">{{ empty($projectId) ? 'Pilih project terlebih dahulu.' : 'Memuat device…' }}</p>
    </div>
    <p class="mt-2 text-xs text-slate-500">Hanya device yang terdaftar di project ini. Kondisi device otomatis menjadi <span class="font-semibold">Bad</span> sampai ticket di-approve (closed).</p>
</div>
