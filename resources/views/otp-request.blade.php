{{-- 
=============================================================================
HALAMAN VIEW: OTP REQUEST (PERMINTAAN KODE OTP)
Package: mixudev/laravel-authentication
Deskripsi: Halaman pengajuan pengiriman kode OTP sekali pakai ke email/identifier pengguna.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'split') === 'card' 
        ? 'authentication::layouts.card' 
        : 'authentication::layouts.split';

    $sendRoute = Route::has('authentication.otp.send') 
        ? route('authentication.otp.send') 
        : (Route::has('otp.send') ? route('otp.send') : url('/otp/request'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('Masuk Tanpa Kata Sandi (OTP)')">
    
    <div class="space-y-6">
        
        {{-- Header Formulir --}}
        <x-authentication::header 
            :title="__('Masuk via Kode OTP')"
            :subtitle="__('Masukkan email atau identifier akun Anda. Kami akan mengirimkan kode verifikasi numerik sekali pakai.')"
            :badge="__('PASSWORDLESS AUTHENTICATION')"
        />

        {{-- Notifikasi Status / Error --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Formulir Kirim OTP --}}
        <form method="POST" action="{{ $sendRoute }}" class="space-y-5" novalidate>
            @csrf

            {{-- Input Identifier / Email --}}
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

            {{-- Tombol Kirim Kode --}}
            <x-authentication::button type="submit" variant="primary" class="mt-2">
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </x-slot:icon>
                {{ __('Kirim Kode Verifikasi OTP') }}
            </x-authentication::button>

        </form>

        {{-- Navigasi ke Login Password --}}
        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-center text-xs text-slate-600 dark:text-slate-400">
            <p>
                {{ __('Ingin masuk menggunakan kata sandi?') }}
                <a href="{{ $loginRoute }}" class="text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300 font-medium ml-1">
                    &larr; {{ __('Masuk dengan password biasa') }}
                </a>
            </p>
        </div>

    </div>

</x-dynamic-component>
