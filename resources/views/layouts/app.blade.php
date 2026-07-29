<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'PRISM' }} — PRISM</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        #sidebar { transition: width 0.2s ease; }
        #main-wrapper { transition: margin-left 0.2s ease; }
        #sidebar.collapsed .sidebar-label { display: none; }
        #sidebar.collapsed .nav-chevron { display: none; }
        #sidebar.collapsed .nav-sub { display: none !important; }
        #sidebar.collapsed #sidebar-logo-link { display: none; }
        #sidebar.collapsed #sidebar-header { justify-content: center; padding-left: 0; padding-right: 0; }
        .nav-item {
            display: flex; align-items: center; gap: 0.625rem;
            padding: 0.5rem 0.625rem; border-radius: 0.625rem;
            font-size: 0.8125rem; font-weight: 600; color: #475569;
            transition: background 0.15s, color 0.15s; cursor: pointer;
            text-decoration: none; width: 100%;
        }
        .nav-item:hover { background: #f1f5f9; color: #0f172a; }
        .nav-item.active { background: #f0fdfd; color: #0d9191; }
        .nav-subitem {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.375rem 0.625rem 0.375rem 2.25rem;
            border-radius: 0.5rem; font-size: 0.75rem; font-weight: 500;
            color: #64748b; text-decoration: none; transition: background 0.15s, color 0.15s;
        }
        .nav-subitem:hover { background: #f8fafc; color: #0f172a; }
        .nav-subitem.active { color: #0d9191; font-weight: 700; background: #f0fdfd; }
        .nav-icon { width: 1.125rem; height: 1.125rem; flex-shrink: 0; }
    </style>
</head>
<body class="h-full bg-slate-100 text-slate-900">

<div id="app-toast" class="pointer-events-none fixed right-5 top-5 z-50 hidden max-w-sm rounded-2xl px-5 py-4 text-sm font-semibold text-white shadow-xl transition duration-200 translate-y-3 opacity-0"></div>

@auth

<div class="flex h-full">

    {{-- ══════════════════════════════════════════════════════
         SIDEBAR
    ══════════════════════════════════════════════════════ --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-60 flex-col bg-white border-r border-slate-200 shadow-sm overflow-hidden">

        {{-- Logo + Toggle --}}
        <div id="sidebar-header" class="flex h-16 shrink-0 items-center justify-between px-4 border-b border-slate-100">
            <a id="sidebar-logo-link" href="{{ route('dashboard') }}" class="flex items-center gap-2.5 min-w-0">
                <svg class="h-7 w-7 shrink-0" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="sg1" x1="0.3" y1="0" x2="0.7" y2="1"><stop offset="0%" stop-color="#7de8e8"/><stop offset="100%" stop-color="#1ab5b5"/></linearGradient>
                        <linearGradient id="sg2" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#0d9191"/><stop offset="100%" stop-color="#0a7a7a"/></linearGradient>
                        <linearGradient id="sg3" x1="1" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#0a6060"/><stop offset="100%" stop-color="#074747"/></linearGradient>
                    </defs>
                    <polygon points="60,60 88,45 88,82 60,97" fill="url(#sg3)"/>
                    <polygon points="60,60 32,45 32,82 60,97" fill="url(#sg2)"/>
                    <polygon points="60,60 32,45 88,45" fill="url(#sg1)"/>
                    <line x1="60" y1="60" x2="32" y2="45" stroke="rgba(255,255,255,0.6)" stroke-width="1.2"/>
                    <line x1="60" y1="60" x2="88" y2="45" stroke="rgba(255,255,255,0.3)" stroke-width="1.2"/>
                    <line x1="60" y1="60" x2="60" y2="97" stroke="rgba(255,255,255,0.35)" stroke-width="1.2"/>
                    <circle cx="60" cy="60" r="3" fill="rgba(255,255,255,0.7)"/>
                </svg>
                <span class="sidebar-label text-lg font-black tracking-tight text-slate-900">PRISM</span>
            </a>
            <button id="sidebar-toggle" title="Toggle sidebar" class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition shrink-0">
                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
                </svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 px-2 space-y-0.5">

            {{-- Dashboard --}}
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 0113.5 8.25V6zm0 9.75a1.125 1.125 0 011.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V18a1.125 1.125 0 01-1.125 1.125h-2.25A1.125 1.125 0 0113.5 18v-2.25z"/>
                </svg>
                <span class="sidebar-label">Dashboard</span>
            </a>

            {{-- PROJECT group (all roles) --}}
            @php $projectActive = request()->routeIs('monitoring.*','project.*'); @endphp
            <div data-nav-group>
                <button type="button" data-group-trigger class="nav-item {{ $projectActive ? 'active' : '' }} w-full text-left">
                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                    </svg>
                    <span class="sidebar-label flex-1">Project</span>
                    <svg class="nav-chevron sidebar-label nav-icon transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>
                <div data-nav-sub class="{{ $projectActive ? '' : 'hidden' }} space-y-0.5 mt-0.5">
                    <a href="{{ route('monitoring.index') }}" class="nav-subitem {{ request()->routeIs('monitoring.*') ? 'active' : '' }}">Main</a>
                    <a href="{{ route('project.assets') }}" class="nav-subitem {{ request()->routeIs('project.assets') ? 'active' : '' }}">Asset</a>
                    <a href="{{ route('project.manpower') }}" class="nav-subitem {{ request()->routeIs('project.manpower') ? 'active' : '' }}">Manpower</a>
                </div>
            </div>

            {{-- VENDOR group (excludes User tier) --}}
            @php $vendorActive = request()->routeIs('vendor.*'); @endphp
            @if(!auth()->user()->isUser())
            <div data-nav-group>
                <button type="button" data-group-trigger class="nav-item {{ $vendorActive ? 'active' : '' }} w-full text-left">
                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                    </svg>
                    <span class="sidebar-label flex-1">Vendor</span>
                    <svg class="nav-chevron sidebar-label nav-icon transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>
                <div data-nav-sub class="{{ $vendorActive ? '' : 'hidden' }} space-y-0.5 mt-0.5">
                    <a href="{{ route('vendor.main') }}" class="nav-subitem {{ request()->routeIs('vendor.main') ? 'active' : '' }}">Main</a>
                    <a href="{{ route('vendor.contracts') }}" class="nav-subitem {{ request()->routeIs('vendor.contracts') ? 'active' : '' }}">Contract</a>
                </div>
            </div>
            @endif

            {{-- REPORT group (Issues: all roles; Expenses: excludes User tier) --}}
            @php $reportActive = request()->routeIs('report.*','reports.*'); @endphp
            <div data-nav-group>
                <button type="button" data-group-trigger class="nav-item {{ $reportActive ? 'active' : '' }} w-full text-left">
                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zm6.75-6c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v12.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V7.125zm6.75 3.75c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v9a1.125 1.125 0 01-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125v-9z"/>
                    </svg>
                    <span class="sidebar-label flex-1">Report</span>
                    <svg class="nav-chevron sidebar-label nav-icon transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>
                <div data-nav-sub class="{{ $reportActive ? '' : 'hidden' }} space-y-0.5 mt-0.5">
                    <a href="{{ route('report.issues') }}" class="nav-subitem {{ request()->routeIs('report.issues') ? 'active' : '' }}">Issues</a>
                    @if(!auth()->user()->isUser())
                    <a href="{{ route('report.expenses') }}" class="nav-subitem {{ request()->routeIs('report.expenses') ? 'active' : '' }}">Expenses</a>
                    @endif
                </div>
            </div>

            {{-- TICKETS --}}
            <a href="{{ route('tickets.index') }}" class="nav-item {{ request()->routeIs('tickets.*') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                </svg>
                <span class="sidebar-label">Tickets</span>
            </a>

            {{-- ADMIN (Super Admin, VIP only — plain Admin has no accessible admin page) --}}
            @if(auth()->user()->isSuperAdmin() || auth()->user()->isVip())
            @php $adminActive = request()->routeIs('admin.*'); @endphp
            <div data-nav-group>
                <button type="button" data-group-trigger class="nav-item {{ $adminActive ? 'active' : '' }} w-full text-left">
                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="sidebar-label flex-1">Admin</span>
                    <svg class="nav-chevron sidebar-label nav-icon transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>
                <div data-nav-sub class="{{ $adminActive ? '' : 'hidden' }} space-y-0.5 mt-0.5">
                    <a href="{{ route('admin.users.index') }}" class="nav-subitem {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">IAM Role</a>
                </div>
            </div>
            @endif

        </nav>

        {{-- User Footer --}}
        <div class="shrink-0 border-t border-slate-100 p-3">
            <div class="flex items-center gap-2.5">
                <button type="button" id="sidebar-avatar-btn" title="Expand sidebar"
                    class="h-8 w-8 rounded-full bg-brand-600 flex items-center justify-center text-white text-xs font-black shrink-0 uppercase">
                    {{ substr(auth()->user()->user_name, 0, 1) }}
                </button>
                <div class="sidebar-label min-w-0 flex-1">
                    <p class="text-xs font-semibold text-slate-900 truncate">{{ auth()->user()->user_name }}</p>
                    <p class="text-[10px] text-slate-400">{{ auth()->user()->user_div }}</p>
                </div>
                <button type="button" data-open-dialog="logout-dialog" title="Sign out"
                    class="sidebar-label h-7 w-7 rounded-lg flex items-center justify-center text-slate-400 hover:bg-red-50 hover:text-red-500 transition shrink-0">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </button>
            </div>
        </div>
    </aside>

    {{-- ══════════════════════════════════════════════════════
         MAIN WRAPPER
    ══════════════════════════════════════════════════════ --}}
    <div id="main-wrapper" class="flex min-h-full flex-col ml-60 w-[calc(100vw-15rem)]">

        {{-- Top Bar --}}
        <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-4 border-b border-slate-200 bg-white px-6 shadow-sm">
            <div class="flex-1 min-w-0">
                <h1 class="text-base font-black text-slate-900 truncate">{{ $heading ?? 'Dashboard' }}</h1>
                <p class="text-xs text-slate-400 hidden sm:block">{{ auth()->user()->user_name }} · {{ auth()->user()->user_div }}</p>
            </div>

            {{-- Notifications bell --}}
            <div class="relative" data-user-dropdown>
                <button type="button" data-notif-toggle class="relative h-9 w-9 rounded-xl flex items-center justify-center text-slate-500 hover:bg-slate-100 transition">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                    </svg>
                    @php $unreadCount = auth()->user()->notificationsFeed()->latest()->limit(5)->count(); @endphp
                    @if($unreadCount > 0)
                        <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500"></span>
                    @endif
                </button>
                {{-- Notification dropdown --}}
                <div data-notif-panel class="hidden absolute right-0 top-[calc(100%+0.5rem)] z-50 w-80 rounded-2xl border border-slate-200 bg-white shadow-xl p-3">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2 px-1">Notifications</p>
                    <div class="space-y-1.5">
                        @forelse(auth()->user()->notificationsFeed()->latest()->limit(5)->get() as $notification)
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5 text-xs hover:border-brand-200 hover:bg-brand-50 transition">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $notification->ticket ? route('tickets.show', $notification->ticket) : '#' }}" class="font-semibold text-slate-800 block truncate">{{ $notification->title }}</a>
                                        <p class="text-slate-500 mt-0.5 line-clamp-2">{{ $notification->message }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('notifications.destroy', $notification) }}" class="shrink-0">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-slate-300 hover:text-slate-500">✕</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 px-1">No notifications.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </header>

        {{-- Page Content --}}
        <main class="flex-1 p-6">
            @include('partials.flash')
            @yield('content')
            {{ $slot ?? '' }}
        </main>

    </div>{{-- /main-wrapper --}}

</div>{{-- /flex --}}

{{-- Logout Dialog --}}
<dialog id="logout-dialog" class="max-w-lg">
    <div class="panel m-0">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Confirm Sign Out</p>
                <h2 class="mt-2 text-2xl font-black text-slate-900">Sign out of PRISM?</h2>
            </div>
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 text-slate-500 hover:bg-slate-200" data-close-dialog>✕</button>
        </div>
        <p class="text-sm text-slate-600">Your session will end and you'll need to log in again.</p>
        <div class="mt-6 flex justify-end gap-3">
            <button class="btn-soft" type="button" data-close-dialog>Cancel</button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 transition" type="submit">Sign out</button>
            </form>
        </div>
    </div>
</dialog>

<script>
(function () {
    const sidebar     = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('main-wrapper');
    const KEY         = 'prism-sb-collapsed';

    function apply(collapsed) {
        if (collapsed) {
            sidebar.classList.add('collapsed');
            sidebar.style.width = '4rem';
            mainWrapper.style.marginLeft = '4rem';
            mainWrapper.style.width = 'calc(100vw - 4rem)';
            sidebar.querySelectorAll('[data-nav-sub]').forEach(function (sub) {
                sub.classList.add('hidden');
            });
            sidebar.querySelectorAll('.nav-chevron').forEach(function (arrow) {
                arrow.style.transform = '';
            });
        } else {
            sidebar.classList.remove('collapsed');
            sidebar.style.width = '15rem';
            mainWrapper.style.marginLeft = '15rem';
            mainWrapper.style.width = 'calc(100vw - 15rem)';
        }
    }

    // Init
    apply(localStorage.getItem(KEY) === '1');

    // Set rotasi chevron awal berdasarkan state submenu (bukan CSS class)
    document.querySelectorAll('[data-nav-group]').forEach(function (group) {
        const sub   = group.querySelector('[data-nav-sub]');
        const arrow = group.querySelector('.nav-chevron');
        if (arrow && sub && !sub.classList.contains('hidden')) {
            arrow.style.transform = 'rotate(90deg)';
        }
    });

    document.getElementById('sidebar-toggle')?.addEventListener('click', function () {
        const next = !sidebar.classList.contains('collapsed');
        apply(next);
        localStorage.setItem(KEY, next ? '1' : '0');
    });

    document.getElementById('sidebar-avatar-btn')?.addEventListener('click', function () {
        if (sidebar.classList.contains('collapsed')) {
            apply(false);
            localStorage.setItem(KEY, '0');
        }
    });

    // Submenu accordion — if sidebar collapsed, expand first then open submenu
    document.querySelectorAll('[data-group-trigger]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (sidebar.classList.contains('collapsed')) {
                apply(false);
                localStorage.setItem(KEY, '0');
                const group = this.closest('[data-nav-group]');
                const sub   = group.querySelector('[data-nav-sub]');
                const arrow = this.querySelector('.nav-chevron');
                sub.classList.remove('hidden');
                if (arrow) arrow.style.transform = 'rotate(90deg)';
                return;
            }
            const group  = this.closest('[data-nav-group]');
            const sub    = group.querySelector('[data-nav-sub]');
            const arrow  = this.querySelector('.nav-chevron');
            const hidden = sub.classList.toggle('hidden');
            if (arrow) arrow.style.transform = hidden ? '' : 'rotate(90deg)';
        });
    });

    // Notification panel toggle
    const notifToggle = document.querySelector('[data-notif-toggle]');
    const notifPanel  = document.querySelector('[data-notif-panel]');
    notifToggle?.addEventListener('click', function (e) {
        e.stopPropagation();
        notifPanel.classList.toggle('hidden');
    });
    document.addEventListener('click', function () {
        notifPanel?.classList.add('hidden');
    });

    // Dialog helpers
    document.querySelectorAll('[data-open-dialog]').forEach(function (el) {
        el.addEventListener('click', function () {
            const id = this.getAttribute('data-open-dialog');
            document.getElementById(id)?.showModal();
        });
    });
    document.querySelectorAll('[data-close-dialog]').forEach(function (el) {
        el.addEventListener('click', function () {
            this.closest('dialog')?.close();
        });
    });
})();
</script>

@else
    {{-- Guest: just render content --}}
    @yield('content')
    {{ $slot ?? '' }}
@endauth

@stack('scripts')
</body>
</html>
