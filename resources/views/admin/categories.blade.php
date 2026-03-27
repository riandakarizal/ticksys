@extends('layouts.app', ['title' => 'Admin Category', 'heading' => 'Admin'])

@section('content')
@include('admin._tabs')

<div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)] xl:items-start">
    <div class="panel-soft">
        <p class="text-sm uppercase tracking-[0.3em] text-blue-600">Category Management</p>
        <h3 class="mt-2 text-3xl font-black leading-tight text-slate-900">Manage Categories</h3>
        <p class="mt-3 text-slate-600">based on your project needs</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
            <div class="stat"><p class="text-sm text-slate-500">Categories</p><p class="mt-2 text-3xl font-black">{{ $categories->count() }}</p></div>
            <div class="stat"><p class="text-sm text-slate-500">Active</p><p class="mt-2 text-3xl font-black">{{ $categories->where('is_active', true)->count() }}</p></div>
        </div>
    </div>

    <div class="panel">
        <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-xl font-black text-slate-900">Categories</h3>
                <p class="text-sm text-slate-500">Detailed list of all categories.</p>
            </div>
            <button type="button" class="btn-primary" data-open-dialog="category-create-dialog">Create category</button>
        </div>

        <div class="datatable-shell mt-5">
            <table class="min-w-full text-sm" data-datatable>
                <thead>
                    <tr class="text-left text-slate-500">
                        <th>Name</th>
                        <th>Parent</th>
                        <th>Projects</th>
                        <th>Auto assign</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $category)
                        <tr>
                            <td><p class="font-semibold text-slate-900">{{ $category->name }}</p><p class="text-xs text-slate-500">{{ $category->slug }}</p></td>
                            <td>{{ $category->parent?->name ?? '-' }}</td>
                            <td>{{ $category->projects->pluck('name')->join(', ') ?: 'All projects' }}</td>
                            <td>{{ $category->autoAssignUser?->name ?? '-' }}</td>
                            <td>{{ $category->is_active ? 'Active' : 'Inactive' }}</td>
                            <td><div class="flex flex-wrap gap-2"><button type="button" class="btn-soft" data-open-dialog="category-edit-{{ $category->id }}">Edit</button><button type="button" class="btn-soft" data-open-dialog="category-delete-{{ $category->id }}">Delete</button></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<dialog id="category-create-dialog" class="max-w-4xl"><form method="POST" action="{{ route('admin.categories.store') }}" data-ajax-form class="panel m-0">@csrf<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Create category</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><div class="grid gap-4 md:grid-cols-2"><input class="field" name="name" placeholder="Category name" required><input class="field" name="slug" placeholder="category-slug" required><select class="field" name="parent_id"><option value="" disabled hidden>Select parent category</option>@foreach($categories->whereNull('parent_id') as $parent)<option value="{{ $parent->id }}">{{ $parent->name }}</option>@endforeach</select><input class="field" name="color" placeholder="#2563eb"><div class="md:col-span-2"><label class="label">Projects</label><select class="field" name="project_ids[]" multiple size="8">@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select><p class="mt-2 text-xs text-slate-500">Kosongkan jika category boleh dipakai di semua project.</p></div><select class="field md:col-span-2" name="auto_assign_user_id"><option value="" disabled hidden>Select auto assign user</option>@foreach($assignableAgents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }}</option>@endforeach</select><label class="flex items-center gap-3 text-sm text-slate-500 md:col-span-2"><input type="checkbox" name="is_active" value="1" checked> Active category</label></div><div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Save category</button></div></form></dialog>

@foreach($categories as $category)
    <dialog id="category-edit-{{ $category->id }}" class="max-w-4xl"><form method="POST" action="{{ route('admin.categories.update', $category) }}" data-ajax-form class="panel m-0">@csrf @method('PATCH')<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Edit category</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><div class="grid gap-4 md:grid-cols-2"><input class="field" name="name" value="{{ $category->name }}" required><input class="field" name="slug" value="{{ $category->slug }}" required><select class="field" name="parent_id"><option value="" disabled hidden>Select parent category</option>@foreach($categories->whereNull('parent_id')->where('id', '!=', $category->id) as $parent)<option value="{{ $parent->id }}" @selected($category->parent_id === $parent->id)>{{ $parent->name }}</option>@endforeach</select><input class="field" name="color" value="{{ $category->color }}" placeholder="#2563eb"><div class="md:col-span-2"><label class="label">Projects</label><select class="field" name="project_ids[]" multiple size="8">@foreach($projects as $project)<option value="{{ $project->id }}" @selected($category->projects->contains('id', $project->id))>{{ $project->name }}</option>@endforeach</select><p class="mt-2 text-xs text-slate-500">Kosongkan jika category boleh dipakai di semua project.</p></div><select class="field md:col-span-2" name="auto_assign_user_id"><option value="" disabled hidden>Select auto assign user</option>@foreach($assignableAgents as $agent)<option value="{{ $agent->id }}" @selected($category->auto_assign_user_id === $agent->id)>{{ $agent->name }}</option>@endforeach</select><label class="flex items-center gap-3 text-sm text-slate-500 md:col-span-2"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Active category</label></div><div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Update category</button></div></form></dialog>
    <dialog id="category-delete-{{ $category->id }}" class="max-w-lg"><form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-ajax-form class="panel m-0">@csrf @method('DELETE')<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Delete category</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><p class="text-sm text-slate-500">Category akan dihapus dari daftar admin dan dilepas dari semua project terkait.</p><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-soft" data-close-dialog>Cancel</button><button class="btn-primary" type="submit">Delete category</button></div></form></dialog>
@endforeach
@endsection
