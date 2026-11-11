@extends('layouts.app', ['title' => 'Dashboard', 'heading' => 'Dashboard'])

{{-- Dashboard teknisi (role user): aset & issue di project penugasannya (pjct_emp).
     Gaya: kartu pastel, tombol & tab berbentuk pill, kolom bertumpuk membulat. --}}
@section('content')
@php
    $ico = fn (string $d, string $cls = 'h-5 w-5') => '<svg class="'.$cls.'" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.$d.'"/></svg>';
    $path = [
        'device'     => 'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25',
        'ticket'     => 'M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z',
        'cube'       => 'm21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
        'plus'       => 'M12 4.5v15m7.5-7.5h-15',
        'play'       => 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z',
        'arrow'      => 'M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3',
        'Excellence' => 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z',
        'Good'       => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'Fair'       => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        'Bad'        => 'm9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    ];

    // Meter 8 blok: berapa blok terisi untuk part/whole.
    $blocks = fn (int $part, int $whole) => $whole > 0 ? (int) round($part / $whole * 8) : 0;
    $pct    = fn (int $part, int $whole) => $whole > 0 ? (int) round($part / $whole * 100) : 0;

    $healthy     = $assetCond['Excellence'] + $assetCond['Good'] + $assetCond['Fair'];
    $ticketTotal = array_sum($ticketCounts);
    $firstName   = strtok(trim($user->user_name), ' ') ?: $user->user_name;

    // Grafik: skala sumbu-y dibulatkan ke kelipatan 4 supaya 5 tick-nya bilangan bulat.
    $weekMax = collect($ticketWeeks)->map(fn ($w) => $w['berjalan'] + $w['closed'])->max() ?? 0;
    $yMax    = max(4, (int) ceil($weekMax / 4) * 4);
@endphp

<div class="-m-6 min-h-full bg-white p-4 sm:p-6 lg:p-8">

    {{-- ── Judul + aksi ───────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <h2 class="text-4xl leading-tight tracking-tight text-slate-900 sm:text-5xl">
                <span class="font-light">Halo,</span> <span class="font-extrabold">{{ $firstName }}</span>
            </h2>
            @if($projects->isEmpty())
                <p class="mt-3 max-w-xl rounded-2xl bg-amber-50 px-4 py-2.5 text-sm text-amber-800">
                    Anda belum ditugaskan di project mana pun. Minta admin mendaftarkan NIK Anda
                    ({{ $user->user_empid ?: '-' }}) di data Manpower project terkait.
                </p>
            @else
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
                    @foreach($projects as $project)
                        <span><span class="font-semibold text-slate-700">{{ $project->pjct_name }}</span> · {{ $project->pjct_client ?? '-' }}{{ $project->pjct_area ? ' · '.$project->pjct_area : '' }}</span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <a href="{{ route('project.assets') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-800 bg-white px-5 py-3 text-sm font-medium text-slate-800 transition hover:bg-slate-50">
                {!! $ico($path['cube']) !!} Asset saya
            </a>
            <a href="{{ route('tickets.create') }}" class="inline-flex items-center gap-2 rounded-full bg-linear-to-r from-[#fde2ea] to-[#e3e9fb] px-5 py-3 text-sm font-medium text-slate-800 transition hover:brightness-95">
                {!! $ico($path['plus']) !!} Buat Ticket
            </a>
        </div>
    </div>

    {{-- ── Tab status ticket ──────────────────────────────────────────────── --}}
    <nav class="-mx-1 mt-7 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Ticket per status">
        <span class="shrink-0 rounded-full bg-[#18191d] px-6 py-3 text-sm font-medium text-white" aria-current="page">Ringkasan</span>
        @foreach([
            ['Open',              'open'],
            ['Escalated',         'escalated'],
            ['Menunggu approval', 'solved'],
            ['Closed',            'closed'],
        ] as [$label, $status])
            <a href="{{ route('tickets.index', ['status' => $status]) }}" class="inline-flex shrink-0 items-center gap-2 rounded-full bg-slate-100 px-6 py-3 text-sm text-slate-700 transition hover:bg-slate-200">
                {{ $label }}
                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-900">{{ $ticketCounts[$status] }}</span>
            </a>
        @endforeach
        <a href="{{ route('tickets.index') }}" class="shrink-0 rounded-full bg-slate-100 px-6 py-3 text-sm text-slate-700 transition hover:bg-slate-200">Semua ticket</a>
    </nav>

    {{-- ── Kartu ringkasan ────────────────────────────────────────────────── --}}
    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_0.85fr]">

        <a href="{{ route('project.assets') }}" class="block rounded-[28px] bg-[#eef0fd] p-5 transition hover:brightness-[0.98] sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-white text-slate-700">{!! $ico($path['device']) !!}</span>
                    <span class="text-lg font-semibold text-slate-900 sm:text-xl">Device sehat</span>
                </div>
                <span class="shrink-0 rounded-full bg-[#18191d] px-3 py-1.5 text-xs font-medium text-white">{{ $pct($healthy, $assetTotal) }}% baik</span>
            </div>
            <p class="mt-6 flex items-baseline gap-2">
                <span class="text-6xl font-semibold tracking-tight text-slate-900">{{ $healthy }}</span>
                <span class="text-sm text-slate-500">/ {{ $assetTotal }} device</span>
            </p>
            <div class="mt-5 grid grid-cols-8 gap-1.5" role="img" aria-label="{{ $healthy }} dari {{ $assetTotal }} device berkondisi Excellence, Good, atau Fair">
                @for($i = 0, $n = $blocks($healthy, $assetTotal); $i < 8; $i++)
                    <span class="h-11 rounded-xl {{ $i < $n ? 'bg-[#aeb8fa]' : 'border border-dashed border-slate-300' }}"></span>
                @endfor
            </div>
        </a>

        <a href="{{ route('tickets.index', ['status' => 'closed']) }}" class="block rounded-[28px] bg-[#e7f1f6] p-5 transition hover:brightness-[0.98] sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-white text-slate-700">{!! $ico($path['ticket']) !!}</span>
                    <span class="text-lg font-semibold text-slate-900 sm:text-xl">Ticket closed</span>
                </div>
                <span class="shrink-0 rounded-full bg-[#18191d] px-3 py-1.5 text-xs font-medium text-white">{{ $pct($ticketCounts['closed'], $ticketTotal) }}% selesai</span>
            </div>
            <p class="mt-6 flex items-baseline gap-2">
                <span class="text-6xl font-semibold tracking-tight text-slate-900">{{ $ticketCounts['closed'] }}</span>
                <span class="text-sm text-slate-500">/ {{ $ticketTotal }} ticket</span>
            </p>
            <div class="mt-5 grid grid-cols-8 gap-1.5" role="img" aria-label="{{ $ticketCounts['closed'] }} dari {{ $ticketTotal }} ticket sudah closed">
                @for($i = 0, $n = $blocks($ticketCounts['closed'], $ticketTotal); $i < 8; $i++)
                    <span class="h-11 rounded-xl {{ $i < $n ? 'bg-[#c3d3db]' : 'border border-dashed border-slate-300' }}"></span>
                @endfor
            </div>
        </a>

        <div class="flex flex-col justify-between gap-6 rounded-[28px] bg-[#18191d] p-6 text-white md:col-span-2 xl:col-span-1">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-2xl font-semibold leading-snug">
                        @if($needsAction > 0)
                            {{ $needsAction }} ticket menunggu tindakan Anda
                        @else
                            Tidak ada ticket yang menunggu Anda
                        @endif
                    </p>
                    <p class="mt-3 text-sm text-white/60">
                        {{ $needsAction > 0 ? 'Selesaikan, eskalasi, atau lengkapi dokumennya sekarang.' : 'Ada device rusak di lapangan? Laporkan lewat ticket.' }}
                    </p>
                </div>
                <span class="relative h-10 w-12 shrink-0" aria-hidden="true">
                    <span class="absolute left-0 top-1 h-7 w-7 rounded-full bg-[#f9c9d8]"></span>
                    <span class="absolute left-3 top-0 h-7 w-7 rounded-full bg-[#aeb8fa] mix-blend-screen"></span>
                    <span class="absolute left-5 top-3 h-7 w-7 rounded-full bg-[#9fe0d2] mix-blend-screen"></span>
                </span>
            </div>
            <a href="{{ $needsAction > 0 ? route('tickets.index', ['needs_action' => 1]) : route('tickets.create') }}"
               class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-5 py-3.5 text-sm font-medium text-slate-900 transition hover:bg-slate-100">
                {{ $needsAction > 0 ? 'Kerjakan sekarang' : 'Buat Ticket' }} {!! $ico($path['play'], 'h-4 w-4') !!}
            </a>
        </div>
    </div>

    {{-- ── Statistik ticket + kondisi asset ───────────────────────────────── --}}
    <div class="mt-4 grid gap-4 xl:grid-cols-[1.75fr_1fr]">

        <section class="flex min-w-0 flex-col rounded-[28px] bg-[#f5f5f6] p-2">
            <div class="flex flex-wrap items-center gap-2 px-4 pb-3 pt-4">
                <h3 class="mr-2 text-xl font-semibold text-slate-900">Statistik ticket</h3>
                <span class="inline-flex items-center gap-2 rounded-full bg-white px-3.5 py-1.5 text-sm text-slate-700"><span class="h-2 w-2 rounded-full bg-[#9fb6c2]"></span>Closed</span>
                <span class="inline-flex items-center gap-2 rounded-full bg-white px-3.5 py-1.5 text-sm text-slate-700"><span class="h-2 w-2 rounded-full bg-[#f5a9c0]"></span>Berjalan</span>
                <span class="ml-auto rounded-full bg-white px-3.5 py-1.5 text-sm text-slate-700">8 minggu terakhir</span>
            </div>
            <div class="flex flex-1 flex-col justify-center rounded-3xl bg-white px-3 pb-4 pt-5 sm:px-5">
                <div class="flex gap-2 sm:gap-3">
                    <div class="flex h-56 shrink-0 flex-col justify-between text-right text-[11px] tabular-nums leading-none text-slate-400" aria-hidden="true">
                        @foreach([$yMax, $yMax * 3 / 4, $yMax / 2, $yMax / 4, 0] as $tick)
                            <span>{{ $tick }}</span>
                        @endforeach
                    </div>
                    <div class="grid min-w-0 flex-1 grid-cols-8 gap-1.5 sm:gap-3">
                        @foreach($ticketWeeks as $week)
                            @php $label = $week['start']->format('j M'); @endphp
                            <div class="group flex min-w-0 flex-col items-center outline-none" tabindex="0"
                                 aria-label="Minggu {{ $label }}: {{ $week['berjalan'] }} berjalan, {{ $week['closed'] }} closed">
                                <div class="flex h-56 w-full max-w-16 flex-col justify-end gap-0.5 rounded-2xl bg-[repeating-linear-gradient(135deg,#f1f2f4_0_6px,#fafafa_6px_12px)]">
                                    @foreach([['berjalan', 'bg-[#fbd3df]'], ['closed', 'bg-[#c3d3db]']] as [$key, $fill])
                                        @continue($week[$key] === 0)
                                        <div class="relative w-full rounded-2xl {{ $fill }}" style="height: {{ round($week[$key] / $yMax * 100, 2) }}%">
                                            <span class="pointer-events-none absolute left-1/2 top-1 z-10 -translate-x-1/2 rounded-full bg-[#18191d] px-2 py-0.5 text-[11px] font-medium text-white transition {{ $loop->parent->last ? '' : 'opacity-0 group-hover:opacity-100 group-focus:opacity-100' }}">{{ $week[$key] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                <span class="mt-2 whitespace-nowrap text-[11px] text-slate-400" aria-hidden="true">
                                    <span class="sm:hidden">{{ $week['start']->format('j/n') }}</span><span class="hidden sm:inline">{{ $label }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="min-w-0 rounded-[28px] bg-[#f5f5f6] p-2">
            <div class="flex items-center justify-between gap-2 px-4 pb-3 pt-4">
                <h3 class="text-xl font-semibold text-slate-900">Kondisi asset</h3>
                <a href="{{ route('project.assets') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3.5 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100">
                    {{ $assetTotal }} device {!! $ico($path['arrow'], 'h-3.5 w-3.5') !!}
                </a>
            </div>
            <div class="grid grid-cols-2 gap-2">
                @foreach([
                    'Excellence' => 'bg-[#dff3ea] text-emerald-700',
                    'Good'       => 'bg-[#e3eefb] text-sky-700',
                    'Fair'       => 'bg-[#fcefd6] text-amber-700',
                    'Bad'        => 'bg-[#fde2ea] text-rose-700',
                ] as $cond => $chip)
                    <a href="{{ route('project.assets', ['cond' => $cond]) }}" class="flex flex-col items-center justify-center gap-2 rounded-3xl bg-white px-3 py-6 text-center transition hover:shadow-md">
                        <span class="grid h-14 w-14 place-items-center rounded-full {{ $chip }}">{!! $ico($path[$cond]) !!}</span>
                        <span class="text-sm font-semibold text-slate-800">{{ $cond }}</span>
                        <span class="text-3xl font-semibold leading-none text-slate-900">{{ $assetCond[$cond] }}</span>
                    </a>
                @endforeach
            </div>
            @if($assetCond['Lainnya'] > 0)
                <p class="px-4 pb-1 pt-3 text-xs text-slate-500">+ {{ $assetCond['Lainnya'] }} device tanpa data kondisi.</p>
            @endif
        </section>
    </div>

    {{-- ── Ticket berjalan + device bermasalah ────────────────────────────── --}}
    <div class="mt-4 grid gap-4 xl:grid-cols-[1.75fr_1fr]">

        <section class="min-w-0 rounded-[28px] bg-[#f5f5f6] p-2">
            <div class="flex items-center justify-between gap-2 px-4 pb-3 pt-4">
                <h3 class="text-xl font-semibold text-slate-900">Ticket berjalan</h3>
                <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3.5 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100">
                    Semua {!! $ico($path['arrow'], 'h-3.5 w-3.5') !!}
                </a>
            </div>
            <div class="space-y-2">
                @forelse($activeTickets as $ticket)
                    <a href="{{ route('tickets.show', $ticket) }}" class="flex flex-col gap-2 rounded-3xl bg-white px-5 py-4 transition hover:shadow-md sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-medium uppercase tracking-[0.18em] text-slate-400">{{ $ticket->ticket_number }}</p>
                            <p class="mt-0.5 font-semibold text-slate-900 break-words">{{ $ticket->subject }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 break-words">
                                {{ $ticket->project?->pjct_name ?? '-' }} · {{ $ticket->issueTypeLabel() }}
                                @if($ticket->assets->isNotEmpty()) · {{ $ticket->assets->count() }} device @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 sm:shrink-0">
                            <span class="badge {{ $ticket->statusBadgeClass() }}">{{ $ticket->statusLabel() }}</span>
                            @if($ticket->slaBadgeLabel())
                                <span class="badge {{ $ticket->slaBadgeClass() }}"
                                    @if($ticket->isSlaCountdownLive()) data-sla-due="{{ $ticket->resolution_due_at->toIso8601String() }}" @endif
                                >{{ $ticket->slaBadgeLabel() }}</span>
                            @endif
                        </div>
                    </a>
                @empty
                    <p class="rounded-3xl bg-white py-10 text-center text-sm text-slate-400">Tidak ada ticket berjalan.</p>
                @endforelse
            </div>
        </section>

        <div class="flex min-w-0 flex-col gap-4">
            <section class="rounded-[28px] bg-[#f5f5f6] p-2">
                <div class="flex items-center justify-between gap-2 px-4 pb-3 pt-4">
                    <h3 class="text-xl font-semibold text-slate-900">Device kondisi Bad</h3>
                    @if($assetCond['Bad'] > count($badAssets))
                        <a href="{{ route('project.assets', ['cond' => 'Bad']) }}" class="rounded-full bg-white px-3.5 py-1.5 text-sm text-slate-700 transition hover:bg-slate-100">Semua ({{ $assetCond['Bad'] }})</a>
                    @endif
                </div>
                <div class="space-y-2">
                    @forelse($badAssets as $asset)
                        <div class="flex items-center gap-3 rounded-3xl bg-white px-4 py-3 text-sm">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#fde2ea] text-rose-700">{!! $ico($path['device'], 'h-4 w-4') !!}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-slate-800 break-words">{{ trim($asset->ast_type.' — '.$asset->ast_brand.' '.$asset->ast_brandmodel) }}</p>
                                <p class="text-xs text-slate-500 break-words">{{ $asset->id }} · SN {{ $asset->ast_serial ?: '-' }} · {{ $asset->ast_userloc ?: '-' }}</p>
                            </div>
                            @if($asset->ast_last_ticket_id)
                                <a href="{{ route('tickets.show', $asset->ast_last_ticket_id) }}" class="shrink-0 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200">Ticket</a>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-3xl bg-white py-8 text-center text-sm text-slate-400">Tidak ada device berkondisi Bad.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-[28px] bg-[#f5f5f6] p-2">
                <h3 class="px-4 pb-3 pt-4 text-xl font-semibold text-slate-900">Asset per tipe</h3>
                <div class="rounded-3xl bg-white p-5">
                    <div class="mb-4 flex flex-wrap gap-1.5">
                        @foreach($assetStat as $label => $count)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">{{ $label }} <span class="font-semibold text-slate-900">{{ $count }}</span></span>
                        @endforeach
                    </div>
                    @php $typeMax = max($assetTypes->max() ?? 0, 1); @endphp
                    <div class="space-y-3">
                        @forelse($assetTypes as $type => $count)
                            <div class="flex items-center gap-3 text-xs">
                                <span class="w-24 shrink-0 truncate font-medium text-slate-600" title="{{ $type }}">{{ $type ?: '-' }}</span>
                                <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-[#aeb8fa]" style="width: {{ round(($count / $typeMax) * 100) }}%"></div>
                                </div>
                                <span class="w-6 shrink-0 text-right font-semibold text-slate-800">{{ $count }}</span>
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-slate-400">Belum ada asset di project penugasan.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

@endsection
