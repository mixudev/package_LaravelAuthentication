{{-- 
=============================================================================
HALAMAN VIEW: REGISTER (PENDAFTARAN AKUN)
Package: mixudev/laravel-authentication
Deskripsi: Halaman registrasi akun baru yang sepenuhnya modular dengan Blade components,
           validasi live password policy, serta dukungan multi-template (split / card).
=============================================================================
--}}
@php
    $activeLayout = config('authentication.ui.layout', 'split') === 'card' 
        ? 'authentication::layouts.card' 
        : 'authentication::layouts.split';

    $registerRoute = Route::has('authentication.register') 
        ? route('authentication.register') 
        : (Route::has('register.perform') ? route('register.perform') : url('/register'));

    $loginRoute = Route::has('authentication.login') 
        ? route('authentication.login') 
        : (Route::has('login') ? route('login') : url('/login'));

    $policy = config('authentication.password.validation_rules', [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
    ]);
@endphp

<x-dynamic-component :component="$activeLayout" :title="__('Daftar Akun Baru')">
    
    <div class="space-y-6">
        
        {{-- Header Formulir --}}
        <x-authentication::header 
            :title="__('Buat Akun Baru')"
            :subtitle="__('Mulai gunakan sistem dengan mendaftarkan identitas akun terverifikasi Anda.')"
            :badge="__('REGISTRATION PORTAL')"
        />

        {{-- Notifikasi Status & Error --}}
        @if (session('status'))
            <x-authentication::alert type="success" :message="session('status')" />
        @endif

        @if (session('error'))
            <x-authentication::alert type="error" :message="session('error')" />
        @endif

        {{-- Tombol Social OAuth (Jika Aktif) --}}
        <x-authentication::social-buttons />

        @if (config('authentication.features.social.enabled', false))
            <x-authentication::divider :label="__('ATAU DAFTAR DENGAN EMAIL')" />
        @endif

        {{-- Formulir Registrasi --}}
        <form method="POST" action="{{ $registerRoute }}" class="space-y-4" novalidate>
            @csrf

            {{-- Input Nama Lengkap --}}
            <x-authentication::input 
                name="name"
                :label="__('Nama Lengkap')"
                :placeholder="__('Contoh: John Doe')"
                :required="true"
                autocomplete="name"
                :autofocus="true"
            >
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </x-slot:icon>
            </x-authentication::input>

            {{-- Input Alamat Email --}}
            <x-authentication::input 
                name="email"
                type="email"
                :label="__('Alamat Email')"
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

            {{-- Input Kata Sandi --}}
            <div class="space-y-2">
                <x-authentication::input 
                    name="password"
                    type="password"
                    :label="__('Kata Sandi')"
                    :placeholder="__('Minimal ' . ($policy['min_length'] ?? 8) . ' karakter kuat')"
                    :required="true"
                    autocomplete="new-password"
                >
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </x-slot:icon>
                </x-authentication::input>

                {{-- Kebijakan Password --}}
                <div class="p-3 rounded-xl bg-slate-100 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-[11px] font-mono-code text-slate-600 dark:text-slate-400 space-y-1">
                    <span class="text-slate-500 font-semibold uppercase tracking-wider block text-[10px]">KEBIJAKAN KATA SANDI:</span>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-1 text-slate-700 dark:text-slate-300">
                        <li class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            Min. {{ $policy['min_length'] ?? 8 }} Karakter
                        </li>
                        @if (!empty($policy['require_uppercase']) && !empty($policy['require_lowercase']))
                            <li class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Huruf Besar &amp; Kecil
                            </li>
                        @endif
                        @if (!empty($policy['require_numbers']))
                            <li class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Minimal 1 Angka
                            </li>
                        @endif
                        @if (!empty($policy['require_symbols']))
                            <li class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Karakter Simbol
                            </li>
                        @endif
                    </ul>
                </div>
            </div>

            {{-- Input Konfirmasi Kata Sandi --}}
            <x-authentication::input 
                name="password_confirmation"
                type="password"
                :label="__('Konfirmasi Kata Sandi')"
                :placeholder="__('Ulangi kata sandi')"
                :required="true"
                autocomplete="new-password"
            >
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </x-slot:icon>
            </x-authentication::input>

            {{-- Persetujuan Syarat & Privasi --}}
            <div class="pt-1">
                <x-authentication::checkbox 
                    name="terms"
                    :required="true"
                >
                    <span class="text-slate-600 dark:text-slate-400">
                        {{ __('Saya menyetujui') }} 
                        <a href="#" class="text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300 font-medium hover:underline">{{ __('Syarat & Ketentuan') }}</a> 
                        {{ __('serta') }} 
                        <a href="#" class="text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300 font-medium hover:underline">{{ __('Kebijakan Privasi') }}</a>.
                    </span>
                </x-authentication::checkbox>
            </div>

            {{-- Tombol Submit Daftar --}}
            <x-authentication::button type="submit" variant="primary" class="mt-2">
                <x-slot:icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </x-slot:icon>
                {{ __('Daftar Akun Sekarang') }}
            </x-authentication::button>

        </form>

        {{-- Navigasi ke Halaman Login --}}
        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-center text-xs text-slate-600 dark:text-slate-400">
            <p>
                {{ __('Sudah memiliki akun?') }}
                <a href="{{ $loginRoute }}" class="text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300 font-medium ml-1">
                    {{ __('Masuk ke sistem') }} &rarr;
                </a>
            </p>
        </div>

    </div>

</x-dynamic-component>

