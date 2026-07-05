@extends('layouts.app', ['title' => 'Dashboard', 'heading' => 'Dashboard'])

@section('content')

{{-- ── KPI Cards ──────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5 mb-6">
    <div class="stat col-span-2 sm:col-span-1 lg:col-span-1">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Total Projects</p>
        <p class="mt-2 text-4xl font-black text-slate-900">{{ $projectKpi['total'] }}</p>
    </div>
    <div class="stat border-l-4 border-l-green-400">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400">On Going</p>
        <p class="mt-2 text-4xl font-black text-green-600">{{ $projectKpi['og'] }}</p>
    </div>
    <div class="stat border-l-4 border-l-amber-400">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Delay</p>
        <p class="mt-2 text-4xl font-black text-amber-600">{{ $projectKpi['dly'] }}</p>
    </div>
    <div class="stat border-l-4 border-l-blue-400">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Hand Over</p>
        <p class="mt-2 text-4xl font-black text-blue-600">{{ $projectKpi['hvr'] }}</p>
    </div>
    <div class="stat border-l-4 border-l-brand-500">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Contract Value</p>
        <p class="mt-2 text-2xl font-black text-slate-800 leading-tight">
            Rp {{ number_format($projectKpi['nilai'] / 1e9, 1) }}B
        </p>
        <p class="text-xs text-slate-400 mt-1">{{ number_format($projectKpi['nilai']) }}</p>
    </div>
</div>

{{-- ── Charts Row ─────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">

    {{-- Status Donut --}}
    <div class="panel">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">By Status</p>
        <div class="flex items-center gap-6">
            <div class="relative shrink-0">
                <svg width="100" height="100" viewBox="0 0 100 100">
                    @php
                        $statuses = [
                            ['label'=>'On Going','count'=>$projectKpi['og'], 'color'=>'#22c55e'],
                            ['label'=>'Hand Over','count'=>$projectKpi['hvr'], 'color'=>'#3b82f6'],
                            ['label'=>'Delay','count'=>$projectKpi['dly'], 'color'=>'#f59e0b'],
                            ['label'=>'Ended','count'=>$projectKpi['end'], 'color'=>'#94a3b8'],
                        ];
                        $total = max($projectKpi['total'], 1);
                        $circ = 2 * M_PI * 35;
                        $dashOffset = 0;
                    @endphp
                    @foreach($statuses as $seg)
                        @php
                            $pct = $seg['count'] / $total;
                            $dash = $circ * $pct;
                        @endphp
                        <circle cx="50" cy="50" r="35"
                            fill="none" stroke="{{ $seg['color'] }}" stroke-width="16"
                            stroke-dasharray="{{ number_format($dash, 2, '.', '') }} {{ number_format($circ - $dash, 2, '.', '') }}"
                            stroke-dashoffset="{{ number_format(-$dashOffset, 2, '.', '') }}"
                            transform="rotate(-90 50 50)"/>
                        @php $dashOffset += $dash; @endphp
                    @endforeach
                    <circle cx="50" cy="50" r="27" fill="white"/>
                    <text x="50" y="46" text-anchor="middle" font-size="14" font-weight="900" fill="#0f172a">{{ $projectKpi['total'] }}</text>
                    <text x="50" y="58" text-anchor="middle" font-size="7" fill="#94a3b8">projects</text>
                </svg>
            </div>
            <div class="space-y-2 min-w-0 flex-1">
                @foreach($statuses as $seg)
                <div class="flex items-center justify-between gap-2 text-xs">
                    <span class="flex items-center gap-1.5 min-w-0 truncate text-slate-600 font-medium">
                        <span class="h-2 w-2 rounded-full shrink-0" style="background:{{ $seg['color'] }}"></span>
                        {{ $seg['label'] }}
                    </span>
                    <span class="font-black text-slate-900 shrink-0">{{ $seg['count'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- By Type --}}
    <div class="panel">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">By Type</p>
        @php
            $typeMax = max(array_values($byType) + [1]);
            $typeColors = ['RENT'=>'bg-violet-400','SUPPLY'=>'bg-sky-400','JASA'=>'bg-teal-400'];
            $typeLabels = ['RENT'=>'Rental','SUPPLY'=>'Supply','JASA'=>'Service'];
        @endphp
        <div class="space-y-4">
            @foreach($byType as $type => $count)
            <div>
                <div class="flex justify-between text-xs font-semibold mb-1">
                    <span class="text-slate-700">{{ $typeLabels[$type] }}</span>
                    <span class="text-slate-500">{{ $count }}</span>
                </div>
                <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full {{ $typeColors[$type] }}" style="width: {{ $typeMax > 0 ? round(($count / $typeMax) * 100) : 0 }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- By Year --}}
    <div class="panel">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">Projects by Year</p>
        @php $yearMax = max($byYear->values()->all() + [1]); @endphp
        <div class="flex items-end justify-around gap-2 h-28">
            @foreach($byYear as $year => $count)
            <div class="flex flex-col items-center gap-1 flex-1">
                <span class="text-xs font-black text-slate-700">{{ $count }}</span>
                <div class="w-full rounded-t-lg bg-brand-500 transition-all" style="height: {{ round(($count / $yearMax) * 80) }}px; min-height: 4px;"></div>
                <span class="text-[10px] text-slate-400">{{ substr($year, 2) }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── Bottom Row ─────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    {{-- Recent Projects --}}
    <div class="panel lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Recent Projects</p>
            <a href="{{ route('monitoring.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">View all →</a>
        </div>
        <div class="overflow-x-auto -mx-2">
            <table class="min-w-full text-xs">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="px-2 pb-2 text-left font-bold uppercase tracking-wide text-slate-400">Project</th>
                        <th class="px-2 pb-2 text-left font-bold uppercase tracking-wide text-slate-400">Client</th>
                        <th class="px-2 pb-2 text-left font-bold uppercase tracking-wide text-slate-400">Type</th>
                        <th class="px-2 pb-2 text-left font-bold uppercase tracking-wide text-slate-400">Value</th>
                        <th class="px-2 pb-2 text-left font-bold uppercase tracking-wide text-slate-400">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($recentProjects as $p)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-2 py-2.5">
                            <p class="font-semibold text-slate-800 truncate max-w-[180px]">{{ $p->pjct_name }}</p>
                            @if($p->pjct_contract)
                                <p class="text-slate-400 font-mono text-[10px]">{{ $p->pjct_contract }}</p>
                            @endif
                        </td>
                        <td class="px-2 py-2.5 text-slate-600 max-w-[120px] truncate">{{ $p->pjct_client ?? '-' }}</td>
                        <td class="px-2 py-2.5">
                            <span class="badge {{ $p->typeBadgeClass() }}">{{ $p->pjct_type }}</span>
                        </td>
                        <td class="px-2 py-2.5 text-slate-700 font-semibold whitespace-nowrap">
                            @if($p->pjct_value)
                                Rp {{ number_format($p->pjct_value / 1e6, 0) }}M
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-2 py-2.5">
                            <span class="badge {{ $p->statusBadgeClass() }}">{{ $p->statusLabel() }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-2 py-6 text-center text-slate-400">No projects yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Right column: Area breakdown + Ticket quick-stats --}}
    <div class="flex flex-col gap-6">

        {{-- By Area --}}
        <div class="panel flex-1">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">By Area</p>
            @php $areaMax = max($byArea->values()->all() + [1]); @endphp
            <div class="space-y-2.5">
                @foreach($byArea as $area => $count)
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-24 truncate text-slate-600 font-medium shrink-0">{{ $area }}</span>
                    <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ round(($count / $areaMax) * 100) }}%"></div>
                    </div>
                    <span class="font-black text-slate-800 w-4 text-right shrink-0">{{ $count }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Ticket quick-stats --}}
        <div class="panel">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Tickets</p>
                <a href="{{ route('tickets.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">View →</a>
            </div>
            <div class="grid grid-cols-2 gap-2">
                @foreach([
                    ['Open',        $ticketCounts['open'],        'bg-blue-100',  'text-blue-700'],
                    ['In Progress', $ticketCounts['in_progress'], 'bg-amber-100', 'text-amber-700'],
                    ['Pending',     $ticketCounts['pending'],     'bg-orange-100','text-orange-700'],
                    ['Resolved',    $ticketCounts['resolved'],    'bg-green-100', 'text-green-700'],
                ] as [$label, $count, $bg, $fg])
                <div class="rounded-xl {{ $bg }} px-3 py-2.5">
                    <p class="text-[10px] font-semibold {{ $fg }} uppercase tracking-wide">{{ $label }}</p>
                    <p class="text-xl font-black {{ $fg }}">{{ $count }}</p>
                </div>
                @endforeach
            </div>
        </div>

    </div>
</div>

@endsection
