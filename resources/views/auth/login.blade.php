@extends('auth.shell', ['title' => 'Login'])

@section('auth_form')
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
                            <label class="label" for="login-password">Password</label>
                            <div class="relative">
                                <input class="field pr-11" type="password" name="password" id="login-password" placeholder="********" autocomplete="current-password" required>
                                @include('partials.password-toggle', ['target' => 'login-password'])
                            </div>
                        </div>
                        <label class="flex items-center gap-3 text-sm text-slate-500">
                            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                            Remember me
                        </label>
                        <button class="btn-primary w-full" type="submit">Login</button>
                    </form>

                    <p class="mt-6 text-center text-sm text-slate-500">
                        Belum punya akun?
                        <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:underline">Daftar dengan nomor karyawan</a>
                    </p>
@endsection
