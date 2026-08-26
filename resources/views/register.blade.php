{{-- 
=============================================================================
HALAMAN VIEW: REGISTER (MODERN DEEP ZINC)
Package: mixudev/laravel-authentication
Deskripsi: Halaman registrasi bersih, modern, dan pekat standar Laravel.
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
            :subtitle="__('Lengkapi informasi di bawah untuk mendaftarkan akun.')"
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
                :placeholder="__('Nama lengkap Anda')"
                :required="true"
                autocomplete="name"
                :autofocus="true"
            />

            {{-- Alamat Email --}}
            <x-authentication::input 
                name="email"
                type="email"
                :label="__('Alamat Email')"
                :placeholder="__('nama@domain.com')"
                :required="true"
                autocomplete="email"
            />

            {{-- Username (Opsional) --}}
            @if (config('authentication.strategies.active') === 'username_password' || config('authentication.strategies.active') === 'username_or_email')
                <x-authentication::input 
                    name="username"
                    :label="__('Username')"
                    :placeholder="__('Pilih username unik')"
                    :required="false"
                    autocomplete="username"
                />
            @endif

            {{-- Password --}}
            <x-authentication::input 
                name="password"
                type="password"
                :label="__('Kata Sandi')"
                :placeholder="__('Minimal 8 karakter')"
                :required="true"
                autocomplete="new-password"
            />

            {{-- Konfirmasi Password --}}
            <x-authentication::input 
                name="password_confirmation"
                type="password"
                :label="__('Konfirmasi Kata Sandi')"
                :placeholder="__('Ulangi kata sandi')"
                :required="true"
                autocomplete="new-password"
            />

            {{-- Persetujuan Syarat & Ketentuan --}}
            <div class="block pt-1">
                <x-authentication::checkbox 
                    name="terms"
                    :required="true"
                >
                    <span class="text-xs text-zinc-600 dark:text-zinc-400">
                        {{ __('Saya menyetujui') }} 
                        <a href="#" class="underline text-zinc-900 dark:text-zinc-100 hover:opacity-80">{{ __('Syarat & Ketentuan') }}</a>.
                    </span>
                </x-authentication::checkbox>
            </div>

            {{-- Tombol Submit --}}
            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('Daftar Akun') }}
                </x-authentication::button>
            </div>

        </form>

        {{-- Link ke Login --}}
        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs text-zinc-600 dark:text-zinc-400">
            <p>
                {{ __('Sudah memiliki akun?') }}
                <a href="{{ $loginRoute }}" class="font-medium text-zinc-900 dark:text-zinc-100 hover:underline ml-1">
                    {{ __('Masuk ke sistem') }} &rarr;
                </a>
            </p>
        </div>

    </div>

</x-dynamic-component>
