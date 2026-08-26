{{-- 
=============================================================================
HALAMAN VIEW: FORGOT PASSWORD (LUPA KATA SANDI)
Package: mixudev/laravel-authentication
Deskripsi: Halaman permintaan tautan reset kata sandi dengan mitigasi proteksi
           user enumeration dan arsitektur modular Blade + Tailwind CSS.
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'split') === 'card' 
        ? 'authentication::layouts.card' 
        : 'authentication::layouts.split';

    $emailRoute = Route::has('authentication.password.email') 
        ? route('authentication.password.email') 
        : (Route::has('password.email') ? route('password.email') : url('/forgot-password'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('Pemulihan Kata Sandi')">
    
    <div class="space-y-6">
        
        {{-- Header Formulir --}}
        <x-authentication::header 
            :title="__('Lupa Kata Sandi?')"
            :subtitle="__('Masukkan alamat email terdaftar Anda. Kami akan mengirimkan tautan aman untuk mengatur ulang kata sandi.')"
            :badge="__('ACCOUNT RECOVERY')"
        />

        {{-- Notifikasi Status Flash Session --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Formulir Kirim Tautan Reset --}}
        <form method="POST" action="{{ $emailRoute }}" class="space-y-5" novalidate>
            @csrf

            {{-- Input Email --}}
            <x-authentication::input 
                name="email"
                type="email"
                :label="__('Alamat Email')"
                :placeholder="__('nama@domain.com')"
                :required="true"
                autocomplete="email"
                :autofocus="true"
            >
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </x-slot:icon>
            </x-authentication::input>

            {{-- Tombol Kirim Tautan --}}
            <x-authentication::button type="submit" variant="primary" class="mt-2">
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </x-slot:icon>
                {{ __('Kirim Tautan Pemulihan') }}
            </x-authentication::button>

        </form>

        {{-- Link Navigasi Kembali ke Login --}}
        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-center text-xs text-slate-600 dark:text-slate-400">
            <p>
                {{ __('Ingat kata sandi Anda?') }}
                <a href="{{ $loginRoute }}" class="text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300 font-medium ml-1">
                    &larr; {{ __('Kembali ke halaman masuk') }}
                </a>
            </p>
        </div>

    </div>

</x-dynamic-component>
