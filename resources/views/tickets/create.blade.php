@extends('layouts.app', ['title' => 'Buat Ticket', 'heading' => 'Buat Ticket'])

@section('content')
<nav class="mb-4 flex items-center gap-1.5 text-sm" aria-label="Breadcrumb">
    <a href="{{ route('tickets.index') }}" class="text-slate-500 hover:text-slate-700">Tickets</a>
    <span class="text-slate-300">›</span>
    <span class="font-medium text-slate-900">Buat Ticket</span>
</nav>

<div class="panel-soft">
    <p class="text-sm uppercase tracking-[0.3em] text-brand-600">Laporan Kerusakan</p>
    <h2 class="mt-2 text-3xl font-black leading-tight text-slate-900">Laporkan kerusakan, accident, atau masalah sistem</h2>
    <ol class="mt-4 grid gap-2 text-sm text-slate-600 md:grid-cols-4">
        <li class="rounded-2xl border border-slate-200 bg-white px-4 py-3"><span class="font-semibold text-slate-900">1. Open</span> — teknisi membuat ticket + dokumen kronologi.</li>
        <li class="rounded-2xl border border-slate-200 bg-white px-4 py-3"><span class="font-semibold text-slate-900">2. Escalated</span> — bila perlu tim pusat, dengan BA serah terima.</li>
        <li class="rounded-2xl border border-slate-200 bg-white px-4 py-3"><span class="font-semibold text-slate-900">3. Solved</span> — teknisi menandai selesai.</li>
        <li class="rounded-2xl border border-slate-200 bg-white px-4 py-3"><span class="font-semibold text-slate-900">4. Closed</span> — atasan meng-approve.</li>
    </ol>
</div>

<form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" data-ajax-form data-reload="false" class="mt-6">
    @csrf
    <div class="panel">
        <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_340px] xl:items-start">
            <div class="min-w-0 space-y-6">
                <div>
                    <label class="label">Project <span class="text-rose-500">*</span></label>
                    <select class="field" name="pjct_id" data-ticket-project required>
                        <option value="" disabled hidden selected>Pilih project</option>
                        @foreach($pjctProjects as $project)
                            <option value="{{ $project->id }}" data-client="{{ $project->pjct_client }}">{{ $project->pjct_name }} ({{ $project->pjct_div }})</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-slate-500">Client: <span class="font-semibold text-slate-700" data-ticket-client-label>-</span></p>
                </div>

                <div>
                    <span class="label">Jenis Masalah <span class="text-rose-500">*</span></span>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach($issueTypes as $value => $label)
                            <label class="choice-card">
                                <input class="sr-only" type="radio" name="issue_type" value="{{ $value }}" @checked($loop->first) required>
                                <span class="choice-card-box">
                                    <span class="choice-indicator">✓</span>
                                    <span>
                                        <span class="block font-semibold">{{ $label }}</span>
                                        <span class="block text-xs text-slate-500">{{ in_array($value, \App\Models\Ticket::ISSUE_TYPES_REQUIRING_DEVICE, true) ? 'Device wajib dipilih' : 'Device opsional' }}</span>
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @include('tickets.partials.asset-picker', [
                    'projectSelect' => '[data-ticket-project]',
                    'selected'      => [],
                    'issueType'     => array_key_first($issueTypes),
                ])

                <div>
                    <label class="label">Judul <span class="text-rose-500">*</span></label>
                    <input class="field" name="subject" maxlength="255" placeholder="mis. Laptop meja check-in 3 tidak bisa menyala" required>
                </div>
                <div>
                    <label class="label">Kronologi & Rencana Penanganan <span class="text-rose-500">*</span></label>
                    <textarea class="field min-h-48" name="description" placeholder="Waktu & lokasi kejadian, kronologi, dampak, dan resolusi yang akan dijalani" required></textarea>
                </div>
                <div>
                    <label class="label">Dokumen Laporan Kerusakan / Kronologi <span class="text-rose-500">*</span></label>
                    <input class="field file:mr-4 file:rounded-2xl file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:font-semibold file:text-white" type="file" name="documents[]" multiple required>
                    <p class="mt-2 text-xs text-slate-500">PDF, JPG, PNG, Word, atau Excel — maks. 10 MB per file.</p>
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-5 xl:border-l xl:border-slate-200 xl:pl-6">
                <div>
                    <label class="label">Pelapor</label>
                    <input class="field" name="reporter_name" maxlength="255" placeholder="Nama user client yang melapor">
                </div>
                <div>
                    <label class="label">Prioritas <span class="text-rose-500">*</span></label>
                    <select class="field" name="priority" required>
                        @foreach($priorities as $priority)
                            <option value="{{ $priority }}" @selected($priority === 'medium')>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Kategori</label>
                    <select class="field" name="category_id" data-ticket-category>
                        <option value="">Tanpa kategori</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Subkategori</label>
                    <select class="field" name="subcategory_id" data-ticket-subcategory>
                        <option value="">Tanpa subkategori</option>
                        @foreach($categories as $category)
                            @foreach($category->children as $child)
                                <option value="{{ $child->id }}" data-parent="{{ $category->id }}">{{ $category->name }} / {{ $child->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                @foreach($customFields as $field)
                    <div>
                        <label class="label">{{ $field->name }} @if($field->is_required)<span class="text-rose-500">*</span>@endif</label>
                        @if($field->type === 'textarea')
                            <textarea class="field min-h-28" name="custom_fields[{{ $field->key }}]"></textarea>
                        @elseif($field->type === 'select')
                            <select class="field" name="custom_fields[{{ $field->key }}]">
                                <option value="">Pilih {{ $field->name }}</option>
                                @foreach($field->options ?? [] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                        @else
                            <input class="field" type="{{ $field->type === 'number' ? 'number' : ($field->type === 'date' ? 'date' : 'text') }}" name="custom_fields[{{ $field->key }}]">
                        @endif
                    </div>
                @endforeach

                <div class="rounded-2xl bg-slate-50 px-4 py-3 text-xs text-slate-500">
                    Ticket tercatat atas nama Anda sebagai teknisi. Approval penyelesaian dikirim ke atasan langsung Anda.
                </div>
                <button class="btn-primary w-full" type="submit">Buat Ticket</button>
            </div>
        </div>
    </div>
</form>
@endsection
