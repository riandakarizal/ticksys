@extends('layouts.app', ['title' => 'Tickets', 'heading' => 'Tickets'])

@section('content')
@php
    $advancedKeys = ['category_id', 'client', 'date_from', 'date_to'];
    $advancedCount = collect($advancedKeys)->filter(fn ($key) => filled(request($key)))->count();
    $hasFilters = $advancedCount > 0
        || collect(['search', 'pjct_id', 'status', 'priority'])->contains(fn ($key) => filled(request($key)));
    $ctl = 'h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100';
    // Heroicons outline paths; kelas warna ditulis utuh supaya terbaca Tailwind.
    $metricCards = [
        ['label' => 'Total Tickets', 'value' => $metrics['total'], 'hint' => 'All visible tickets',
         'chip' => 'bg-slate-100 text-slate-600', 'tone' => 'text-slate-900',
         'icon' => 'M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z'],
        ['label' => 'Active', 'value' => $metrics['active'], 'hint' => $metrics['pending'].' pending',
         'chip' => 'bg-blue-50 text-blue-600', 'tone' => 'text-slate-900',
         'icon' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z'],
        ['label' => 'Unassigned', 'value' => $metrics['unassigned'], 'hint' => 'Active, no assignee',
         'chip' => 'bg-amber-50 text-amber-600', 'tone' => $metrics['unassigned'] ? 'text-amber-600' : 'text-slate-900',
         'icon' => 'M22 10.5h-6m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z'],
        ['label' => 'SLA Breached', 'value' => $metrics['breached'], 'hint' => 'Past resolution due',
         'chip' => 'bg-red-50 text-red-600', 'tone' => $metrics['breached'] ? 'text-red-600' : 'text-slate-900',
         'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        ['label' => 'Resolved · 30d', 'value' => $metrics['resolved_30d'], 'hint' => $metrics['avg_resolve_h'] !== null ? 'Avg '.$metrics['avg_resolve_h'].' h to resolve' : 'No resolved tickets',
         'chip' => 'bg-green-50 text-green-600', 'tone' => 'text-slate-900',
         'icon' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
    ];
@endphp

{{-- Metrics --}}
<div class="mb-6 flex gap-4 overflow-x-auto">
    @foreach($metricCards as $card)
        <div class="stat flex min-w-[180px] flex-1 items-center gap-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $card['chip'] }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" /></svg>
            </span>
            <div class="min-w-0">
                <p class="truncate text-xs font-semibold text-slate-500">{{ $card['label'] }}</p>
                <p class="text-2xl font-black leading-tight {{ $card['tone'] }}">{{ number_format($card['value']) }}</p>
                <p class="truncate text-[11px] text-slate-400">{{ $card['hint'] }}</p>
            </div>
        </div>
    @endforeach
</div>

<div class="panel overflow-hidden">
    {{-- Header --}}
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-baseline gap-3">
            <h2 class="text-xl font-black text-slate-900">Ticket List</h2>
            <span class="text-xs text-slate-400">{{ number_format($tickets->total()) }} {{ $hasFilters ? 'matching' : 'total' }}</span>
        </div>
        @if(!auth()->user()->isVip())
            <a href="{{ route('tickets.create') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white transition hover:bg-blue-700">
                + New Ticket
            </a>
        @endif
    </div>

    {{-- Filters: Enter submits search, every select/date auto-submits --}}
    <form method="GET" class="mt-4 border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[220px] flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                <input class="{{ $ctl }} w-full pl-9" type="search" name="search" value="{{ request('search') }}" placeholder="Search number, subject, description…" aria-label="Search tickets">
            </div>

            <select class="{{ $ctl }} max-w-[200px]" name="pjct_id" aria-label="Project" onchange="this.form.submit()">
                <option value="">All projects</option>
                @foreach($pjctProjects as $pjct)
                    <option value="{{ $pjct->id }}" @selected(request('pjct_id') === $pjct->id)>{{ $pjct->pjct_name }}</option>
                @endforeach
            </select>

            <select class="{{ $ctl }}" name="status" aria-label="Status" onchange="this.form.submit()">
                <option value="">All status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                @endforeach
            </select>

            <select class="{{ $ctl }}" name="priority" aria-label="Priority" onchange="this.form.submit()">
                <option value="">All priority</option>
                @foreach($priorities as $priority)
                    <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                @endforeach
            </select>

            <button type="button" class="{{ $ctl }} inline-flex items-center gap-1.5 font-medium hover:bg-slate-50"
                    onclick="document.getElementById('ticket-more-filters').classList.toggle('hidden')" aria-controls="ticket-more-filters">
                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M6 12h12M10 19h4" /></svg>
                Filters
                @if($advancedCount)
                    <span class="rounded-full bg-blue-600 px-1.5 text-[11px] font-bold leading-5 text-white">{{ $advancedCount }}</span>
                @endif
            </button>

            @if($hasFilters)
                <a class="px-1 text-sm font-medium text-slate-500 hover:text-slate-900" href="{{ route('tickets.index') }}">Clear</a>
            @endif
        </div>

        <div id="ticket-more-filters" class="{{ $advancedCount ? '' : 'hidden' }} mt-2 flex flex-wrap items-center gap-2">
            <select class="{{ $ctl }}" name="category_id" aria-label="Category" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>

            @if(!auth()->user()->isUser())
                <select class="{{ $ctl }} max-w-[200px]" name="client" aria-label="Client" onchange="this.form.submit()">
                    <option value="">All clients</option>
                    @foreach($clients as $client)
                        <option value="{{ $client }}" @selected(request('client') === $client)>{{ $client }}</option>
                    @endforeach
                </select>
            @endif

            <div class="flex items-center gap-1.5 text-sm text-slate-500">
                <span>Created</span>
                <input class="{{ $ctl }}" type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Created from" onchange="this.form.submit()">
                <span>–</span>
                <input class="{{ $ctl }}" type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Created to" onchange="this.form.submit()">
            </div>
        </div>
    </form>

    {{-- Mobile: card layout --}}
    <div class="mt-6 space-y-3 md:hidden">
        @forelse($tickets as $ticket)
            <a href="{{ route('tickets.show', $ticket) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-blue-200 hover:bg-blue-50">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $ticket->ticket_number }}</p>
                        <p class="mt-1 truncate font-semibold text-slate-900">{{ $ticket->subject }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $ticket->requesterLabel() }} · {{ $ticket->project?->pjct_name ?? '-' }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <span class="badge {{ $ticket->statusBadgeClass() }}">{{ \Illuminate\Support\Str::headline($ticket->status) }}</span>
                        <span class="badge {{ $ticket->priorityBadgeClass() }}">{{ \Illuminate\Support\Str::headline($ticket->priority) }}</span>
                    </div>
                </div>
            </a>
        @empty
            <p class="py-8 text-center text-slate-500">No tickets found.</p>
        @endforelse
    </div>

    {{-- Desktop: table layout --}}
    <div class="mt-6 hidden overflow-x-auto md:block">
        <table class="min-w-full text-sm">
            <thead class="text-left text-slate-500">
                <tr>
                    <th class="pb-3">Number</th>
                    <th class="pb-3">Subject</th>
                    <th class="pb-3">Client</th>
                    <th class="pb-3">Project</th>
                    <th class="pb-3">Category</th>
                    <th class="pb-3">Status</th>
                    <th class="pb-3">Priority</th>
                    <th class="pb-3">SLA</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($tickets as $ticket)
                    <tr>
                        <td class="py-4 font-semibold"><a href="{{ route('tickets.show', $ticket) }}" class="hover:text-blue-600">{{ $ticket->ticket_number }}</a></td>
                        <td class="py-4"><p class="font-semibold">{{ $ticket->subject }}</p><p class="text-slate-500">{{ $ticket->assignee?->user_name ?? 'Unassigned' }}</p></td>
                        <td class="py-4">{{ $ticket->requesterLabel() }}</td>
                        <td class="py-4">{{ $ticket->project?->pjct_name ?? '-' }}</td>
                        <td class="py-4">{{ $ticket->subcategory?->name ?? $ticket->category?->name ?? 'No category' }}</td>
                        <td class="py-4"><span class="badge {{ $ticket->statusBadgeClass() }}">{{ \Illuminate\Support\Str::headline($ticket->status) }}</span></td>
                        <td class="py-4"><span class="badge {{ $ticket->priorityBadgeClass() }}">{{ \Illuminate\Support\Str::headline($ticket->priority) }}</span></td>
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
                        <td colspan="8" class="py-8 text-center text-slate-500">No tickets found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $tickets->links() }}</div>
</div>
@endsection
