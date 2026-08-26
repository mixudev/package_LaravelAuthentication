{{-- 
=============================================================================
HALAMAN VIEW: FORGOT PASSWORD (MODERN DEEP ZINC)
Package: mixudev/laravel-authentication
Deskripsi: Halaman lupa password bersih dan pekat standar Laravel.
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
        
        <x-authentication::header 
            :title="__('Pemulihan Kata Sandi')"
            :subtitle="__('Masukkan email terdaftar Anda dan kami akan mengirimkan tautan untuk mengatur ulang kata sandi.')"
        />

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
                :placeholder="__('nama@domain.com')"
                :required="true"
                autocomplete="email"
                :autofocus="true"
            />

            <div class="pt-2">
                <x-authentication::button type="submit" variant="primary">
                    {{ __('Kirim Tautan Reset') }}
                </x-authentication::button>
            </div>

        </form>

        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs">
            <a href="{{ $loginRoute }}" class="text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:underline">
                &larr; {{ __('Kembali ke halaman masuk') }}
            </a>
        </div>

    </div>

</x-dynamic-component>
