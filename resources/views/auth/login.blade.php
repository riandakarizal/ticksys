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
            <section class="relative flex min-h-[22rem] flex-col justify-between overflow-hidden border-b border-slate-200/80 px-8 py-8 sm:px-10 md:min-h-full md:border-b-0 md:border-r md:border-slate-200/80 md:px-12 md:py-10">

                {{-- Carousel background --}}
                <div class="absolute inset-0 z-0" aria-hidden="true">
                    <div class="login-slide absolute inset-0 bg-cover bg-center opacity-100 transition-opacity duration-1000"
                         style="background-image: url('https://loremflickr.com/900/700/helpdesk,office,computer')"></div>
                    <div class="login-slide absolute inset-0 bg-cover bg-center opacity-0 transition-opacity duration-1000"
                         style="background-image: url('https://loremflickr.com/900/700/customer,support,technology')"></div>
                    <div class="login-slide absolute inset-0 bg-cover bg-center opacity-0 transition-opacity duration-1000"
                         style="background-image: url('https://loremflickr.com/900/700/teamwork,workspace,laptop')"></div>
                    <div class="absolute inset-0 bg-slate-900/60"></div>
                </div>

                <div class="relative z-10 max-w-xl">
                    <svg class="mb-3 h-10 w-10" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <defs>
                            <linearGradient id="pll1" x1="0.3" y1="0" x2="0.7" y2="1"><stop offset="0%" stop-color="#bfdbfe"/><stop offset="100%" stop-color="#3b82f6"/></linearGradient>
                            <linearGradient id="pll2" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#2563eb"/><stop offset="100%" stop-color="#1e40af"/></linearGradient>
                            <linearGradient id="pll3" x1="1" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1e3a8a"/><stop offset="100%" stop-color="#172554"/></linearGradient>
                        </defs>
                        <ellipse cx="60" cy="102" rx="38" ry="7" fill="#1e3a8a" opacity="0.25"/>
                        <polygon points="60,60 88,45 88,82 60,97" fill="url(#pll3)"/>
                        <polygon points="60,60 32,45 32,82 60,97" fill="url(#pll2)"/>
                        <polygon points="60,60 32,45 88,45" fill="url(#pll1)"/>
                        <line x1="60" y1="60" x2="32" y2="45" stroke="rgba(255,255,255,0.5)" stroke-width="1.2"/>
                        <line x1="60" y1="60" x2="88" y2="45" stroke="rgba(255,255,255,0.25)" stroke-width="1.2"/>
                        <line x1="60" y1="60" x2="60" y2="97" stroke="rgba(255,255,255,0.3)" stroke-width="1.2"/>
                        <line x1="32" y1="45" x2="88" y2="45" stroke="rgba(255,255,255,0.4)" stroke-width="1"/>
                        <circle cx="60" cy="60" r="3" fill="rgba(255,255,255,0.6)"/>
                        <circle cx="32" cy="45" r="2" fill="rgba(255,255,255,0.35)"/>
                        <circle cx="88" cy="45" r="2" fill="rgba(255,255,255,0.2)"/>
                    </svg>
                    <p class="text-sm font-bold uppercase tracking-[0.35em] text-blue-300">PRISM</p>
                    <h1 class="mt-5 text-4xl font-black leading-tight text-white xl:text-[3.15rem]">Quick Help for Your Business</h1>
                    <p class="mt-5 max-w-lg text-base leading-8 text-slate-300">All in One Ticketing Solution</p>
                </div>

                <div class="relative z-10 mt-8">
                    {{-- Cards: compact on mobile & md (narrow panel), fuller at sm (full-width) & lg+ --}}
                    <div class="grid grid-cols-3 gap-2 sm:gap-3 md:gap-2 lg:gap-4">
                        <div class="rounded-xl border border-white/25 bg-white/15 p-3 sm:p-4 md:p-3 lg:p-5 shadow-md shadow-black/20 backdrop-blur-md">
                            <p class="truncate text-[0.55rem] font-bold uppercase tracking-tight text-white/60">Total</p>
                            <p class="mt-1.5 text-xl sm:text-2xl md:text-xl lg:text-3xl font-black leading-none text-white">{{ number_format($completedTicketCount) }}</p>
                            <p class="mt-1.5 hidden text-xs leading-5 text-slate-200 sm:block md:hidden lg:block">Resolved &amp; closed tickets</p>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/15 p-3 sm:p-4 md:p-3 lg:p-5 shadow-md shadow-black/20 backdrop-blur-md">
                            <p class="truncate text-[0.55rem] font-bold uppercase tracking-tight text-white/60">Monitor</p>
                            <p class="mt-1.5 text-xl sm:text-2xl md:text-xl lg:text-3xl font-black leading-none text-white">24/7</p>
                            <p class="mt-1.5 hidden text-xs leading-5 text-slate-200 sm:block md:hidden lg:block">Real-time SLA monitoring</p>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/15 p-3 sm:p-4 md:p-3 lg:p-5 shadow-md shadow-black/20 backdrop-blur-md">
                            <p class="truncate text-[0.55rem] font-bold uppercase tracking-tight text-white/60">Support</p>
                            <p class="mt-1.5 text-xl sm:text-2xl md:text-xl lg:text-3xl font-black leading-none text-white">Teams</p>
                            <p class="mt-1.5 hidden text-xs leading-5 text-slate-200 sm:block md:hidden lg:block">Expert support teams</p>
                        </div>
                    </div>

                    {{-- Dot indicators --}}
                    <div class="mt-5 flex justify-center gap-2" id="login-dots" aria-hidden="true">
                        <button class="h-1.5 w-6 rounded-full bg-white transition-all duration-300" data-dot="0"></button>
                        <button class="h-1.5 w-2 rounded-full bg-white/40 transition-all duration-300" data-dot="1"></button>
                        <button class="h-1.5 w-2 rounded-full bg-white/40 transition-all duration-300" data-dot="2"></button>
                    </div>
                </div>
            </section>

            <section class="flex min-h-[22rem] bg-white/96 px-8 py-8 sm:px-10 md:min-h-full md:px-12 md:py-10">
                <div class="my-auto w-full max-w-md">
                    <p class="text-xs font-bold uppercase tracking-[0.3em] text-slate-400">Sign In</p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900">Login to PRISM</h2>
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

@push('scripts')
<script>
(function () {
    const slides = document.querySelectorAll('.login-slide');
    const dots = document.querySelectorAll('#login-dots [data-dot]');
    let current = 0;

    function goTo(index) {
        slides[current].classList.replace('opacity-100', 'opacity-0');
        dots[current].classList.replace('w-6', 'w-2');
        dots[current].classList.replace('bg-white', 'bg-white/40');
        current = index;
        slides[current].classList.replace('opacity-0', 'opacity-100');
        dots[current].classList.replace('w-2', 'w-6');
        dots[current].classList.replace('bg-white/40', 'bg-white');
    }

    dots.forEach(dot => dot.addEventListener('click', () => goTo(+dot.dataset.dot)));
    setInterval(() => goTo((current + 1) % slides.length), 4500);
})();
</script>
@endpush
