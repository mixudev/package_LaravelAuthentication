{{-- 
=============================================================================
HALAMAN VIEW: OTP REQUEST (MODERN DEEP ZINC)
Package: mixudev/laravel-authentication
Deskripsi: Halaman permintaan OTP bersih dan pekat standar Laravel.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $sendRoute = Route::has('authentication.otp.send') 
        ? route('authentication.otp.send') 
        : (Route::has('otp.send') ? route('otp.send') : url('/otp/request'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('Masuk via OTP')">
    
    <div class="space-y-4">
        
        <x-authentication::header 
            :title="__('Masuk Tanpa Kata Sandi')"
            :subtitle="__('Masukkan email Anda untuk menerima kode OTP verifikasi sekali pakai.')"
        />

        {{-- Flash Alerts --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ $sendRoute }}" class="space-y-4" novalidate>
            @csrf

            <x-authentication::input 
                name="identifier"
                :label="__('Email atau Username')"
                :placeholder="__('nama@domain.com atau username')"
                :required="true"
                autocomplete="username"
                :autofocus="true"
            />

            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('Kirim Kode Verifikasi') }}
                </x-authentication::button>
            </div>

        </form>

        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs">
            <a href="{{ $loginRoute }}" class="auth-link hover:underline">
                &larr; {{ __('Masuk dengan kata sandi biasa') }}
            </a>
        </div>

    </div>

</x-dynamic-component>
