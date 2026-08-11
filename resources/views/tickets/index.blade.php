@extends('layouts.app', ['title' => 'Tickets', 'heading' => 'Tickets'])

@section('content')
@php
    $hasFilters = collect(['pjct_id', 'status', 'priority', 'category_id', 'client', 'date_from', 'date_to'])
        ->contains(fn ($key) => filled(request($key))) || filled(request('search'));
@endphp

<div class="panel overflow-hidden">
    @php
        $inputClass = 'border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:border-blue-400 focus:outline-none';
        $btnPrimaryClass = 'bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition';
    @endphp

    {{-- Filter Bar --}}
    <div class="border-b border-slate-200 pb-4">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input class="{{ $inputClass }} min-w-[200px] flex-1" type="text" name="search" value="{{ request('search') }}" placeholder="Search subject, number, or description...">

            <select class="{{ $inputClass }}" name="pjct_id" onchange="this.form.submit()">
                <option value="">All Projects</option>
                @foreach($pjctProjects as $pjct)
                    <option value="{{ $pjct->id }}" @selected(request('pjct_id') === $pjct->id)>{{ $pjct->pjct_name }}</option>
                @endforeach
            </select>

            <select class="{{ $inputClass }}" name="status" onchange="this.form.submit()">
                <option value="">All Status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                @endforeach
            </select>

            <select class="{{ $inputClass }}" name="priority" onchange="this.form.submit()">
                <option value="">All Priority</option>
                @foreach($priorities as $priority)
                    <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                @endforeach
            </select>

            <button type="submit" class="{{ $btnPrimaryClass }}">Search</button>

            <details class="group w-full">
                <summary class="inline-flex cursor-pointer list-none items-center gap-1 {{ $inputClass }}">
                    More filters
                    <svg class="h-3.5 w-3.5 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </summary>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="label">Category</label>
                        <select class="field" name="category_id">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(!auth()->user()->isUser())
                        <div>
                            <label class="label">Client</label>
                            <select class="field" name="client">
                                <option value="">All Clients</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client }}" @selected(request('client') === $client)>{{ $client }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="flex gap-2">
                        <div class="flex-1 min-w-0">
                            <label class="label">From</label>
                            <input class="field" type="date" name="date_from" value="{{ request('date_from') }}">
                        </div>
                        <div class="flex-1 min-w-0">
                            <label class="label">To</label>
                            <input class="field" type="date" name="date_to" value="{{ request('date_to') }}">
                        </div>
                    </div>
                </div>
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="{{ $btnPrimaryClass }}">Apply filters</button>
                </div>
            </details>

            @if($hasFilters)
                <a class="text-xs font-semibold text-slate-500 hover:text-slate-800 px-2 py-1.5" href="{{ route('tickets.index') }}">Reset</a>
            @endif
        </form>
    </div>

    {{-- Table header --}}
    <div class="flex items-center justify-between pt-5">
        <div class="flex items-center gap-3">
            <h2 class="text-xl font-black text-slate-900">Ticket List</h2>
            <span class="text-xs text-slate-400">{{ $tickets->total() }} ticket{{ $tickets->total() !== 1 ? 's' : '' }}</span>
        </div>
        @if(!auth()->user()->isVip())
            <a href="{{ route('tickets.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-all duration-200 ease-out hover:bg-blue-700 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-95">
                + New Ticket
            </a>
        @endif
    </div>

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
