@extends('layouts.app', ['title' => 'Tickets', 'heading' => 'Tickets'])

@section('content')
@php
    $hasMoreFilters = filled(request('category_id'))
        || filled(request('requester_id'))
        || filled(request('date_from'))
        || filled(request('date_to'));
@endphp

<div class="panel overflow-hidden">
    <div class="flex flex-col gap-5 border-b border-slate-200 pb-5">
        <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-xl font-black text-slate-900">Ticket Directory</h2>
                <p class="text-sm text-slate-500">Manage your open tickets with just a click</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button class="btn-primary rounded-xl px-3 py-2 text-xs" type="submit" form="ticket-filter-form">Apply</button>
                <a class="btn-soft rounded-xl px-3 py-2 text-xs" href="{{ route('tickets.index') }}">Reset</a>
            </div>
        </div>

        <form id="ticket-filter-form" method="GET" class="space-y-3">
            <div class="flex flex-wrap items-end gap-3 xl:flex-nowrap xl:gap-2.5">
                <div class="w-full xl:flex-none" style="width: 47rem;">
                    <label class="label">Search</label>
                    <input class="field" type="text" name="search" value="{{ request('search') }}" placeholder="Search">
                </div>
                <div class="w-full sm:w-[11rem] xl:flex-none" style="width: 8.5rem;">
                    <label class="label">Project</label>
                    <select class="field" name="project_id">
                        <option value="" disabled hidden @selected(blank(request('project_id')))>Select project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full sm:w-[10rem] xl:flex-none" style="width: 7.5rem;">
                    <label class="label">Status</label>
                    <select class="field" name="status">
                        <option value="" disabled hidden @selected(blank(request('status')))>Select status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full sm:w-[10rem] xl:flex-none" style="width: 7.5rem;">
                    <label class="label">Priority</label>
                    <select class="field" name="priority">
                        <option value="" disabled hidden @selected(blank(request('priority')))>Select priority</option>
                        @foreach($priorities as $priority)
                            <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <details class="relative inline-block w-[7.5rem] align-top" @if($hasMoreFilters) open @endif>
                <summary class="relative flex w-[7.5rem] cursor-pointer list-none items-center justify-center rounded-2xl border border-slate-200 bg-slate-50/80 px-3 py-3 text-sm font-semibold text-slate-700 shadow-sm shadow-slate-200/70">
                    <span class="truncate">More filters</span>
                    <span class="absolute right-3 text-xs text-slate-400">v</span>
                </summary>
                <div class="absolute left-0 top-[calc(100%+0.5rem)] z-20 w-[34rem] max-w-[calc(100vw-4rem)] rounded-2xl border border-slate-200 bg-white p-4 shadow-xl shadow-slate-200/80">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="min-w-0">
                            <label class="label">Category</label>
                            <select class="field" name="category_id">
                                <option value="" disabled hidden @selected(blank(request('category_id')))>Select category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if(!auth()->user()->isClient())
                            <div class="min-w-0">
                                <label class="label">Client</label>
                                <select class="field" name="requester_id">
                                    <option value="" disabled hidden @selected(blank(request('requester_id')))>Select client</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" @selected((string) request('requester_id') === (string) $client->id)>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <label class="label">Date from</label>
                            <input class="field" type="date" name="date_from" value="{{ request('date_from') }}">
                        </div>
                        <div class="min-w-0">
                            <label class="label">Date to</label>
                            <input class="field" type="date" name="date_to" value="{{ request('date_to') }}">
                        </div>
                    </div>
                </div>
            </details>
        </form>
    </div>

    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-left text-slate-500">
                <tr>
                    <th class="pb-3">Number</th>
                    <th class="pb-3">Subject</th>
                    <th class="pb-3">Requester</th>
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
                        <td class="py-4"><p class="font-semibold">{{ $ticket->subject }}</p><p class="text-slate-500">{{ $ticket->assignee?->name ?? 'Unassigned' }}</p></td>
                        <td class="py-4">{{ $ticket->requester?->name }}</td>
                        <td class="py-4">{{ $ticket->team?->name ?? '-' }}</td>
                        <td class="py-4">{{ $ticket->subcategory?->name ?? $ticket->category?->name ?? 'Uncategorized' }}</td>
                        <td class="py-4">{{ \Illuminate\Support\Str::headline($ticket->status) }}</td>
                        <td class="py-4">{{ \Illuminate\Support\Str::headline($ticket->priority) }}</td>
                        <td class="py-4">
                            @if($ticket->isResolutionBreached())
                                <span class="badge bg-rose-100 text-rose-700">Breached</span>
                            @elseif($ticket->resolution_due_at)
                                <span class="badge bg-amber-100 text-amber-700">{{ $ticket->resolution_due_at->diffForHumans() }}</span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-500">Belum ada ticket.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $tickets->links() }}</div>
</div>
@endsection




