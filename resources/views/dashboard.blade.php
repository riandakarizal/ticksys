@extends('layouts.app', ['title' => 'Dashboard', 'heading' => 'Dashboard'])

@section('content')
<script type="application/json" id="status-chart-data">{!! json_encode(['labels' => $statusChart->pluck('label')->values(), 'values' => $statusChart->pluck('value')->values(), 'colors' => $statusChart->pluck('color')->values()]) !!}</script>
<script type="application/json" id="monthly-chart-data">{!! json_encode(['labels' => $monthlySeries->pluck('label')->values(), 'values' => $monthlySeries->pluck('value')->values()]) !!}</script>
<script type="application/json" id="category-chart-data">{!! json_encode(['labels' => $categorySeries->pluck('label')->values(), 'values' => $categorySeries->pluck('value')->values()]) !!}</script>

<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
    @if($isClient)
        <div class="stat"><p class="text-sm text-slate-500">My Open Requests</p><p class="mt-2 text-4xl font-black">{{ $openRequestCount }}</p><p class="mt-2 text-xs text-slate-500">Tickets still waiting to be handled or closed.</p></div>
    @else
        <div class="stat"><p class="text-sm text-slate-500">Assigned to Me</p><p class="mt-2 text-4xl font-black">{{ $assignedCount }}</p><p class="mt-2 text-xs text-slate-500">Tickets currently in your queue.</p></div>
    @endif
    <div class="stat"><p class="text-sm text-slate-500">Ticket Volume</p><p class="mt-2 text-4xl font-black">{{ $ticketVolume }}</p><p class="mt-2 text-xs text-slate-500">Total tickets visible in your workspace.</p></div>
    <div class="stat"><p class="text-sm text-slate-500">Due in 2 Hours</p><p class="mt-2 text-4xl font-black">{{ $dueTickets }}</p><p class="mt-2 text-xs text-slate-500">Prioritize tickets approaching their SLA deadline.</p></div>
    <div class="stat"><p class="text-sm text-slate-500">SLA Compliance</p><p class="mt-2 text-4xl font-black">{{ $slaCompliance }}%</p><div class="mt-3 h-2 rounded-full bg-slate-200"><div class="h-2 rounded-full bg-blue-500" style="width: {{ min(100, $slaCompliance) }}%"></div></div></div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
    <div class="panel">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-black">Status Distribution</h2>
                <p class="text-sm text-slate-500">Current status of your tickets</p>
            </div>
            <a href="{{ route('tickets.index') }}" class="btn-soft">View All</a>
        </div>
        <div class="grid items-center gap-6 lg:grid-cols-[280px_1fr]">
            <div class="mx-auto w-full max-w-[280px]"><canvas id="statusChart"></canvas></div>
            <div class="space-y-3">
                @foreach($statusChart as $segment)
                    <div class="rounded-2xl bg-slate-100 px-4 py-3">
                        <div class="flex items-center justify-between text-sm"><span class="font-semibold" style="color: {{ $segment['color'] }}">{{ $segment['label'] }}</span><span>{{ $segment['value'] }}</span></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-black">Ticket Trend</h2>
                <p class="text-sm text-slate-500">Ticket trend for the past six months.</p>
            </div>
            <a href="{{ route('tickets.create') }}" class="btn-primary">New Ticket</a>
        </div>
        <canvas id="monthlyChart" height="220"></canvas>
    </div>
</div>

<div class="panel mt-6">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-xl font-black">Recent Tickets</h2>
        <a href="{{ route('tickets.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">View all →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-left text-slate-500">
                <tr>
                    <th class="pb-3">Ticket</th>
                    <th class="pb-3">Status</th>
                    <th class="pb-3">Priority</th>
                    <th class="pb-3">Assignee</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($tickets as $ticket)
                    <tr>
                        <td class="py-3"><a class="font-semibold hover:text-blue-600" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a><div class="text-slate-500">{{ $ticket->subject }}</div></td>
                        <td class="py-3"><span class="badge {{ $ticket->statusBadgeClass() }}">{{ \Illuminate\Support\Str::headline($ticket->status) }}</span></td>
                        <td class="py-3"><span class="badge {{ $ticket->priorityBadgeClass() }}">{{ \Illuminate\Support\Str::headline($ticket->priority) }}</span></td>
                        <td class="py-3">{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-slate-500">No tickets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <div class="panel">
        <h2 class="text-xl font-black">Top Categories</h2>
        <div class="mt-4"><canvas id="categoryChart" height="230"></canvas></div>

        <div class="mt-8">
            <h2 class="text-xl font-black">Recent Updates</h2>
            <div class="mt-4 space-y-3">
                @forelse($notifications as $notification)
                    <div class="rounded-2xl bg-slate-100 p-4">
                        <p class="font-semibold">{{ $notification->title }}</p>
                        <p class="text-sm text-slate-500">{{ $notification->message }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No updates — keep up the great work!</p>
                @endforelse
            </div>
        </div>
    </div>

    @if(!$isClient && (auth()->user()->isSupervisor() || auth()->user()->isAdmin()))
    <div class="panel">
        <details>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                <h2 class="text-xl font-black">Agent Performance</h2>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500">Click to expand ▼</span>
            </summary>
            <div class="mt-4 space-y-3">
                @forelse($agentPerformance as $agent)
                    <div class="rounded-2xl bg-slate-100 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ $agent->name }}</p>
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ \Illuminate\Support\Str::headline($agent->role) }}</p>
                            </div>
                            <div class="text-right text-sm">
                                <p>Resolved: <span class="font-bold">{{ $agent->resolved_count }}</span></p>
                                <p>Open: <span class="font-bold">{{ $agent->open_count }}</span></p>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No agent data available.</p>
                @endforelse
            </div>
        </details>
    </div>
    @endif
</div>
@endsection
