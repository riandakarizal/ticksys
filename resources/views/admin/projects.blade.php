@extends('layouts.app', ['title' => 'Admin Project', 'heading' => 'Admin'])

@section('content')
@include('admin._tabs')

<div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)] xl:items-start">
    <div class="panel-soft">
        <p class="text-sm uppercase tracking-[0.3em] text-blue-600">Project Access</p>
        <h3 class="mt-2 text-3xl font-black leading-tight text-slate-900">Projects On Going</h3>
        <p class="mt-3 text-slate-600">Your active projects.</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
            <div class="stat"><p class="text-sm text-slate-500">Projects</p><p class="mt-2 text-3xl font-black">{{ $projects->count() }}</p></div>
            <div class="stat"><p class="text-sm text-slate-500">Members</p><p class="mt-2 text-3xl font-black">{{ $projects->sum(fn ($project) => $project->members->count()) }}</p></div>
            <div class="stat"><p class="text-sm text-slate-500">Coordinators</p><p class="mt-2 text-3xl font-black">{{ $coordinators->count() }}</p></div>
        </div>
    </div>

    <div class="panel">
        <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-xl font-black text-slate-900">Projects Summary</h3>
                <p class="text-sm text-slate-500">Detailed list of all projects.</p>
            </div>
            <button type="button" class="btn-primary" data-open-dialog="project-create-dialog">Create project</button>
        </div>

        <div class="datatable-shell mt-5">
            <table class="min-w-full text-sm" data-datatable>
                <thead>
                    <tr class="text-left text-slate-500">
                        <th>Name</th>
                        <th>Code</th>
                        <th>Coordinator</th>
                        <th>Members</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($projects as $project)
                        <tr>
                            <td><p class="font-semibold text-slate-900">{{ $project->name }}</p><p class="text-xs text-slate-500">{{ $project->description ?: '-' }}</p></td>
                            <td>{{ $project->code }}</td>
                            <td>{{ $project->lead?->name ?? '-' }}</td>
                            <td>{{ $project->members->pluck('name')->join(', ') ?: '-' }}</td>
                            <td><div class="flex flex-wrap gap-2"><button type="button" class="btn-soft" data-open-dialog="project-edit-{{ $project->id }}">Edit</button><button type="button" class="btn-soft" data-open-dialog="project-delete-{{ $project->id }}">Delete</button></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<dialog id="project-create-dialog" class="max-w-4xl"><form method="POST" action="{{ route('admin.projects.store') }}" data-ajax-form class="panel m-0">@csrf<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Create project</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><div class="grid gap-4 md:grid-cols-2"><input class="field" name="name" placeholder="Project name" required><input class="field" name="code" placeholder="PROJECT-CODE"><textarea class="field md:col-span-2" name="description" placeholder="Description"></textarea><select class="field" name="lead_user_id"><option value="" disabled hidden>Select coordinator</option>@foreach($coordinators as $coordinator)<option value="{{ $coordinator->id }}">{{ $coordinator->name }}</option>@endforeach</select><div class="md:col-span-2"><label class="label">Project members</label><select class="field" name="member_ids[]" multiple size="10">@foreach($projectUsers as $member)<option value="{{ $member->id }}">{{ $member->name }} - {{ \Illuminate\Support\Str::headline($member->role) }}</option>@endforeach</select></div></div><div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Save project</button></div></form></dialog>

@foreach($projects as $project)
    <dialog id="project-edit-{{ $project->id }}" class="max-w-4xl"><form method="POST" action="{{ route('admin.projects.update', $project) }}" data-ajax-form class="panel m-0">@csrf @method('PATCH')<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Edit project</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><div class="grid gap-4 md:grid-cols-2"><input class="field" name="name" value="{{ $project->name }}" required><input class="field" name="code" value="{{ $project->code }}"><textarea class="field md:col-span-2" name="description" placeholder="Description">{{ $project->description }}</textarea><select class="field" name="lead_user_id"><option value="" disabled hidden>Select coordinator</option>@foreach($coordinators as $coordinator)<option value="{{ $coordinator->id }}" @selected($project->lead_user_id === $coordinator->id)>{{ $coordinator->name }}</option>@endforeach</select><div class="md:col-span-2"><label class="label">Project members</label><select class="field" name="member_ids[]" multiple size="10">@foreach($projectUsers as $member)<option value="{{ $member->id }}" @selected($project->members->contains('id', $member->id))>{{ $member->name }} - {{ \Illuminate\Support\Str::headline($member->role) }}</option>@endforeach</select></div></div><div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Update project</button></div></form></dialog>
    <dialog id="project-delete-{{ $project->id }}" class="max-w-lg"><form method="POST" action="{{ route('admin.projects.destroy', $project) }}" data-ajax-form class="panel m-0">@csrf @method('DELETE')<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Delete project</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><p class="text-sm text-slate-500">Project akan dihapus dari daftar admin, dilepas dari category terkait, dan ticket lama tetap aman.</p><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-soft" data-close-dialog>Cancel</button><button class="btn-primary" type="submit">Delete project</button></div></form></dialog>
@endforeach
@endsection

