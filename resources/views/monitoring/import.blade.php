@extends('layouts.app', ['title' => 'Import Excel — Monitoring EQT', 'heading' => 'Import Data from Excel'])

@section('content')

@if(session('error'))
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm font-semibold text-red-700">{{ session('error') }}</div>
@endif

<div class="max-w-2xl mx-auto space-y-5">

    {{-- Section 1: Download Template --}}
    <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="text-sm font-bold text-slate-800 mb-1">1. Download Template</h2>
        <p class="text-xs text-slate-500 mb-4">Choose the template matching the data tab you want to input. Fill in data starting from row 2.</p>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-3">
            @php
            $templates = [
                ['type'=>'project_eq',   'label'=>'Equipment (EQ)',    'cols'=>14, 'card'=>'border-l-blue-500 hover:bg-blue-50',     'icon'=>'group-hover:text-blue-700'],
                ['type'=>'project_tech', 'label'=>'Technology (TECH)', 'cols'=>14, 'card'=>'border-l-violet-500 hover:bg-violet-50',   'icon'=>'group-hover:text-violet-700'],
                ['type'=>'handover',     'label'=>'Hand Over',         'cols'=>8,  'card'=>'border-l-emerald-500 hover:bg-emerald-50', 'icon'=>'group-hover:text-emerald-700'],
                ['type'=>'vehicle',      'label'=>'Vehicle',           'cols'=>9,  'card'=>'border-l-orange-400 hover:bg-orange-50',   'icon'=>'group-hover:text-orange-700'],
                ['type'=>'maintenance',  'label'=>'Maintenance',       'cols'=>9,  'card'=>'border-l-slate-400 hover:bg-slate-50',    'icon'=>'group-hover:text-slate-700'],
            ];
            @endphp

            @foreach($templates as $t)
                <a href="{{ route('monitoring.import.template.type', ['type' => $t['type']]) }}"
                   class="flex items-center gap-3 border border-slate-200 border-l-4 {{ $t['card'] }} rounded-lg px-3 py-2.5 transition group">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-800 truncate">{{ $t['label'] }}</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">{{ $t['cols'] }} columns</p>
                    </div>
                    <span class="text-slate-300 {{ $t['icon'] }} transition text-sm">⬇</span>
                </a>
            @endforeach
        </div>

        <div class="pt-2 border-t border-slate-100">
            <a href="{{ route('monitoring.import.template') }}"
               class="inline-flex items-center gap-2 text-xs text-slate-500 hover:text-slate-800 transition">
                <span>⬇</span>
                <span>Download all in 1 file (5 sheets) — for a complete data update</span>
            </a>
        </div>
    </div>

    {{-- Section 2: Upload File --}}
    <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="text-sm font-bold text-slate-800 mb-1">2. Upload Excel File</h2>
        <p class="text-xs text-slate-500 mb-4">File may contain 1 sheet or all 5 sheets. Sheets not present will be skipped.</p>

        <form method="POST" action="{{ route('monitoring.import.preview') }}" enctype="multipart/form-data" id="import-form">
            @csrf

            {{-- Drag & drop area --}}
            <div id="drop-area"
                 class="relative border-2 border-dashed border-slate-200 rounded-xl p-8 text-center transition cursor-pointer hover:border-blue-400 hover:bg-blue-50/30"
                 onclick="document.getElementById('file-input').click()">
                <input type="file" name="file" id="file-input" accept=".xlsx,.xls" required class="hidden">

                <div id="drop-idle" class="space-y-2">
                    <p class="text-3xl">⬆</p>
                    <p class="text-sm font-semibold text-slate-700">Drag .xlsx file here</p>
                    <p class="text-xs text-slate-400">or click to select a file</p>
                </div>

                <div id="drop-selected" class="hidden space-y-1">
                    <p class="text-3xl">📄</p>
                    <p class="text-sm font-semibold text-slate-700" id="drop-filename">—</p>
                    <p class="text-xs text-blue-600">Click to change file</p>
                </div>
            </div>

            <p class="text-[10px] text-slate-400 mt-2">Format: .xlsx · Max 10 MB</p>

            @error('file')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex items-center justify-between mt-5 pt-4 border-t border-slate-100">
                <a href="{{ route('monitoring.index') }}" class="btn-soft text-xs px-4 py-2">← Back</a>
                <button type="submit" id="submit-btn" class="btn-primary text-xs px-5 py-2" disabled>
                    Preview Changes →
                </button>
            </div>
        </form>
    </div>

    {{-- Info box --}}
    <div class="rounded-lg bg-slate-50 border border-slate-200 border-l-4 border-l-slate-400 p-4 text-xs text-slate-600 leading-relaxed">
        <p class="font-semibold text-slate-700 mb-1.5">Import Notes</p>
        <ul class="space-y-1 list-disc pl-4">
            <li>Import only <strong>adds</strong> and <strong>updates</strong> records — it never deletes data.</li>
            <li>Matching key: <code class="bg-slate-200 px-1 rounded">no_kontrak</code> for projects, <code class="bg-slate-200 px-1 rounded">nopol</code> for vehicles, <code class="bg-slate-200 px-1 rounded">nama_alat+area</code> for maintenance, <code class="bg-slate-200 px-1 rounded">nama+tahun</code> for hand over.</li>
            <li>After uploading, you will see a preview of all changes before data is saved.</li>
        </ul>
    </div>

</div>

<script>
(function () {
    const dropArea  = document.getElementById('drop-area');
    const fileInput = document.getElementById('file-input');
    const idle      = document.getElementById('drop-idle');
    const selected  = document.getElementById('drop-selected');
    const filename  = document.getElementById('drop-filename');
    const submitBtn = document.getElementById('submit-btn');

    function showFile(file) {
        if (!file) return;
        filename.textContent = file.name;
        idle.classList.add('hidden');
        selected.classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50');
    }

    fileInput.addEventListener('change', () => {
        showFile(fileInput.files[0]);
    });

    dropArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropArea.classList.add('border-blue-400', 'bg-blue-50/30');
    });

    dropArea.addEventListener('dragleave', () => {
        dropArea.classList.remove('border-blue-400', 'bg-blue-50/30');
    });

    dropArea.addEventListener('drop', (e) => {
        e.preventDefault();
        dropArea.classList.remove('border-blue-400', 'bg-blue-50/30');
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
            showFile(file);
        }
    });

    // Disable submit until file selected
    submitBtn.classList.add('opacity-50');
})();
</script>

@endsection
