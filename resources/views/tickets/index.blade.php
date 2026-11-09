@extends('layouts.app', ['title' => 'Tickets', 'heading' => 'Tickets'])

@section('content')
@php
    $hasFilters = collect(['search', 'pjct_id', 'status', 'priority', 'issue_type', 'category_id', 'client', 'date_from', 'date_to', 'needs_action'])
        ->contains(fn ($key) => filled(request($key)));
    $inputClass = 'border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:border-blue-400 focus:outline-none';
    $btnPrimaryClass = 'bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition';
    $tabs = ['' => ['Semua', $statusCounts->sum()]];
    foreach ($statuses as $value => $label) {
        $tabs[$value] = [$label, $statusCounts[$value] ?? 0];
    }
    $currentStatus = request('status', '');
    $needsAction = request()->boolean('needs_action');
@endphp

{{-- Ringkasan status --}}
<div class="mb-4 flex flex-wrap gap-2">
    <a href="{{ route('tickets.index', ['needs_action' => 1]) }}"
       class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $needsAction ? 'border-amber-500 bg-amber-500 text-white' : 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
        Perlu tindakan saya <span class="rounded-full bg-white/80 px-2 text-amber-700">{{ $needsActionCount }}</span>
    </a>
    @foreach($tabs as $value => [$label, $count])
        <a href="{{ route('tickets.index', array_filter(['status' => $value])) }}"
           class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ ! $needsAction && $currentStatus === $value ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
            {{ $label }} <span class="rounded-full bg-slate-100 px-2 text-slate-600">{{ $count }}</span>
        </a>
    @endforeach
</div>

<div class="panel overflow-hidden">
    <div class="border-b border-slate-200 pb-4">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            @if($needsAction)<input type="hidden" name="needs_action" value="1">@endif
            @if($currentStatus !== '')<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
            <input class="{{ $inputClass }} min-w-[200px] flex-1" type="text" name="search" value="{{ request('search') }}" placeholder="Cari judul, nomor, atau kronologi…">

            <select class="{{ $inputClass }} max-w-[220px]" name="pjct_id" onchange="this.form.submit()">
                <option value="">Semua Project</option>
                @foreach($pjctProjects as $pjct)
                    <option value="{{ $pjct->id }}" @selected(request('pjct_id') === $pjct->id)>{{ $pjct->pjct_name }}</option>
                @endforeach
            </select>

            <select class="{{ $inputClass }}" name="issue_type" onchange="this.form.submit()">
                <option value="">Semua Jenis</option>
                @foreach($issueTypes as $value => $label)
                    <option value="{{ $value }}" @selected(request('issue_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <select class="{{ $inputClass }}" name="priority" onchange="this.form.submit()">
                <option value="">Semua Prioritas</option>
                @foreach($priorities as $priority)
                    <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                @endforeach
            </select>

            <button type="submit" class="{{ $btnPrimaryClass }}">Cari</button>

            <details class="group w-full" @if(filled(request('category_id')) || filled(request('client')) || filled(request('date_from')) || filled(request('date_to'))) open @endif>
                <summary class="inline-flex cursor-pointer list-none items-center gap-1 {{ $inputClass }}">
                    Filter lainnya
                    <svg class="h-3.5 w-3.5 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </summary>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="label">Kategori</label>
                        <select class="field" name="category_id">
                            <option value="">Semua Kategori</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Client</label>
                        <select class="field" name="client">
                            <option value="">Semua Client</option>
                            @foreach($clients as $client)
                                <option value="{{ $client }}" @selected(request('client') === $client)>{{ $client }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <div class="min-w-0 flex-1">
                            <label class="label">Dari</label>
                            <input class="field" type="date" name="date_from" value="{{ request('date_from') }}">
                        </div>
                        <div class="min-w-0 flex-1">
                            <label class="label">Sampai</label>
                            <input class="field" type="date" name="date_to" value="{{ request('date_to') }}">
                        </div>
                    </div>
                </div>
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="{{ $btnPrimaryClass }}">Terapkan</button>
                </div>
            </details>

            @if($hasFilters)
                <a class="px-2 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800" href="{{ route('tickets.index') }}">Reset</a>
            @endif
        </form>
    </div>

    <div class="flex items-center justify-between pt-5">
        <div class="flex items-center gap-3">
            <h2 class="text-xl font-black text-slate-900">Daftar Ticket</h2>
            <span class="text-xs text-slate-400">{{ $tickets->total() }} ticket</span>
        </div>
        @can('create', \App\Models\Ticket::class)
            <a href="{{ route('tickets.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700">
                + Buat Ticket
            </a>
        @endcan
    </div>

    {{-- Mobile --}}
    <div class="mt-6 space-y-3 md:hidden">
        @forelse($tickets as $ticket)
            <a href="{{ route('tickets.show', $ticket) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-blue-200 hover:bg-blue-50">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $ticket->ticket_number }}</p>
                        <p class="mt-1 truncate font-semibold text-slate-900">{{ $ticket->subject }}</p>
                        <p class="mt-1 truncate text-sm text-slate-500">{{ $ticket->project?->pjct_name ?? '-' }} · {{ $ticket->issueTypeLabel() }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <span class="badge {{ $ticket->statusBadgeClass() }}">{{ $ticket->statusLabel() }}</span>
                        <span class="badge {{ $ticket->priorityBadgeClass() }}">{{ \Illuminate\Support\Str::headline($ticket->priority) }}</span>
                    </div>
                </div>
            </a>
        @empty
            <p class="py-8 text-center text-slate-500">Tidak ada ticket.</p>
        @endforelse
    </div>

    {{-- Desktop --}}
    <div class="mt-6 hidden overflow-x-auto md:block">
        <table class="min-w-full text-sm">
            <thead class="text-left text-slate-500">
                <tr>
                    <th class="pb-3 pr-3">Nomor</th>
                    <th class="pb-3 pr-3">Judul</th>
                    <th class="pb-3 pr-3">Project</th>
                    <th class="pb-3 pr-3">Jenis</th>
                    <th class="pb-3 pr-3">Device</th>
                    <th class="pb-3 pr-3">Status</th>
                    <th class="pb-3 pr-3">Prioritas</th>
                    <th class="pb-3">SLA</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($tickets as $ticket)
                    <tr>
                        <td class="py-4 pr-3 font-semibold"><a href="{{ route('tickets.show', $ticket) }}" class="hover:text-blue-600">{{ $ticket->ticket_number }}</a></td>
                        <td class="py-4 pr-3"><p class="font-semibold">{{ $ticket->subject }}</p><p class="text-slate-500">{{ $ticket->creator?->user_name ?? '-' }}</p></td>
                        <td class="py-4 pr-3"><p>{{ $ticket->project?->pjct_name ?? '-' }}</p><p class="text-slate-500">{{ $ticket->clientLabel() }}</p></td>
                        <td class="py-4 pr-3">{{ $ticket->issueTypeLabel() }}</td>
                        <td class="py-4 pr-3">{{ $ticket->assets_count ?: '-' }}</td>
                        <td class="py-4 pr-3"><span class="badge {{ $ticket->statusBadgeClass() }}">{{ $ticket->statusLabel() }}</span></td>
                        <td class="py-4 pr-3"><span class="badge {{ $ticket->priorityBadgeClass() }}">{{ \Illuminate\Support\Str::headline($ticket->priority) }}</span></td>
                        <td class="py-4">
                            @if($ticket->slaBadgeLabel())
                                <span class="badge {{ $ticket->slaBadgeClass() }}"
                                    @if($ticket->isSlaCountdownLive())
                                        data-sla-due="{{ $ticket->resolution_due_at->toIso8601String() }}"
                                    @endif
                                >{{ $ticket->slaBadgeLabel() }}</span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-500">Tidak ada ticket.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $tickets->links() }}</div>
</div>
@endsection
