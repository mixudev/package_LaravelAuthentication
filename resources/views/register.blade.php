{{-- 
=============================================================================
HALAMAN VIEW: REGISTER (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Halaman registrasi bersih, simpel, dan elegan standar Laravel Breeze.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $registerPerformRoute = Route::has('register.perform') 
        ? route('register.perform') 
        : (Route::has('authentication.register') ? route('authentication.register') : url('/register'));

    $loginRoute = Route::has('login') 
        ? route('login') 
        : (Route::has('authentication.login') ? route('authentication.login') : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('Daftar Akun')">
    
    <div class="space-y-4">
        
        {{-- Header Singkat --}}
        <x-authentication::header 
            :title="__('Buat Akun Baru')"
        />

        {{-- Flash Alerts --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Social Register (Google / GitHub) --}}
        <x-authentication::social-buttons />

        @if (config('authentication.features.social.enabled', false))
            <x-authentication::divider :label="__('ATAU')" />
        @endif

        {{-- Formulir Registrasi --}}
        <form method="POST" action="{{ $registerPerformRoute }}" class="space-y-4" novalidate>
            @csrf

            {{-- Nama Lengkap --}}
            <x-authentication::input 
                name="name"
                :label="__('Nama Lengkap')"
                :required="true"
                autocomplete="name"
                :autofocus="true"
            />

            {{-- Alamat Email --}}
            <x-authentication::input 
                name="email"
                type="email"
                :label="__('Alamat Email')"
                :required="true"
                autocomplete="email"
            />

            {{-- Username (Opsional) --}}
            @if (config('authentication.strategies.active') === 'username_password' || config('authentication.strategies.active') === 'username_or_email')
                <x-authentication::input 
                    name="username"
                    :label="__('Username')"
                    :required="false"
                    autocomplete="username"
                />
            @endif

            {{-- Password --}}
            <x-authentication::input 
                name="password"
                type="password"
                :label="__('Kata Sandi')"
                :required="true"
                autocomplete="new-password"
            />

            {{-- Konfirmasi Password --}}
            <x-authentication::input 
                name="password_confirmation"
                type="password"
                :label="__('Konfirmasi Kata Sandi')"
                :required="true"
                autocomplete="new-password"
            />

            {{-- Persetujuan Syarat & Ketentuan --}}
            <div class="block">
                <x-authentication::checkbox 
                    name="terms"
                    :required="true"
                >
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Saya menyetujui') }} 
                        <a href="#" class="underline hover:text-gray-900 dark:hover:text-gray-100">{{ __('Syarat & Ketentuan') }}</a>.
                    </span>
                </x-authentication::checkbox>
            </div>

            {{-- Tombol Submit --}}
            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('Daftar') }}
                </x-authentication::button>
            </div>

        </form>

        {{-- Link ke Login --}}
        <div class="pt-4 border-t border-gray-100 dark:border-gray-700 text-center text-sm text-gray-600 dark:text-gray-400">
            <p class="text-xs">
                {{ __('Sudah memiliki akun?') }}
                <a href="{{ $loginRoute }}" class="underline font-medium text-gray-900 dark:text-gray-100 ml-1">
                    {{ __('Masuk') }}
                </a>
            </p>
        </div>

    </div>

</x-dynamic-component>
