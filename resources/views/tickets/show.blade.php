@extends('layouts.app', ['title' => $ticket->ticket_number, 'heading' => $ticket->ticket_number])

@section('content')
@php
    $everEscalated = $ticket->status === 'escalated' || $ticket->activityLogs->contains('action', 'ticket_escalate');
    $order = ['open' => 0, 'escalated' => 1, 'solved' => 2, 'closed' => 3];
    $stepState = function (string $key) use ($ticket, $everEscalated, $order): string {
        if ($key === $ticket->status) {
            return 'current';
        }
        if ($key === 'escalated') {
            return $everEscalated ? 'done' : 'skip';
        }
        return $order[$key] < ($order[$ticket->status] ?? 0) ? 'done' : 'todo';
    };
    $steps = [
        'open'      => 'Teknisi menangani',
        'escalated' => $everEscalated ? 'Ditangani tim pusat' : 'Hanya jika perlu',
        'solved'    => 'Menunggu approval',
        'closed'    => 'Disetujui atasan',
    ];
    $stepClasses = [
        'current' => 'border-brand-500 bg-brand-600 text-white',
        'done'    => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'todo'    => 'border-slate-200 bg-white text-slate-500',
        'skip'    => 'border-dashed border-slate-200 bg-white text-slate-300',
    ];

    $actionDialogs = [
        'solve' => [
            'title' => 'Tandai Selesai (Solved)', 'button' => 'Tandai Selesai', 'primary' => true,
            'intro' => 'Kerusakan sudah ditangani. Ticket akan dikirim ke atasan untuk di-approve.',
            'note' => 'Tindakan perbaikan yang dilakukan', 'noteRequired' => true,
            'doc' => 'Dokumen penyelesaian (opsional)', 'docRequired' => false,
        ],
        'escalate' => [
            'title' => 'Eskalasi ke Tim Pusat', 'button' => 'Eskalasi ke Pusat', 'primary' => false,
            'intro' => 'Kerusakan tidak bisa diatasi on-site — unit diserahkan ke tim pusat divisi project.',
            'note' => 'Alasan eskalasi', 'noteRequired' => true,
            'doc' => 'BA serah terima unit ke tim pusat', 'docRequired' => true,
        ],
        'return' => [
            'title' => 'Kembalikan ke Teknisi', 'button' => 'Kembalikan ke Teknisi', 'primary' => true,
            'intro' => 'Unit sudah diperbaiki/diganti tim pusat. Status kembali Open agar teknisi menyelesaikan ticket.',
            'note' => 'Hasil perbaikan / penggantian unit', 'noteRequired' => true,
            'doc' => 'BA serah terima unit dari pusat ke teknisi', 'docRequired' => true,
        ],
        'approve' => [
            'title' => 'Approve & Tutup Ticket', 'button' => 'Approve & Close', 'primary' => true,
            'intro' => 'Ticket ditutup dan kondisi device dikembalikan seperti sebelum dilaporkan.',
            'note' => 'Catatan (opsional)', 'noteRequired' => false,
            'doc' => null, 'docRequired' => false,
        ],
        'reject' => [
            'title' => 'Tolak Penyelesaian', 'button' => 'Tolak', 'primary' => false,
            'intro' => 'Ticket kembali ke status Open untuk ditindaklanjuti teknisi.',
            'note' => 'Alasan penolakan', 'noteRequired' => true,
            'doc' => null, 'docRequired' => false,
        ],
    ];
    $availableActions = collect($actionDialogs)->keys()->filter(fn ($action) => $abilities[$action]);

    $logs = $ticket->activityLogs->sortByDesc('created_at')->values();
    $fileHint = 'PDF, JPG, PNG, Word, atau Excel — maks. 10 MB per file.';
@endphp

<nav class="mb-4 flex items-center gap-1.5 text-sm" aria-label="Breadcrumb">
    <a href="{{ route('tickets.index') }}" class="text-slate-500 hover:text-slate-700">Tickets</a>
    <span class="text-slate-300">›</span>
    <span class="font-medium text-slate-900">{{ $ticket->ticket_number }}</span>
</nav>

@if($errors->any())
    <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

{{-- Alur status --}}
<ol class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4">
    @foreach($steps as $key => $hint)
        @php $state = $stepState($key); @endphp
        <li class="rounded-2xl border px-4 py-3 {{ $stepClasses[$state] }}">
            <p class="text-xs font-bold uppercase tracking-widest">{{ \App\Models\Ticket::STATUS_LABELS[$key] }}</p>
            <p class="mt-0.5 text-xs {{ $state === 'current' ? 'text-white/80' : '' }}">{{ $hint }}</p>
        </li>
    @endforeach
</ol>

<div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
    <div class="min-w-0 space-y-6">
        <div class="panel">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <p class="text-sm uppercase tracking-[0.2em] text-slate-500">{{ $ticket->ticket_number }}</p>
                    <h2 class="mt-2 text-3xl font-black break-words">{{ $ticket->subject }}</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="badge {{ $ticket->statusBadgeClass() }}">{{ $ticket->statusLabel() }}</span>
                    <span class="badge {{ $ticket->priorityBadgeClass() }}">{{ \Illuminate\Support\Str::headline($ticket->priority) }}</span>
                    <span class="badge bg-slate-100 text-slate-700">{{ $ticket->issueTypeLabel() }}</span>
                    @if($ticket->isResolutionBreached())
                        <span class="badge bg-rose-100 text-rose-700">SLA Breached</span>
                    @endif
                </div>
            </div>
            <p class="mt-5 text-xs font-bold uppercase tracking-widest text-slate-400">Kronologi & rencana penanganan</p>
            <p class="mt-2 whitespace-pre-line break-words text-slate-700">{{ $ticket->description }}</p>

            @if($ticket->customFieldValues->filter(fn ($value) => filled($value->value))->isNotEmpty())
                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                    @foreach($ticket->customFieldValues->filter(fn ($value) => filled($value->value)) as $value)
                        <div><dt class="text-slate-500">{{ $value->customField?->name }}</dt><dd class="font-semibold">{{ $value->value }}</dd></div>
                    @endforeach
                </dl>
            @endif
        </div>

        {{-- Dokumen alur --}}
        <div class="panel">
            <h3 class="text-xl font-black">Dokumen</h3>
            @forelse($documents as $type => $files)
                <div class="mt-4">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ \App\Models\TicketAttachment::DOC_TYPES[$type] }}</p>
                    <ul class="mt-2 divide-y divide-slate-100 rounded-2xl border border-slate-200">
                        @foreach($files as $attachment)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                                <a class="min-w-0 break-all font-semibold text-brand-600 hover:text-brand-700" target="_blank" href="{{ route('tickets.attachments.download', [$ticket, $attachment]) }}">{{ $attachment->original_name }}</a>
                                <span class="text-xs text-slate-500">{{ $attachment->user?->user_name ?? '-' }} · {{ $attachment->created_at->format('d M Y H:i') }} · {{ number_format($attachment->size / 1024, 0) }} KB</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="mt-4 text-sm text-slate-500">Belum ada dokumen.</p>
            @endforelse
        </div>

        {{-- Diskusi --}}
        <div class="panel">
            <h3 class="text-xl font-black">Diskusi</h3>
            <div class="mt-4 space-y-3">
                @forelse($ticket->messages as $message)
                    <div class="rounded-3xl bg-slate-100 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold">{{ $message->user?->user_name ?? 'Deleted user' }}</p>
                            <p class="text-sm text-slate-500">{{ $message->created_at->format('d M Y H:i') }}</p>
                        </div>
                        <div class="mt-3 whitespace-pre-line break-words text-sm leading-7">{{ $message->body }}</div>
                        @if($message->attachments->count())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($message->attachments as $attachment)
                                    <a class="btn-soft max-w-full truncate" target="_blank" href="{{ route('tickets.attachments.download', [$ticket, $attachment]) }}">{{ $attachment->original_name }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada komentar.</p>
                @endforelse
            </div>

            @if($abilities['comment'])
                <form method="POST" action="{{ route('tickets.messages.store', $ticket) }}" enctype="multipart/form-data" class="mt-5 space-y-3">
                    @csrf
                    <textarea class="field min-h-28" name="body" placeholder="Tulis komentar. Mention user dengan @nama" required>{{ old('body') }}</textarea>
                    <input class="field file:mr-4 file:rounded-2xl file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:font-semibold file:text-white" type="file" name="attachments[]" multiple>
                    <div class="flex justify-end"><button class="btn-primary" type="submit">Kirim</button></div>
                </form>
            @elseif($ticket->isClosed())
                <p class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Ticket sudah <span class="font-semibold">closed</span> — tidak bisa menambah komentar.</p>
            @endif
        </div>

        {{-- Riwayat --}}
        <div class="panel">
            <h3 class="text-xl font-black">Riwayat</h3>
            <div class="mt-4 space-y-3">
                @forelse($logs->take(6) as $log)
                    @include('tickets.partials.log-entry', ['log' => $log])
                @empty
                    <p class="text-sm text-slate-500">Belum ada riwayat.</p>
                @endforelse
            </div>
            @if($logs->count() > 6)
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-600 hover:text-slate-900">Lihat {{ $logs->count() - 6 }} riwayat lainnya</summary>
                    <div class="mt-3 space-y-3">
                        @foreach($logs->slice(6) as $log)
                            @include('tickets.partials.log-entry', ['log' => $log])
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </div>

    <div class="min-w-0 space-y-6">
        {{-- Aksi alur --}}
        <div class="panel">
            <h3 class="text-xl font-black">Status & Tindakan</h3>
            <div class="mt-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                @switch($ticket->status)
                    @case('open')
                        Teknisi menangani di lokasi. Tandai <span class="font-semibold">selesai</span> bila sudah diperbaiki, atau <span class="font-semibold">eskalasi</span> bila perlu tim pusat.
                        @break
                    @case('escalated')
                        Unit sedang ditangani tim pusat divisi <span class="font-semibold">{{ $ticket->project?->pjct_div ?? '-' }}</span>. Setelah diperbaiki/diganti, tim divisi mengembalikan status ke Open dengan BA serah terima.
                        @break
                    @case('solved')
                        Menunggu approval <span class="font-semibold">{{ $approver?->user_name ?? 'superadmin' }}</span>.
                        @break
                    @case('closed')
                        Ditutup {{ $ticket->closed_at?->format('d M Y H:i') ?? '' }}@if($ticket->approver) — disetujui <span class="font-semibold">{{ $ticket->approver->user_name }}</span>@endif.
                        @break
                @endswitch
            </div>
            @if($availableActions->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($availableActions as $action)
                        <button type="button" class="{{ $actionDialogs[$action]['primary'] ? 'btn-primary' : 'btn-soft' }}" data-open-dialog="dialog-{{ $action }}">{{ $actionDialogs[$action]['button'] }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel">
            <h3 class="text-xl font-black">Detail</h3>
            <dl class="mt-4 space-y-3 text-sm">
                @foreach([
                    'Client'        => $ticket->clientLabel(),
                    'Pelapor'       => $ticket->reporter_name ?? '-',
                    'Project'       => $ticket->project?->pjct_name ?? '-',
                    'Divisi'        => $ticket->project?->pjct_div ?? '-',
                    'Teknisi'       => $ticket->creator?->user_name ?? '-',
                    'Atasan'        => $approver?->user_name ?? 'Superadmin',
                    'Kategori'      => $ticket->subcategory?->name ?? $ticket->category?->name ?? '-',
                    'Dibuat'        => $ticket->created_at->format('d M Y H:i'),
                ] as $label => $value)
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $label }}</dt><dd class="text-right font-medium">{{ $value }}</dd></div>
                @endforeach
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Target Selesai</dt>
                    <dd class="text-right {{ $ticket->resolutionDueClass() }}">{{ $ticket->resolution_due_at?->format('d M Y H:i') ?? '-' }}</dd>
                </div>
                @if($ticket->resolved_at)
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Solved</dt><dd class="text-right font-medium">{{ $ticket->resolved_at->format('d M Y H:i') }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="panel">
            <h3 class="text-xl font-black">Device Terkait</h3>
            <div class="mt-4 space-y-2">
                @forelse($ticket->assets as $asset)
                    <div class="flex items-start justify-between gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $asset->id }} · {{ $asset->ast_type }}</p>
                            <p class="text-xs text-slate-500">{{ trim($asset->ast_brand . ' ' . $asset->ast_brandmodel) }}@if($asset->ast_serial) · SN {{ $asset->ast_serial }}@endif @if($asset->ast_userloc) · {{ $asset->ast_userloc }}@endif</p>
                            @if($asset->pivot->cond_before)
                                <p class="text-xs text-slate-400">Kondisi sebelum dilaporkan: {{ $asset->pivot->cond_before }}</p>
                            @endif
                        </div>
                        <span class="badge shrink-0 {{ $asset->condBadgeClass() }}">{{ $asset->ast_cond ?? '-' }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Tidak ada device terkait.</p>
                @endforelse
            </div>
        </div>

        @if($abilities['update'])
            <details class="panel" @if($errors->hasAny(['priority', 'issue_type', 'category_id', 'subcategory_id', 'ast_ids', 'ast_ids.*'])) open @endif>
                <summary class="cursor-pointer list-none text-xl font-black">Ubah Detail</summary>
                <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="mt-4 grid gap-4">
                    @csrf
                    @method('PATCH')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Jenis Masalah</label>
                            <select class="field" name="issue_type">
                                @foreach($issueTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('issue_type', $ticket->issue_type) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Prioritas</label>
                            <select class="field" name="priority">
                                @foreach($priorities as $priority)
                                    <option value="{{ $priority }}" @selected(old('priority', $ticket->priority) === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Kategori</label>
                            <select class="field" name="category_id" data-ticket-category>
                                <option value="">Tanpa kategori</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id', $ticket->category_id) === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Subkategori</label>
                            <select class="field" name="subcategory_id" data-ticket-subcategory>
                                <option value="">Tanpa subkategori</option>
                                @foreach($categories as $category)
                                    @foreach($category->children as $child)
                                        <option value="{{ $child->id }}" data-parent="{{ $category->id }}" @selected((string) old('subcategory_id', $ticket->subcategory_id) === (string) $child->id)>{{ $category->name }} / {{ $child->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @include('tickets.partials.asset-picker', [
                        'projectId' => $ticket->pjct_id,
                        'selected'  => old('ast_ids', $ticket->assets->pluck('id')->all()),
                        'issueType' => old('issue_type', $ticket->issue_type),
                    ])
                    <div class="flex justify-end"><button class="btn-primary" type="submit">Simpan</button></div>
                </form>
            </details>
        @endif
    </div>
</div>

{{-- Dialog aksi alur --}}
@foreach($availableActions as $action)
    @php $dialog = $actionDialogs[$action]; @endphp
    <dialog id="dialog-{{ $action }}" class="w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0">
        <form method="POST" action="{{ route('tickets.transition', [$ticket, $action]) }}" enctype="multipart/form-data" class="panel m-0 space-y-4">
            @csrf
            <input type="hidden" name="_dialog" value="dialog-{{ $action }}">
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                <h3 class="text-xl font-black">{{ $dialog['title'] }}</h3>
                <button type="button" class="btn-soft" data-close-dialog>Batal</button>
            </div>
            <p class="text-sm text-slate-600">{{ $dialog['intro'] }}</p>
            <div>
                <label class="label">{{ $dialog['note'] }} @if($dialog['noteRequired'])<span class="text-rose-500">*</span>@endif</label>
                <textarea class="field min-h-28" name="note" @required($dialog['noteRequired'])>{{ old('_dialog') === 'dialog-' . $action ? old('note') : '' }}</textarea>
            </div>
            @if($dialog['doc'])
                <div>
                    <label class="label">{{ $dialog['doc'] }} @if($dialog['docRequired'])<span class="text-rose-500">*</span>@endif</label>
                    <input class="field file:mr-4 file:rounded-2xl file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:font-semibold file:text-white" type="file" name="documents[]" multiple @required($dialog['docRequired'])>
                    <p class="mt-1 text-xs text-slate-500">{{ $fileHint }}</p>
                </div>
            @endif
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-soft" data-close-dialog>Batal</button>
                <button type="submit" class="btn-primary">{{ $dialog['button'] }}</button>
            </div>
        </form>
    </dialog>
@endforeach

@if($errors->any() && old('_dialog'))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => document.getElementById(@json(old('_dialog')))?.showModal());
    </script>
    @endpush
@endif
@endsection
