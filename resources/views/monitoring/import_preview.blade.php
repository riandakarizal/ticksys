@extends('layouts.app', ['title' => 'Import Preview — Monitoring EQT', 'heading' => 'Import Changes Preview'])

@section('content')
@php
$typeLabels = [
    'project_eq'   => 'Projects Equipment (EQ)',
    'project_tech' => 'Projects Technology (TECH)',
    'handover'     => 'Hand Over',
    'vehicle'      => 'Vehicle',
    'maintenance'  => 'Maintenance',
];
$totalNew       = collect($summary)->sum('new');
$totalUpdate    = collect($summary)->sum('update');
$totalUnchanged = collect($summary)->sum('unchanged');
@endphp

{{-- Summary bar --}}
<div class="flex flex-wrap divide-x divide-slate-200 border border-slate-200 rounded-xl bg-white overflow-hidden mb-5">
    <div class="flex items-center gap-3 px-5 py-3">
        <span class="text-2xl font-black text-green-600">{{ $totalNew }}</span>
        <span class="text-xs text-slate-500">New<br><span class="text-slate-400">to be added</span></span>
    </div>
    <div class="flex items-center gap-3 px-5 py-3">
        <span class="text-2xl font-black text-blue-600">{{ $totalUpdate }}</span>
        <span class="text-xs text-slate-500">Changed<br><span class="text-slate-400">to be updated</span></span>
    </div>
    <div class="flex items-center gap-3 px-5 py-3">
        <span class="text-2xl font-black text-slate-400">{{ $totalUnchanged }}</span>
        <span class="text-xs text-slate-500">Unchanged<br><span class="text-slate-400">skipped</span></span>
    </div>
</div>

@if($totalNew === 0 && $totalUpdate === 0)
    <div class="mb-5 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-600 text-center">
        All data is already up to date. Nothing needs to be updated.
    </div>
@endif

{{-- Confirm form --}}
<form method="POST" action="{{ route('monitoring.import.confirm') }}" class="mb-6" id="confirm-form">
    @csrf
    <div class="flex gap-3 items-center">
        <a href="{{ route('monitoring.import.show') }}" class="btn-soft text-sm px-4 py-2">← Re-upload</a>
        @if($totalNew > 0 || $totalUpdate > 0)
            <button type="submit" class="btn-primary text-sm px-6 py-2"
                onclick="return confirm('Confirm import of {{ $totalNew }} new and {{ $totalUpdate }} updated records?')">
                Confirm Import
            </button>
        @endif
        <a href="{{ route('monitoring.index') }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
    </div>
</form>

{{-- Per-type diff tables --}}
@foreach($diff as $type => $rows)
    @if(empty($rows)) @continue @endif
    @php
        $s = $summary[$type];
        $hasChanges = $s['new'] > 0 || $s['update'] > 0;
        $borderColor = match($type) {
            'project_eq'   => 'border-l-blue-500',
            'project_tech' => 'border-l-violet-500',
            'handover'     => 'border-l-emerald-500',
            'vehicle'      => 'border-l-orange-400',
            'maintenance'  => 'border-l-slate-400',
            default        => 'border-l-slate-300',
        };
    @endphp

    <div class="bg-white border border-slate-200 border-l-4 {{ $borderColor }} rounded-xl p-4 mb-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-black">{{ $typeLabels[$type] ?? $type }}</h3>
            <div class="flex gap-2 text-xs">
                <span class="px-2 py-1 rounded-lg bg-green-100 text-green-800 font-semibold">{{ $s['new'] }} new</span>
                <span class="px-2 py-1 rounded-lg bg-blue-100 text-blue-800 font-semibold">{{ $s['update'] }} changed</span>
                <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-500 font-semibold">{{ $s['unchanged'] }} unchanged</span>
            </div>
        </div>

        @if(!$hasChanges)
            <p class="text-xs text-slate-400 py-2">No changes in this section.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="text-left text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="pb-2 pr-2 w-8">#</th>
                            <th class="pb-2 pr-3">Status</th>
                            <th class="pb-2 pr-3">Label</th>
                            <th class="pb-2">Change Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($rows as $i => $item)
                            @if($item['status'] === 'unchanged') @continue @endif
                            @php
                                $bgClass = $item['status'] === 'new' ? 'bg-green-50/60' : 'bg-blue-50/40';
                                $badge   = $item['status'] === 'new'
                                    ? '<span class="badge bg-green-100 text-green-800">New</span>'
                                    : '<span class="badge bg-blue-100 text-blue-800">Update</span>';
                            @endphp
                            <tr class="{{ $bgClass }}">
                                <td class="py-2 pr-2 text-slate-400">{{ $i + 1 }}</td>
                                <td class="py-2 pr-3 whitespace-nowrap">{!! $badge !!}</td>
                                <td class="py-2 pr-3 font-medium max-w-[220px]">
                                    @php
                                        $label = match($type) {
                                            'project_eq', 'project_tech' => ($item['row']['nama'] ?? '') . ' (' . ($item['row']['tahun'] ?? '') . ')',
                                            'vehicle'     => $item['row']['nopol'] ?? '',
                                            'maintenance' => ($item['row']['nama_alat'] ?? '') . ' — ' . ($item['row']['area'] ?? ''),
                                            'handover'    => ($item['row']['nama'] ?? '') . ' (' . ($item['row']['tahun'] ?? '') . ')',
                                            default       => json_encode(array_slice($item['row'], 0, 2)),
                                        };
                                    @endphp
                                    {{ $label }}
                                </td>
                                <td class="py-2 text-[10px] text-slate-600 max-w-[400px]">
                                    @if($item['status'] === 'new')
                                        @php $preview = array_filter($item['row'], fn($v) => $v !== null && $v !== ''); @endphp
                                        <div class="flex flex-wrap gap-x-3 gap-y-0.5">
                                            @foreach(array_slice($preview, 0, 6, true) as $field => $val)
                                                <span><span class="text-slate-400">{{ $field }}:</span> {{ $val }}</span>
                                            @endforeach
                                        </div>
                                    @elseif($item['status'] === 'update')
                                        @foreach($item['diff'] as $field => $newVal)
                                            <div>
                                                <span class="font-semibold text-slate-700">{{ $field }}:</span>
                                                <span class="line-through text-red-400 mr-1">{{ $item['db'][$field] ?? '—' }}</span>
                                                → <span class="text-green-700">{{ $newVal ?? '—' }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endforeach

@endsection
