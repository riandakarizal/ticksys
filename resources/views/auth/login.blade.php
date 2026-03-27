@extends('layouts.app', ['title' => 'Login'])

@section('content')
@php
    $shellHeightClass = $errors->any() || session('success')
        ? 'md:min-h-[40rem]'
        : 'md:min-h-[34rem]';
@endphp
<div class="relative mx-auto flex min-h-screen max-w-7xl items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
    <div class="pointer-events-none absolute inset-x-4 top-10 -z-10 h-48 rounded-full bg-gradient-to-r from-blue-200/35 via-sky-100/20 to-transparent blur-3xl sm:inset-x-10"></div>
    <div class="pointer-events-none absolute -bottom-10 right-0 -z-10 h-56 w-56 rounded-full bg-blue-200/20 blur-3xl"></div>

    <div class="w-full max-w-[70rem] overflow-hidden rounded-[2.5rem] border border-white/90 bg-white/78 shadow-2xl shadow-blue-200/50 backdrop-blur-xl {{ $shellHeightClass }}">
        <div class="grid min-h-full md:grid-cols-[1.05fr_0.95fr]">
            <section class="relative flex min-h-[22rem] flex-col justify-between border-b border-slate-200/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(244,248,255,0.96))] px-8 py-8 sm:px-10 md:min-h-full md:border-b-0 md:border-r md:border-slate-200/80 md:px-12 md:py-10">
                <div class="pointer-events-none absolute inset-y-0 right-0 w-44 bg-[radial-gradient(circle_at_top_right,rgba(59,130,246,0.14),transparent_64%)]"></div>
                <div class="pointer-events-none absolute inset-x-10 top-0 h-px bg-gradient-to-r from-transparent via-blue-200 to-transparent"></div>

                <div class="relative max-w-xl">
                    <p class="text-sm font-bold uppercase tracking-[0.35em] text-blue-600">IASSI TickSys</p>
                    <h1 class="mt-5 text-4xl font-black leading-tight text-slate-900 xl:text-[3.15rem]">Quick Help for Your Business</h1>
                    <p class="mt-5 max-w-lg text-base leading-8 text-slate-600">All in One Ticketing Solution</p>
                </div>

                <div class="relative mt-8 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-[1.6rem] border border-slate-200 bg-white/88 p-5 shadow-md shadow-slate-200/70">
                        <p class="text-xs font-bold uppercase tracking-[0.3em] text-slate-400">Total</p>
                        <p class="mt-4 text-4xl font-black text-slate-900">{{ number_format($completedTicketCount) }}</p>
                        <p class="mt-3 text-sm leading-7 text-slate-500">Resolved and closed tickets</p>
                    </div>
                    <div class="rounded-[1.6rem] border border-slate-200 bg-white/88 p-5 shadow-md shadow-slate-200/70">
                        <p class="text-xs font-bold uppercase tracking-[0.3em] text-slate-400">Monitoring</p>
                        <p class="mt-4 text-4xl font-black text-slate-900">24/7</p>
                        <p class="mt-3 text-sm leading-7 text-slate-500">Monitor performance and compliance in real-time.</p>
                    </div>
                    <div class="rounded-[1.6rem] border border-slate-200 bg-white/88 p-5 shadow-md shadow-slate-200/70">
                        <p class="text-xs font-bold uppercase tracking-[0.3em] text-slate-400">Support</p>
                        <p class="mt-4 text-4xl font-black text-slate-900">Teams</p>
                        <p class="mt-3 text-sm leading-7 text-slate-500">Professional support for your business needs.</p>
                    </div>
                </div>
            </section>

            <section class="flex min-h-[22rem] bg-white/96 px-8 py-8 sm:px-10 md:min-h-full md:px-12 md:py-10">
                <div class="my-auto w-full max-w-md">
                    <p class="text-xs font-bold uppercase tracking-[0.3em] text-slate-400">Sign In</p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900">Login to IASSI TickSys</h2>
                    <p class="mt-2 text-sm leading-7 text-slate-500">Insert your email and password to access the helpdesk workspace.</p>

                    @include('partials.flash')

                    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
                        @csrf
                        <div>
                            <label class="label">Email</label>
                            <input class="field" type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com" required>
                        </div>
                        <div>
                            <label class="label">Password</label>
                            <input class="field" type="password" name="password" placeholder="********" required>
                        </div>
                        <label class="flex items-center gap-3 text-sm text-slate-500">
                            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                            Remember me
                        </label>
                        <button class="btn-primary w-full" type="submit">Login</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
