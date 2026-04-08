@php
    $tabs = [
        ['label' => 'User', 'route' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*')],
        ['label' => 'SLA Policy', 'route' => route('admin.sla.index'), 'active' => request()->routeIs('admin.sla.*')],
        ['label' => 'Category', 'route' => route('admin.categories.index'), 'active' => request()->routeIs('admin.categories.*')],
        ['label' => 'Project', 'route' => route('admin.projects.index'), 'active' => request()->routeIs('admin.projects.*')],
        ['label' => 'Device', 'route' => route('admin.devices.index'), 'active' => request()->routeIs('admin.devices.*')],
    ];
@endphp

<div class="panel mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
        <p class="text-sm uppercase tracking-[0.28em] text-slate-500">Admin Workspace</p>
        <h2 class="mt-2 text-3xl font-black text-slate-900">{{ $pageTitle }}</h2>
        <p class="mt-2 text-sm text-slate-500">{{ $pageDescription }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @foreach($tabs as $tab)
            <a href="{{ $tab['route'] }}" class="{{ $tab['active'] ? 'btn-primary' : 'btn-soft' }}">{{ $tab['label'] }}</a>
        @endforeach
    </div>
</div>
