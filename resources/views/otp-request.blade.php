{{-- 
=============================================================================
HALAMAN VIEW: OTP REQUEST (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Halaman permintaan OTP bersih standar Laravel Breeze.
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
            :title="__('Masuk dengan Kode OTP')"
            :subtitle="__('Masukkan email Anda untuk menerima kode verifikasi sekali pakai.')"
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
                :label="__('Email / Username')"
                :required="true"
                autocomplete="username"
                :autofocus="true"
            />

            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('Kirim Kode OTP') }}
                </x-authentication::button>
            </div>

        </form>

        <div class="pt-4 border-t border-gray-100 dark:border-gray-700 text-center text-sm">
            <a href="{{ $loginRoute }}" class="underline text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                &larr; {{ __('Masuk dengan kata sandi') }}
            </a>
        </div>

    </div>

</x-dynamic-component>
