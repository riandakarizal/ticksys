@extends('layouts.app', ['title' => 'Admin Category', 'heading' => 'Admin'])

@section('content')
@include('admin._tabs')

<div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)] xl:items-start">
    <div class="panel-soft">
        <p class="text-sm uppercase tracking-[0.3em] text-blue-600">Category Management</p>
        <h3 class="mt-2 text-3xl font-black leading-tight text-slate-900">Manage Categories</h3>
        <p class="mt-3 text-slate-600">Satu form untuk membuat parent category maupun child category.</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
            <div class="stat"><p class="text-sm text-slate-500">Categories</p><p class="mt-2 text-3xl font-black">{{ $categories->count() }}</p></div>
            <div class="stat"><p class="text-sm text-slate-500">Active</p><p class="mt-2 text-3xl font-black">{{ $categories->where('is_active', true)->count() }}</p></div>
        </div>
    </div>

    <div class="panel">
        <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-xl font-black text-slate-900">Categories</h3>
                <p class="text-sm text-slate-500">Create parent or child category from the same form.</p>
            </div>
            <button type="button" class="btn-primary" data-open-dialog="category-create-dialog">Create category</button>
        </div>

        <div class="datatable-shell mt-5">
            <table class="min-w-full text-sm" data-datatable>
                <thead>
                    <tr class="text-left text-slate-500">
                        <th>Name</th>
                        <th>Parent</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $category)
                        <tr>
                            <td><div class="flex items-start gap-3"><span class="mt-1 h-3.5 w-3.5 shrink-0 rounded-full border border-white shadow-sm ring-1 ring-slate-200" style="background-color: {{ $category->color ?: '#cbd5e1' }}"></span><div><p class="font-semibold text-slate-900">{{ $category->name }}</p><p class="text-xs text-slate-500">{{ $category->slug }}</p></div></div></td>
                            <td>{{ $category->parent?->name ?? '-' }}</td>
                            <td>{{ $category->is_active ? 'Active' : 'Inactive' }}</td>
                            <td><div class="flex flex-wrap gap-2"><button type="button" class="btn-soft" data-open-dialog="category-edit-{{ $category->id }}">Edit</button><button type="button" class="btn-soft" data-open-dialog="category-delete-{{ $category->id }}">Delete</button></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<dialog id="category-create-dialog" class="max-w-4xl"><form method="POST" action="{{ route('admin.categories.store') }}" data-ajax-form class="panel m-0">@csrf<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Create category</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><div class="grid gap-4 md:grid-cols-2"><div><label class="label">Category name</label><input class="field" name="name" placeholder="Category name" required></div><div><label class="label">Category slug</label><input class="field" name="slug" placeholder="category-slug" required></div><div><label class="label">Parent category</label><select class="field" name="parent_id"><option value="" @selected(blank(old('parent_id')))>None - Create as parent category</option>@foreach($categories->whereNull('parent_id') as $parent)<option value="{{ $parent->id }}" @selected((string) old('parent_id') === (string) $parent->id)>{{ $parent->name }}</option>@endforeach</select><p class="mt-2 text-xs text-slate-500">Pilih parent untuk membuat child category. Jika child category dipilih, warna akan otomatis mengikuti parent.</p></div><div><label class="label">Category Color</label><input class="field" name="color" value="{{ old('color') }}" placeholder="#0f766e"><p class="mt-2 text-xs text-slate-500">Kosongkan untuk memakai warna clean otomatis pada parent category.</p></div><label class="flex items-center gap-3 text-sm text-slate-500 md:col-span-2"><input type="checkbox" name="is_active" value="1" checked> Active category</label></div><div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Save category</button></div></form></dialog>

@foreach($categories as $category)
    <dialog id="category-edit-{{ $category->id }}" class="max-w-4xl"><form method="POST" action="{{ route('admin.categories.update', $category) }}" data-ajax-form class="panel m-0">@csrf @method('PATCH')<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Edit category</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><div class="grid gap-4 md:grid-cols-2"><div><label class="label">Category name</label><input class="field" name="name" value="{{ $category->name }}" required></div><div><label class="label">Category slug</label><input class="field" name="slug" value="{{ $category->slug }}" required></div><div><label class="label">Parent category</label><select class="field" name="parent_id"><option value="" @selected(blank($category->parent_id))>None - Keep as parent category</option>@foreach($categories->whereNull('parent_id')->where('id', '!=', $category->id) as $parent)<option value="{{ $parent->id }}" @selected($category->parent_id === $parent->id)>{{ $parent->name }}</option>@endforeach</select><p class="mt-2 text-xs text-slate-500">Jika category dijadikan child, warna akan otomatis ikut parent.</p></div><div><label class="label">Category Color</label><input class="field" name="color" value="{{ $category->color }}" placeholder="#0f766e"><p class="mt-2 text-xs text-slate-500">Kosongkan untuk mempertahankan warna sekarang pada parent category.</p></div><label class="flex items-center gap-3 text-sm text-slate-500 md:col-span-2"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Active category</label></div><div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Update category</button></div></form></dialog>
    <dialog id="category-delete-{{ $category->id }}" class="max-w-lg"><form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-ajax-form class="panel m-0">@csrf @method('DELETE')<div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4"><h3 class="text-xl font-black">Delete category</h3><button type="button" class="btn-soft" data-close-dialog>Close</button></div><p class="text-sm text-slate-500">Category akan dihapus dari daftar admin.</p><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-soft" data-close-dialog>Cancel</button><button class="btn-primary" type="submit">Delete category</button></div></form></dialog>
@endforeach
@endsection
