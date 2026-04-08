@extends('layouts.app', ['title' => 'Devices', 'heading' => 'Admin'])

@section('content')
@include('admin._tabs')

<div class="space-y-6">
    <div class="rounded-[3.2rem] border border-blue-100 bg-white/95 p-8 shadow-[0_28px_80px_rgba(148,163,184,0.16)]">
        <div class="rounded-[2.8rem] border border-slate-200 bg-white p-8 shadow-[0_18px_40px_rgba(148,163,184,0.14)]">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm uppercase tracking-[0.28em] text-slate-500">Device Inventory</p>
                    <h3 class="mt-3 text-3xl font-black text-slate-900">Manage your Devices</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-600">Track total devices, project coverage, and units that are currently under reparation.</p>
                </div>
                <div class="flex flex-wrap gap-2 xl:max-w-[34rem] xl:justify-end">
                    <a class="btn-soft" href="{{ route('admin.devices.template') }}">CSV template</a>
                    <a class="btn-soft" href="{{ route('admin.devices.export') }}">Export CSV</a>
                    <button class="btn-soft" type="button" data-open-dialog="device-import-dialog">Upload devices</button>
                    <button class="btn-primary" type="button" data-open-dialog="device-create-dialog">Create device</button>
                </div>
            </div>

            <div class="mt-8 rounded-[2.5rem] border border-blue-100 bg-slate-50/80 p-5 shadow-inner shadow-slate-200/60">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="flex min-h-28 flex-col justify-between rounded-[2.2rem] border border-blue-100 bg-white px-6 py-5 shadow-sm shadow-slate-200/70">
                        <p class="text-sm text-slate-500">Total devices</p>
                        <p class="mt-4 text-3xl font-black text-slate-900">{{ $devices->count() }}</p>
                    </div>
                    <div class="flex min-h-28 flex-col justify-between rounded-[2.2rem] border border-blue-100 bg-white px-6 py-5 shadow-sm shadow-slate-200/70">
                        <p class="text-sm text-slate-500">Projects with devices</p>
                        <p class="mt-4 text-3xl font-black text-slate-900">{{ $devices->pluck('team_id')->unique()->count() }}</p>
                    </div>
                    <div class="flex min-h-28 flex-col justify-between rounded-[2.2rem] border border-blue-100 bg-white px-6 py-5 shadow-sm shadow-slate-200/70">
                        <p class="text-sm text-slate-500">Under reparation</p>
                        <p class="mt-4 text-3xl font-black text-slate-900">{{ $devices->filter(fn ($device) => $device->hasOpenRepairTicket())->count() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel datatable-shell mt-8 rounded-[2.8rem] border border-slate-200 bg-white px-7 py-7 shadow-[0_18px_45px_rgba(148,163,184,0.16)]">
            <table class="min-w-full text-sm" data-datatable>
                <thead>
                    <tr class="text-left text-slate-500">
                        <th>Device</th>
                        <th>Project</th>
                        <th>Type</th>
                        <th>Serial Number</th>
                        <th>Location / IP</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($devices as $device)
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-900">{{ $device->name }}</p>
                                <p class="text-slate-500">{{ $device->notes ?: 'No notes' }}</p>
                            </td>
                            <td>{{ $device->team?->name ?? '-' }}</td>
                            <td>{{ $device->device_type ?: '-' }}</td>
                            <td>
                                <p>{{ $device->asset_code ?: '-' }}</p>
                                <p class="text-slate-500">{{ $device->serial_number ?: '-' }}</p>
                            </td>
                            <td>
                                <p>{{ $device->location ?: '-' }}</p>
                                <p class="text-slate-500">{{ $device->ip_address ?: '-' }}</p>
                            </td>
                            <td>
                                <span class="badge {{ $device->operationalStatusBadgeClass() }}">{{ $device->operationalStatusLabel() }}</span>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <a class="btn-soft" href="{{ route('admin.projects.devices', $device->team_id) }}">Project detail</a>
                                    <button class="btn-soft" type="button" data-open-dialog="device-edit-{{ $device->id }}">Edit</button>
                                    <button class="btn-soft" type="button" data-open-dialog="device-delete-{{ $device->id }}">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<dialog id="device-create-dialog" class="max-w-4xl">
    <form method="POST" action="{{ route('admin.devices.store') }}" data-ajax-form class="panel m-0">
        @csrf
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <h3 class="text-xl font-black">Create device</h3>
            <button type="button" class="btn-soft" data-close-dialog>Close</button>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="label">Project</label>
                <select class="field" name="team_id" required>
                    <option value="" disabled hidden selected>Select project</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Device name</label>
                <input class="field" name="name" placeholder="Contoh: Laptop Finance-01" required>
            </div>
            <input class="field" name="asset_code" placeholder="Asset code">
            <input class="field" name="device_type" placeholder="Device type">
            <input class="field" name="serial_number" placeholder="Serial number">
            <input class="field" name="ip_address" placeholder="IP address">
            <input class="field" name="location" placeholder="Location">
            <textarea class="field md:col-span-2" name="notes" placeholder="Notes"></textarea>
            <label class="flex items-center gap-3 text-sm text-slate-500 md:col-span-2"><input type="checkbox" name="is_active" value="1" checked> Active device</label>
        </div>
        <div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Save device</button></div>
    </form>
</dialog>

<dialog id="device-import-dialog" class="max-w-2xl">
    <form method="POST" action="{{ route('admin.devices.import') }}" enctype="multipart/form-data" data-ajax-form class="panel m-0">
        @csrf
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <h3 class="text-xl font-black">Upload devices</h3>
            <button type="button" class="btn-soft" data-close-dialog>Close</button>
        </div>
        <div class="grid gap-4">
            <div>
                <label class="label">Project</label>
                <select class="field" name="team_id" required>
                    <option value="" disabled hidden selected>Select project</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">CSV file</label>
                <input class="field" type="file" name="file" accept=".csv,.txt" required>
                <p class="mt-2 text-xs text-slate-500">Kolom yang didukung: <code>name, asset_code, device_type, serial_number, ip_address, location, notes</code>. Baris header boleh ada atau tidak.</p>
            </div>
        </div>
        <div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Upload devices</button></div>
    </form>
</dialog>

@foreach($devices as $device)
    <dialog id="device-edit-{{ $device->id }}" class="max-w-4xl">
        <form method="POST" action="{{ route('admin.devices.update', $device) }}" data-ajax-form class="panel m-0">
            @csrf
            @method('PATCH')
            <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                <h3 class="text-xl font-black">Edit device</h3>
                <button type="button" class="btn-soft" data-close-dialog>Close</button>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="label">Project</label>
                    <select class="field" name="team_id" required>
                        <option value="" disabled hidden>Select project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected($device->team_id === $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Device name</label>
                    <input class="field" name="name" value="{{ $device->name }}" required>
                </div>
                <input class="field" name="asset_code" value="{{ $device->asset_code }}" placeholder="Asset code">
                <input class="field" name="device_type" value="{{ $device->device_type }}" placeholder="Device type">
                <input class="field" name="serial_number" value="{{ $device->serial_number }}" placeholder="Serial number">
                <input class="field" name="ip_address" value="{{ $device->ip_address }}" placeholder="IP address">
                <input class="field" name="location" value="{{ $device->location }}" placeholder="Location">
                <textarea class="field md:col-span-2" name="notes" placeholder="Notes">{{ $device->notes }}</textarea>
                <label class="flex items-center gap-3 text-sm text-slate-500 md:col-span-2"><input type="checkbox" name="is_active" value="1" @checked($device->is_active)> Active device</label>
            </div>
            <div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Update device</button></div>
        </form>
    </dialog>

    <dialog id="device-delete-{{ $device->id }}" class="max-w-lg">
        <form method="POST" action="{{ route('admin.devices.destroy', $device) }}" data-ajax-form class="panel m-0">
            @csrf
            @method('DELETE')
            <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                <h3 class="text-xl font-black">Delete device</h3>
                <button type="button" class="btn-soft" data-close-dialog>Close</button>
            </div>
            <p class="text-sm text-slate-500">Perangkat <span class="font-semibold text-slate-900">{{ $device->name }}</span> akan dihapus dari inventori project.</p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="btn-soft" data-close-dialog>Cancel</button>
                <button class="btn-primary" type="submit">Delete device</button>
            </div>
        </form>
    </dialog>
@endforeach
@endsection
