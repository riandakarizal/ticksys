@extends('layouts.app', ['title' => 'Admin SLA', 'heading' => 'Admin'])

@section('content')
@include('admin._tabs')

<div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)] xl:items-start">
    <div class="panel-soft">
        <p class="text-sm uppercase tracking-[0.3em] text-blue-600">SLA Policy</p>
        <h3 class="mt-2 text-3xl font-black leading-tight text-slate-900">Set SLA Policies</h3>
        <p class="mt-3 text-slate-600">Monitor and manage your SLA policies to ensure timely responses and resolutions.</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
            <div class="stat"><p class="text-sm text-slate-500">Policies</p><p class="mt-2 text-3xl font-black">{{ $slaPolicies->count() }}</p></div>
            <div class="stat"><p class="text-sm text-slate-500">Default</p><p class="mt-2 text-3xl font-black">{{ $slaPolicies->where('is_default', true)->count() }}</p></div>
        </div>
    </div>

    <div class="panel">
        <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-xl font-black text-slate-900">SLA Policies</h3>
                <p class="text-sm text-slate-500">Detailed list of all SLA policies.</p>
            </div>
            <button type="button" class="btn-primary" data-open-dialog="sla-create-dialog">Create SLA</button>
        </div>

        <div class="datatable-shell mt-5">
            <table class="min-w-full text-sm" data-datatable>
                <thead>
                    <tr class="text-left text-slate-500">
                        <th>Name</th>
                        <th>Response</th>
                        <th>Resolution</th>
                        <th>Default</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($slaPolicies as $sla)
                        <tr>
                            <td class="font-semibold text-slate-900">{{ $sla->name }}</td>
                            <td>{{ $sla->response_minutes }} min</td>
                            <td>{{ $sla->resolution_minutes }} min</td>
                            <td>{{ $sla->is_default ? 'Yes' : 'No' }}</td>
                            <td><div class="flex flex-wrap gap-2"><button type="button" class="btn-soft" data-open-dialog="sla-edit-{{ $sla->id }}">Edit</button><button type="button" class="btn-soft" data-open-dialog="sla-delete-{{ $sla->id }}">Delete</button></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<dialog id="sla-create-dialog" class="max-w-2xl"><form method="POST" action="{{ route('admin.sla.store') }}" data-ajax-form class="panel m-0">@csrf<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Create SLA</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><div class="grid gap-4 md:grid-cols-2"><input class="field md:col-span-2" name="name" placeholder="SLA name" required><input class="field" type="number" name="response_minutes" min="1" placeholder="Response minutes" required><input class="field" type="number" name="resolution_minutes" min="1" placeholder="Resolution minutes" required><label class="flex items-center gap-3 text-sm text-slate-500 md:col-span-2"><input type="checkbox" name="is_default" value="1"> Set as default</label></div><div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Save SLA</button></div></form></dialog>

@foreach($slaPolicies as $sla)
    <dialog id="sla-edit-{{ $sla->id }}" class="max-w-2xl"><form method="POST" action="{{ route('admin.sla.update', $sla) }}" data-ajax-form class="panel m-0">@csrf @method('PATCH')<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Edit SLA</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><div class="grid gap-4 md:grid-cols-2"><input class="field md:col-span-2" name="name" value="{{ $sla->name }}" required><input class="field" type="number" name="response_minutes" min="1" value="{{ $sla->response_minutes }}" required><input class="field" type="number" name="resolution_minutes" min="1" value="{{ $sla->resolution_minutes }}" required><label class="flex items-center gap-3 text-sm text-slate-500 md:col-span-2"><input type="checkbox" name="is_default" value="1" @checked($sla->is_default)> Set as default</label></div><div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Update SLA</button></div></form></dialog>
    <dialog id="sla-delete-{{ $sla->id }}" class="max-w-lg"><form method="POST" action="{{ route('admin.sla.destroy', $sla) }}" data-ajax-form class="panel m-0">@csrf @method('DELETE')<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Delete SLA</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><p class="text-sm text-slate-500">SLA akan dihapus dari daftar admin dan ticket lama akan melepas referensinya secara otomatis.</p><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-soft" data-close-dialog>Cancel</button><button class="btn-primary" type="submit">Delete SLA</button></div></form></dialog>
@endforeach
@endsection

