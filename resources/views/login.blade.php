{{-- 
=============================================================================
HALAMAN VIEW: LOGIN (MASUK)
Package: mixudev/laravel-authentication
Deskripsi: Halaman autentikasi utama modular berstandar Blade & Tailwind CSS.
           Layout dapat diubah otomatis via config('authentication.ui.layout') ('split' / 'card').
=============================================================================
--}}
@php
    // Resolusi Template UI Dinamis: 'split' (2-kolom) atau 'card' (kartu tengah)
    $activeLayout = config('authentication.ui.layout', 'split') === 'card' 
        ? 'authentication::layouts.card' 
        : 'authentication::layouts.split';
        
    $loginPerformRoute = Route::has('login.perform') 
        ? route('login.perform') 
        : (Route::has('authentication.login') ? route('authentication.login') : url('/login'));

    $forgotPasswordRoute = Route::has('password.request') 
        ? route('password.request') 
        : (Route::has('authentication.password.request') ? route('authentication.password.request') : url('/forgot-password'));

    $otpRequestRoute = Route::has('otp.request.form') 
        ? route('otp.request.form') 
        : (Route::has('authentication.otp.request') ? route('authentication.otp.request') : url('/otp/login'));

    $registerRoute = Route::has('register') 
        ? route('register') 
        : (Route::has('authentication.register') ? route('authentication.register') : url('/register'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('Masuk ke Sistem')">
    
    <div class="space-y-6">
        
        {{-- Header Formulir --}}
        <x-authentication::header 
            :title="__('Masuk ke Akun')"
            :subtitle="__('Silakan masukkan kredensial Anda untuk mengakses console sistem.')"
            :badge="__('IDENTITY AUTHENTICATION')"
        />

        {{-- Notifikasi Status Flash Session (Sukses / Reset Password / dsb) --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Tombol Social OAuth (Google / GitHub) --}}
        <x-authentication::social-buttons />

        {{-- Garis Pemisah Kredensial --}}
        @if (config('authentication.features.social.enabled', false))
            <x-authentication::divider :label="__('ATAU DENGAN IDENTIFIER')" />
        @endif

        {{-- Formulir Autentikasi Utama --}}
        <form method="POST" action="{{ $loginPerformRoute }}" class="space-y-5" novalidate>
            @csrf

            {{-- Input Identifier (Email / Username / Kustom ID) --}}
            <x-authentication::input 
                name="identifier"
                :label="__('Email atau Username')"
                :placeholder="__('nama@domain.com atau username')"
                :required="true"
                autocomplete="username"
                :autofocus="true"
            >
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </x-slot:icon>
            </x-authentication::input>

            {{-- Input Password dengan Tombol Toggle Visibility --}}
            <x-authentication::input 
                name="password"
                type="password"
                :label="__('Kata Sandi')"
                :placeholder="__('Masukkan kata sandi akun')"
                :required="true"
                autocomplete="current-password"
            >
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </x-slot:icon>

                {{-- Link Lupa Password di Samping Label --}}
                @if (config('authentication.features.forgot_password.enabled', true))
                    <x-slot:labelRight>
                        <a href="{{ $forgotPasswordRoute }}" class="text-xs font-mono-code text-amber-400 hover:text-amber-300 transition-colors">
                            {{ __('Lupa password?') }}
                        </a>
                    </x-slot:labelRight>
                @endif
            </x-authentication::input>

            {{-- Baris Opsi: Ingat Saya --}}
            <div class="flex items-center justify-between pt-1">
                <x-authentication::checkbox 
                    name="remember"
                    :label="__('Ingat sesi saya pada perangkat ini')"
                />
            </div>

            {{-- Tombol Submit Masuk --}}
            <x-authentication::button type="submit" variant="primary" class="mt-2">
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                </x-slot:icon>
                {{ __('Masuk ke Akun') }}
            </x-authentication::button>

        </form>

        {{-- Opsi Alternatif Masuk (OTP & Registrasi) --}}
        <div class="space-y-3 pt-2 text-center text-xs text-slate-400">
            @if (config('authentication.features.otp.enabled', true))
                <div>
                    <a href="{{ $otpRequestRoute }}" class="inline-flex items-center gap-1.5 text-slate-300 hover:text-amber-400 font-mono-code transition-colors">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        {{ __('Masuk tanpa password via Kode OTP') }}
                    </a>
                </div>
            @endif

            @if (config('authentication.features.registration.enabled', true))
                <div class="pt-2 border-t border-slate-850">
                    <p>
                        {{ __('Belum memiliki akun?') }}
                        <a href="{{ $registerRoute }}" class="text-amber-400 hover:text-amber-300 font-medium ml-1">
                            {{ __('Daftar sekarang') }} &rarr;
                        </a>
                    </p>
                </div>
            @endif
        </div>

    </div>

</x-dynamic-component>
