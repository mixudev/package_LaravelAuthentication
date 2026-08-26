{{-- 
=============================================================================
HALAMAN VIEW: RESET PASSWORD (MODERN DEEP ZINC)
Package: mixudev/laravel-authentication
Deskripsi: Halaman reset password bersih dan pekat standar Laravel.
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
            :subtitle="__('Silakan masukkan kata sandi baru untuk akun Anda.')"
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
                :placeholder="__('Minimal 8 karakter baru')"
                :required="true"
                autocomplete="new-password"
                :autofocus="true"
            />

            <x-authentication::input 
                name="password_confirmation"
                type="password"
                :label="__('Konfirmasi Kata Sandi Baru')"
                :placeholder="__('Ulangi kata sandi baru')"
                :required="true"
                autocomplete="new-password"
            />

            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('Simpan Kata Sandi Baru') }}
                </x-authentication::button>
            </div>

        </form>

        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs">
            <a href="{{ $loginRoute }}" class="auth-link hover:underline">
                &larr; {{ __('Kembali ke halaman masuk') }}
            </a>
        </div>

    </div>

</x-dynamic-component>
