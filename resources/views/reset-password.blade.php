{{-- 
=============================================================================
HALAMAN VIEW: RESET PASSWORD (CLEAN BREEZE STYLE)
Package: mixudev/laravel-authentication
Deskripsi: Halaman reset password bersih standar Laravel Breeze.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'card') === 'split' 
        ? 'authentication::layouts.split' 
        : 'authentication::layouts.card';

    $updateRoute = Route::has('authentication.password.update') 
        ? route('authentication.password.update') 
        : (Route::has('password.update') ? route('password.update') : url('/reset-password'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('Atur Ulang Kata Sandi')">
    
    <div class="space-y-4">
        
        <x-authentication::header 
            :title="__('Atur Ulang Kata Sandi')"
        />

        {{-- Flash Alerts --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ $updateRoute }}" class="space-y-4" novalidate>
            @csrf

            <input type="hidden" name="token" value="{{ $token ?? request()->route('token') }}">

            <x-authentication::input 
                name="email"
                type="email"
                :label="__('Email')"
                :value="old('email', $email ?? request()->email)"
                :required="true"
                autocomplete="email"
            />

            <x-authentication::input 
                name="password"
                type="password"
                :label="__('Kata Sandi Baru')"
                :required="true"
                autocomplete="new-password"
                :autofocus="true"
            />

            <x-authentication::input 
                name="password_confirmation"
                type="password"
                :label="__('Konfirmasi Kata Sandi')"
                :required="true"
                autocomplete="new-password"
            />

            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('Simpan Kata Sandi Baru') }}
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
