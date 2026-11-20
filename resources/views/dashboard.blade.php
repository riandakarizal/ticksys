@extends('layouts.app', ['title' => 'Dashboard', 'heading' => 'Dashboard'])

{{-- Dashboard kantor (superadmin, admin, siteadmin, vip, fin, qhsse). Bahasa visualnya sama dengan
     dashboard-technician: sapaan, chip pill, kartu pastel besar, satu kartu hitam untuk aksi. --}}
@section('content')
@php
    $ico = fn (string $d, string $cls = 'h-5 w-5') => '<svg class="'.$cls.'" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.$d.'"/></svg>';
    $path = [
        'folder'    => 'M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z',
        'money'     => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z',
        'ticket'    => 'M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z',
        'play'      => 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z',
        'arrow'     => 'M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3',
        'open'      => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'escalated' => 'M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18',
        'solved'    => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'closed'    => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
    ];

    $canMonitor = ! $user->isFin();   // route monitoring.index tanpa `fin`
    $canTickets = ! $user->isFin();   // Finance tidak punya akses helpdesk
    $firstName  = strtok(trim($user->user_name), ' ') ?: $user->user_name;
    $total      = $projectKpi['total'];
    $running    = $projectKpi['og'] + $projectKpi['dly'];
    $blocks     = fn (int $part, int $whole) => $whole > 0 ? (int) round($part / $whole * 8) : 0;
    $pct        = fn (int $part, int $whole) => $whole > 0 ? (int) round($part / $whole * 100) : 0;

    // Satuan Indonesia: Jt = juta, M = miliar, T = triliun.
    $rpParts = function ($n): array {
        $n = (float) $n;

        return match (true) {
            $n <= 0    => ['-', ''],
            $n >= 1e12 => [number_format($n / 1e12, 2, ',', '.'), 'triliun'],
            $n >= 1e9  => [number_format($n / 1e9, 2, ',', '.'), 'miliar'],
            $n >= 1e6  => [number_format($n / 1e6, 0, ',', '.'), 'juta'],
            default    => [number_format($n, 0, ',', '.'), ''],
        };
    };
    $rpShort = function ($n) use ($rpParts): string {
        [$num, $unit] = $rpParts($n);

        return $num === '-' ? '-' : 'Rp '.$num.match ($unit) { 'triliun' => ' T', 'miliar' => ' M', 'juta' => ' Jt', default => '' };
    };
    [$valueNum, $valueUnit] = $rpParts($projectKpi['nilai']);

    // Warna status sejalan dengan PjctMain::statusBadgeClass(), dalam nada pastel.
    $statuses = [
        ['UPC', 'Upcoming',  $projectKpi['upc'], '#a9cfe0'],
        ['OG',  'On Going',  $projectKpi['og'],  '#8fd9c4'],
        ['HVR', 'Hand Over', $projectKpi['hvr'], '#aeb8fa'],
        ['DLY', 'Delay',     $projectKpi['dly'], '#f6b37f'],
        ['END', 'Ended',     $projectKpi['end'], '#d5d9de'],
    ];
    $monitorUrl = fn (?string $status = null) => $canMonitor ? route('monitoring.index', $status ? ['status' => $status] : []) : null;

    $typeColors = ['RENT' => '#aeb8fa', 'SUPPLY' => '#8fd9c4', 'JASA' => '#f9c9d8'];
    $typeLabels = ['RENT' => 'Rental', 'SUPPLY' => 'Supply', 'JASA' => 'Jasa'];

    $yearMax = max(1, (int) ($byYear->max() ?? 0));
    $areaMax = max(1, (int) ($byArea->max() ?? 0));
@endphp

<div class="-m-4 sm:-m-6 min-h-full bg-white p-4 sm:p-6 lg:p-8">

    {{-- ── Sapaan + aksi ──────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <h2 class="text-4xl leading-tight tracking-tight text-slate-900 sm:text-5xl">
                <span class="font-light">Halo,</span> <span class="font-extrabold">{{ $firstName }}</span>
            </h2>
            <p class="mt-3 text-sm text-slate-500">{{ $user->user_unit ?: "-" }}</p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            @if($canMonitor)
                <a href="{{ route('monitoring.index') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-800 bg-white px-5 py-3 text-sm font-medium text-slate-800 transition hover:bg-slate-50">
                    {!! $ico($path['folder']) !!} Monitoring project
                </a>
            @endif
            @if($canTickets)
                <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-2 rounded-full bg-linear-to-r from-[#fde2ea] to-[#e3e9fb] px-5 py-3 text-sm font-medium text-slate-800 transition hover:brightness-95">
                    {!! $ico($path['ticket']) !!} Tickets
                </a>
            @endif
        </div>
    </div>

    {{-- ── Chip status project ────────────────────────────────────────────── --}}
    <nav class="-mx-1 mt-7 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Project per status">
        <span class="shrink-0 rounded-full bg-[#18191d] px-6 py-3 text-sm font-medium text-white" aria-current="page">Ringkasan</span>
        @foreach($statuses as [$code, $label, $count])
            @php
                $url = $monitorUrl($code);
                $chipCls = $code === 'DLY' && $count > 0 ? 'bg-[#fdebdc] text-[#8a3b06] hover:bg-[#fbdcc3]' : 'bg-slate-100 text-slate-700 hover:bg-slate-200';
            @endphp
            @if($url)
                <a href="{{ $url }}" class="inline-flex shrink-0 items-center gap-2 rounded-full px-6 py-3 text-sm transition {{ $chipCls }}">
                    {{ $label }} <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-900">{{ $count }}</span>
                </a>
            @else
                <span class="inline-flex shrink-0 items-center gap-2 rounded-full px-6 py-3 text-sm {{ $chipCls }}">
                    {{ $label }} <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-900">{{ $count }}</span>
                </span>
            @endif
        @endforeach
    </nav>

    {{-- ── Kartu ringkasan ────────────────────────────────────────────────── --}}
    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_0.85fr]">

        <a @if($canMonitor) href="{{ route('monitoring.index', ['status' => 'OG']) }}" @endif class="block rounded-[28px] bg-[#eef0fd] p-5 transition hover:brightness-[0.98] sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-white text-slate-700">{!! $ico($path['folder']) !!}</span>
                    <span class="text-lg font-semibold text-slate-900 sm:text-xl">Project berjalan</span>
                </div>
                <span class="shrink-0 rounded-full bg-[#18191d] px-3 py-1.5 text-xs font-medium text-white">{{ $pct($running, $total) }}% dari total</span>
            </div>
            <p class="mt-6 flex items-baseline gap-2">
                <span class="text-6xl font-semibold tabular-nums tracking-tight text-slate-900">{{ $running }}</span>
                <span class="text-sm text-slate-500">/ {{ $total }} project · On Going + Delay</span>
            </p>
            <div class="mt-5 grid grid-cols-8 gap-1.5" role="img" aria-label="{{ $running }} dari {{ $total }} project berstatus On Going atau Delay">
                @for($i = 0, $n = $blocks($running, $total); $i < 8; $i++)
                    <span class="h-11 rounded-xl {{ $i < $n ? 'bg-[#aeb8fa]' : 'border border-dashed border-slate-300' }}"></span>
                @endfor
            </div>
        </a>

        <div class="rounded-[28px] bg-[#e7f1f6] p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-white text-slate-700">{!! $ico($path['money']) !!}</span>
                    <span class="text-lg font-semibold text-slate-900 sm:text-xl">Nilai kontrak</span>
                </div>
                <span class="shrink-0 rounded-full bg-[#18191d] px-3 py-1.5 text-xs font-medium text-white">{{ $total }} project</span>
            </div>
            <p class="mt-6 flex items-baseline gap-2" title="{{ $projectKpi['nilai'] > 0 ? 'Rp '.number_format($projectKpi['nilai'], 0, ',', '.') : '' }}">
                @if($valueNum !== '-')<span class="text-2xl font-semibold text-slate-500">Rp</span>@endif
                <span class="text-6xl font-semibold tabular-nums tracking-tight text-slate-900">{{ $valueNum }}</span>
                <span class="text-sm text-slate-500">{{ $valueUnit }}</span>
            </p>
            <dl class="mt-5 grid grid-cols-2 gap-1.5 text-sm">
                <div class="rounded-2xl bg-white/70 px-4 py-2.5"><dt class="text-xs text-slate-500">Total lengkap</dt><dd class="mt-0.5 whitespace-nowrap text-[13px] font-semibold tabular-nums text-slate-900">{{ $projectKpi['nilai'] > 0 ? 'Rp '.number_format($projectKpi['nilai'], 0, ',', '.') : '-' }}</dd></div>
                <div class="rounded-2xl bg-white/70 px-4 py-2.5"><dt class="text-xs text-slate-500">Rata-rata per project</dt><dd class="mt-0.5 font-semibold tabular-nums text-slate-900">{{ $rpShort($total > 0 ? $projectKpi['nilai'] / $total : 0) }}</dd></div>
            </dl>
        </div>

        @php
            if ($canTickets && $needsAction > 0) {
                [$actTitle, $actText, $actLabel, $actUrl] = [$needsAction.' ticket menunggu tindakan Anda', 'Approve, kembalikan eskalasi, atau tindak lanjuti sekarang.', 'Kerjakan sekarang', route('tickets.index', ['needs_action' => 1])];
            } elseif ($projectKpi['dly'] > 0) {
                [$actTitle, $actText, $actLabel, $actUrl] = [$projectKpi['dly'].' project berstatus delay', 'Cek jadwal dan dokumennya sebelum terlambat lebih jauh.', 'Lihat project delay', $monitorUrl('DLY')];
            } else {
                [$actTitle, $actText, $actLabel, $actUrl] = ['Semua project berjalan normal', 'Tidak ada ticket yang menunggu Anda dan tidak ada project delay.', null, null];
            }
        @endphp
        <div class="flex flex-col justify-between gap-6 rounded-[28px] bg-[#18191d] p-6 text-white md:col-span-2 xl:col-span-1">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-2xl font-semibold leading-snug">{{ $actTitle }}</p>
                    <p class="mt-3 text-sm text-white/60">{{ $actText }}</p>
                </div>
                <span class="relative h-10 w-12 shrink-0" aria-hidden="true">
                    <span class="absolute left-0 top-1 h-7 w-7 rounded-full bg-[#f9c9d8]"></span>
                    <span class="absolute left-3 top-0 h-7 w-7 rounded-full bg-[#aeb8fa] mix-blend-screen"></span>
                    <span class="absolute left-5 top-3 h-7 w-7 rounded-full bg-[#9fe0d2] mix-blend-screen"></span>
                </span>
            </div>
            @if($actUrl)
                <a href="{{ $actUrl }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-5 py-3.5 text-sm font-medium text-slate-900 transition hover:bg-slate-100">
                    {{ $actLabel }} {!! $ico($path['play'], 'h-4 w-4') !!}
                </a>
            @endif
        </div>
    </div>

    {{-- ── Project per tahun + status ─────────────────────────────────────── --}}
    <div class="mt-4 grid gap-4 xl:grid-cols-[1.75fr_1fr]">

        <section class="flex min-w-0 flex-col rounded-[28px] bg-[#f5f5f6] p-2">
            <div class="flex flex-wrap items-center gap-2 px-4 pb-3 pt-4">
                <h3 class="mr-2 text-xl font-semibold text-slate-900">Project per tahun kontrak</h3>
                @if($byYear->isNotEmpty())
                    <span class="ml-auto rounded-full bg-white px-3.5 py-1.5 text-sm text-slate-700">Terbanyak {{ $byYear->search($byYear->max()) }} · {{ $byYear->max() }} project</span>
                @endif
            </div>
            <div class="flex flex-1 flex-col justify-center rounded-3xl bg-white px-3 pb-4 pt-5 sm:px-5">
                @if($byYear->isEmpty())
                    <p class="py-16 text-center text-sm text-slate-400">Belum ada tanggal kontrak.</p>
                @else
                    <div class="flex gap-2 sm:gap-3">
                        @foreach($byYear as $year => $count)
                            <div class="flex min-w-0 flex-1 flex-col items-center" aria-label="{{ $year }}: {{ $count }} project">
                                <div class="flex h-72 w-full max-w-20 flex-col justify-end rounded-2xl bg-[repeating-linear-gradient(135deg,#f1f2f4_0_6px,#fafafa_6px_12px)]">
                                    @php $h = max(4, round($count / $yearMax * 100, 2)); @endphp
                                    <div class="relative w-full rounded-2xl bg-[#aeb8fa]" style="height: {{ $h }}%">
                                        {{-- Label di dalam batang; di atasnya kalau batangnya terlalu pendek. --}}
                                        <span class="absolute left-1/2 -translate-x-1/2 rounded-full bg-[#18191d] px-2 py-0.5 text-[11px] font-medium text-white {{ $h < 15 ? '-top-7' : 'top-1.5' }}">{{ $count }}</span>
                                    </div>
                                </div>
                                <span class="mt-2 text-xs text-slate-500">{{ $year }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="min-w-0 rounded-[28px] bg-[#f5f5f6] p-2">
            <div class="flex items-center justify-between gap-2 px-4 pb-3 pt-4">
                <h3 class="text-xl font-semibold text-slate-900">Status project</h3>
                @if($canMonitor)
                    <a href="{{ route('monitoring.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3.5 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100">
                        Semua {!! $ico($path['arrow'], 'h-3.5 w-3.5') !!}
                    </a>
                @endif
            </div>
            <div class="flex flex-col items-center gap-5 rounded-3xl bg-white p-5 sm:flex-row xl:flex-col 2xl:flex-row">
                @php $circ = 2 * M_PI * 38; $offset = 0; @endphp
                <svg class="h-44 w-44 shrink-0" viewBox="0 0 100 100" role="img" aria-label="Status {{ $total }} project">
                    <circle cx="50" cy="50" r="38" fill="none" stroke="#f1f2f4" stroke-width="14"/>
                    @foreach($statuses as [$code, $label, $count, $color])
                        @continue($count === 0)
                        @php $dash = $circ * $count / max($total, 1); @endphp
                        <circle cx="50" cy="50" r="38" fill="none" stroke="{{ $color }}" stroke-width="14"
                            stroke-dasharray="{{ number_format($dash, 2, '.', '') }} {{ number_format($circ - $dash, 2, '.', '') }}"
                            stroke-dashoffset="{{ number_format(-$offset, 2, '.', '') }}" transform="rotate(-90 50 50)"/>
                        @php $offset += $dash; @endphp
                    @endforeach
                    <text x="50" y="50" text-anchor="middle" font-size="18" font-weight="700" fill="#18191d">{{ $total }}</text>
                    <text x="50" y="62" text-anchor="middle" font-size="7" fill="#64748b">project</text>
                </svg>
                <ul class="w-full space-y-1">
                    @foreach($statuses as [$code, $label, $count, $color])
                        <li>
                            <a @if($monitorUrl($code)) href="{{ $monitorUrl($code) }}" @endif class="flex items-center justify-between gap-3 rounded-2xl px-3 py-2 text-sm transition hover:bg-slate-50">
                                <span class="flex items-center gap-2.5 text-slate-700"><span class="h-3 w-3 rounded-full" style="background: {{ $color }}"></span>{{ $label }}</span>
                                <span class="font-semibold tabular-nums text-slate-900">{{ $count }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    </div>

    {{-- ── Project terbaru + ticket & area ────────────────────────────────── --}}
    <div class="mt-4 grid gap-4 xl:grid-cols-[1.75fr_1fr]">

        <section class="min-w-0 rounded-[28px] bg-[#f5f5f6] p-2">
            <div class="flex items-center justify-between gap-2 px-4 pb-3 pt-4">
                <h3 class="text-xl font-semibold text-slate-900">Project terbaru</h3>
                @if($canMonitor)
                    <a href="{{ route('monitoring.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3.5 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100">
                        Semua {!! $ico($path['arrow'], 'h-3.5 w-3.5') !!}
                    </a>
                @endif
            </div>
            <div class="space-y-2">
                @forelse($recentProjects as $p)
                    <div class="flex flex-col gap-2 rounded-3xl bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 font-semibold text-slate-900" title="{{ $p->pjct_name }}">{{ $p->pjct_name }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">
                                {{ $p->pjct_client ?? '-' }}{{ $p->pjct_area ? ' · '.$p->pjct_area : '' }}{{ $p->pjct_contract ? ' · '.$p->pjct_contract : '' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 sm:shrink-0">
                            <span class="badge {{ $p->typeBadgeClass() }}">{{ $p->pjct_type }}</span>
                            <span class="badge {{ $p->statusBadgeClass() }}">{{ $p->statusLabel() }}</span>
                            <span class="w-24 text-right text-sm font-semibold tabular-nums text-slate-800" title="{{ $p->pjct_value ? 'Rp '.number_format($p->pjct_value, 0, ',', '.') : '' }}">{{ $rpShort($p->pjct_value) }}</span>
                        </div>
                    </div>
                @empty
                    <p class="rounded-3xl bg-white py-10 text-center text-sm text-slate-400">Belum ada project.</p>
                @endforelse
            </div>
        </section>

        <div class="flex min-w-0 flex-col gap-4">
            @if($canTickets)
                <section class="rounded-[28px] bg-[#f5f5f6] p-2">
                    <div class="flex items-center justify-between gap-2 px-4 pb-3 pt-4">
                        <h3 class="text-xl font-semibold text-slate-900">Ticket</h3>
                        <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3.5 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100">
                            {{ array_sum($ticketCounts) }} ticket {!! $ico($path['arrow'], 'h-3.5 w-3.5') !!}
                        </a>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach([
                            'open'      => ['Open',      'bg-[#e3eefb] text-sky-700'],
                            'escalated' => ['Escalated', 'bg-[#ece6fd] text-violet-700'],
                            'solved'    => ['Solved',    'bg-[#dff3ea] text-emerald-700'],
                            'closed'    => ['Closed',    'bg-slate-100 text-slate-600'],
                        ] as $status => [$label, $chip])
                            <a href="{{ route('tickets.index', ['status' => $status]) }}" class="flex flex-col items-center justify-center gap-2 rounded-3xl bg-white px-3 py-5 text-center transition hover:shadow-md">
                                <span class="grid h-12 w-12 place-items-center rounded-full {{ $chip }}">{!! $ico($path[$status]) !!}</span>
                                <span class="text-sm font-semibold text-slate-800">{{ $label }}</span>
                                <span class="text-3xl font-semibold leading-none tabular-nums text-slate-900">{{ $ticketCounts[$status] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="rounded-[28px] bg-[#f5f5f6] p-2">
                <h3 class="px-4 pb-3 pt-4 text-xl font-semibold text-slate-900">Project per tipe</h3>
                @php $typeMax = max(1, max($byType)); @endphp
                <div class="space-y-3 rounded-3xl bg-white p-5">
                    @foreach($byType as $type => $count)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-14 shrink-0 font-semibold text-slate-700">{{ $typeLabels[$type] }}</span>
                            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full" style="width: {{ round($count / $typeMax * 100) }}%; background: {{ $typeColors[$type] }}"></div>
                            </div>
                            <span class="w-8 shrink-0 text-right font-semibold tabular-nums text-slate-800">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-[28px] bg-[#f5f5f6] p-2">
                <h3 class="px-4 pb-3 pt-4 text-xl font-semibold text-slate-900">Project per area</h3>
                <div class="space-y-3 rounded-3xl bg-white p-5">
                    @forelse($byArea as $area => $count)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-14 shrink-0 truncate font-semibold text-slate-700" title="{{ $area }}">{{ $area }}</span>
                            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-[#aeb8fa]" style="width: {{ round($count / $areaMax * 100) }}%"></div>
                            </div>
                            <span class="w-8 shrink-0 text-right font-semibold tabular-nums text-slate-800">{{ $count }}</span>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-slate-400">Belum ada data area.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>

@endsection
