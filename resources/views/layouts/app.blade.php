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

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6 rounded-[2rem] border border-slate-200 bg-white px-6 py-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <a href="<?php echo e(route('dashboard')); ?>" class="text-3xl font-black tracking-tight text-slate-900">IASSI TickSys</a>
                        <p class="text-sm text-slate-500">Ticketing Helpdesk for Your Business</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <a class="inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?php echo e(request()->routeIs('dashboard') ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-200'); ?>" href="<?php echo e(route('dashboard')); ?>">Dashboard</a>
                        <a class="inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?php echo e(request()->routeIs('tickets.*') ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-200'); ?>" href="<?php echo e(route('tickets.index')); ?>">Tickets</a>
                        <?php if(auth()->user()->canViewReports()): ?>
                            <a class="inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?php echo e(request()->routeIs('reports.*') ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-200'); ?>" href="<?php echo e(route('reports.index')); ?>">Reports</a>
                        <?php endif; ?>
                        <?php if(auth()->user()->isAdmin()): ?>
                            <a class="inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?php echo e(request()->routeIs('admin.*') ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-200'); ?>" href="<?php echo e(route('admin.users.index')); ?>">Admin</a>
                        <?php endif; ?>
                        <button
                            class="inline-flex items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"
                            type="button"
                            data-open-dialog="logout-dialog"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>

            <dialog id="logout-dialog" class="max-w-lg">
                <div class="panel m-0">
                    <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.28em] text-slate-500">Logout Confirmation</p>
                            <h2 class="mt-2 text-2xl font-black text-slate-900">Sure to logout?</h2>
                        </div>
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700" data-close-dialog aria-label="Close dialog">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm leading-7 text-slate-600">Your session will be ended and you will need to log in again to access IASSI TicketSys.</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button class="btn-soft" type="button" data-close-dialog>Cancel</button>
                        <form method="POST" action="<?php echo e(route('logout')); ?>">
                            <?php echo csrf_field(); ?>
                            <button class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700" type="submit">Yes, logout</button>
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
                            <p class="text-xs font-bold uppercase tracking-[0.28em] text-slate-500">Recent notifications</p>
                            <span class="text-xs text-slate-400">Last updates</span>
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
                                <p class="text-sm text-slate-500" data-notification-empty>Belum ada notifikasi.</p>
                            <?php else: ?>
                                <p class="hidden text-sm text-slate-500" data-notification-empty>Belum ada notifikasi.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if(request()->routeIs('tickets.index')): ?>
                        <a class="btn-primary w-full" href="<?php echo e(route('tickets.create')); ?>">Create Ticket</a>
                    <?php endif; ?>
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
</body>
</html>




