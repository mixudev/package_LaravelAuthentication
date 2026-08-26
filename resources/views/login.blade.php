{{-- 
=============================================================================
HALAMAN VIEW: LOGIN (MODERN ZINC)
Package: mixudev/laravel-authentication
Deskripsi: Halaman login bersih, modern, dan pekat standar Laravel.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';
        
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

<x-dynamic-component :component="$activeLayout" :title="__('Masuk')">
    
    <div class="space-y-4">
        
        {{-- Header Singkat --}}
        <x-authentication::header 
            :title="__('Masuk ke Akun')"
            :subtitle="__('Silakan masukkan kredensial Anda untuk melanjutkan.')"
        />

        {{-- Flash Alerts --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Social Login (Google / GitHub) --}}
        <x-authentication::social-buttons />

        @if (config('authentication.features.social.enabled', false))
            <x-authentication::divider :label="__('ATAU')" />
        @endif

        {{-- Formulir Login Utama --}}
        <form method="POST" action="{{ $loginPerformRoute }}" class="space-y-4" novalidate>
            @csrf

            {{-- Identifier (Email / Username) --}}
            <x-authentication::input 
                name="identifier"
                :label="__('Email atau Username')"
                :placeholder="__('nama@domain.com atau username')"
                :required="true"
                autocomplete="username"
                :autofocus="true"
            />

            {{-- Password --}}
            <x-authentication::input 
                name="password"
                type="password"
                :label="__('Kata Sandi')"
                :placeholder="__('Masukkan kata sandi')"
                :required="true"
                autocomplete="current-password"
            >
                @if (config('authentication.features.forgot_password.enabled', true))
                    <x-slot:labelRight>
                        <a href="{{ $forgotPasswordRoute }}" class="auth-link text-xs hover:underline">
                            {{ __('Lupa password?') }}
                        </a>
                    </x-slot:labelRight>
                @endif
            </x-authentication::input>

            {{-- Ingat Saya --}}
            <div class="block pt-1">
                <x-authentication::checkbox 
                    name="remember"
                    :label="__('Ingat sesi saya')"
                />
            </div>

            {{-- Tombol Submit Masuk --}}
            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('Masuk') }}
                </x-authentication::button>
            </div>

        </form>

        {{-- Link Alternatif (OTP & Registrasi) --}}
        <div class="space-y-2 pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs">
            @if (config('authentication.features.otp.enabled', true))
                <div>
                    <a href="{{ $otpRequestRoute }}" class="auth-link hover:underline">
                        {{ __('Masuk tanpa password via Kode OTP') }}
                    </a>
                </div>
            @endif

            @if (config('authentication.features.registration.enabled', true))
                <div class="auth-subtext">
                    <p>
                        {{ __('Belum memiliki akun?') }}
                        <a href="{{ $registerRoute }}" class="auth-link font-medium hover:underline ml-1">
                            {{ __('Daftar sekarang') }} &rarr;
                        </a>
                    </p>
                </div>
            @endif
        </div>

    </div>

</x-dynamic-component>
