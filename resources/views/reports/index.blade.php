@extends('layouts.app', ['title' => 'Reports', 'heading' => 'Reports & Analytics'])

@section('content')
<div class="grid gap-5 lg:grid-cols-4">
    <div class="stat"><p class="text-sm text-slate-500">Tickets</p><p class="mt-2 text-4xl font-black">{{ $ticketCount }}</p></div>
    <div class="stat"><p class="text-sm text-slate-500">Avg resolution</p><p class="mt-2 text-4xl font-black">{{ $averageResolutionMinutes }}m</p></div>
    <div class="stat"><p class="text-sm text-slate-500">SLA breach rate</p><p class="mt-2 text-4xl font-black">{{ $breachRate }}%</p></div>
    <div class="stat"><p class="text-sm text-slate-500">Export</p><a class="btn-primary mt-3" href="{{ route('reports.export', request()->query()) }}">Download CSV</a></div>
</div>

<div class="mt-6 panel">
    <form method="GET" class="grid gap-4 md:grid-cols-7">
        <input class="field" type="date" name="date_from" value="{{ request('date_from') }}">
        <input class="field" type="date" name="date_to" value="{{ request('date_to') }}">
        <select class="field" name="project_id"><option value="" disabled hidden @selected(blank(request('project_id')))>Select project</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>{{ $project->name }}</option>@endforeach</select>
        <select class="field" name="status"><option value="" disabled hidden @selected(blank(request('status')))>Select status</option>@foreach(['open','in_progress','pending','resolved','closed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>@endforeach</select>
        <select class="field" name="priority"><option value="" disabled hidden @selected(blank(request('priority')))>Select priority</option>@foreach(['low','medium','high','critical'] as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>@endforeach</select>
        <select class="field" name="category_id"><option value="" disabled hidden @selected(blank(request('category_id')))>Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select>
        <select class="field" name="requester_id"><option value="" disabled hidden @selected(blank(request('requester_id')))>Select client</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected((string) request('requester_id') === (string) $client->id)>{{ $client->name }}</option>@endforeach</select>
        <button class="btn-primary md:col-span-7 md:w-max" type="submit">Filter report</button>
    </form>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
    <div class="panel">
        <h2 class="text-xl font-black">Ticket by category</h2>
        <div class="mt-4 space-y-3 text-sm">
            @forelse($byCategory as $category => $count)
                <div class="flex items-center justify-between rounded-2xl bg-slate-100 px-4 py-3"><span>{{ $category }}</span><span class="font-bold">{{ $count }}</span></div>
            @empty
                <p class="text-slate-500">Belum ada data.</p>
            @endforelse
        </div>
    </div>
    <div class="panel overflow-hidden">
        <h2 class="text-xl font-black">Filtered tickets</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-slate-500"><tr><th class="pb-3">Ticket</th><th class="pb-3">Client</th><th class="pb-3">Project</th><th class="pb-3">Category</th><th class="pb-3">Status</th><th class="pb-3">Assignee</th></tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($tickets as $ticket)
                        <tr>
                            <td class="py-3"><a class="font-semibold hover:text-blue-600" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a><div class="text-slate-500">{{ $ticket->subject }}</div></td>
                            <td class="py-3">{{ $ticket->requester?->name }}</td>
                            <td class="py-3">{{ $ticket->team?->name ?? '-' }}</td>
                            <td class="py-3">{{ $ticket->category?->name ?? '-' }}</td>
                            <td class="py-3">{{ \Illuminate\Support\Str::headline($ticket->status) }}</td>
                            <td class="py-3">{{ $ticket->assignee?->name ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $tickets->links() }}</div>
    </div>
</div>
@endsection
