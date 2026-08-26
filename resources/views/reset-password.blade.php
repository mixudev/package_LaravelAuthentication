{{-- 
=============================================================================
HALAMAN VIEW: RESET PASSWORD (ATUR ULANG KATA SANDI)
Package: mixudev/laravel-authentication
Deskripsi: Halaman konfirmasi dan pembaruan kata sandi baru berbasis token
           dengan arsitektur modular Blade, form components, dan Tailwind CSS.
=============================================================================
--}}
@props([
    'token' => request()->route('token') ?? request()->get('token', ''),
    'email' => request()->get('email', ''),
])

@php
    $activeLayout = config('authentication.ui.layout', 'split') === 'card' 
        ? 'authentication::layouts.card' 
        : 'authentication::layouts.split';

    $updateRoute = Route::has('authentication.password.update') 
        ? route('authentication.password.update') 
        : (Route::has('password.update') ? route('password.update') : url('/reset-password'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('Atur Ulang Kata Sandi')">
    
    <div class="space-y-6">
        
        {{-- Header Formulir --}}
        <x-authentication::header 
            :title="__('Perbarui Kata Sandi')"
            :subtitle="__('Silakan masukkan kata sandi baru yang kuat untuk memulihkan akses akun Anda.')"
            :badge="__('CREDENTIAL UPDATE')"
        />

        {{-- Notifikasi Status / Error --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Formulir Pembaruan Kata Sandi --}}
        <form method="POST" action="{{ $updateRoute }}" class="space-y-4" novalidate>
            @csrf

            {{-- Token Reset Rahasia --}}
            <input type="hidden" name="token" value="{{ $token }}">

            {{-- Input Email --}}
            <x-authentication::input 
                name="email"
                type="email"
                :label="__('Alamat Email')"
                :value="$email"
                :placeholder="__('nama@domain.com')"
                :required="true"
                autocomplete="email"
            >
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </x-slot:icon>
            </x-authentication::input>

            {{-- Input Kata Sandi Baru --}}
            <x-authentication::input 
                name="password"
                type="password"
                :label="__('Kata Sandi Baru')"
                :placeholder="__('Minimal 8 karakter unik')"
                :required="true"
                autocomplete="new-password"
                :autofocus="true"
            >
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </x-slot:icon>
            </x-authentication::input>

            {{-- Input Konfirmasi Kata Sandi Baru --}}
            <x-authentication::input 
                name="password_confirmation"
                type="password"
                :label="__('Konfirmasi Kata Sandi Baru')"
                :placeholder="__('Ulangi kata sandi baru')"
                :required="true"
                autocomplete="new-password"
            >
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </x-slot:icon>
            </x-authentication::input>

            {{-- Tombol Submit Pembaruan --}}
            <x-authentication::button type="submit" variant="primary" class="mt-2">
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7" />
                    </svg>
                </x-slot:icon>
                {{ __('Perbarui Kata Sandi Akun') }}
            </x-authentication::button>

        </form>

        {{-- Navigasi ke Halaman Login --}}
        <div class="pt-3 border-t border-slate-850 text-center text-xs text-slate-400">
            <p>
                {{ __('Batal atur ulang?') }}
                <a href="{{ $loginRoute }}" class="text-amber-400 hover:text-amber-300 font-medium ml-1">
                    &larr; {{ __('Kembali ke halaman masuk') }}
                </a>
            </p>
        </div>

    </div>

</x-dynamic-component>
