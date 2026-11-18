@extends('auth.shell', ['title' => 'Daftar Akun'])

@section('auth_form')
                    <p class="text-xs font-bold uppercase tracking-[0.3em] text-slate-400">Daftar Akun</p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900">Buat akun PRISM</h2>
                    <p class="mt-2 text-sm leading-7 text-slate-500">Khusus karyawan yang terdaftar di data manpower project. Gunakan nomor karyawan (NIK) Anda.</p>

                    @include('partials.flash')

                    <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4" id="register-form">
                        @csrf
                        <div>
                            <label class="label" for="reg-nik">Nomor karyawan (NIK)</label>
                            <input class="field @error('nik') border-red-400 @enderror" type="text" name="nik" id="reg-nik" value="{{ old('nik') }}"
                                   inputmode="numeric" autocomplete="off" maxlength="20" placeholder="Nomor karyawan sesuai data HR" required autofocus>
                            @error('nik')<p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label" for="reg-email">Email <span class="font-normal text-slate-400">(dipakai untuk login)</span></label>
                            <input class="field @error('email') border-red-400 @enderror" type="email" name="email" id="reg-email" value="{{ old('email') }}"
                                   autocomplete="email" maxlength="225" placeholder="you@company.com" required>
                            @error('email')<p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        @foreach([['password', 'Password', 'new-password'], ['password_confirmation', 'Ulangi password', 'new-password']] as [$name, $label, $ac])
                            <div>
                                <label class="label" for="reg-{{ $name }}">{{ $label }}</label>
                                <div class="relative">
                                    <input class="field pr-11 @error($name) border-red-400 @enderror" type="password" name="{{ $name }}" id="reg-{{ $name }}"
                                           autocomplete="{{ $ac }}" required>
                                    @include('partials.password-toggle', ['target' => 'reg-'.$name])
                                </div>
                                @error($name)<p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                        <p class="text-[11px] text-slate-400">Password minimal 8 karakter, mengandung huruf dan angka.</p>

                        <button class="btn-primary w-full" type="submit" id="register-submit">Daftar</button>
                    </form>

                    <p class="mt-6 text-center text-sm text-slate-500">
                        Sudah punya akun?
                        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:underline">Login</a>
                    </p>

                    <script>
                        document.getElementById('register-form').addEventListener('submit', function () {
                            const btn = document.getElementById('register-submit');
                            btn.disabled = true;
                            btn.textContent = 'Mendaftarkan…';
                        });
                    </script>
@endsection
