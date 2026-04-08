@extends('layouts.app', ['title' => 'Project Devices', 'heading' => 'Admin'])

@section('content')
@include('admin._tabs')

<div class="space-y-6">
    <div class="panel-soft">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm uppercase tracking-[0.28em] text-slate-500">Project Device Detail</p>
                <h3 class="mt-2 text-3xl font-black text-slate-900">{{ $project->name }}</h3>
                <p class="mt-2 text-sm leading-7 text-slate-600">Coordinator: {{ $project->lead?->name ?? '-' }} | Members: {{ $project->members->count() }} | Code: {{ $project->code }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a class="btn-soft" href="{{ route('admin.projects.index') }}">Back to projects</a>
                <a class="btn-soft" href="{{ route('admin.devices.export', ['team_id' => $project->id]) }}">Export project CSV</a>
                <a class="btn-primary" href="{{ route('admin.devices.index') }}">Manage devices</a>
            </div>
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div class="stat"><p class="text-sm text-slate-500">Total devices</p><p class="mt-2 text-3xl font-black">{{ $projectDevices->count() }}</p></div>
            <div class="stat"><p class="text-sm text-slate-500">Under reparation</p><p class="mt-2 text-3xl font-black">{{ $projectDevices->filter(fn ($device) => $device->hasOpenRepairTicket())->count() }}</p></div>
            <div class="stat"><p class="text-sm text-slate-500">Tickets linked</p><p class="mt-2 text-3xl font-black">{{ $projectDevices->sum('tickets_count') }}</p></div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="panel datatable-shell overflow-hidden">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-xl font-black text-slate-900">Device Inventory</h3>
                    <p class="text-sm text-slate-500">Daftar perangkat yang terhubung ke project ini.</p>
                </div>
            </div>
            <table class="min-w-full text-sm" data-datatable>
                <thead>
                    <tr class="text-left text-slate-500">
                        <th>Device</th>
                        <th>Type</th>
                        <th>Asset / Serial</th>
                        <th>Location / IP</th>
                        <th>Tickets</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($projectDevices as $device)
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-900">{{ $device->name }}</p>
                                <p class="text-slate-500">{{ $device->notes ?: 'No notes' }}</p>
                            </td>
                            <td>{{ $device->device_type ?: '-' }}</td>
                            <td>
                                <p>{{ $device->asset_code ?: '-' }}</p>
                                <p class="text-slate-500">{{ $device->serial_number ?: '-' }}</p>
                            </td>
                            <td>
                                <p>{{ $device->location ?: '-' }}</p>
                                <p class="text-slate-500">{{ $device->ip_address ?: '-' }}</p>
                            </td>
                            <td>{{ $device->tickets_count }}</td>
                            <td><span class="badge {{ $device->operationalStatusBadgeClass() }}">{{ $device->operationalStatusLabel() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3 class="text-xl font-black text-slate-900">Recent Tickets in Project</h3>
            <div class="mt-4 space-y-3">
                @forelse($recentTickets as $ticket)
                    <a href="{{ route('tickets.show', $ticket) }}" class="block rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 transition hover:border-blue-200 hover:bg-blue-50">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $ticket->ticket_number }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $ticket->subject }}</p>
                                <p class="mt-2 text-xs uppercase tracking-[0.2em] text-slate-400">{{ $ticket->device?->name ?? 'No device linked' }}</p>
                            </div>
                            <span class="badge bg-slate-100 text-slate-700">{{ \Illuminate\Support\Str::headline($ticket->status) }}</span>
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Belum ada ticket untuk project ini.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
