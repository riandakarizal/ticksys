@extends('layouts.app', ['title' => 'Admin Users', 'heading' => 'Admin'])

@section('content')
@include('admin._tabs')

<div class="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)] xl:items-start">
    <div class="panel-soft">
        <p class="text-sm uppercase tracking-[0.3em] text-blue-600">User Management</p>
        <h3 class="mt-2 text-3xl font-black leading-tight text-slate-900">Manage User Access</h3>
        <p class="mt-3 text-slate-600">Control user access and permissions across the application.</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
            <div class="stat"><p class="text-sm text-slate-500">Users</p><p class="mt-2 text-3xl font-black">{{ $users->count() }}</p></div>
            <div class="stat"><p class="text-sm text-slate-500">Projects</p><p class="mt-2 text-3xl font-black">{{ $projects->count() }}</p></div>
            <div class="stat"><p class="text-sm text-slate-500">Active</p><p class="mt-2 text-3xl font-black">{{ $users->where('is_active', true)->count() }}</p></div>
        </div>
    </div>

    <div class="panel">
        <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-xl font-black text-slate-900">Users</h3>
                <p class="text-sm text-slate-500">Detailed list of all users.</p>
            </div>
            <button type="button" class="btn-primary" data-open-dialog="user-create-dialog">Create user</button>
        </div>

        <div class="datatable-shell mt-5">
            <table class="min-w-full text-sm" data-datatable>
                <thead>
                    <tr class="text-left text-slate-500">
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Projects</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                                <span class="text-xs text-slate-500">{{ $user->job_title ?: '-' }}</span>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ \Illuminate\Support\Str::headline($user->role) }}</td>
                            <td>{{ $user->projects->pluck('name')->join(', ') ?: '-' }}</td>
                            <td>
                                <span class="badge {{ $user->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="btn-soft" data-open-dialog="user-edit-{{ $user->id }}">Edit</button>
                                    <button type="button" class="btn-soft" data-open-dialog="user-delete-{{ $user->id }}">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<dialog id="user-create-dialog" class="max-w-4xl">
    <form method="POST" action="{{ route('admin.users.store') }}" data-ajax-form class="panel m-0">
        @csrf
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <h3 class="text-xl font-black">Create user</h3>
                <p class="text-sm text-slate-500">User non-admin wajib punya assignment project.</p>
            </div>
            <button type="button" class="btn-soft" data-close-dialog>Close</button>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <input class="field" name="name" placeholder="Full name" required>
            <input class="field" type="email" name="email" placeholder="Email" required>
            <select class="field" name="role" required>
                <option value="" disabled hidden>Select role</option>
                <option value="client">Client</option>
                <option value="agent">Agent</option>
                <option value="supervisor">Coordinator</option>
                <option value="admin">Admin</option>
            </select>
            <input class="field" name="job_title" placeholder="Job title">
            <input class="field" name="phone" placeholder="Phone">
            <input class="field" name="password" type="password" placeholder="Password" required>
            <div class="md:col-span-2">
                <label class="label">Assigned projects</label>
                <select class="field" name="project_ids[]" multiple size="8">
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-3 text-sm text-slate-500 md:col-span-2"><input type="checkbox" name="is_active" value="1" checked> Active user</label>
        </div>
        <div class="mt-5 flex justify-end">
            <button class="btn-primary" type="submit">Save user</button>
        </div>
    </form>
</dialog>

@foreach($users as $user)
    <dialog id="user-edit-{{ $user->id }}" class="max-w-4xl">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" data-ajax-form class="panel m-0">
            @csrf
            @method('PATCH')
            <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                <div>
                    <h3 class="text-xl font-black">Edit user</h3>
                    <p class="text-sm text-slate-500">Perbarui profile, role, dan assignment project.</p>
                </div>
                <button type="button" class="btn-soft" data-close-dialog>Close</button>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <input class="field" name="name" value="{{ $user->name }}" required>
                <input class="field" type="email" name="email" value="{{ $user->email }}" required>
                <select class="field" name="role" required>
                    <option value="client" @selected($user->role === 'client')>Client</option>
                    <option value="agent" @selected($user->role === 'agent')>Agent</option>
                    <option value="supervisor" @selected($user->role === 'supervisor')>Coordinator</option>
                    <option value="admin" @selected($user->role === 'admin')>Admin</option>
                </select>
                <input class="field" name="job_title" value="{{ $user->job_title }}" placeholder="Job title">
                <input class="field" name="phone" value="{{ $user->phone }}" placeholder="Phone">
                <input class="field" name="password" type="password" placeholder="New password (optional)">
                <div class="md:col-span-2">
                    <label class="label">Assigned projects</label>
                    <select class="field" name="project_ids[]" multiple size="8">
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected($user->projects->contains('id', $project->id))>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-3 text-sm text-slate-500 md:col-span-2"><input type="checkbox" name="is_active" value="1" @checked($user->is_active)> Active user</label>
            </div>
            <div class="mt-5 flex justify-end">
                <button class="btn-primary" type="submit">Update user</button>
            </div>
        </form>
    </dialog>

    <dialog id="user-delete-{{ $user->id }}" class="max-w-lg">
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-ajax-form class="panel m-0">
            @csrf
            @method('DELETE')
            <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                <h3 class="text-xl font-black">Delete user</h3>
                <button type="button" class="btn-soft" data-close-dialog>Close</button>
            </div>
            <p class="text-sm text-slate-500">User akan dihapus dari directory dan assignment project. Histori ticket tetap aman.</p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="btn-soft" data-close-dialog>Cancel</button>
                <button class="btn-primary" type="submit">Delete user</button>
            </div>
        </form>
    </dialog>
@endforeach
@endsection

