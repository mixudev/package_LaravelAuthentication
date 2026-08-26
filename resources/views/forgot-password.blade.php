{{-- 
=============================================================================
HALAMAN VIEW: FORGOT PASSWORD (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Halaman lupa password bersih standar Laravel Breeze.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $emailRoute = Route::has('authentication.password.email') 
        ? route('authentication.password.email') 
        : (Route::has('password.email') ? route('password.email') : url('/forgot-password'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('Lupa Kata Sandi')">
    
    <div class="space-y-4">
        
        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Lupa kata sandi? Masukkan alamat email Anda dan kami akan mengirimkan tautan untuk mengatur ulang kata sandi Anda.') }}
        </div>

        {{-- Flash Alerts --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ $emailRoute }}" class="space-y-4" novalidate>
            @csrf

            <x-authentication::input 
                name="email"
                type="email"
                :label="__('Email')"
                :required="true"
                autocomplete="email"
                :autofocus="true"
            />

            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('Kirim Tautan Reset Password') }}
                </x-authentication::button>
            </div>

        </form>

        <div class="pt-4 border-t border-gray-100 dark:border-gray-700 text-center text-sm">
            <a href="{{ $loginRoute }}" class="underline text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                &larr; {{ __('Kembali ke halaman masuk') }}
            </a>
        </div>

    </div>

</x-dynamic-component>
