<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e($title ?? 'Helpdesk'); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo e(asset('favicon.ico')); ?>">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="min-h-full text-slate-900">
    <div id="app-toast" class="pointer-events-none fixed right-5 top-5 z-50 hidden max-w-sm rounded-2xl px-5 py-4 text-sm font-semibold text-white shadow-[0_18px_45px_rgba(15,23,42,0.18)] ring-1 ring-white/30 transition duration-200 translate-y-3 opacity-0"></div>
    <?php if(auth()->guard()->check()): ?>

        <header class="bg-white border-b border-slate-200">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between gap-4 py-4">
                    {{-- Brand --}}
                    <div class="shrink-0">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 text-2xl font-black tracking-tight text-slate-900 hover:text-blue-600">
                            <svg class="h-7 w-7 flex-shrink-0" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <defs>
                                    <linearGradient id="pln1" x1="0.3" y1="0" x2="0.7" y2="1"><stop offset="0%" stop-color="#93c5fd"/><stop offset="100%" stop-color="#2563eb"/></linearGradient>
                                    <linearGradient id="pln2" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#1d4ed8"/><stop offset="100%" stop-color="#1e40af"/></linearGradient>
                                    <linearGradient id="pln3" x1="1" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1e3a8a"/><stop offset="100%" stop-color="#172554"/></linearGradient>
                                </defs>
                                <ellipse cx="60" cy="102" rx="38" ry="7" fill="#1e3a8a" opacity="0.15"/>
                                <polygon points="60,60 88,45 88,82 60,97" fill="url(#pln3)"/>
                                <polygon points="60,60 32,45 32,82 60,97" fill="url(#pln2)"/>
                                <polygon points="60,60 32,45 88,45" fill="url(#pln1)"/>
                                <line x1="60" y1="60" x2="32" y2="45" stroke="rgba(255,255,255,0.6)" stroke-width="1.2"/>
                                <line x1="60" y1="60" x2="88" y2="45" stroke="rgba(255,255,255,0.3)" stroke-width="1.2"/>
                                <line x1="60" y1="60" x2="60" y2="97" stroke="rgba(255,255,255,0.35)" stroke-width="1.2"/>
                                <line x1="32" y1="45" x2="88" y2="45" stroke="rgba(255,255,255,0.5)" stroke-width="1"/>
                                <circle cx="60" cy="60" r="3" fill="rgba(255,255,255,0.7)"/>
                                <circle cx="32" cy="45" r="2" fill="rgba(255,255,255,0.4)"/>
                                <circle cx="88" cy="45" r="2" fill="rgba(255,255,255,0.25)"/>
                            </svg>
                            PRISM
                        </a>
                        <p class="hidden text-xs text-slate-500 sm:block">Helpdesk Ticket System</p>
                    </div>

                    {{-- Desktop navigation --}}
                    <nav class="hidden items-center gap-0.5 lg:flex" aria-label="Navigasi utama">
                        <a class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" href="{{ route('dashboard') }}">Dashboard</a>
                        <a class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('tickets.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" href="{{ route('tickets.index') }}">Tickets</a>
                        @if(auth()->user()->canViewReports())
                            <a class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('reports.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" href="{{ route('reports.index') }}">Reports</a>
                            <a class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('monitoring.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" href="{{ route('monitoring.index') }}">Monitoring EQT</a>
                        @endif
                        @if(auth()->user()->isAdmin())
                            <a class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('admin.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" href="{{ route('admin.users.index') }}">Admin</a>
                        @endif
                    </nav>

                    {{-- Right: buat tiket + user dropdown + mobile hamburger --}}
                    <div class="flex shrink-0 items-center gap-2">
                        {{-- Buat Tiket button (desktop only) --}}
                        @unless(request()->routeIs('tickets.create'))
                            <a class="hidden lg:inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700" href="{{ route('tickets.create') }}">+ New Ticket</a>
                        @endunless

                        {{-- User dropdown (desktop only) --}}
                        <details class="relative hidden lg:block" data-user-dropdown>
                            <summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-xl bg-slate-100 px-4 py-2 transition hover:bg-slate-200">
                                <div class="text-left">
                                    <p class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                                    <p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::headline(auth()->user()->role) }}</p>
                                </div>
                                <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                                </svg>
                            </summary>
                            <div class="absolute right-0 top-[calc(100%+0.5rem)] z-30 w-56 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-200/80">
                                <div class="px-3 py-2">
                                    <p class="text-xs text-slate-400">Signed in as</p>
                                    <p class="text-sm font-semibold text-slate-700">{{ auth()->user()->email }}</p>
                                </div>
                                <div class="my-1 border-t border-slate-100"></div>
                                <button
                                    class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
                                    type="button"
                                    data-open-dialog="logout-dialog"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1" />
                                    </svg>
                                    Sign Out
                                </button>
                            </div>
                        </details>

                        {{-- Hamburger (mobile only) --}}
                        <button
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700 transition hover:bg-slate-200 lg:hidden"
                            type="button"
                            data-toggle-mobile-menu
                            aria-label="Buka menu navigasi"
                            aria-expanded="false"
                        >
                            <svg class="h-5 w-5" data-hamburger-icon viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg class="hidden h-5 w-5" data-close-icon viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Mobile menu (hidden by default) --}}
                <div class="hidden" data-mobile-menu>
                    <div class="border-t border-slate-200 pb-4 pt-4">
                        <nav class="flex flex-col gap-0.5" aria-label="Navigasi mobile">
                            <a class="flex items-center rounded-xl px-4 py-2.5 text-sm font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" href="{{ route('dashboard') }}">Dashboard</a>
                            <a class="flex items-center rounded-xl px-4 py-2.5 text-sm font-semibold transition {{ request()->routeIs('tickets.*') && !request()->routeIs('tickets.create') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" href="{{ route('tickets.index') }}">Tickets</a>
                            @if(auth()->user()->canViewReports())
                                <a class="flex items-center rounded-xl px-4 py-2.5 text-sm font-semibold transition {{ request()->routeIs('reports.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" href="{{ route('reports.index') }}">Reports</a>
                                <a class="flex items-center rounded-xl px-4 py-2.5 text-sm font-semibold transition {{ request()->routeIs('monitoring.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" href="{{ route('monitoring.index') }}">Monitoring EQT</a>
                            @endif
                            @if(auth()->user()->isAdmin())
                                <a class="flex items-center rounded-xl px-4 py-2.5 text-sm font-semibold transition {{ request()->routeIs('admin.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" href="{{ route('admin.users.index') }}">Admin</a>
                            @endif
                            @unless(request()->routeIs('tickets.create'))
                                <a class="flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700" href="{{ route('tickets.create') }}">+ New Ticket</a>
                            @endunless
                        </nav>
                        <div class="mt-3 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ \Illuminate\Support\Str::headline(auth()->user()->role) }} · {{ auth()->user()->email }}</p>
                            <button
                                class="mt-3 flex items-center gap-2 text-sm font-semibold text-rose-600 transition hover:text-rose-700"
                                type="button"
                                data-open-dialog="logout-dialog"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1" />
                                </svg>
                                Keluar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

            <dialog id="logout-dialog" class="max-w-lg">
                <div class="panel m-0">
                    <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.28em] text-slate-500">Confirm Sign Out</p>
                            <h2 class="mt-2 text-2xl font-black text-slate-900">Are you sure you want to sign out?</h2>
                        </div>
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700" data-close-dialog aria-label="Close dialog">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm leading-7 text-slate-600">Your session will be ended and you will need to log in again to access PRISM.</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button class="btn-soft" type="button" data-close-dialog>Cancel</button>
                        <form method="POST" action="<?php echo e(route('logout')); ?>">
                            <?php echo csrf_field(); ?>
                            <button class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700" type="submit">Yes, sign out</button>
                        </form>
                    </div>
                </div>
            </dialog>

            <div class="mb-6 grid gap-4 lg:grid-cols-[1fr_380px] lg:items-start">
                <div class="lg:pl-2">
                    <h1 class="text-2xl font-black tracking-tight text-slate-900"><?php echo e($heading ?? 'Helpdesk Workspace'); ?></h1>
                    <p class="mt-1 text-sm text-slate-500"><?php echo e(auth()->user()->name); ?> / <?php echo e(\Illuminate\Support\Str::headline(auth()->user()->role)); ?></p>
                </div>
                <div class="space-y-4">
                    <div class="panel p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <p class="text-xs font-bold uppercase tracking-[0.28em] text-slate-500">Recent Notifications</p>
                            <span class="text-xs text-slate-400">Last update</span>
                        </div>
                        <div class="space-y-2" data-notification-list>
                            <?php $__empty_1 = true; $__currentLoopData = auth()->user()->notificationsFeed()->with('ticket')->latest()->limit(5)->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm transition hover:border-blue-200 hover:bg-blue-50" data-notification-item>
                                    <div class="flex items-start justify-between gap-3">
                                        <a href="<?php echo e($notification->ticket ? route('tickets.show', $notification->ticket) : '#'); ?>" class="min-w-0 flex-1">
                                            <p class="font-semibold text-slate-900"><?php echo e($notification->title); ?></p>
                                            <p class="mt-1 text-slate-600"><?php echo e($notification->message); ?></p>
                                            <?php if($notification->ticket): ?>
                                                <p class="mt-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"><?php echo e($notification->ticket->ticket_number); ?></p>
                                            <?php endif; ?>
                                        </a>
                                        <div class="flex items-start gap-2">
                                            <span class="pt-1 text-xs text-slate-400"><?php echo e($notification->created_at->diffForHumans()); ?></span>
                                            <form method="POST" action="<?php echo e(route('notifications.destroy', $notification)); ?>">
                                                <?php echo csrf_field(); ?>
                                                <?php echo method_field('DELETE'); ?>
                                                <button type="submit" class="inline-flex h-6 w-6 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-200 hover:text-slate-700" aria-label="Delete notification">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <p class="text-sm text-slate-500" data-notification-empty>No notifications yet.</p>
                            <?php else: ?>
                                <p class="hidden text-sm text-slate-500" data-notification-empty>No notifications yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php echo $__env->make('partials.flash', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <?php echo e($slot ?? ''); ?>

            <?php echo $__env->yieldContent('content'); ?>
        </div>

        <footer class="mt-12 border-t border-slate-200 bg-white/80">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[1.2fr_1fr_auto] lg:items-start lg:px-8">
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.28em] text-slate-500">Office</p>
                    <p class="mt-2 text-sm leading-7 text-slate-600">PT IAS Support Indonesia<br>Area Perkantoran Gedung 601 Bandara Internasional Soekarno - Hatta, Tangerang 15126<br>Indonesia</p>
                </div>
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.28em] text-slate-500">Social Media</p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <a class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700" href="#" aria-label="LinkedIn">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M6.94 8.5H3.56V20h3.38V8.5Zm.22-3.56c0-1.08-.81-1.94-1.91-1.94-1.09 0-1.91.86-1.91 1.94 0 1.07.81 1.94 1.88 1.94h.03c1.12 0 1.91-.87 1.91-1.94ZM20.44 13.03c0-3.54-1.89-5.19-4.42-5.19-2.04 0-2.95 1.12-3.46 1.91V8.5H9.18c.04.83 0 11.5 0 11.5h3.38v-6.42c0-.34.02-.67.13-.91.27-.67.88-1.36 1.92-1.36 1.35 0 1.89 1.03 1.89 2.54V20h3.38v-6.97Z"/>
                            </svg>
                            <span class="sr-only">LinkedIn</span>
                        </a>
                        <a class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 text-slate-700 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" href="#" aria-label="Instagram">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <rect x="3.5" y="3.5" width="17" height="17" rx="5"></rect>
                                <circle cx="12" cy="12" r="4"></circle>
                                <circle cx="17.5" cy="6.5" r="1"></circle>
                            </svg>
                            <span class="sr-only">Instagram</span>
                        </a>
                        <a class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" href="#" aria-label="YouTube">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M21.58 7.19a2.99 2.99 0 0 0-2.1-2.11C17.62 4.5 12 4.5 12 4.5s-5.62 0-7.48.58a2.99 2.99 0 0 0-2.1 2.11A31.2 31.2 0 0 0 2 12a31.2 31.2 0 0 0 .42 4.81 2.99 2.99 0 0 0 2.1 2.11c1.86.58 7.48.58 7.48.58s5.62 0 7.48-.58a2.99 2.99 0 0 0 2.1-2.11c.28-1.6.42-3.21.42-4.81s-.14-3.21-.42-4.81ZM10 15.5v-7l6 3.5-6 3.5Z"/>
                            </svg>
                            <span class="sr-only">YouTube</span>
                        </a>
                    </div>
                </div>
                <div class="text-sm text-slate-500 lg:pt-[2.2rem] lg:text-right">
                    &copy; <?php echo e(now()->year); ?> PT IAS Support Indonesia. All rights reserved.
                </div>
            </div>
        </footer>
    <?php else: ?>
        <?php echo $__env->yieldContent('content'); ?>
    <?php endif; ?>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>




