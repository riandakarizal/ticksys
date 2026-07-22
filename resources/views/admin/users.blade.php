@extends('layouts.app', ['title' => 'Users — Admin', 'heading' => 'Admin'])

@section('content')
<div class="panel mb-6">
    <p class="text-sm uppercase tracking-[0.28em] text-slate-500">Admin Workspace</p>
    <h2 class="mt-2 text-3xl font-black text-slate-900">{{ $pageTitle }}</h2>
    <p class="mt-2 text-sm text-slate-500">{{ $pageDescription }}</p>
</div>

<div class="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)] xl:items-start">
    <div class="panel-soft">
        <p class="text-sm uppercase tracking-[0.3em] text-blue-600">User Management</p>
        <h3 class="mt-2 text-3xl font-black leading-tight text-slate-900">Manage User Access</h3>
        <p class="mt-3 text-slate-600">Control user access and permissions within the application.</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
            <div class="stat"><p class="text-sm text-slate-500">Users</p><p class="mt-2 text-3xl font-black">{{ $users->count() }}</p></div>
            <div class="stat"><p class="text-sm text-slate-500">Projects</p><p class="mt-2 text-3xl font-black">{{ $projects->count() }}</p></div>
            <div class="stat"><p class="text-sm text-slate-500">Active</p><p class="mt-2 text-3xl font-black">{{ $users->where('user_status', 'active')->count() }}</p></div>
        </div>
    </div>

    <div class="panel">
        <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-xl font-black text-slate-900">Users</h3>
                <p class="text-sm text-slate-500">Complete list of all users.</p>
            </div>
            <button type="button" class="btn-primary" data-open-dialog="user-create-dialog">+User</button>
        </div>

        <div class="datatable-shell mt-5">
            <table class="min-w-full text-sm" data-datatable>
                <thead>
                    <tr class="text-left text-slate-500">
                        <th>Name</th>
                        <th>Employee ID</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Division / Unit</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-900">{{ $user->user_name }}</p>
                                <span class="text-xs text-slate-500">{{ $user->user_level ?: '-' }}</span>
                            </td>
                            <td class="text-slate-600">{{ $user->user_empid }}</td>
                            <td>{{ $user->user_email }}</td>
                            <td>{{ $user->roleLabel() }}</td>
                            <td class="text-slate-600">{{ $user->user_div }} / {{ $user->user_unit }}</td>
                            <td>
                                <span class="badge {{ $user->user_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                    {{ \Illuminate\Support\Str::headline($user->user_status) }}
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

{{-- ── CREATE DIALOG ─────────────────────────────────────────────────────── --}}
<dialog id="user-create-dialog" class="max-w-4xl">
    <form method="POST" action="{{ route('admin.users.store') }}" data-ajax-form class="panel m-0">
        @csrf
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <h3 class="text-xl font-black">Add User</h3>
            </div>
            <button type="button" class="btn-soft" data-close-dialog>Close</button>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <input class="field" name="user_empid" placeholder="Employee ID"                 required maxlength="20">
            <input class="field" name="user_name"  placeholder="Nama (maks 20 karakter)"     required maxlength="20">
            <input class="field" type="email" name="user_email" placeholder="Email"          required>
            <input class="field" name="user_pass"   type="password" placeholder="Password"   required>
            <select class="field" name="user_role" required>
                <option value="" disabled hidden selected>Select Role</option>
                <option value="user">User</option>
                <option value="siteadmin">Site Admin</option>
                <option value="admin">Admin</option>
                <option value="superadmin">Super Admin</option>
                <option value="vip">VIP</option>
            </select>
            <select class="field" name="user_level" required>
                <option value="" disabled hidden selected>Select Level</option>
                <option value="L1">L1 — Direktur</option>
                <option value="L2">L2 — Grouphead</option>
                <option value="L3">L3 — Divisionhead</option>
                <option value="L4">L4 — Analyst</option>
                <option value="L5">L5 — Senior Officer</option>
                <option value="L6">L6 — Officer</option>
                <option value="L7">L7 — Project Employee</option>
            </select>
            <select class="field" name="user_div" required>
                <option value="" disabled hidden selected>Select Divisi</option>
                <option value="-">— Tidak ada (Direktorat) —</option>
                <option value="Equipment & Technology Commercial">Equipment & Technology Commercial</option>
                <option value="Equipment & Technology Operation & Maintenance">Equipment & Technology Operation & Maintenance</option>
            </select>
            <select class="field" name="user_unit" required>
                <option value="" disabled hidden selected>Select Unit</option>
                <option value="-">— Tidak ada —</option>
                <option value="Technology Commercial">Technology Commercial</option>
                <option value="Equipment Commercial">Equipment Commercial</option>
                <option value="Technology Operation & Maintenance">Technology Operation & Maintenance</option>
                <option value="Equipment Operation & Maintenance">Equipment Operation & Maintenance</option>
            </select>
            <select class="field" name="user_parid">
                <option value="">— Tidak ada (Top Level) —</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->user_name }} ({{ $u->id }})</option>
                @endforeach
            </select>
            <select class="field" name="user_status" required>
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="mt-5 flex justify-end">
            <button class="btn-primary" type="submit">Save</button>
        </div>
    </form>
</dialog>

{{-- ── EDIT / DELETE DIALOGS ─────────────────────────────────────────────── --}}
@foreach($users as $user)
    <dialog id="user-edit-{{ $user->id }}" class="max-w-4xl">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" data-ajax-form class="panel m-0">
            @csrf
            @method('PATCH')
            <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                <div>
                    <h3 class="text-xl font-black">Edit User</h3>
                    <p class="text-sm text-slate-500">Update profile and role.</p>
                </div>
                <button type="button" class="btn-soft" data-close-dialog>Close</button>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <input class="field" name="user_empid" value="{{ $user->user_empid }}" placeholder="Employee ID" required maxlength="20">
                <input class="field" name="user_name"  value="{{ $user->user_name }}"  placeholder="Nama" required maxlength="20">
                <input class="field" type="email" name="user_email" value="{{ $user->user_email }}" required>
                <select class="field" name="user_role" required>
                    <option value="user"       @selected($user->user_role === 'user')>User</option>
                    <option value="siteadmin"  @selected($user->user_role === 'siteadmin')>Site Admin</option>
                    <option value="admin"      @selected($user->user_role === 'admin')>Admin</option>
                    <option value="superadmin" @selected($user->user_role === 'superadmin')>Super Admin</option>
                    <option value="vip"        @selected($user->user_role === 'vip')>VIP</option>
                </select>
                <select class="field" name="user_level" required>
                    <option value="L1" @selected($user->user_level === 'L1')>L1 — Direktur</option>
                    <option value="L2" @selected($user->user_level === 'L2')>L2 — Grouphead</option>
                    <option value="L3" @selected($user->user_level === 'L3')>L3 — Divisionhead</option>
                    <option value="L4" @selected($user->user_level === 'L4')>L4 — Analyst</option>
                    <option value="L5" @selected($user->user_level === 'L5')>L5 — Senior Officer</option>
                    <option value="L6" @selected($user->user_level === 'L6')>L6 — Officer</option>
                    <option value="L7" @selected($user->user_level === 'L7')>L7 — Project Employee</option>
                </select>
                <select class="field" name="user_div" required>
                    <option value="-" @selected($user->user_div === '-')>— Tidak ada (Direktorat) —</option>
                    <option value="Equipment & Technology Commercial" @selected($user->user_div === 'Equipment & Technology Commercial')>Equipment & Technology Commercial</option>
                    <option value="Equipment & Technology Operation & Maintenance" @selected($user->user_div === 'Equipment & Technology Operation & Maintenance')>Equipment & Technology Operation & Maintenance</option>
                </select>
                <select class="field" name="user_unit" required>
                    <option value="-" @selected($user->user_unit === '-')>— Tidak ada —</option>
                    <option value="Technology Commercial" @selected($user->user_unit === 'Technology Commercial')>Technology Commercial</option>
                    <option value="Equipment Commercial" @selected($user->user_unit === 'Equipment Commercial')>Equipment Commercial</option>
                    <option value="Technology Operation & Maintenance" @selected($user->user_unit === 'Technology Operation & Maintenance')>Technology Operation & Maintenance</option>
                    <option value="Equipment Operation & Maintenance" @selected($user->user_unit === 'Equipment Operation & Maintenance')>Equipment Operation & Maintenance</option>
                </select>
                <select class="field" name="user_parid">
                    <option value="" @selected($user->user_parid === '')>— Tidak ada (Top Level) —</option>
                    @foreach($users as $u)
                        @continue($u->id === $user->id)
                        <option value="{{ $u->id }}" @selected($user->user_parid === $u->id)>{{ $u->user_name }} ({{ $u->id }})</option>
                    @endforeach
                </select>
                <input class="field" name="user_pass"   type="password" placeholder="New password (optional)">
                <select class="field" name="user_status" required>
                    <option value="active"   @selected($user->user_status === 'active')>Active</option>
                    <option value="inactive" @selected($user->user_status === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="mt-5 flex justify-end">
                <button class="btn-primary" type="submit">Update</button>
            </div>
        </form>
    </dialog>

    <dialog id="user-delete-{{ $user->id }}" class="max-w-lg">
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-ajax-form class="panel m-0">
            @csrf
            @method('DELETE')
            <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                <h3 class="text-xl font-black">Delete User</h3>
                <button type="button" class="btn-soft" data-close-dialog>Close</button>
            </div>
            <p class="text-sm text-slate-500">This user will be removed from the directory and all project assignments. Ticket history will be retained.</p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" class="btn-soft" data-close-dialog>Cancel</button>
                <button class="btn-primary" type="submit">Delete</button>
            </div>
        </form>
    </dialog>
@endforeach
@endsection
